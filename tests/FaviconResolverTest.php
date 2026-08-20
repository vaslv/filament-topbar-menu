<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Support\Facades\Http;
use ReflectionMethod;
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

        Http::assertNothingSent();
    }

    public function test_an_ipv4_address_wrapped_in_an_ipv6_one_is_refused(): void
    {
        Http::fake();

        $resolver = app(FaviconResolver::class);

        // The range flags do not see through the wrapper on every runtime this
        // package supports: on PHP 8.2 filter_var() calls all three of these
        // public, so the guard has to reduce them to the address the packet
        // actually reaches. Both spellings of the mapped form, plus the
        // deprecated IPv4-compatible one.
        $this->assertNull($resolver->resolve('http://[::ffff:127.0.0.1]/'));
        $this->assertNull($resolver->resolve('http://[::ffff:7f00:1]/'));
        $this->assertNull($resolver->resolve('http://[::ffff:10.0.0.1]/'));
        $this->assertNull($resolver->resolve('http://[::127.0.0.1]/'));

        // NAT64: on a network with such a gateway this is how a host reaches
        // 127.0.0.1 over IPv6, and no PHP version rejects it on its own.
        $this->assertNull($resolver->resolve('http://[64:ff9b::7f00:1]/'));

        Http::assertNothingSent();
    }

    public function test_a_public_ipv6_literal_is_still_reachable(): void
    {
        Http::fake([
            '*' => Http::response('Not Found', 404),
        ]);

        // Unwrapping the embedded-IPv4 forms must not turn into refusing IPv6
        // literals at large: an ordinary global-unicast address is fetched.
        $this->assertNull(app(FaviconResolver::class)->resolve('http://[2606:4700::1]/'));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '2606:4700'));
    }

    public function test_a_unicode_host_name_is_still_reachable(): void
    {
        if (! function_exists('idn_to_ascii')) {
            $this->markTestSkipped('ext-intl is not installed.');
        }

        Http::fake([
            '*' => Http::response('Not Found', 404),
        ]);

        // The system resolver does not speak IDN, so this host looks
        // unresolvable — but an IDN-capable curl reaches it, and the numeric-
        // literal guard must not turn "unresolvable name" into "blocked".
        $this->assertNull(app(FaviconResolver::class)->resolve('https://пример.рф/docs'));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'docs'));
    }

    public function test_without_ext_intl_a_unicode_host_is_refused_rather_than_misjudged(): void
    {
        if (function_exists('idn_to_ascii')) {
            $this->markTestSkipped('ext-intl is installed, so the degraded branch cannot run.');
        }

        Http::fake(['*' => Http::response('Not Found', 404)]);

        // Without intl the host keeps its Cyrillic last label, `рф` does not
        // start with an ASCII letter, and looksLikeHostName() therefore reads it
        // as a numeric literal and refuses it. That is the safe direction — the
        // guard exists to stop `0x7f.0.0.1` — but it does mean IDN favicons
        // cannot be resolved at all on an install without the extension, which
        // is why composer suggests it and the README says so.
        $this->assertNull(app(FaviconResolver::class)->resolve('https://пример.рф/docs'));

        Http::assertNothingSent();
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

    public function test_the_search_stops_when_it_runs_out_of_its_budget(): void
    {
        Http::fake(['*' => Http::response(self::PNG, 200, ['Content-Type' => 'image/png'])]);

        // `favicon_request_timeout` bounds ONE request; without a bound on the
        // search itself the worst case is the page plus five declared icons plus
        // six conventional locations, each following up to three redirects — 48
        // requests for one item, inside whatever is calling. Here the budget is
        // spent before the first one, so nothing goes out at all.
        $resolver = new class extends FaviconResolver
        {
            protected function resolutionTimeout(): float
            {
                return -1.0;
            }
        };

        $this->assertNull($resolver->resolve('https://example.com'));

        Http::assertNothingSent();
    }

    public function test_an_unusable_budget_falls_back_instead_of_removing_the_bound(): void
    {
        $timeout = new ReflectionMethod(FaviconResolver::class, 'resolutionTimeout');
        $resolver = new FaviconResolver;

        // The one thing a misconfigured cap must not do is uncap the search, so
        // every unusable value lands on the default rather than on zero or on
        // "no deadline".
        foreach ([0, -5, 'soon', null, ''] as $configured) {
            config(['filament-topbar-menu.favicon_resolution_timeout' => $configured]);

            $this->assertSame(
                20.0,
                $timeout->invoke($resolver),
                'A '.var_export($configured, true).' budget must fall back to the default.',
            );
        }

        config(['filament-topbar-menu.favicon_resolution_timeout' => 2.5]);
        $this->assertSame(2.5, $timeout->invoke($resolver));
    }

    public function test_an_icon_href_keeps_its_query_string_intact(): void
    {
        Http::fake([
            'https://example.com/icon.png?url=https://cdn.example/x' => Http::response(self::PNG, 200, ['Content-Type' => 'image/png']),
            'https://example.com/app' => Http::response(
                '<html><head><link rel="icon" href="/icon.png?url=https://cdn.example/x"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            '*' => Http::response('Not Found', 404),
        ]);

        // The query used to go through the path normaliser with the path: a
        // `//` in a value was collapsed and a `/../` in one rewrote the path
        // itself, so the resolver probed a URL the page never declared.
        $favicon = app(FaviconResolver::class)->resolve('https://example.com/app');

        $this->assertSame('https://example.com/icon.png?url=https://cdn.example/x', $favicon);
    }

    public function test_a_query_value_cannot_rewrite_the_path_it_belongs_to(): void
    {
        Http::fake([
            'https://example.com/icon.png?a=/../b' => Http::response(self::PNG, 200, ['Content-Type' => 'image/png']),
            'https://example.com/app' => Http::response(
                '<html><head><link rel="icon" href="/icon.png?a=/../b"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            '*' => Http::response('Not Found', 404),
        ]);

        // This one resolved to `https://example.com/b` — a different resource,
        // chosen by the contents of a query value.
        $favicon = app(FaviconResolver::class)->resolve('https://example.com/app');

        $this->assertSame('https://example.com/icon.png?a=/../b', $favicon);
    }

    public function test_a_double_slash_inside_a_path_is_part_of_the_path(): void
    {
        Http::fake([
            'https://example.com/cdn-cgi/image/width=32/https://origin.test/icon.png' => Http::response(self::PNG, 200, ['Content-Type' => 'image/png']),
            'https://example.com/app' => Http::response(
                '<html><head><link rel="icon" href="/cdn-cgi/image/width=32/https://origin.test/icon.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            '*' => Http::response('Not Found', 404),
        ]);

        // The shape Cloudflare Image Resizing emits. An empty segment is a
        // literal part of a path — only `.` and `..` are resolved away — so
        // collapsing it built a URL the origin does not serve and the page's
        // declared icon was skipped for a guess at conventional file names.
        $favicon = app(FaviconResolver::class)->resolve('https://example.com/app');

        $this->assertSame('https://example.com/cdn-cgi/image/width=32/https://origin.test/icon.png', $favicon);
    }

    public function test_a_query_only_href_keeps_the_pages_own_path(): void
    {
        Http::fake([
            'https://example.com/app/index.php?icon=1' => Http::response(self::PNG, 200, ['Content-Type' => 'image/png']),
            'https://example.com/app/index.php' => Http::response(
                '<html><head><link rel="icon" href="?icon=1"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            '*' => Http::response('Not Found', 404),
        ]);

        // RFC 3986 §5.3: a query-only reference replaces the query and keeps
        // the base path. Treating it as path-relative pointed at the directory
        // (`/app/?icon=1`), which is not what a browser fetches.
        $favicon = app(FaviconResolver::class)->resolve('https://example.com/app/index.php');

        $this->assertSame('https://example.com/app/index.php?icon=1', $favicon);
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

    public function test_the_conventional_probe_covers_svg_and_png_not_just_ico(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('Not Found', 404),
            'https://example.com/favicon.svg' => Http::response('<svg/>', 200, ['Content-Type' => 'image/svg+xml']),
            'https://example.com/' => Http::response('Unauthorized', 401),
        ]);

        // A site that ships only `favicon.svg` and declares it from markup this
        // resolver cannot read (here: the whole host is behind auth) still has a
        // findable icon — guessing at `.ico` alone would store nothing.
        $favicon = app(FaviconResolver::class)->resolve('https://example.com/');

        $this->assertSame('https://example.com/favicon.svg', $favicon);
    }

    public function test_the_conventional_probe_prefers_the_link_directory_over_the_root_in_any_format(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('root-icon', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/admin/favicon.ico' => Http::response('Not Found', 404),
            'https://example.com/admin/favicon.svg' => Http::response('<svg/>', 200, ['Content-Type' => 'image/svg+xml']),
            'https://example.com/admin' => Http::response(
                '<html><head><title>No icon here</title></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        // Every name is tried in the link's own directory before the host root,
        // so a sub-path app keeps its icon whatever format either side ships.
        $favicon = app(FaviconResolver::class)->resolve('https://example.com/admin');

        $this->assertSame('https://example.com/admin/favicon.svg', $favicon);
        Http::assertNotSent(fn ($request): bool => $request->url() === 'https://example.com/favicon.ico');
    }

    public function test_the_conventional_probe_prefers_ico_over_the_other_formats(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/favicon.svg' => Http::response('<svg/>', 200, ['Content-Type' => 'image/svg+xml']),
            'https://example.com/' => Http::response('Unauthorized', 401),
        ]);

        $favicon = app(FaviconResolver::class)->resolve('https://example.com/');

        $this->assertSame('https://example.com/favicon.ico', $favicon);
        Http::assertNotSent(fn ($request): bool => $request->url() === 'https://example.com/favicon.svg');
    }

    public function test_it_reads_an_icon_declared_with_unquoted_attributes(): void
    {
        Http::fake([
            'https://example.com/favicon.svg' => Http::response('<svg/>', 200, ['Content-Type' => 'image/svg+xml']),
            'https://example.com/' => Http::response(
                '<html><head><link rel=icon type=image/svg+xml href=/favicon.svg></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        // Unquoted attributes are valid HTML and what a minifier emits; the page
        // does declare its icon, so it must be read rather than guessed at.
        $favicon = app(FaviconResolver::class)->resolve('https://example.com/');

        $this->assertSame('https://example.com/favicon.svg', $favicon);
        Http::assertNotSent(fn ($request): bool => $request->url() === 'https://example.com/favicon.ico');
    }

    public function test_it_reads_a_base_href_declared_without_quotes(): void
    {
        Http::fake([
            'https://example.com/admin/favicon.png' => Http::response('app-icon', 200, ['Content-Type' => 'image/png']),
            'https://example.com/admin' => Http::response(
                '<html><head><base href=/admin/><link rel=icon href=favicon.png></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $favicon = app(FaviconResolver::class)->resolve('https://example.com/admin');

        $this->assertSame('https://example.com/admin/favicon.png', $favicon);
    }

    public function test_a_prefixed_attribute_is_not_read_as_the_one_asked_for(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response('root-icon', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/decoy.png' => Http::response('decoy', 200, ['Content-Type' => 'image/png']),
            'https://example.com/' => Http::response(
                '<html><head><link rel="icon" data-href="/decoy.png" href="/favicon.ico"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        // `data-href` is not `href`: the attribute name is matched on its own
        // boundary, so a prefixed one never stands in for the real value.
        $favicon = app(FaviconResolver::class)->resolve('https://example.com/');

        $this->assertSame('https://example.com/favicon.ico', $favicon);
        Http::assertNotSent(fn ($request): bool => $request->url() === 'https://example.com/decoy.png');
    }

    public function test_it_embeds_the_icon_bytes_when_asked_to(): void
    {
        Http::fake([
            'https://example.com/favicon.png' => Http::response(self::PNG, 200, ['Content-Type' => 'image/png']),
            'https://example.com/' => Http::response(
                '<html><head><link rel="icon" href="/favicon.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $favicon = app(FaviconResolver::class)->resolveEmbedded('https://example.com/');

        // The image itself, so nothing has to be reachable from a visitor's
        // browser at render time.
        $this->assertSame('data:image/png;base64,'.base64_encode(self::PNG), $favicon);
    }

    public function test_embedding_does_not_download_the_icon_a_second_time(): void
    {
        Http::fake([
            'https://example.com/favicon.ico' => Http::response(self::PNG, 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/' => Http::response('Unauthorized', 401),
        ]);

        app(FaviconResolver::class)->resolveEmbedded('https://example.com/');

        // The search already read the bytes to decide the candidate was an
        // image; embedding must reuse them rather than fetch the icon again.
        $probes = 0;

        foreach (Http::recorded() as [$request]) {
            if ($request->url() === 'https://example.com/favicon.ico') {
                $probes++;
            }
        }

        $this->assertSame(1, $probes);
    }

    public function test_embedding_refuses_bytes_that_are_not_a_whitelisted_image(): void
    {
        Http::fake([
            // A server that mislabels its own 404 page as an icon: the type
            // header says image, the bytes are HTML.
            'https://example.com/favicon.ico' => Http::response('<html>Not an icon</html>', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/favicon.svg' => Http::response('Not Found', 404),
            'https://example.com/favicon.png' => Http::response('Not Found', 404),
            'https://example.com/' => Http::response('Unauthorized', 401),
        ]);

        $favicon = app(FaviconResolver::class)->resolveEmbedded('https://example.com/');

        // The type is sniffed from the bytes, not taken from the header, so
        // nothing unrenderable is stored.
        $this->assertNull($favicon);
    }

    public function test_embedding_refuses_an_icon_over_the_byte_cap(): void
    {
        config()->set('filament-topbar-menu.favicon_max_bytes', strlen(self::PNG) - 1);

        Http::fake([
            'https://example.com/favicon.ico' => Http::response(self::PNG, 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/' => Http::response('Unauthorized', 401),
        ]);

        $this->assertNull(app(FaviconResolver::class)->resolveEmbedded('https://example.com/'));
    }

    public function test_the_byte_cap_does_not_stop_the_link_from_being_stored(): void
    {
        config()->set('filament-topbar-menu.favicon_max_bytes', strlen(self::PNG) - 1);

        Http::fake([
            'https://example.com/favicon.ico' => Http::response(self::PNG, 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/' => Http::response('Unauthorized', 401),
        ]);

        // The cap governs what goes into the record and the cached snapshot,
        // not whether the icon is the right one — an icon too big to embed is
        // still perfectly good to link to.
        $this->assertSame('https://example.com/favicon.ico', app(FaviconResolver::class)->resolve('https://example.com/'));
    }

    public function test_embedding_obeys_the_feature_switch(): void
    {
        config()->set('filament-topbar-menu.enable_favicons', false);

        Http::fake();

        $this->assertNull(app(FaviconResolver::class)->resolveEmbedded('https://example.com/'));

        Http::assertNothingSent();
    }

    public function test_a_declared_tab_icon_wins_over_an_apple_touch_icon_declared_first(): void
    {
        Http::fake([
            'https://example.com/apple-touch-icon.png' => Http::response('big-tile', 200, ['Content-Type' => 'image/png']),
            'https://example.com/favicon.ico' => Http::response('real-icon', 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/' => Http::response(
                '<html><head>'
                .'<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">'
                .'<link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">'
                .'<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">'
                .'</head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        // Pages routinely declare the home-screen tile first. It is 180×180 and
        // several times the bytes, while the topbar renders the marker at ~16px
        // — so document order must not decide this.
        $this->assertSame('https://example.com/favicon.ico', app(FaviconResolver::class)->resolve('https://example.com/'));

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'apple-touch-icon'));
    }

    public function test_an_apple_touch_icon_is_still_used_when_it_is_all_there_is(): void
    {
        Http::fake([
            'https://example.com/apple-touch-icon.png' => Http::response('tile', 200, ['Content-Type' => 'image/png']),
            'https://example.com/favicon.ico' => Http::response('Not Found', 404),
            'https://example.com/favicon.svg' => Http::response('Not Found', 404),
            'https://example.com/favicon.png' => Http::response('Not Found', 404),
            'https://example.com/' => Http::response(
                '<html><head><link rel="apple-touch-icon" href="/apple-touch-icon.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        // Ranking is a preference, not a filter — a lower-ranked icon is still
        // better than no icon.
        $this->assertSame(
            'https://example.com/apple-touch-icon.png',
            app(FaviconResolver::class)->resolve('https://example.com/'),
        );
    }

    public function test_embedding_moves_on_when_a_candidate_is_too_big_to_store(): void
    {
        config()->set('filament-topbar-menu.favicon_max_bytes', strlen(self::PNG));

        $oversized = self::PNG.str_repeat("\x00", 64);

        Http::fake([
            'https://example.com/big.png' => Http::response($oversized, 200, ['Content-Type' => 'image/png']),
            'https://example.com/small.png' => Http::response(self::PNG, 200, ['Content-Type' => 'image/png']),
            'https://example.com/' => Http::response(
                '<html><head>'
                .'<link rel="icon" href="/big.png">'
                .'<link rel="icon" href="/small.png">'
                .'</head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        // "Too big to store" is not the same answer as "this site has no icon":
        // the next declared candidate must still be tried.
        $this->assertSame(
            'data:image/png;base64,'.base64_encode(self::PNG),
            app(FaviconResolver::class)->resolveEmbedded('https://example.com/'),
        );
    }

    public function test_embedding_falls_through_to_a_conventional_location(): void
    {
        config()->set('filament-topbar-menu.favicon_max_bytes', strlen(self::PNG));

        Http::fake([
            'https://example.com/declared.png' => Http::response(self::PNG.str_repeat("\x00", 64), 200, ['Content-Type' => 'image/png']),
            'https://example.com/favicon.ico' => Http::response(self::PNG, 200, ['Content-Type' => 'image/x-icon']),
            'https://example.com/' => Http::response(
                '<html><head><link rel="icon" href="/declared.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        // The only declared icon is unstorable, so the search continues into
        // the conventional locations rather than giving up on the page.
        $this->assertSame(
            'data:image/png;base64,'.base64_encode(self::PNG),
            app(FaviconResolver::class)->resolveEmbedded('https://example.com/'),
        );
    }

    public function test_storing_a_link_is_unaffected_by_what_can_be_embedded(): void
    {
        config()->set('filament-topbar-menu.favicon_max_bytes', 8);

        Http::fake([
            'https://example.com/icon.png' => Http::response(self::PNG, 200, ['Content-Type' => 'image/png']),
            'https://example.com/' => Http::response(
                '<html><head><link rel="icon" href="/icon.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        // The cap governs what is copied into the record, not which icon the
        // site has — resolve() must not inherit the embed test.
        $this->assertSame('https://example.com/icon.png', app(FaviconResolver::class)->resolve('https://example.com/'));
    }

    public function test_embedding_keeps_the_ssrf_guard(): void
    {
        Http::fake();

        $this->assertNull(app(FaviconResolver::class)->resolveEmbedded('http://127.0.0.1/'));

        // Embedding is the same search as resolve(), so a private address is
        // refused before any request is made — the guard is not bypassed by
        // asking for the bytes instead of the link.
        Http::assertNothingSent();
    }
}
