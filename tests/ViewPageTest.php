<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Filament\Actions\ActionsServiceProvider;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\ViewAction;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Routing\RouteCollection;
use Livewire\Component;
use ReflectionMethod;
use ReflectionProperty;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource\Pages\CreateTopbarMenuItem;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource\Pages\EditTopbarMenuItem;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\Tests\Fixtures\TestPanelProvider;

/**
 * The read-only View page is on by default and switched off with
 * `enable_view_page` (FILAMENT_TOPBAR_MENU_VIEW_PAGE): on it adds a route, a
 * row action and the post-save landing page; off the resource is the plain
 * list/create/edit it was before the feature existed.
 *
 * The panel here boots on the package's own default — the route is registered
 * from getPages() at boot time, so the enabled case cannot be set up per test.
 */
class ViewPageTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            TestPanelProvider::class,
        ]);
    }

    public function test_the_page_is_routed_after_create_so_the_create_url_is_not_swallowed(): void
    {
        // No config set anywhere in this class: the page is part of the
        // resource unless an application turns it off.
        $this->assertTrue(TopbarMenuItemResource::hasViewPage());

        $pages = TopbarMenuItemResource::getPages();

        $this->assertArrayHasKey('view', $pages);

        // `/{record}` matches `/create` as well, so the create page has to be
        // registered first or it becomes unreachable.
        $this->assertSame(['index', 'create', 'view', 'edit'], array_keys($pages));

        $item = TopbarMenuItem::create(['label' => 'Docs', 'url' => 'https://docs.example.com']);

        $this->assertSame(
            "http://localhost/admin/topbar-menu-items/{$item->getKey()}",
            TopbarMenuItemResource::getUrl('view', ['record' => $item]),
        );
    }

    public function test_creating_an_item_lands_on_the_view_page(): void
    {
        $item = TopbarMenuItem::create(['label' => 'Docs', 'url' => 'https://docs.example.com']);

        $page = new CreateTopbarMenuItem;
        $page->record = $item;

        // Filament's own CreateRecord picks `view` once the page is registered;
        // the promise is in the README, so pin it rather than trusting the
        // framework's default to stay put across upgrades.
        $this->assertSame(
            TopbarMenuItemResource::getUrl('view', ['record' => $item]),
            (new ReflectionMethod($page, 'getRedirectUrl'))->invoke($page),
        );
    }

    public function test_creating_an_item_lands_on_the_edit_form_while_the_page_is_disabled(): void
    {
        config()->set('filament-topbar-menu.enable_view_page', false);

        $item = TopbarMenuItem::create(['label' => 'Docs', 'url' => 'https://docs.example.com']);

        $page = new CreateTopbarMenuItem;
        $page->record = $item;

        $this->assertSame(
            TopbarMenuItemResource::getUrl('edit', ['record' => $item]),
            (new ReflectionMethod($page, 'getRedirectUrl'))->invoke($page),
        );
    }

    public function test_saving_an_edit_lands_on_the_view_page(): void
    {
        $item = TopbarMenuItem::create(['label' => 'Docs', 'url' => 'https://docs.example.com']);

        $page = new EditTopbarMenuItem;
        $page->record = $item;

        $redirect = (new ReflectionMethod($page, 'getRedirectUrl'))->invoke($page);

        // Filament's own default is to stay on the edit form (null) — with the
        // page enabled, an edit finishes where a create does.
        $this->assertSame(TopbarMenuItemResource::getUrl('view', ['record' => $item]), $redirect);
    }

    public function test_the_table_and_the_edit_page_offer_a_view_action(): void
    {
        $this->assertTrue($this->tableHasViewAction());
        $this->assertTrue($this->editPageHasViewAction());
    }

    public function test_nothing_is_added_while_the_page_is_disabled(): void
    {
        config()->set('filament-topbar-menu.enable_view_page', false);

        $this->assertFalse(TopbarMenuItemResource::hasViewPage());
        $this->assertArrayNotHasKey('view', TopbarMenuItemResource::getPages());

        // Not merely hidden: a ViewAction without a view route falls back to a
        // modal, which is not the opt-out that disabling the page asks for.
        $this->assertFalse($this->tableHasViewAction());
        $this->assertFalse($this->editPageHasViewAction());
    }

    public function test_saving_an_edit_stays_on_the_form_while_the_page_is_disabled(): void
    {
        config()->set('filament-topbar-menu.enable_view_page', false);

        $item = TopbarMenuItem::create(['label' => 'Docs', 'url' => 'https://docs.example.com']);

        $page = new EditTopbarMenuItem;
        $page->record = $item;

        // Filament's default: null keeps the admin on the edit form.
        $this->assertNull((new ReflectionMethod($page, 'getRedirectUrl'))->invoke($page));
    }

    public function test_the_infolist_shows_the_link_and_the_marker_the_topbar_uses(): void
    {
        $item = TopbarMenuItem::create([
            'label' => 'Docs',
            'url' => 'https://docs.example.com',
            'favicon_url' => 'https://docs.example.com/favicon.ico',
        ]);

        $entries = $this->infolistEntries($item);

        $this->assertArrayHasKey('label', $entries);

        // The link the topbar actually renders — generated for route items,
        // null for a broken route name — and the same favicon-over-icon marker
        // the menu and the items table resolve.
        $this->assertSame($item->resolveUrl(), $entries['resolved_url']->getState());
        $this->assertSame($item->displayIcon(), $entries['display_icon']->getState());
    }

    public function test_the_marker_reaches_the_rendered_page_as_an_image_or_an_svg(): void
    {
        // State alone proves nothing: IconEntry::toEmbeddedHtml() returns the
        // placeholder before it ever calls ->icon(), which is exactly how the
        // items table used to render an empty cell for every favicon-only item
        // (fixed in 1.8.1). Only a real render proves the marker is on the page.
        $favicon = TopbarMenuItem::create([
            'label' => 'Docs',
            'url' => 'https://docs.example.com',
            'favicon_url' => 'https://docs.example.com/favicon.ico',
        ]);

        $this->assertStringContainsString(
            '<img src="https://docs.example.com/favicon.ico"',
            $this->renderedInfolist($favicon),
        );

        $heroicon = TopbarMenuItem::create([
            'label' => 'Home',
            'url' => 'https://home.example.com',
            'icon' => 'heroicon-o-home',
        ]);

        $this->assertStringContainsString('<svg', $this->renderedInfolist($heroicon));

        // A typo in the free-text icon field must not 500 the page: safeIconName
        // resolves an unknown name to null, and the entry falls back to its
        // placeholder.
        $typo = TopbarMenuItem::create([
            'label' => 'Typo',
            'url' => 'https://typo.example.com',
            'icon' => 'heroicon-o-definitely-not-an-icon',
        ]);

        $this->assertStringContainsString('fi-in-icon', $this->renderedInfolist($typo));
    }

    public function test_the_infolist_surfaces_a_role_restriction(): void
    {
        $item = TopbarMenuItem::create([
            'label' => 'Admin tools',
            'url' => 'https://admin.example.com',
            'visibility' => ['roles' => ['admin', 'super-admin']],
        ]);

        $entries = $this->infolistEntries($item);

        $this->assertSame(['admin', 'super-admin'], $entries['visibility_roles']->getState());

        // State alone does not prove the badges render: an array state takes a
        // different branch of TextEntry::toEmbeddedHtml() than a scalar one.
        // Render this entry alone — the whole page also carries the type and
        // target badges, which would satisfy a page-wide fi-badge assertion no
        // matter what this entry does.
        $rolesHtml = $entries['visibility_roles']->toEmbeddedHtml();

        $this->assertStringContainsString('super-admin', $rolesHtml);
        $this->assertStringContainsString('fi-badge', $rolesHtml);

        $html = $this->renderedInfolist($item);

        // The audience entry only knows auth/guest, so it has no state here —
        // and must not fall back to "Everyone", which would contradict the
        // roles row beside it.
        $this->assertNull($entries['visibility_mode']->getState());
        $this->assertStringNotContainsString('Everyone', $html);

        // Nothing to show for an item without the rule: the entry is hidden
        // rather than rendering an empty badge row.
        $plain = TopbarMenuItem::create(['label' => 'Docs', 'url' => 'https://docs.example.com']);

        $this->assertArrayNotHasKey('visibility_roles', $this->infolistEntries($plain));
        $this->assertStringContainsString('Everyone', $this->renderedInfolist($plain));

        // A stored value whose roles are all unusable is the case the two
        // closures used to disagree on: visible, but with nothing in it.
        $junk = TopbarMenuItem::create([
            'label' => 'Junk',
            'url' => 'https://junk.example.com',
            'visibility' => ['roles' => [1, '']],
        ]);

        $this->assertArrayNotHasKey('visibility_roles', $this->infolistEntries($junk));
    }

    public function test_the_infolist_reports_a_route_item_the_way_the_topbar_resolves_it(): void
    {
        $item = TopbarMenuItem::create([
            'label' => 'Broken',
            'type' => TopbarMenuItem::TYPE_ROUTE,
            'route' => 'no.such.route',
            'route_parameters' => ['id' => 5],
        ]);

        $entries = $this->infolistEntries($item);

        // A broken route name resolves to null on the render path too — the
        // page is where an admin sees why the item vanished from the menu.
        $this->assertNull($entries['resolved_url']->getState());

        // getChildComponents() drops hidden entries, so absence is the
        // assertion: a route item shows `route`, never `url`.
        $this->assertArrayHasKey('route', $entries);
        $this->assertArrayNotHasKey('url', $entries);

        // The domain rule is URL-only, so the entry is hidden rather than
        // reporting an invented "no" over whatever the record has stored.
        $this->assertArrayNotHasKey('visibility_hide_on_current_domain', $entries);
    }

    public function test_the_page_switches_itself_off_when_the_route_cache_predates_it(): void
    {
        // The upgrade hazard: routes cached before this version, so the router
        // has no `…resources.topbar-menu-items.view`. Config alone would still
        // claim the page exists, and every URL Filament generates for it — the
        // row action, the breadcrumb, the redirect a create runs AFTER writing
        // the record — would throw RouteNotFoundException.
        //
        // `routes.cached` is the container binding Application::routesAreCached()
        // memoizes into, so this is the same state a cached deploy is in.
        $this->app->instance('routes.cached', true);
        $this->app['router']->setRoutes(new RouteCollection);

        $this->assertFalse(TopbarMenuItemResource::hasViewPage());
        $this->assertArrayNotHasKey('view', TopbarMenuItemResource::getPages());
        $this->assertFalse($this->tableHasViewAction());

        // And the redirect degrades with it, instead of pointing at a route the
        // router cannot resolve.
        $item = TopbarMenuItem::create(['label' => 'Docs', 'url' => 'https://docs.example.com']);

        $page = new EditTopbarMenuItem;
        $page->record = $item;

        $this->assertNull((new ReflectionMethod($page, 'getRedirectUrl'))->invoke($page));
    }

    public function test_the_page_is_registered_during_boot_even_with_routes_cached(): void
    {
        // Laravel requires the cached route file from a `booted` callback, so
        // while providers boot — exactly when Filament reads getPages() to
        // register each page's Livewire component — the router is empty. Asking
        // it then would strip the page off a healthy cached deploy and leave
        // its cached route pointing at an unregistered component.
        $this->app->instance('routes.cached', true);
        $this->app['router']->setRoutes(new RouteCollection);

        $booted = new ReflectionProperty($this->app, 'booted');
        $booted->setValue($this->app, false);

        try {
            $this->assertTrue(TopbarMenuItemResource::hasViewPage());
            $this->assertArrayHasKey('view', TopbarMenuItemResource::getPages());
        } finally {
            $booted->setValue($this->app, true);
        }
    }

    public function test_a_cached_route_collection_that_has_the_page_keeps_it(): void
    {
        // The normal cached deploy: the route is in the cache, so nothing is
        // degraded — the guard above must not punish every cached panel.
        $this->app->instance('routes.cached', true);

        $this->assertTrue(TopbarMenuItemResource::hasViewPage());
        $this->assertArrayHasKey('view', TopbarMenuItemResource::getPages());
    }

    public function test_a_published_config_without_the_key_still_gets_the_page(): void
    {
        $config = config('filament-topbar-menu');

        unset($config['enable_view_page']);

        config()->set('filament-topbar-menu', $config);

        // The upgrade path: a config file published before this key existed.
        // The fallback has to match the packaged default, or updating the
        // package would silently drop the page for every such install.
        $this->assertTrue(TopbarMenuItemResource::hasViewPage());
    }

    /**
     * The resource infolist bound to a record, keyed by entry name.
     *
     * @return array<string, \Filament\Schemas\Components\Component>
     */
    protected function infolistEntries(TopbarMenuItem $record): array
    {
        $entries = [];

        foreach (TopbarMenuItemResource::infolist(Schema::make($this->schemaHost())->record($record))->getComponents() as $section) {
            foreach ($section->getChildComponents() as $entry) {
                $entries[$entry->getName()] = $entry;
            }
        }

        return $entries;
    }

    protected function renderedInfolist(TopbarMenuItem $record): string
    {
        $html = '';

        foreach (TopbarMenuItemResource::infolist(Schema::make($this->schemaHost())->record($record))->getComponents() as $section) {
            foreach ($section->getChildComponents() as $entry) {
                $html .= $entry->toEmbeddedHtml();
            }
        }

        return $html;
    }

    /**
     * A bare Livewire host, the one thing a schema needs outside a real page.
     */
    protected function schemaHost(): Component
    {
        return new class extends Component implements HasActions, HasSchemas
        {
            use InteractsWithActions;
            use InteractsWithSchemas;

            public function render(): string
            {
                return '';
            }
        };
    }

    protected function tableHasViewAction(): bool
    {
        // The resource table outside a panel: a bare host component plus the
        // table property InteractsWithTable would set up during a real boot.
        $livewire = new class extends Component implements HasActions, HasSchemas, HasTable
        {
            use InteractsWithActions;
            use InteractsWithSchemas;
            use InteractsWithTable;

            public function render(): string
            {
                return '';
            }
        };

        $table = TopbarMenuItemResource::table(Table::make($livewire));

        (new ReflectionProperty($livewire, 'table'))->setValue($livewire, $table);

        return (bool) array_filter(
            $table->getRecordActions(),
            fn ($action): bool => $action instanceof ViewAction,
        );
    }

    protected function editPageHasViewAction(): bool
    {
        $actions = (new ReflectionMethod(EditTopbarMenuItem::class, 'getHeaderActions'))
            ->invoke(new EditTopbarMenuItem);

        return (bool) array_filter(
            $actions,
            fn ($action): bool => $action instanceof ViewAction,
        );
    }
}
