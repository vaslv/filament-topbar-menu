<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\Tests\Fixtures\ExplodingCacheStore;
use Vaslv\FilamentTopbarMenu\TopbarMenu;

/**
 * The topbar is rendered on every panel page, so nothing the menu depends on —
 * the table, the connection it lives on, the cache store holding its snapshot —
 * may turn a fault of its own into a 500 on every page of the panel. Each case
 * below degrades to an empty menu instead.
 */
class RenderResilienceTest extends TestCase
{
    public function test_the_menu_renders_empty_when_the_table_is_missing(): void
    {
        Schema::drop('filament_topbar_menu_items');

        $this->assertCount(0, app(TopbarMenu::class)->items());
        $this->assertCount(0, app(TopbarMenu::class)->visibleItems());
    }

    public function test_the_menu_recovers_on_the_first_request_after_the_table_appears(): void
    {
        Schema::drop('filament_topbar_menu_items');

        // The failed render must not cache an empty snapshot — otherwise the
        // menu would stay blank for a full cache TTL after the table appears.
        $this->assertCount(0, app(TopbarMenu::class)->items());

        $this->artisan('filament-topbar-menu:create-table')->assertSuccessful();
        TopbarMenuItem::create(['label' => 'Home', 'url' => 'https://example.com']);

        $this->assertCount(1, app(TopbarMenu::class)->items());
    }

    public function test_the_menu_renders_empty_when_the_connection_is_not_configured(): void
    {
        // Half a connection switch: FILAMENT_TOPBAR_MENU_DB_CONNECTION is set
        // (or misspelled) before config/database.php defines the connection.
        // DatabaseManager throws an InvalidArgumentException, not a
        // QueryException, so this is the same deploy window as the missing
        // table one step earlier — and it must degrade the same way.
        config(['filament-topbar-menu.connection' => 'does-not-exist']);

        $this->assertCount(0, app(TopbarMenu::class)->items());
        $this->assertCount(0, app(TopbarMenu::class)->visibleItems());
    }

    public function test_the_menu_renders_empty_when_the_connection_is_not_configured_on_a_cache_hit(): void
    {
        // hydrate() resolves the model's connection to build its query grammar,
        // so a cache HIT reaches the broken connection too. Warm the snapshot
        // first, then break the connection: without hydrate() inside the guard
        // this is the path that still throws.
        TopbarMenuItem::create(['label' => 'Home', 'url' => 'https://example.com']);
        $this->assertCount(1, app(TopbarMenu::class)->items());

        config(['filament-topbar-menu.connection' => 'does-not-exist']);

        $this->assertCount(0, app(TopbarMenu::class)->items());
    }

    public function test_the_menu_renders_empty_when_the_cache_store_is_down(): void
    {
        TopbarMenuItem::create(['label' => 'Home', 'url' => 'https://example.com']);

        $this->breakTheMenuCache();

        // An outage of an OPTIONAL cache must not be an outage of the panel.
        $this->assertCount(0, app(TopbarMenu::class)->items());
    }

    public function test_flushing_the_cache_survives_a_store_that_is_down(): void
    {
        $this->breakTheMenuCache();

        // Every write through the package flushes the cache from a model event,
        // so a store that is down would otherwise turn saving a menu item into
        // a 500 as well.
        app(TopbarMenu::class)->flushCache();

        $this->assertNull(TopbarMenuItem::create(['label' => 'Home', 'url' => 'https://example.com'])->fresh()?->favicon_url);
    }

    /**
     * Point the cache at a store that refuses the menu's own keys and works for
     * everything else.
     */
    protected function breakTheMenuCache(): void
    {
        Cache::extend('exploding', fn (): Repository => Cache::repository(new ExplodingCacheStore));

        config(['cache.stores.exploding' => ['driver' => 'exploding'], 'cache.default' => 'exploding']);

        Cache::forgetDriver('exploding');
    }
}
