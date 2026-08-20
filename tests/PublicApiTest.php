<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Vaslv\FilamentTopbarMenu\Facades\TopbarMenu as TopbarMenuFacade;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\TopbarMenu;
use Vaslv\FilamentTopbarMenu\TopbarMenuPlugin;

/**
 * The surfaces the README tells a consumer to use.
 *
 * Everything here is documented public API, and none of it had a test: the
 * facade the README hands out for rendering the menu in a custom view, and the
 * two plugin options that place the resource in a panel's navigation. A break
 * in any of them is silent — the facade would fatal in the consumer's app, and
 * the navigation options would quietly fall back to Filament's defaults.
 */
class PublicApiTest extends TestCase
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

    public function test_the_facade_reaches_the_same_service_the_container_does(): void
    {
        $parent = TopbarMenuItem::create(['label' => 'Group', 'type' => 'url', 'url' => 'https://group.test']);
        TopbarMenuItem::create(['label' => 'Child', 'type' => 'url', 'url' => 'https://child.test', 'parent_id' => $parent->id]);

        $this->assertSame(app(TopbarMenu::class)->cacheKey(), TopbarMenuFacade::cacheKey());
        $this->assertCount(1, TopbarMenuFacade::items());
        $this->assertCount(1, TopbarMenuFacade::visibleItems());
        $this->assertSame('Child', TopbarMenuFacade::items()->first()?->activeChildren->first()?->label);

        TopbarMenuFacade::flushCache();

        $this->assertCount(1, TopbarMenuFacade::items());
    }

    public function test_the_facade_documents_the_methods_it_actually_forwards(): void
    {
        // The docblock on the facade is what a consumer's IDE reads. A method
        // listed there that the service does not have is a broken hint; one the
        // service has and the docblock omits is an undiscoverable feature.
        $documented = [];

        preg_match_all(
            '/@method static [^\s]+(?:<[^>]*>)? (\w+)\(/',
            (string) file_get_contents(__DIR__.'/../src/Facades/TopbarMenu.php'),
            $matches,
        );

        foreach ($matches[1] as $name) {
            $documented[] = $name;
        }

        $this->assertNotEmpty($documented);

        foreach ($documented as $method) {
            $this->assertTrue(
                method_exists(TopbarMenu::class, $method),
                "The facade documents [{$method}], which TopbarMenu does not have.",
            );
        }
    }

    public function test_the_plugin_places_the_resource_in_the_navigation_it_was_given(): void
    {
        $panel = Panel::make()->id('nav')->plugin(
            TopbarMenuPlugin::make()
                ->resourceNavigationGroup('Settings')
                ->resourceNavigationSort(10),
        );

        Filament::setCurrentPanel($panel);

        $this->assertSame('Settings', TopbarMenuItemResource::getNavigationGroup());
        $this->assertSame(10, TopbarMenuItemResource::getNavigationSort());
    }

    public function test_the_resource_falls_back_to_filaments_defaults_when_nothing_was_configured(): void
    {
        $panel = Panel::make()->id('plain')->plugin(TopbarMenuPlugin::make());

        Filament::setCurrentPanel($panel);

        // Not a hardcoded null: the point is that the package defers rather than
        // imposing a group of its own on a panel that never asked for one.
        $this->assertNull(TopbarMenuItemResource::getNavigationGroup());
        $this->assertNull(TopbarMenuItemResource::getNavigationSort());
    }

    public function test_the_resource_survives_a_panel_without_the_plugin(): void
    {
        $panel = Panel::make()->id('other');

        Filament::setCurrentPanel($panel);

        // A second panel that renders the resource without registering the
        // plugin must not fatal on the plugin lookup.
        $this->assertNull(TopbarMenuItemResource::getNavigationGroup());
    }
}
