<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

class TopbarMenuItemTest extends TestCase
{
    public function test_it_builds_the_url_for_url_type_items(): void
    {
        $item = TopbarMenuItem::create([
            'label' => 'External',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com/docs',
        ]);

        $this->assertSame('https://example.com/docs', $item->resolveUrl());
    }

    public function test_it_builds_the_url_for_route_type_items(): void
    {
        Route::get('/reports/{report}', fn () => 'ok')->name('reports.show');

        $item = TopbarMenuItem::create([
            'label' => 'Report',
            'type' => TopbarMenuItem::TYPE_ROUTE,
            'route' => 'reports.show',
            'route_parameters' => ['report' => 7],
        ]);

        $this->assertSame(route('reports.show', ['report' => 7]), $item->resolveUrl());
        $this->assertStringEndsWith('/reports/7', $item->resolveUrl());
    }

    public function test_it_returns_null_for_unknown_routes(): void
    {
        $item = TopbarMenuItem::create([
            'label' => 'Broken',
            'type' => TopbarMenuItem::TYPE_ROUTE,
            'route' => 'route.that.does.not.exist',
        ]);

        $this->assertNull($item->resolveUrl());
    }

    public function test_it_returns_null_instead_of_throwing_when_a_required_route_parameter_is_missing(): void
    {
        Route::get('/users/{user}', fn () => 'ok')->name('users.show');

        $item = TopbarMenuItem::create([
            'label' => 'User',
            'type' => TopbarMenuItem::TYPE_ROUTE,
            'route' => 'users.show',
            // route_parameters intentionally omitted — the route needs {user}.
        ]);

        // Must not throw UrlGenerationException: the menu renders on every
        // panel page, so a bad item would otherwise 500 the whole panel.
        $this->assertNull($item->resolveUrl());
    }

    public function test_it_returns_null_instead_of_throwing_for_a_non_scalar_route_parameter(): void
    {
        Route::get('/reports/{report}', fn () => 'ok')->name('reports.view');

        $item = TopbarMenuItem::create([
            'label' => 'Report',
            'type' => TopbarMenuItem::TYPE_ROUTE,
            'route' => 'reports.view',
        ]);

        // An array where a path parameter is expected throws an "Array to
        // string conversion" ErrorException, not UrlGenerationException. The
        // import validator blocks this, but resolveUrl must still fail safe for
        // a value written directly to the column (seeder, manual SQL).
        $item->forceFill(['route_parameters' => ['report' => ['nested']]])->save();

        $this->assertNull($item->resolveUrl());
    }

    public function test_the_per_item_target_is_honored_literally(): void
    {
        // "Same tab" must always mean same tab — even for an external URL and
        // even when open_external_links_in_new_tab is enabled. It must never be
        // silently promoted to a new tab.
        config()->set('filament-topbar-menu.open_external_links_in_new_tab', true);

        $sameTab = TopbarMenuItem::create([
            'label' => 'External, same tab',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://other-service.com',
            'target' => TopbarMenuItem::TARGET_SELF,
        ]);

        $newTab = TopbarMenuItem::create([
            'label' => 'External, new tab',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://other-service.com',
            'target' => TopbarMenuItem::TARGET_BLANK,
        ]);

        $this->assertSame('_self', $sameTab->resolveTarget());
        $this->assertSame('_blank', $newTab->resolveTarget());
    }

    public function test_target_defaults_to_self_when_unset(): void
    {
        $item = new TopbarMenuItem([
            'label' => 'No explicit target',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://other-service.com',
        ]);

        $this->assertSame('_self', $item->resolveTarget());
    }

    public function test_visibility_rules_are_evaluated_per_user(): void
    {
        $everyone = new TopbarMenuItem(['visibility' => null]);
        $authOnly = new TopbarMenuItem(['visibility' => ['auth' => true]]);
        $guestOnly = new TopbarMenuItem(['visibility' => ['guest' => true]]);

        $user = new GenericUser(['id' => 1]);

        $this->assertTrue($everyone->isVisibleTo(null));
        $this->assertTrue($everyone->isVisibleTo($user));

        $this->assertFalse($authOnly->isVisibleTo(null));
        $this->assertTrue($authOnly->isVisibleTo($user));

        $this->assertTrue($guestOnly->isVisibleTo(null));
        $this->assertFalse($guestOnly->isVisibleTo($user));
    }

