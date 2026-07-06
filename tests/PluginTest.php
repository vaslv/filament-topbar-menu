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
use ReflectionProperty;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;
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
            'target' => TopbarMenuItem::TARGET_SELF,
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

        // The per-item target is honored: the "New tab" parent gets _blank
        // (with rel), and the "Same tab" child is NOT promoted to a new tab.
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('target="_self"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);

        // The parent renders a dropdown with its child inside.
        $this->assertStringContainsString('ftm-dropdown', $html);
        $this->assertStringContainsString('href="https://analytics.example.com"', $html);

        // The favicon is rendered from the stored URL — no HTTP request needed.
        $this->assertStringContainsString('src="https://services.example.com/favicon.ico"', $html);

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
}
