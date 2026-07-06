<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Filament\Panel;
use Filament\View\PanelsRenderHook;
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
            \Filament\Actions\ActionsServiceProvider::class,
            \Filament\Forms\FormsServiceProvider::class,
            \Filament\Infolists\InfolistsServiceProvider::class,
            \Filament\Notifications\NotificationsServiceProvider::class,
            \Filament\Schemas\SchemasServiceProvider::class,
            \Filament\Tables\TablesServiceProvider::class,
            \Filament\Widgets\WidgetsServiceProvider::class,
            \Filament\FilamentServiceProvider::class,
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
        $schema = TopbarMenuItemResource::form(\Filament\Schemas\Schema::make());

        $this->assertNotEmpty($schema->getComponents());

        $livewire = new class extends \Livewire\Component implements \Filament\Actions\Contracts\HasActions, \Filament\Schemas\Contracts\HasSchemas, \Filament\Tables\Contracts\HasTable
        {
            use \Filament\Actions\Concerns\InteractsWithActions;
            use \Filament\Schemas\Concerns\InteractsWithSchemas;
            use \Filament\Tables\Concerns\InteractsWithTable;

            public function render(): string
            {
                return '';
            }
        };

        $table = TopbarMenuItemResource::table(\Filament\Tables\Table::make($livewire));

        $this->assertNotEmpty($table->getColumns());
        $this->assertSame('sort', $table->getReorderColumn());
    }

    public function test_drag_and_drop_reordering_flushes_the_menu_cache(): void
    {
        TopbarMenuItem::create(['label' => 'A', 'type' => 'url', 'url' => '/a', 'sort' => 0]);
        TopbarMenuItem::create(['label' => 'B', 'type' => 'url', 'url' => '/b', 'sort' => 1]);

        $livewire = new class extends \Livewire\Component implements \Filament\Actions\Contracts\HasActions, \Filament\Schemas\Contracts\HasSchemas, \Filament\Tables\Contracts\HasTable
        {
            use \Filament\Actions\Concerns\InteractsWithActions;
            use \Filament\Schemas\Concerns\InteractsWithSchemas;
            use \Filament\Tables\Concerns\InteractsWithTable;

            public function render(): string
            {
                return '';
            }
        };

        $table = TopbarMenuItemResource::table(\Filament\Tables\Table::make($livewire));

        // Prime the cache, then simulate Filament's post-reorder callback.
        app(TopbarMenu::class)->items();
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has(app(TopbarMenu::class)->cacheKey()));

        $table->callAfterReordering(['2', '1']);

        $this->assertFalse(\Illuminate\Support\Facades\Cache::has(app(TopbarMenu::class)->cacheKey()));
    }

    public function test_the_menu_view_renders_items_dropdowns_and_favicons(): void
    {
        $parent = TopbarMenuItem::create([
            'label' => 'Services',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://services.example.com',
            'favicon_url' => 'https://services.example.com/favicon.ico',
        ]);

        TopbarMenuItem::create([
            'label' => 'Analytics',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://analytics.example.com',
            'parent_id' => $parent->id,
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

        // External links open in a new tab by default.
        $this->assertStringContainsString('target="_blank"', $html);
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
