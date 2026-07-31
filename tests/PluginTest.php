<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Filament\Actions\ActionsServiceProvider;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\TablesServiceProvider;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use ReflectionMethod;
use ReflectionProperty;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource\Pages;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\TopbarMenu;
use Vaslv\FilamentTopbarMenu\TopbarMenuPlugin;

class PluginTest extends TestCase
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
        ]);
    }

    /**
     * The resource's table, standing on its own outside a panel: a bare host
     * component plus the table property InteractsWithTable would normally
     * initialize during a real Livewire boot. Column state resolution reaches
     * back into that property, so a hand-built table has to wire it up.
     */
    protected function menuItemsTable(): Table
    {
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

        return $table;
    }

    public function test_the_plugin_registers_the_resource_and_the_topbar_render_hook(): void
    {
        $panel = Panel::make()->id('test');

        TopbarMenuPlugin::make()->register($panel);

        $this->assertContains(TopbarMenuItemResource::class, $panel->getResources());

        $renderHooks = new ReflectionProperty($panel, 'renderHooks');

        $this->assertArrayHasKey(PanelsRenderHook::TOPBAR_LOGO_AFTER, $renderHooks->getValue($panel));
    }

    public function test_the_resource_can_be_disabled(): void
    {
        $panel = Panel::make()->id('test');

        TopbarMenuPlugin::make()->resource(false)->register($panel);

        $this->assertNotContains(TopbarMenuItemResource::class, $panel->getResources());
    }

    public function test_the_render_hook_can_be_customized(): void
    {
        $panel = Panel::make()->id('test');

        TopbarMenuPlugin::make()
            ->renderHook(PanelsRenderHook::TOPBAR_START)
            ->register($panel);

        $renderHooks = new ReflectionProperty($panel, 'renderHooks');

        $this->assertArrayHasKey(PanelsRenderHook::TOPBAR_START, $renderHooks->getValue($panel));
        $this->assertArrayNotHasKey(PanelsRenderHook::TOPBAR_LOGO_AFTER, $renderHooks->getValue($panel));
    }

    public function test_the_resource_form_and_table_definitions_build(): void
    {
        $schema = TopbarMenuItemResource::form(Schema::make());

        $this->assertNotEmpty($schema->getComponents());

        $table = $this->menuItemsTable();

        $this->assertNotEmpty($table->getColumns());
        $this->assertSame('sort', $table->getReorderColumn());
    }

    public function test_the_list_page_defines_export_import_and_create_header_actions(): void
    {
        $this->app->setLocale('en');

        $page = new Pages\ListTopbarMenuItems;

        $actions = (new ReflectionMethod($page, 'getHeaderActions'))->invoke($page);

        $this->assertSame(
            ['setupTable', 'export', 'import', 'create'],
            array_map(fn ($action) => $action->getName(), $actions),
        );

        [, $export, $import] = $actions;

        $this->assertSame('Export', $export->getLabel());
        $this->assertSame('Import', $import->getLabel());
    }

    public function test_drag_and_drop_reordering_flushes_the_menu_cache(): void
    {
        TopbarMenuItem::create(['label' => 'A', 'type' => 'url', 'url' => '/a', 'sort' => 0]);
        TopbarMenuItem::create(['label' => 'B', 'type' => 'url', 'url' => '/b', 'sort' => 1]);

        $table = $this->menuItemsTable();

        // Prime the cache, then simulate Filament's post-reorder callback.
        app(TopbarMenu::class)->items();
        $this->assertTrue(Cache::has(app(TopbarMenu::class)->cacheKey()));

        $table->callAfterReordering(['2', '1']);

        $this->assertFalse(Cache::has(app(TopbarMenu::class)->cacheKey()));
    }

    public function test_the_table_icon_column_prefers_the_favicon_and_validates_the_icon_name(): void
    {
        $column = $this->menuItemsTable()->getColumn('display_icon');

        $this->assertNotNull($column);

        // Assert on the rendered HTML, not on getIcon(): IconColumn returns the
        // empty-cell placeholder for a blank column state before it ever calls
        // the ->icon() closure, so only a real render proves that a favicon
        // actually reaches the page.
        $render = fn (TopbarMenuItem $record): string => $column->record($record)->toHtml();

        // The regression this guards: an external link typically carries a
        // favicon and no Heroicon name at all. It must still show its favicon.
        $faviconOnly = TopbarMenuItem::create([
            'label' => 'Favicon only',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
            'favicon_url' => 'https://example.com/favicon.ico',
        ]);

        $this->assertStringContainsString('<img src="https://example.com/favicon.ico"', $render($faviconOnly));

        // Mirrors the topbar: the favicon takes precedence over the Heroicon
        // and is rendered by the column as an <img> (Filament renders any
        // slash-containing icon string as an image source).
        $withFavicon = TopbarMenuItem::create([
            'label' => 'Favicon wins',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
            'icon' => 'heroicon-o-home',
            'favicon_url' => 'https://example.com/other-favicon.ico',
        ]);

        $html = $render($withFavicon);

        $this->assertStringContainsString('<img src="https://example.com/other-favicon.ico"', $html);
        $this->assertStringNotContainsString('<svg', $html);

        // Without a favicon, a known icon name renders as the Heroicon's SVG.
        $withIcon = TopbarMenuItem::create([
            'label' => 'Icon',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
            'icon' => 'heroicon-o-home',
        ]);

        $this->assertStringContainsString('<svg', $render($withIcon));

        // An unknown name must render nothing instead of throwing SvgNotFound.
        $withBadIcon = TopbarMenuItem::create([
            'label' => 'Typo',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
            'icon' => 'not-a-real-icon-xyz',
        ]);

        $this->assertStringNotContainsString('<svg', $render($withBadIcon));

        // A URL smuggled into the icon field must not become an <img src>.
        $withUrlIcon = TopbarMenuItem::create([
            'label' => 'Smuggled image',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
            'icon' => 'https://attacker.example/pixel.svg',
        ]);

        $this->assertStringNotContainsString('<img', $render($withUrlIcon));
    }

    public function test_the_table_badge_marks_items_hidden_on_the_current_domain(): void
    {
        $this->bindRequest('https://a.example.com/admin');

        $column = $this->menuItemsTable()->getColumn('hidden_on_current_domain');

        $this->assertNotNull($column);

        $ownLink = TopbarMenuItem::create([
            'label' => 'This service',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://a.example.com/home',
            'visibility' => ['hide_on_current_domain' => true],
        ]);

        $foreignLink = TopbarMenuItem::create([
            'label' => 'Sibling service',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://b.example.com',
            'visibility' => ['hide_on_current_domain' => true],
        ]);

        $unflagged = TopbarMenuItem::create([
            'label' => 'Plain',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://a.example.com/other',
        ]);

        // The badge shows exactly for items the flag hides on THIS host — the
        // same isHiddenOnCurrentDomain() the render path uses.
        $this->assertSame('heroicon-o-eye-slash', $column->record($ownLink)->getIcon(true));
        $this->assertTrue($column->record($ownLink)->getState());
        $this->assertFalse($column->record($foreignLink)->getState());
        $this->assertFalse($column->record($unflagged)->getState());

        // No icon at all for a non-hidden row (an empty cell, not a crossed eye).
        $this->assertNull($column->record($foreignLink)->getIcon(false));
    }

    public function test_the_menu_view_renders_items_dropdowns_and_favicons(): void
    {
        $parent = TopbarMenuItem::create([
            'label' => 'Services',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://services.example.com',
            'favicon_url' => 'https://services.example.com/favicon.ico',
            'target' => TopbarMenuItem::TARGET_BLANK,
        ]);

        TopbarMenuItem::create([
            'label' => 'Analytics',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://analytics.example.com',
            'parent_id' => $parent->id,
            'target' => TopbarMenuItem::TARGET_BLANK,
        ]);

        TopbarMenuItem::create([
            'label' => 'Hidden',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://hidden.example.com',
            'is_active' => false,
        ]);

        TopbarMenuItem::create([
            'label' => 'With icon',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://icons.example.com',
            'icon' => 'heroicon-o-link',
        ]);

        $html = view('filament-topbar-menu::menu', [
            'items' => app(TopbarMenu::class)->visibleItems(),
            'user' => null,
        ])->render();

        $this->assertStringContainsString('Services', $html);
        $this->assertStringContainsString('Analytics', $html);
        $this->assertStringNotContainsString('Hidden', $html);

        // Rendered with Filament's own topbar/dropdown markup for pixel-parity
        // with the native top navigation.
        $this->assertStringContainsString('fi-topbar-nav-groups', $html);
        $this->assertStringContainsString('fi-topbar-item', $html);
        $this->assertStringContainsString('fi-dropdown', $html);
        $this->assertStringContainsString('fi-dropdown-list-item', $html);

        // The parent with children renders a dropdown containing its child link.
        $this->assertStringContainsString('href="https://analytics.example.com"', $html);

        // The parent's own URL is NOT duplicated as a row inside its dropdown —
        // the group is a pure toggle, matching Filament's native top navigation.
        // (Its favicon URL still appears on the trigger, hence the distinct path.)
        $this->assertStringNotContainsString('href="https://services.example.com"', $html);

        // The per-item target is honored: the "New tab" child link gets _blank.
        $this->assertStringContainsString('target="_blank"', $html);

        // The favicon is rendered from the stored URL — no HTTP request needed.
        $this->assertStringContainsString('https://services.example.com/favicon.ico', $html);

        // Heroicons render as inline SVG through the Filament icon component.
        $this->assertStringContainsString('<svg', $html);
    }

    public function test_a_favicon_that_is_not_a_url_does_not_reach_the_icon_renderer(): void
    {
        $parent = TopbarMenuItem::create([
            'label' => 'Services',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://services.example.com',
        ]);

        // Not reachable through the form, the import or the resolver — but a
        // fleet-shared menu table also takes rows from sibling apps, seeders
        // and hand-written SQL, and this value would otherwise be handed to
        // Filament as an icon NAME: SvgNotFound, on every panel page.
        $child = TopbarMenuItem::create([
            'label' => 'Broken',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://broken.example.com',
            'icon' => 'heroicon-o-link',
            'parent_id' => $parent->id,
        ]);

        $child->forceFill(['favicon_url' => 'not-a-url'])->save();

        $html = view('filament-topbar-menu::menu', [
            'items' => app(TopbarMenu::class)->visibleItems(),
            'user' => null,
        ])->render();

        $this->assertStringContainsString('Broken', $html);
        $this->assertStringNotContainsString('not-a-url', $html);

        // The stored Heroicon name still renders — the guard skips the unusable
        // favicon rather than blanking the item's marker.
        $this->assertStringContainsString('<svg', $html);
    }

    public function test_a_child_favicon_renders_as_an_icon_image_not_as_a_cropped_circle(): void
    {
        $parent = TopbarMenuItem::create([
            'label' => 'Services',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://services.example.com',
        ]);

        TopbarMenuItem::create([
            'label' => 'Horizon',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://services.example.com/horizon',
            'favicon_url' => 'https://services.example.com/horizon/img/favicon.png',
            'parent_id' => $parent->id,
        ]);

        $html = view('filament-topbar-menu::menu', [
            'items' => app(TopbarMenu::class)->visibleItems(),
            'user' => null,
        ])->render();

        // Filament's `image` slot is an avatar-style circle (border-radius +
        // background-size: cover), which clips a square favicon. The favicon
        // belongs in the icon slot, where it renders as a plain, fully visible
        // <img> at icon size — exactly like the top-level items.
        $this->assertStringContainsString('<img src="https://services.example.com/horizon/img/favicon.png"', $html);
        $this->assertStringNotContainsString('fi-dropdown-list-item-image', $html);
        $this->assertStringNotContainsString('background-image', $html);
    }

    public function test_groups_whose_children_are_hidden_by_the_flag_collapse_per_host(): void
    {
        // A group WITHOUT its own URL disappears entirely once the flag hides
        // all its children; a group WITH its own URL degrades to a flat link —
        // both are the view's existing empty-children behavior, now with a
        // host-dependent trigger.
        $bare = TopbarMenuItem::create([
            'label' => 'Bare group',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => null,
        ]);
        TopbarMenuItem::create([
            'label' => 'Bare child',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://a.example.com/tools',
            'parent_id' => $bare->id,
            'visibility' => ['hide_on_current_domain' => true],
        ]);

        $linked = TopbarMenuItem::create([
            'label' => 'Linked group',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://portal.example.com',
        ]);
        TopbarMenuItem::create([
            'label' => 'Linked child',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://a.example.com/reports',
            'parent_id' => $linked->id,
            'visibility' => ['hide_on_current_domain' => true],
        ]);

        // On the children's own host: no dropdowns at all — the bare group is
        // gone, the linked group renders as a plain link to its own URL.
        $this->bindRequest('https://a.example.com/admin');

        $html = view('filament-topbar-menu::menu', [
            'items' => app(TopbarMenu::class)->visibleItems(),
            'user' => null,
        ])->render();

        $this->assertStringNotContainsString('Bare group', $html);
        $this->assertStringNotContainsString('Bare child', $html);
        $this->assertStringNotContainsString('Linked child', $html);
        $this->assertStringContainsString('Linked group', $html);
        $this->assertStringContainsString('href="https://portal.example.com"', $html);
        $this->assertStringNotContainsString('fi-dropdown-list-item', $html);

        // On a sibling host the same cached tree renders both dropdowns.
        $this->bindRequest('https://b.example.com/admin');

        $html = view('filament-topbar-menu::menu', [
            'items' => app(TopbarMenu::class)->visibleItems(),
            'user' => null,
        ])->render();

        $this->assertStringContainsString('Bare group', $html);
        $this->assertStringContainsString('href="https://a.example.com/tools"', $html);
        $this->assertStringContainsString('Linked group', $html);
        $this->assertStringContainsString('href="https://a.example.com/reports"', $html);
        $this->assertStringContainsString('fi-dropdown-list-item', $html);
    }

    public function test_the_menu_view_renders_nothing_when_there_are_no_items(): void
    {
        $html = view('filament-topbar-menu::menu', [
            'items' => app(TopbarMenu::class)->visibleItems(),
            'user' => null,
        ])->render();

        $this->assertSame('', trim($html));
    }

    public function test_an_unknown_icon_name_does_not_break_menu_rendering(): void
    {
        TopbarMenuItem::create([
            'label' => 'Typo icon',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
            'icon' => 'heroicon-o-this-icon-does-not-exist',
        ]);

        // Must not throw BladeUI\Icons\Exceptions\SvgNotFound — the menu
        // renders on every panel page, so one bad icon must not 500 the panel.
        $html = view('filament-topbar-menu::menu', [
            'items' => app(TopbarMenu::class)->visibleItems(),
            'user' => null,
        ])->render();

        // The link still renders, just without an icon.
        $this->assertStringContainsString('Typo icon', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
    }

    public function test_the_edit_form_fills_for_an_item_with_a_visibility_array(): void
    {
        $item = TopbarMenuItem::create([
            'label' => 'Admin Tools',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => '/tools',
            'visibility' => ['auth' => true, 'roles' => ['admin']],
        ]);

        $livewire = new class extends Component implements HasActions, HasSchemas
        {
            use InteractsWithActions;
            use InteractsWithSchemas;

            /** @var array<string, mixed> */
            public ?array $data = [];

            public function render(): string
            {
                return '';
            }
        };

        $schema = TopbarMenuItemResource::form(
            Schema::make($livewire)->statePath('data')->model($item),
        );

        $page = new Pages\EditTopbarMenuItem;
        $page->record = $item;

        $fillMutator = new ReflectionMethod($page, 'mutateFormDataBeforeFill');

        // Regression: the Select used to bind straight to the `visibility`
        // array, and Filament's OptionStateCast strval()'d it during fill —
        // "Array to string conversion" on every edit page of such an item.
        $schema->fill($fillMutator->invoke($page, $item->attributesToArray()));

        $rawState = $schema->getRawState();

        $this->assertIsArray($rawState);
        $this->assertSame('auth', $rawState['visibility_mode'] ?? null);
    }

    public function test_the_page_mutators_round_trip_visibility_and_preserve_roles(): void
    {
        $item = TopbarMenuItem::create([
            'label' => 'Admin Tools',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => '/tools',
            'visibility' => ['auth' => true, 'roles' => ['admin']],
        ]);

        $page = new Pages\EditTopbarMenuItem;
        $page->record = $item;

        $saveMutator = new ReflectionMethod($page, 'mutateFormDataBeforeSave');

        /** @var array<string, mixed> $data */
        $data = $saveMutator->invoke($page, ['label' => 'Admin Tools', 'visibility_mode' => 'guest']);

        // The mode is remapped and the roles restriction survives the edit.
        $this->assertSame(['roles' => ['admin'], 'guest' => true], $data['visibility']);
        $this->assertArrayNotHasKey('visibility_mode', $data);

        $createMutator = new ReflectionMethod(new Pages\CreateTopbarMenuItem, 'mutateFormDataBeforeCreate');

        /** @var array<string, mixed> $created */
        $created = $createMutator->invoke(new Pages\CreateTopbarMenuItem, ['label' => 'New', 'visibility_mode' => 'auth']);

        $this->assertSame(['auth' => true], $created['visibility']);
        $this->assertArrayNotHasKey('visibility_mode', $created);

        /** @var array<string, mixed> $public */
        $public = $createMutator->invoke(new Pages\CreateTopbarMenuItem, ['label' => 'New', 'visibility_mode' => null]);

        $this->assertNull($public['visibility']);
    }

    public function test_creating_a_url_item_with_the_hide_on_current_domain_toggle_stores_the_key(): void
    {
        $createMutator = new ReflectionMethod(new Pages\CreateTopbarMenuItem, 'mutateFormDataBeforeCreate');

        /** @var array<string, mixed> $created */
        $created = $createMutator->invoke(new Pages\CreateTopbarMenuItem, [
            'label' => 'Sibling service',
            'type' => TopbarMenuItem::TYPE_URL,
            'visibility_mode' => null,
            'visibility_hide_on_current_domain' => true,
        ]);

        $this->assertSame(['hide_on_current_domain' => true], $created['visibility']);
        $this->assertArrayNotHasKey('visibility_hide_on_current_domain', $created);

        // A route item never stores the key, whatever the (hidden) toggle held.
        /** @var array<string, mixed> $routeItem */
        $routeItem = $createMutator->invoke(new Pages\CreateTopbarMenuItem, [
            'label' => 'Dashboard',
            'type' => TopbarMenuItem::TYPE_ROUTE,
            'visibility_mode' => null,
            'visibility_hide_on_current_domain' => true,
        ]);

        $this->assertNull($routeItem['visibility']);
    }

    public function test_switching_a_url_item_to_route_keeps_the_stored_hide_on_current_domain_key(): void
    {
        $item = TopbarMenuItem::create([
            'label' => 'Analytics',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://a.example.com',
            'visibility' => ['hide_on_current_domain' => true],
        ]);

        $page = new Pages\EditTopbarMenuItem;
        $page->record = $item;

        $saveMutator = new ReflectionMethod($page, 'mutateFormDataBeforeSave');

        // The toggle is hidden for route items — its (absent) state must keep
        // the stored key, not erase it.
        /** @var array<string, mixed> $data */
        $data = $saveMutator->invoke($page, [
            'label' => 'Analytics',
            'type' => TopbarMenuItem::TYPE_ROUTE,
            'visibility_mode' => null,
        ]);

        $this->assertSame(['hide_on_current_domain' => true], $data['visibility']);
    }

    public function test_switching_a_route_item_to_url_fills_the_toggle_with_the_stored_value_and_saves_what_the_admin_saw(): void
    {
        // A route item can carry the key via seeder/import; the fill must show
        // the real stored value once the type is switched to URL in the form.
        $item = TopbarMenuItem::create([
            'label' => 'Dashboard',
            'type' => TopbarMenuItem::TYPE_ROUTE,
            'route' => 'dashboard',
            'visibility' => ['hide_on_current_domain' => true],
        ]);

        $page = new Pages\EditTopbarMenuItem;
        $page->record = $item;

        $fillMutator = new ReflectionMethod($page, 'mutateFormDataBeforeFill');

        /** @var array<string, mixed> $filled */
        $filled = $fillMutator->invoke($page, $item->attributesToArray());

        $this->assertTrue($filled['visibility_hide_on_current_domain']);

        // Saving as a URL item honestly applies what the admin saw — here they
        // switched the visible toggle off.
        $saveMutator = new ReflectionMethod($page, 'mutateFormDataBeforeSave');

        /** @var array<string, mixed> $data */
        $data = $saveMutator->invoke($page, [
            'label' => 'Dashboard',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://a.example.com',
            'visibility_mode' => null,
            'visibility_hide_on_current_domain' => false,
        ]);

        $this->assertNull($data['visibility']);
    }

    public function test_a_combined_submit_of_visibility_mode_and_the_domain_toggle_keeps_both_keys(): void
    {
        $item = TopbarMenuItem::create([
            'label' => 'Analytics',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://a.example.com',
            'visibility' => ['roles' => ['admin']],
        ]);

        $page = new Pages\EditTopbarMenuItem;
        $page->record = $item;

        $saveMutator = new ReflectionMethod($page, 'mutateFormDataBeforeSave');

        // Both virtual fields change in one submit: the helpers are chained,
        // so neither result clobbers the other — and roles survive too.
        /** @var array<string, mixed> $data */
        $data = $saveMutator->invoke($page, [
            'label' => 'Analytics',
            'type' => TopbarMenuItem::TYPE_URL,
            'visibility_mode' => 'auth',
            'visibility_hide_on_current_domain' => true,
        ]);

        $this->assertSame(
            ['roles' => ['admin'], 'auth' => true, 'hide_on_current_domain' => true],
            $data['visibility'],
        );
    }

    public function test_the_forms_url_rule_rejects_the_defensive_branch_values(): void
    {
        // Premise fixed in code: pointsAtCurrentHost()'s no-host branches
        // (relative URLs, mailto:/tel:) are defensive-only because the form's
        // ->url() field runs Laravel's `url` rule, which requires `://` — such
        // values never reach the column through the form. (The import
        // additionally allows http(s) with a host only; see MenuTransferTest.)
        //
        // First pin that the actual form field carries the rule — otherwise
        // the assertions below would keep passing even if ->url() were
        // dropped from the resource.
        $livewire = new class extends Component implements HasActions, HasSchemas
        {
            use InteractsWithActions;
            use InteractsWithSchemas;

            /** @var array<string, mixed> */
            public ?array $data = [];

            public function render(): string
            {
                return '';
            }
        };

        $schema = TopbarMenuItemResource::form(
            Schema::make($livewire)->statePath('data')->model(TopbarMenuItem::class),
        );

        $urlField = collect($schema->getFlatComponents(withHidden: true))
            ->first(fn ($component): bool => method_exists($component, 'getName')
                && $component->getName() === 'url');

        $this->assertNotNull($urlField, 'The url field is missing from the form.');
        $this->assertContains('url', $urlField->getValidationRules());

        foreach (['/dashboard', 'mailto:team@example.com', 'tel:+123456789'] as $value) {
            $this->assertTrue(
                Validator::make(['url' => $value], ['url' => 'url'])->fails(),
                "Expected [{$value}] to fail the url rule",
            );
        }

        $this->assertFalse(
            Validator::make(['url' => 'https://a.example.com'], ['url' => 'url'])->fails(),
        );
    }

    public function test_the_hide_on_current_domain_toggle_is_only_offered_for_url_items(): void
    {
        $livewire = new class extends Component implements HasActions, HasSchemas
        {
            use InteractsWithActions;
            use InteractsWithSchemas;

            /** @var array<string, mixed> */
            public ?array $data = [];

            public function render(): string
            {
                return '';
            }
        };

        $schema = TopbarMenuItemResource::form(
            Schema::make($livewire)->statePath('data')->model(TopbarMenuItem::class),
        );

        $toggle = collect($schema->getFlatComponents(withHidden: true))
            ->first(fn ($component): bool => method_exists($component, 'getName')
                && $component->getName() === 'visibility_hide_on_current_domain');

        $this->assertNotNull($toggle, 'The hide-on-current-domain toggle is missing from the form.');

        $schema->fill(['type' => TopbarMenuItem::TYPE_URL]);
        $this->assertTrue($toggle->isVisible());

        $schema->fill(['type' => TopbarMenuItem::TYPE_ROUTE]);
        $this->assertFalse($toggle->isVisible());
    }
}
