<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\Support\MenuTransfer;

/**
 * The `connection` config lets the menu live on a dedicated database — for
 * example one shared by several apps. These tests run the whole package against
 * a second in-memory connection (`menu_db`) while the app default stays
 * `testing`, and assert the menu table and every read/write land on `menu_db`.
 */
class ConnectionTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // A second, independent connection standing in for a shared menu
        // database. It is not the app default, so anything that reaches it
        // proves the package honored the `connection` config.
        $app['config']->set('database.connections.menu_db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('filament-topbar-menu.connection', 'menu_db');
    }

    public function test_the_model_uses_the_configured_connection(): void
    {
        $this->assertSame('menu_db', (new TopbarMenuItem)->getConnectionName());
    }

    public function test_the_migration_runs_on_the_configured_connection(): void
    {
        $this->assertTrue(Schema::connection('menu_db')->hasTable('filament_topbar_menu_items'));
        $this->assertFalse(Schema::connection('testing')->hasTable('filament_topbar_menu_items'));
    }

    public function test_writes_land_on_the_configured_connection(): void
    {
        $item = TopbarMenuItem::create([
            'label' => 'Shared',
            'url' => 'https://example.com',
        ]);

        $this->assertSame('menu_db', $item->getConnectionName());

        $this->assertSame(1, DB::connection('menu_db')
            ->table('filament_topbar_menu_items')
            ->count());
    }

    public function test_import_wraps_writes_in_a_transaction_on_the_configured_connection(): void
    {
        $payload = [
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                ['label' => 'Home', 'type' => 'url', 'url' => 'https://example.com'],
            ],
        ];

        $created = (new MenuTransfer)->import($payload);

        $this->assertSame(1, $created);
        $this->assertSame(1, DB::connection('menu_db')
            ->table('filament_topbar_menu_items')
            ->count());
    }
}