    public function test_visibility_mode_mapping_preserves_roles(): void
    {
        // Array -> mode.
        $this->assertSame('auth', TopbarMenuItem::visibilityModeFromArray(['auth' => true]));
        $this->assertSame('guest', TopbarMenuItem::visibilityModeFromArray(['guest' => true]));
        $this->assertNull(TopbarMenuItem::visibilityModeFromArray(['roles' => ['admin']]));
        $this->assertNull(TopbarMenuItem::visibilityModeFromArray(null));

        // Mode -> array, merged onto existing visibility without dropping roles.
        $this->assertSame(
            ['roles' => ['admin'], 'auth' => true],
            TopbarMenuItem::applyVisibilityMode(['roles' => ['admin']], 'auth'),
        );

        // Clearing the mode keeps the roles restriction intact.
        $this->assertSame(
            ['roles' => ['admin']],
            TopbarMenuItem::applyVisibilityMode(['auth' => true, 'roles' => ['admin']], null),
        );

        // Switching mode replaces only the auth/guest key.
        $this->assertSame(
            ['guest' => true],
            TopbarMenuItem::applyVisibilityMode(['auth' => true], 'guest'),
        );

        // Nothing set at all -> null (column stays empty).
        $this->assertNull(TopbarMenuItem::applyVisibilityMode(null, null));
    }

    public function test_is_active_matches_the_current_url(): void
    {
        $this->bindRequest('https://myapp.test/dashboard');

        $active = new TopbarMenuItem(['type' => 'url', 'url' => 'https://myapp.test/dashboard']);
        $trailingSlash = new TopbarMenuItem(['type' => 'url', 'url' => 'https://myapp.test/dashboard/']);
        $withQuery = new TopbarMenuItem(['type' => 'url', 'url' => 'https://myapp.test/dashboard?tab=1']);
        $other = new TopbarMenuItem(['type' => 'url', 'url' => 'https://myapp.test/other']);

        $this->assertTrue($active->isActive());
        $this->assertTrue($trailingSlash->isActive());
        $this->assertTrue($withQuery->isActive());
        $this->assertFalse($other->isActive());
    }

    public function test_is_active_matches_a_relative_link(): void
    {
        // The shape the package's own seeder writes (`/telescope`, `/horizon`).
        // Compared whole against `request()->url()` it could never match, so the
        // demo menu highlighted nothing at all.
        $this->bindRequest('https://myapp.test/telescope');

        $relative = new TopbarMenuItem(['type' => 'url', 'url' => '/telescope']);
        $other = new TopbarMenuItem(['type' => 'url', 'url' => '/horizon']);

        $this->assertTrue($relative->isActive());
        $this->assertFalse($other->isActive());

        // The same item the visibility rule already called current-host: the
        // two answers used to contradict each other.
        $this->assertTrue($relative->pointsAtCurrentHost());
    }

    public function test_is_active_ignores_differences_that_are_not_differences(): void
    {
        $this->bindRequest('https://myapp.test/dashboard');

        foreach ([
            'a fragment' => 'https://myapp.test/dashboard#top',
            'a host in another case' => 'https://MyApp.test/dashboard',
            'an explicit default port' => 'https://myapp.test:443/dashboard',
            'the other scheme' => 'http://myapp.test/dashboard',
            'a www. prefix' => 'https://www.myapp.test/dashboard',
            'a query and a fragment' => 'https://myapp.test/dashboard?tab=1#top',
        ] as $case => $url) {
            $this->assertTrue(
                (new TopbarMenuItem(['type' => 'url', 'url' => $url]))->isActive(),
                "An item differing only by {$case} is the current page.",
            );
        }
    }

    public function test_is_active_still_refuses_another_page_or_another_host(): void
    {
        $this->bindRequest('https://myapp.test/dashboard');

        foreach ([
            'another host' => 'https://elsewhere.test/dashboard',
            'another path on this host' => 'https://myapp.test/reports',
            'a path that merely starts the same' => 'https://myapp.test/dashboard-archive',
            'a relative link to another page' => '/reports',
            'a mailto link' => 'mailto:team@myapp.test',
            'a tel link' => 'tel:+123456789',
        ] as $case => $url) {
            $this->assertFalse(
                (new TopbarMenuItem(['type' => 'url', 'url' => $url]))->isActive(),
                "An item pointing at {$case} is not the current page.",
            );
        }
    }

