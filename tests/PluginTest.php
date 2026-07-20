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
use Illuminate\Support\Facades\Cache;
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

        $this->assertNotEmpty($table->getColumns());
        $this->assertSame('sort', $table->getReorderColumn());
    }

    public function test_the_list_page_defines_export_import_and_create_header_actions(): void
    {
        $this->app->setLocale('en');

        $page = new Pages\ListTopbarMenuItems;

        $actions = (new ReflectionMethod($page, 'getHeaderActions'))->invoke($page);

        $this->assertSame(
            ['export', 'import', 'create'],
            array_map(fn ($action) => $action->getName(), $actions),
        );

        [$export, $import] = $actions;

        $this->assertSame('Export', $export->getLabel());
        $this->assertSame('Import', $import->getLabel());
    }

    public function test_drag_and_drop_reordering_flushes_the_menu_cache(): void
    {
        TopbarMenuItem::create(['label' => 'A', 'type' => 'url', 'url' => '/a', 'sort' => 0]);
        TopbarMenuItem::create(['label' => 'B', 'type' => 'url', 'url' => '/b', 'sort' => 1]);

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

        // Prime the cache, then simulate Filament's post-reorder callback.
        app(TopbarMenu::class)->items();
        $this->assertTrue(Cache::has(app(TopbarMenu::class)->cacheKey()));

        $table->callAfterReordering(['2', '1']);

        $this->assertFalse(Cache::has(app(TopbarMenu::class)->cacheKey()));
    }

    public function test_the_table_icon_column_prefers_the_favicon_and_validates_the_icon_name(): void
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
        $column = $table->getColumn('icon');

        // Mirrors the topbar: the favicon takes precedence over the Heroicon
        // and is rendered by the column as an <img> (Filament renders any
        // slash-containing icon string as an image source).
        $withFavicon = TopbarMenuItem::create([
            'label' => 'Favicon wins',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
            'icon' => 'heroicon-o-home',
            'favicon_url' => 'https://example.com/favicon.ico',
        ]);

        $this->assertSame(
            'https://example.com/favicon.ico',
            $column->record($withFavicon)->getIcon($withFavicon->icon),
        );

        // Without a favicon, a known icon name renders as-is.
        $withIcon = TopbarMenuItem::create([
            'label' => 'Icon',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
            'icon' => 'heroicon-o-home',
        ]);

        $this->assertSame('heroicon-o-home', $column->record($withIcon)->getIcon($withIcon->icon));

        // An unknown name must resolve to null instead of throwing SvgNotFound.
        $withBadIcon = TopbarMenuItem::create([
            'label' => 'Typo',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
            'icon' => 'not-a-real-icon-xyz',
        ]);

        $this->assertNull($column->record($withBadIcon)->getIcon($withBadIcon->icon));

        // A URL smuggled into the icon field must not become an <img src>.
        $withUrlIcon = TopbarMenuItem::create([
            'label' => 'Smuggled image',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
            'icon' => 'https://attacker.example/pixel.svg',
        ]);

        $this->assertNull($column->record($withUrlIcon)->getIcon($withUrlIcon->icon));
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
}
