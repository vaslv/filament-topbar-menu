<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Support\Facades\Http;
use Vaslv\FilamentTopbarMenu\Support\FaviconResolver;

class FaviconResolverTest extends TestCase
{
    public function test_it_falls_back_to_the_conventional_favicon_ico(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/some/deep/page' => Http::response(
                '<html><head><title>No icon here</title></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $favicon = app(FaviconResolver::class)->resolve('https://example.com/some/deep/page');

        $this->assertSame('https://example.com/favicon.ico', $favicon);
    }

    public function test_the_icon_declared_by_the_page_wins_over_the_root_favicon_ico(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/vendor/horizon/img/favicon.png' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/png']),
            'https://example.com/horizon' => Http::response(
                '<html><head><link rel="icon" type="image/png" href="/vendor/horizon/img/favicon.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        // A sub-page that declares its own icon (a Horizon dashboard, docs
        // section, …) must keep it: the stored favicon is the one a browser
        // shows in the tab for that URL, not the site-wide root icon.
        $favicon = app(FaviconResolver::class)->resolve('https://example.com/horizon');

        $this->assertSame('https://example.com/vendor/horizon/img/favicon.png', $favicon);
        Http::assertNotSent(fn ($request): bool => $request->url() === 'https://example.com/favicon.ico');
    }

    public function test_a_declared_icon_that_does_not_resolve_falls_back_to_the_root_favicon_ico(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/broken.png' => Http::response('Not Found', 404),
            'https://example.com/docs' => Http::response(
                '<html><head><link rel="icon" href="/broken.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        // The page declares an icon, but it 404s. The reorder must fall through
        // to the root location rather than storing nothing at all.
        $favicon = app(FaviconResolver::class)->resolve('https://example.com/docs');

        $this->assertSame('https://example.com/favicon.ico', $favicon);
    }

    public function test_a_page_the_resolver_cannot_read_falls_back_to_the_root_favicon_ico(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/horizon' => Http::response('Forbidden', 403),
        ]);

        // Resolution happens anonymously, so a page behind auth answers 403 and
        // its declared icon is unreachable — the root icon is better than none.
        $favicon = app(FaviconResolver::class)->resolve('https://example.com/horizon');

        $this->assertSame('https://example.com/favicon.ico', $favicon);
    }

    public function test_it_parses_link_tags_from_the_page(): void
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

    public function test_numeric_host_spellings_that_curl_understands_are_refused(): void
    {
        Http::fake();

        $resolver = app(FaviconResolver::class);

        // curl resolves every one of these to a loopback / link-local address
        // (verified: `curl http://0x7f.0.0.1:PORT/` answers from 127.0.0.1),
        // while filter_var() sees no IP and the system resolver returns nothing
        // for the hex form — so the "unresolvable host" leniency must not cover
        // numeric literals.
        $this->assertNull($resolver->resolve('http://0x7f.0.0.1/'));
        $this->assertNull($resolver->resolve('http://0177.0.0.1/'));
        $this->assertNull($resolver->resolve('http://2130706433/'));
        $this->assertNull($resolver->resolve('http://127.1/'));

        Http::assertNothingSent();
    }

    public function test_zone_qualified_ipv6_literals_are_refused(): void
    {
        Http::fake();

        $resolver = app(FaviconResolver::class);

        // curl honours the zone id and connects to the link-local address,
        // while filter_var() cannot parse the zone-qualified form — so the
        // address part has to be checked on its own.
        $this->assertNull($resolver->resolve('http://[fe80::1%25en0]/'));
        $this->assertNull($resolver->resolve('http://[fe80::1]/'));
        $this->assertNull($resolver->resolve('http://[::ffff:127.0.0.1]/'));

        Http::assertNothingSent();
    }

    public function test_a_unicode_host_name_is_still_reachable(): void
    {
        Http::fake([
            '*' => Http::response('Not Found', 404),
        ]);

        // The system resolver does not speak IDN, so this host looks
        // unresolvable — but an IDN-capable curl reaches it, and the numeric-
        // literal guard must not turn "unresolvable name" into "blocked".
        $this->assertNull(app(FaviconResolver::class)->resolve('https://пример.рф/docs'));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'docs'));
    }

    public function test_a_page_cannot_dictate_an_unbounded_number_of_icon_probes(): void
    {
        $links = '';

        for ($i = 0; $i < 50; $i++) {
            $links .= '<link rel="icon" href="/icon-'.$i.'.png">';
        }

        Http::fake([
            'https://example.com/favicon.ico' => Http::response('Not Found', 404),
            'https://example.com/icon-*' => Http::response('Not Found', 404),
            'https://example.com' => Http::response(
                '<html><head>'.$links.'</head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $this->assertNull(app(FaviconResolver::class)->resolve('https://example.com'));

        // One page fetch + at most MAX_ICON_CANDIDATES probes + the root
        // fallback — not one request per declared icon.
        $probes = 0;

        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), '/icon-')) {
                $probes++;
            }
        }

        $this->assertLessThanOrEqual(5, $probes);
    }

    public function test_relative_hrefs_are_resolved_against_the_url_that_answered(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/docs/favicon.png' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/png']),
            'https://example.com/docs' => Http::response('', 301, ['Location' => 'https://example.com/docs/']),
            'https://example.com/docs/' => Http::response(
                '<html><head><link rel="icon" href="favicon.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        // The canonical trailing-slash redirect: resolving the relative href
        // against the requested URL would pick /favicon.png — the site-wide
        // icon this whole resolution order exists to stop preferring.
        $favicon = app(FaviconResolver::class)->resolve('https://example.com/docs');

        $this->assertSame('https://example.com/docs/favicon.png', $favicon);
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
                // Decodes to /ic'on.png — a single quote has no business in a
                // stored URL that ends up in an attribute / CSS url('…') sink.
                '<html><head><link rel="icon" href="/ic&#39;on.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $this->assertNull(app(FaviconResolver::class)->resolve('https://example.com'));
    }

    public function test_a_relative_href_is_resolved_against_the_documents_base_tag(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('root-icon', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/admin/favicon.ico' => Http::response('app-icon', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/admin' => Http::response(
                '<html><head><base href="/admin/"><link rel="icon" href="favicon.ico"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        // The shape every SPA served from a sub-path has. Ignoring <base> makes
        // `href="favicon.ico"` mean the host root — the site-wide icon, and a
        // different image than the one the browser shows on that page.
        $favicon = app(FaviconResolver::class)->resolve('https://example.com/admin');

        $this->assertSame('https://example.com/admin/favicon.ico', $favicon);
    }

    public function test_a_base_tag_pointing_at_another_host_is_honored_like_a_browser_does(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('Not Found', 404),
            'https://cdn.example.com/static/icon.png' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/png']),
            'https://example.com/app' => Http::response(
                '<html><head><base href="https://cdn.example.com/static/"><link rel="icon" href="icon.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $favicon = app(FaviconResolver::class)->resolve('https://example.com/app');

        // The candidate still passes the SSRF check and the storable-URL rules;
        // what <base> changes is which URL is proposed, not what is trusted.
        $this->assertSame('https://cdn.example.com/static/icon.png', $favicon);
    }

    public function test_an_unusable_base_tag_leaves_the_answering_url_in_place(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('Not Found', 404),
            'https://example.com/en/icon.png' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/png']),
            'https://example.com/en/docs' => Http::response(
                // A base with no usable host: honoring it would build candidates
                // the SSRF check has nothing to judge, so it is ignored and the
                // page URL keeps resolving the href.
                '<html><head><base href="data:text/html,x"><link rel="icon" href="icon.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $favicon = app(FaviconResolver::class)->resolve('https://example.com/en/docs');

        $this->assertSame('https://example.com/en/icon.png', $favicon);
    }

    public function test_the_favicon_of_the_linked_directory_is_probed_before_the_host_root(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('root-icon', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/admin/favicon.ico' => Http::response('app-icon', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/admin' => Http::response(
                // A single-page app: the shell declares no icon at all, because
                // its JavaScript inserts the <link> after the page has loaded.
                '<html><head><title>Loading&hellip;</title></head><body><div id="app"></div></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $favicon = app(FaviconResolver::class)->resolve('https://example.com/admin');

        $this->assertSame('https://example.com/admin/favicon.ico', $favicon);
        Http::assertNotSent(fn ($request): bool => $request->url() === 'https://example.com/favicon.ico');
    }

    public function test_the_host_root_stays_the_last_conventional_location(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('root-icon', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/admin/favicon.ico' => Http::response('Not Found', 404),
            'https://example.com/admin' => Http::response(
                '<html><head><title>No icon here</title></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $favicon = app(FaviconResolver::class)->resolve('https://example.com/admin');

        $this->assertSame('https://example.com/favicon.ico', $favicon);
    }

    public function test_the_last_path_segment_is_treated_as_a_file_when_it_has_an_extension(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('root-icon', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/docs/favicon.ico' => Http::response('docs-icon', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/docs/page.html' => Http::response(
                '<html><head><title>No icon here</title></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $favicon = app(FaviconResolver::class)->resolve('https://example.com/docs/page.html');

        // The directory of the document, not a directory named after it: nothing
        // is ever served from `/docs/page.html/`.
        $this->assertSame('https://example.com/docs/favicon.ico', $favicon);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'page.html/favicon.ico'));
    }

    public function test_a_link_to_the_host_root_is_probed_once(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('root-icon', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/' => Http::response(
                '<html><head><title>No icon here</title></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $favicon = app(FaviconResolver::class)->resolve('https://example.com/');

        $this->assertSame('https://example.com/favicon.ico', $favicon);

        // The directory candidate and the root candidate are the same URL here,
        // so the extra specificity must not cost an extra request.
        $probes = 0;

        foreach (Http::recorded() as [$request]) {
            if ($request->url() === 'https://example.com/favicon.ico') {
                $probes++;
            }
        }

        $this->assertSame(1, $probes);
    }
}