    public function test_is_active_matches_the_root_however_it_is_spelled(): void
    {
        $this->bindRequest('https://myapp.test');

        foreach (['https://myapp.test', 'https://myapp.test/', '/'] as $url) {
            $this->assertTrue(
                (new TopbarMenuItem(['type' => 'url', 'url' => $url]))->isActive(),
                "[{$url}] is the root page.",
            );
        }
    }

    public function test_a_group_is_active_when_one_of_its_children_is_active(): void
    {
        $this->bindRequest('https://myapp.test/reports');

        $parent = TopbarMenuItem::create(['label' => 'Group', 'type' => 'url', 'url' => 'https://myapp.test/overview']);
        TopbarMenuItem::create(['label' => 'Reports', 'type' => 'url', 'url' => 'https://myapp.test/reports', 'parent_id' => $parent->id]);

        $lonelyParent = TopbarMenuItem::create(['label' => 'Other', 'type' => 'url', 'url' => 'https://myapp.test/other']);
        TopbarMenuItem::create(['label' => 'Child', 'type' => 'url', 'url' => 'https://myapp.test/child', 'parent_id' => $lonelyParent->id]);

        $this->assertFalse($parent->isActive());
        $this->assertTrue($parent->isBranchActive());
        $this->assertFalse($lonelyParent->isBranchActive());
    }

    public function test_role_visibility_fails_closed_when_it_cannot_be_evaluated(): void
    {
        $rolesOnly = new TopbarMenuItem(['visibility' => ['roles' => ['admin']]]);

        // A guest can never satisfy a role restriction.
        $this->assertFalse($rolesOnly->isVisibleTo(null));

        // A user model without hasAnyRole() must hide the item, not leak it to
        // everyone — the restriction cannot be evaluated, so fail closed.
        $this->assertFalse($rolesOnly->isVisibleTo(new GenericUser(['id' => 1])));
    }

    public function test_role_visibility_uses_has_any_role_when_the_user_supports_it(): void
    {
        $rolesOnly = new TopbarMenuItem(['visibility' => ['roles' => ['admin']]]);

        $admin = new class implements Authenticatable
        {
            use AuthenticatableTrait;

            /** @param  array<int, string>  $roles */
            public function hasAnyRole(array $roles): bool
            {
                return in_array('admin', $roles, true);
            }
        };

        $editor = new class implements Authenticatable
        {
            use AuthenticatableTrait;

            /** @param  array<int, string>  $roles */
            public function hasAnyRole(array $roles): bool
            {
                return false;
            }
        };

        $this->assertTrue($rolesOnly->isVisibleTo($admin));
        $this->assertFalse($rolesOnly->isVisibleTo($editor));
    }

    /**
     * @param  array<string, mixed>|null  $visibility
     */
    private function urlItem(?string $url, ?array $visibility = ['hide_on_current_domain' => true]): TopbarMenuItem
    {
        return new TopbarMenuItem([
            'label' => 'Item',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => $url,
            'visibility' => $visibility,
        ]);
    }

    public function test_hide_on_current_domain_matches_hosts_by_normalized_identity(): void
    {
        $this->bindRequest('https://a.example.com/admin');

        // Hosts identifying the current service: case, www., default ports,
        // scheme and a trailing dot never matter.
        foreach ([
            'https://a.example.com/some/path',
            'https://www.a.example.com',
            'HTTPS://A.EXAMPLE.COM',
            'https://a.example.com:443/x',
            'http://a.example.com',
            'https://a.example.com./x',
        ] as $url) {
            $this->assertFalse($this->urlItem($url)->isVisibleTo(null), "Expected {$url} to be hidden on a.example.com");
        }

        // Different services: subdomains are significant.
        foreach ([
            'https://b.example.com',
            'https://example.com',
            'https://a.example.org',
        ] as $url) {
            $this->assertTrue($this->urlItem($url)->isVisibleTo(null), "Expected {$url} to stay visible on a.example.com");
        }
    }

    public function test_hide_on_current_domain_strips_www_from_the_request_side_too(): void
    {
        $this->bindRequest('https://www.a.example.com/admin');

        $this->assertFalse($this->urlItem('https://a.example.com')->isVisibleTo(null));
    }

