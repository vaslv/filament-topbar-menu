<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Support\Facades\Http;
use Vaslv\FilamentTopbarMenu\Support\FaviconResolver;

class FaviconResolverTest extends TestCase
{
    public function test_it_resolves_the_conventional_favicon_ico(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/x-icon']),
        ]);

        $favicon = app(FaviconResolver::class)->resolve('https://example.com/some/deep/page');

        $this->assertSame('https://example.com/favicon.ico', $favicon);
    }

    public function test_it_falls_back_to_parsing_link_tags_from_the_page(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('Not Found', 404),
            'https://example.com/assets/fav.png' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/png']),
            'https://example.com' => Http::response(
                '<html><head><link rel="shortcut icon" href="/assets/fav.png"></head><body></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $favicon = app(FaviconResolver::class)->resolve('https://example.com');

        $this->assertSame('https://example.com/assets/fav.png', $favicon);
    }

    public function test_absolute_favicon_urls_from_link_tags_are_kept_as_is(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('Not Found', 404),
            'https://cdn.example.com/icon-32.png' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/png']),
            'https://example.com' => Http::response(
                '<html><head><link href="https://cdn.example.com/icon-32.png" rel="icon" sizes="32x32"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $favicon = app(FaviconResolver::class)->resolve('https://example.com');

        $this->assertSame('https://cdn.example.com/icon-32.png', $favicon);
    }

    public function test_relative_hrefs_are_resolved_against_the_page_url_not_the_host_root(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('Not Found', 404),
            'https://example.com/en/static/fav.png' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/png']),
            'https://example.com/en/docs' => Http::response(
                '<html><head><link rel="icon" href="static/fav.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $favicon = app(FaviconResolver::class)->resolve('https://example.com/en/docs');

        // Resolved against the page directory (/en/), not the host root.
        $this->assertSame('https://example.com/en/static/fav.png', $favicon);
    }

    public function test_parent_relative_hrefs_are_normalized(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('Not Found', 404),
            'https://example.com/assets/fav.png' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/png']),
            'https://example.com/en/docs/' => Http::response(
                '<html><head><link rel="icon" href="../../assets/fav.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $favicon = app(FaviconResolver::class)->resolve('https://example.com/en/docs/');

        $this->assertSame('https://example.com/assets/fav.png', $favicon);
    }

    public function test_html_derived_favicon_urls_that_do_not_resolve_are_not_returned(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('Not Found', 404),
            'https://example.com/broken.png' => Http::response('Not Found', 404),
            'https://example.com' => Http::response(
                '<html><head><link rel="icon" href="/broken.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        // The href points at a 404, so nothing is stored (no silently broken icon).
        $this->assertNull(app(FaviconResolver::class)->resolve('https://example.com'));
    }

    public function test_it_returns_null_when_nothing_is_found(): void
    {
        Http::fake([
            '*' => Http::response('Not Found', 404),
        ]);

        $this->assertNull(app(FaviconResolver::class)->resolve('https://example.com'));
    }

    public function test_it_is_disabled_via_config_and_makes_no_requests(): void
    {
        config()->set('filament-topbar-menu.enable_favicons', false);

        Http::fake();

        $this->assertNull(app(FaviconResolver::class)->resolve('https://example.com'));

        Http::assertNothingSent();
    }

    public function test_it_returns_null_for_urls_without_a_host(): void
    {
        Http::fake();

        $this->assertNull(app(FaviconResolver::class)->resolve('/relative/path'));

        Http::assertNothingSent();
    }

    public function test_it_refuses_to_fetch_private_loopback_and_link_local_hosts(): void
    {
        Http::fake();

        $resolver = app(FaviconResolver::class);

        // SSRF guard: loopback (by IP and by name), the cloud metadata endpoint,
        // an RFC1918 address and IPv6 loopback are never requested.
        $this->assertNull($resolver->resolve('http://127.0.0.1/'));
        $this->assertNull($resolver->resolve('http://localhost/'));
        $this->assertNull($resolver->resolve('http://169.254.169.254/latest/meta-data/'));
        $this->assertNull($resolver->resolve('http://10.0.0.1/'));
        $this->assertNull($resolver->resolve('http://[::1]/'));

        Http::assertNothingSent();
    }

    public function test_a_redirect_to_an_internal_address_is_not_followed(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('', 302, [
                'Location' => 'http://169.254.169.254/latest/meta-data/',
            ]),
            '*' => Http::response('', 200),
        ]);

        // A public host 302s toward cloud metadata; the resolver re-checks the
        // hop and refuses it instead of blindly following the redirect.
        $this->assertNull(app(FaviconResolver::class)->resolve('https://example.com'));

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '169.254.169.254'));
    }

    public function test_data_uri_favicons_from_link_tags_are_not_stored(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('Not Found', 404),
            'https://example.com' => Http::response(
                '<html><head><link rel="icon" href="data:image/png;base64,AAAA"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        // data: URIs are unbounded and would land in the panel's CSS url() sink,
        // so they are rejected rather than persisted.
        $this->assertNull(app(FaviconResolver::class)->resolve('https://example.com'));
    }

    public function test_favicon_hrefs_that_decode_to_quotes_are_not_stored(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('Not Found', 404),
            'https://example.com' => Http::response(
                // Decodes to /ic'on.png — a single quote would break out of
                // style="background-image: url('…')" in the dropdown markup.
                '<html><head><link rel="icon" href="/ic&#39;on.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $this->assertNull(app(FaviconResolver::class)->resolve('https://example.com'));
    }
}
