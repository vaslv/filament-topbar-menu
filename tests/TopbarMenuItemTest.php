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
        $this->app->instance('request', Request::create('https://myapp.test/dashboard'));

        $active = new TopbarMenuItem(['type' => 'url', 'url' => 'https://myapp.test/dashboard']);
        $trailingSlash = new TopbarMenuItem(['type' => 'url', 'url' => 'https://myapp.test/dashboard/']);
        $withQuery = new TopbarMenuItem(['type' => 'url', 'url' => 'https://myapp.test/dashboard?tab=1']);
        $other = new TopbarMenuItem(['type' => 'url', 'url' => 'https://myapp.test/other']);

        $this->assertTrue($active->isActive());
        $this->assertTrue($trailingSlash->isActive());
        $this->assertTrue($withQuery->isActive());
        $this->assertFalse($other->isActive());
    }

    public function test_a_group_is_active_when_one_of_its_children_is_active(): void
    {
        $this->app->instance('request', Request::create('https://myapp.test/reports'));

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
}