    public function test_hide_on_current_domain_treats_non_standard_ports_as_significant(): void
    {
        // A dev fleet on ports of one localhost: each service hides only its
        // own port, not every flagged item.
        $this->bindRequest('http://localhost:8000/admin');

        $this->assertFalse($this->urlItem('http://localhost:8000/dashboard')->isVisibleTo(null));
        $this->assertTrue($this->urlItem('http://localhost:8001')->isVisibleTo(null));

        // A non-standard item port never matches a default request port…
        $this->bindRequest('https://a.example.com/admin');

        $this->assertTrue($this->urlItem('https://a.example.com:8443')->isVisibleTo(null));

        // …and the mirror case: a request served on a non-standard port never
        // matches an item URL without one (request-side port normalization).
        $this->bindRequest('https://a.example.com:8443/admin');

        $this->assertTrue($this->urlItem('https://a.example.com')->isVisibleTo(null));
    }

    public function test_hide_on_current_domain_is_ignored_on_route_items(): void
    {
        Route::get('/dashboard', fn () => 'ok')->name('dashboard');

        $this->bindRequest('https://myapp.test/admin');

        // The key can only land on a route item via a seeder or import; the
        // rule is for external links only, so it is ignored — the item stays
        // visible even though route() generates a current-host URL.
        $item = new TopbarMenuItem([
            'label' => 'Dashboard',
            'type' => TopbarMenuItem::TYPE_ROUTE,
            'route' => 'dashboard',
            'visibility' => ['hide_on_current_domain' => true],
        ]);

        $this->assertFalse($item->pointsAtCurrentHost());
        $this->assertTrue($item->isVisibleTo(null));
    }

    public function test_hide_on_current_domain_defensive_branches_for_hostless_and_malformed_urls(): void
    {
        // These values cannot be stored through the form (`->url()` requires
        // `://`) or the import (http(s) only) — only via a seeder or manual
        // SQL. The check must still behave deterministically for them.
        $this->bindRequest('https://a.example.com/admin');

        // A relative URL is, by definition, the current domain.
        $this->assertFalse($this->urlItem('/dashboard')->isVisibleTo(null));

        // A scheme without a host is not "the current domain" — never hidden.
        $this->assertTrue($this->urlItem('mailto:team@a.example.com')->isVisibleTo(null));
        $this->assertTrue($this->urlItem('tel:+123456789')->isVisibleTo(null));

        // parse_url() === false: an undeterminable domain is not a match.
        $this->assertTrue($this->urlItem('http:///path')->isVisibleTo(null));

        // A URL-less dropdown group: nothing to compare, the flag is inert.
        $this->assertTrue($this->urlItem(null)->isVisibleTo(null));
    }

    public function test_hide_on_current_domain_aligns_punycode_and_unicode_spellings(): void
    {
        if (! function_exists('idn_to_ascii')) {
            $this->markTestSkipped('ext-intl is not installed.');
        }

        $this->bindRequest('https://xn--e1afmkfd.xn--p1ai/admin');

        $this->assertFalse($this->urlItem('https://пример.рф')->isVisibleTo(null));

        // Mixed-case unicode relies on UTS #46 case folding inside
        // idn_to_ascii (byte-wise strtolower does not touch multibyte chars).
        $this->assertFalse($this->urlItem('https://Пример.рф')->isVisibleTo(null));

        // Deviation characters (ß) must keep their punycode identity —
        // non-transitional processing, the way browsers resolve the host. A
        // transitional ICU would fold faß.de to fass.de and miss.
        $this->bindRequest('https://xn--fa-hia.de/admin');

        $this->assertFalse($this->urlItem('https://faß.de')->isVisibleTo(null));
        $this->assertTrue($this->urlItem('https://fass.de')->isVisibleTo(null));
    }

    public function test_an_unconvertible_idn_host_is_compared_as_is(): void
    {
        // A DNS label longer than 63 chars makes idn_to_ascii return false —
        // the fail-open branch must compare the raw host instead of hiding
        // the item or breaking the visibility check.
        $long = str_repeat('a', 64);

        $this->bindRequest('https://a.example.com/admin');

        $this->assertTrue($this->urlItem("https://{$long}.example.com")->isVisibleTo(null));

        $this->bindRequest("https://{$long}.example.com/admin");

        $this->assertFalse($this->urlItem("https://{$long}.example.com")->isVisibleTo(null));
    }

    public function test_hide_on_current_domain_canonicalizes_ipv6_literals(): void
    {
        // One address, two spellings: zero-compressed and fully expanded
        // literals must identify the same service; a different address on the
        // same port must not.
        $this->bindRequest('http://[2001:db8::1]:8000/admin');

        $this->assertFalse($this->urlItem('http://[2001:0db8:0:0:0:0:0:1]:8000/x')->isVisibleTo(null));
        $this->assertFalse($this->urlItem('http://[2001:db8::1]:8000')->isVisibleTo(null));
        $this->assertTrue($this->urlItem('http://[2001:db8::2]:8000')->isVisibleTo(null));
    }

    public function test_hide_on_current_domain_wins_over_a_matching_role(): void
    {
        $this->bindRequest('https://a.example.com/admin');

        $item = $this->urlItem('https://a.example.com', [
            'roles' => ['admin'],
            'hide_on_current_domain' => true,
        ]);

        $admin = new class implements Authenticatable
        {
            use AuthenticatableTrait;

            /** @param  array<int, string>  $roles */
            public function hasAnyRole(array $roles): bool
            {
                return true;
            }
        };

        // The domain check runs BEFORE the roles branch (which ends in an
        // early return): even a user with a matching role must not see the
        // item on its own host…
        $this->assertFalse($item->isVisibleTo($admin));

        // …while on a foreign host the roles branch takes over again.
        $this->bindRequest('https://b.example.com/admin');

        $this->assertTrue($item->isVisibleTo($admin));
    }

    public function test_items_without_the_flag_ignore_the_current_host(): void
    {
        $this->bindRequest('https://a.example.com/admin');

        $this->assertTrue($this->urlItem('https://a.example.com', null)->isVisibleTo(null));
        $this->assertTrue($this->urlItem('https://a.example.com', ['hide_on_current_domain' => false])->isVisibleTo(null));
    }

    public function test_hide_on_current_domain_stays_deterministic_in_console_context(): void
    {
        // Outside HTTP, Laravel's SetRequestForConsole bootstrap builds the
        // request from app.url, so the check stays deterministic without a
        // real request. No request is bound here on purpose. (In this suite
        // the console request carries the skeleton's default host, localhost:
        // Testbench creates it before defineEnvironment overrides app.url.)
        $consoleHost = request()->getHost();

        $this->assertFalse($this->urlItem("http://{$consoleHost}/status")->isVisibleTo(null));
        $this->assertTrue($this->urlItem('https://other.test')->isVisibleTo(null));
    }

    public function test_apply_hide_on_current_domain_preserves_foreign_keys(): void
    {
        // Enabling adds only its own key.
        $this->assertSame(
            ['roles' => ['admin'], 'hide_on_current_domain' => true],
            TopbarMenuItem::applyHideOnCurrentDomain(['roles' => ['admin']], true),
        );

        // Disabling removes only its own key.
        $this->assertSame(
            ['auth' => true],
            TopbarMenuItem::applyHideOnCurrentDomain(['auth' => true, 'hide_on_current_domain' => true], false),
        );

        // An empty result collapses to null (column stays empty).
        $this->assertNull(TopbarMenuItem::applyHideOnCurrentDomain(['hide_on_current_domain' => true], false));
        $this->assertNull(TopbarMenuItem::applyHideOnCurrentDomain(null, false));

        // Chained over applyVisibilityMode: both keys survive one submit.
        $this->assertSame(
            ['hide_on_current_domain' => true, 'auth' => true],
            TopbarMenuItem::applyHideOnCurrentDomain(
                TopbarMenuItem::applyVisibilityMode(['hide_on_current_domain' => true], 'auth'),
                true,
            ),
        );
    }

    public function test_display_icon_prefers_the_favicon_and_validates_the_icon_name(): void
    {
        $withFavicon = new TopbarMenuItem([
            'icon' => 'heroicon-o-home',
            'favicon_url' => 'https://example.com/favicon.ico',
        ]);

        $this->assertSame('https://example.com/favicon.ico', $withFavicon->displayIcon());

        $withIcon = new TopbarMenuItem(['icon' => 'heroicon-o-home']);

        $this->assertSame('heroicon-o-home', $withIcon->displayIcon());

        $withBadIcon = new TopbarMenuItem(['icon' => 'not-a-real-icon-xyz']);

        $this->assertNull($withBadIcon->displayIcon());

        $this->assertNull((new TopbarMenuItem)->displayIcon());
    }

    public function test_safe_icon_name_returns_known_heroicons_unchanged(): void
    {
        $this->assertSame('heroicon-o-home', TopbarMenuItem::safeIconName('heroicon-o-home'));
    }

    public function test_safe_icon_name_returns_null_for_blank_or_unknown_names(): void
    {
        $this->assertNull(TopbarMenuItem::safeIconName(null));
        $this->assertNull(TopbarMenuItem::safeIconName(''));
        $this->assertNull(TopbarMenuItem::safeIconName('not-a-real-icon-xyz'));
    }

    public function test_display_icon_prefers_an_embedded_favicon_over_the_url(): void
    {
        $data = TopbarMenuItem::faviconDataFromBytes(self::PNG);

        $item = new TopbarMenuItem([
            'favicon_url' => 'https://example.com/favicon.ico',
            'favicon_data' => $data,
            'icon' => 'heroicon-o-home',
        ]);

        // The embedded copy exists precisely because the URL is not reachable
        // from every visitor's browser; preferring the link would put back the
        // broken image it was stored to avoid.
        $this->assertSame($data, $item->displayIcon());
    }

    public function test_display_icon_falls_back_when_the_embedded_favicon_is_not_renderable(): void
    {
        // A fleet-shared menu table, a seeder or hand-written SQL can put
        // anything in the column; the value goes into an <img src>, so it is
        // validated on the way out and not merely on the way in.
        foreach ([
            'javascript:alert(1)',
            'data:text/html;base64,'.base64_encode('<script>alert(1)</script>'),
            'data:image/png,%3Cscript%3E',
            'data:image/png;base64,"><script>alert(1)</script>',
            'not a data uri at all',
        ] as $stored) {
            $item = new TopbarMenuItem([
                'favicon_url' => 'https://example.com/favicon.ico',
                'favicon_data' => $stored,
            ]);

            $this->assertSame('https://example.com/favicon.ico', $item->displayIcon());
        }
    }

    public function test_an_embedded_favicon_over_the_cap_is_not_rendered(): void
    {
        $data = TopbarMenuItem::faviconDataFromBytes(self::PNG);

        config()->set('filament-topbar-menu.favicon_max_bytes', strlen(self::PNG) - 1);

        $item = new TopbarMenuItem(['favicon_data' => $data, 'icon' => 'heroicon-o-home']);

        // Lowering the cap must take effect for values already stored — the cap
        // protects the cached snapshot, which is rebuilt from what is rendered.
        $this->assertSame('heroicon-o-home', $item->displayIcon());
    }

    public function test_favicon_data_is_built_from_the_bytes_not_from_a_claimed_type(): void
    {
        $this->assertSame(
            'data:image/png;base64,'.base64_encode(self::PNG),
            TopbarMenuItem::faviconDataFromBytes(self::PNG),
        );

        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"></svg>';

        $this->assertSame(
            'data:image/svg+xml;base64,'.base64_encode($svg),
            TopbarMenuItem::faviconDataFromBytes($svg),
        );

        // Not an image in any format the whitelist covers.
        $this->assertNull(TopbarMenuItem::faviconDataFromBytes('<html><body>hi</body></html>'));
        $this->assertNull(TopbarMenuItem::faviconDataFromBytes(''));
    }

    public function test_favicon_data_refuses_bytes_over_the_cap(): void
    {
        config()->set('filament-topbar-menu.favicon_max_bytes', strlen(self::PNG) - 1);

        $this->assertNull(TopbarMenuItem::faviconDataFromBytes(self::PNG));
    }

    public function test_the_byte_cap_falls_back_when_configured_with_nonsense(): void
    {
        foreach ([null, 0, -1, 'huge', []] as $configured) {
            config()->set('filament-topbar-menu.favicon_max_bytes', $configured);

            $this->assertSame(TopbarMenuItem::FAVICON_MAX_BYTES_DEFAULT, TopbarMenuItem::faviconMaxBytes());
        }
    }

    public function test_the_byte_cap_is_clamped_to_what_the_column_can_hold(): void
    {
        config()->set('filament-topbar-menu.favicon_max_bytes', 64 * 1024 * 1024);

        // `favicon_data` is mediumText holding base64, so a decoded image past
        // ~12.58 MB cannot fit. Honouring a larger configured value would mean
        // MySQL truncating the icon silently in non-strict mode.
        $this->assertSame(12582911, TopbarMenuItem::faviconMaxBytes());
        $this->assertLessThan(16777215, TopbarMenuItem::faviconMaxBytes() / 3 * 4);
    }

    public function test_the_byte_cap_is_inclusive_and_both_checks_agree_on_the_edge(): void
    {
        config()->set('filament-topbar-menu.favicon_max_bytes', strlen(self::PNG));

        $data = TopbarMenuItem::faviconDataFromBytes(self::PNG);

        // Exactly at the cap is stored — and what the writer stores the render
        // path must accept, or the item silently loses its icon.
        $this->assertNotNull($data);
        $this->assertTrue(TopbarMenuItem::isStorableFaviconData($data));

        config()->set('filament-topbar-menu.favicon_max_bytes', strlen(self::PNG) - 1);

        $this->assertNull(TopbarMenuItem::faviconDataFromBytes(self::PNG));
        $this->assertFalse(TopbarMenuItem::isStorableFaviconData($data));
    }

    public function test_a_real_ico_keeps_its_own_type(): void
    {
        $ico = self::ico();

        // `favicon.ico` is the first location the resolver probes and the
        // commonest icon on the web, so the sniffer has to read the real
        // format — PHP reports it as image/vnd.microsoft.icon, not image/x-icon.
        $this->assertSame(
            'data:image/vnd.microsoft.icon;base64,'.base64_encode($ico),
            TopbarMenuItem::faviconDataFromBytes($ico),
        );

        $this->assertTrue(TopbarMenuItem::isStorableFaviconData(TopbarMenuItem::faviconDataFromBytes($ico)));
    }

    public function test_an_svg_whose_root_sits_past_a_long_preamble_is_still_recognised(): void
    {
        $svg = self::svgWithLongPreamble();

        // An SVG exported from a design tool leads with a generator comment and
        // a DOCTYPE, which pushes <svg> well past the first kilobyte. That file
        // is exactly the `favicon.svg` this feature exists to store, so a sniff
        // window that stopped short would refuse the main case.
        $this->assertGreaterThan(1024, strpos($svg, '<svg'));
        $this->assertSame(
            'data:image/svg+xml;base64,'.base64_encode($svg),
            TopbarMenuItem::faviconDataFromBytes($svg),
        );
    }

    public function test_a_document_that_only_mentions_svg_is_not_treated_as_one(): void
    {
        // The sniff is on the element, not the word — a text file talking about
        // SVG must not become an icon.
        $this->assertNull(TopbarMenuItem::faviconDataFromBytes(
            'This file explains the svg format and how svg icons are exported.',
        ));
    }

    public function test_the_container_signatures_cover_webp_and_avif(): void
    {
        $vp8l = "\x2f".str_repeat("\x00", 24);
        $webp = 'RIFF'.pack('V', 12 + strlen($vp8l)).'WEBP'.'VP8L'.pack('V', strlen($vp8l)).$vp8l;

        $this->assertSame('data:image/webp;base64,'.base64_encode($webp), TopbarMenuItem::faviconDataFromBytes($webp));

        // getimagesizefromstring() has no AVIF parser, so the brand check is
        // the only thing keeping a whitelisted format usable.
        $avif = pack('N', 32).'ftyp'.'avif'.pack('N', 0).'avifmif1miaf'.str_repeat("\x00", 40);

        $this->assertSame('data:image/avif;base64,'.base64_encode($avif), TopbarMenuItem::faviconDataFromBytes($avif));

        // A brand this package does not claim must not be relabelled.
        $this->assertNull(TopbarMenuItem::faviconDataFromBytes(pack('N', 24).'ftyp'.'isom'.pack('N', 0).'isomiso2'));
    }

    public function test_an_embedded_favicon_with_a_trailing_newline_is_refused(): void
    {
        // PCRE's `$` also matches before a final newline, so the gate is
        // anchored with `\z` — otherwise a value with trailing whitespace would
        // reach an <img src> / CSS url('…') sink that has no use for it.
        $this->assertFalse(TopbarMenuItem::isStorableFaviconData(
            'data:image/png;base64,'.base64_encode(self::PNG)."\n",
        ));
    }

    public function test_an_oversized_value_is_refused_without_scanning_it_all(): void
    {
        $junk = 'data:image/png;base64,'.str_repeat('A', 4 * 1024 * 1024);

        $start = microtime(true);
        $storable = TopbarMenuItem::isStorableFaviconData($junk);
        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertFalse($storable);

        // This runs on every render of every item against an untrusted column,
        // so the length has to be rejected in constant time rather than after a
        // full regex scan and a multi-megabyte decode.
        $this->assertLessThan(1.0, $elapsed, 'Oversized values must be refused by length, not by scanning.');
    }
}
