<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
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

    public function test_a_failed_import_rolls_back_on_the_configured_connection(): void
    {
        TopbarMenuItem::create(['label' => 'Existing', 'url' => 'https://example.com']);

        // A trigger on menu_db aborts the second insert mid-import — after the
        // replace-delete and the first insert already ran inside the
        // transaction. Everything must roll back on the dedicated connection.
        DB::connection('menu_db')->statement(
            "CREATE TRIGGER ftm_fail_boom BEFORE INSERT ON filament_topbar_menu_items
             WHEN NEW.label = 'BOOM' BEGIN SELECT RAISE(ABORT, 'boom'); END"
        );

        try {
            (new MenuTransfer)->import([
                'plugin' => MenuTransfer::PLUGIN,
                'format' => MenuTransfer::FORMAT,
                'items' => [
                    ['label' => 'Fine', 'type' => 'url', 'url' => 'https://example.com'],
                    ['label' => 'BOOM', 'type' => 'url', 'url' => 'https://example.com'],
                ],
            ], replace: true);

            $this->fail('The import should have failed inside the transaction.');
        } catch (QueryException) {
            // Expected: the trigger aborts the second insert.
        }

        // The replace-delete and the first insert must both roll back.
        $this->assertSame(['Existing'], TopbarMenuItem::query()->pluck('label')->all());
    }

    public function test_rerunning_the_migration_is_a_no_op_on_the_configured_connection(): void
    {
        $migration = include __DIR__.'/../database/migrations/create_filament_topbar_menu_items_table.php';

        // The table already exists on menu_db (created by the test migration
        // run) — a second up() must return early without throwing.
        $migration->up();

        $this->assertTrue(Schema::connection('menu_db')->hasTable('filament_topbar_menu_items'));
    }

    public function test_the_migration_guard_checks_the_configured_connection_not_the_default_one(): void
    {
        $migration = include __DIR__.'/../database/migrations/create_filament_topbar_menu_items_table.php';

        // Plant a same-named decoy on the DEFAULT connection and remove the
        // real table: up() must still create the table on menu_db instead of
        // being fooled by the decoy.
        Schema::connection('menu_db')->drop('filament_topbar_menu_items');
        Schema::connection('testing')->create('filament_topbar_menu_items', function (Blueprint $table) {
            $table->id();
        });

        $migration->up();

        $this->assertTrue(Schema::connection('menu_db')->hasTable('filament_topbar_menu_items'));
    }

    public function test_rollback_never_drops_the_table_on_a_dedicated_connection(): void
    {
        $migration = include __DIR__.'/../database/migrations/create_filament_topbar_menu_items_table.php';

        // The dedicated connection may be shared by several apps; down() must
        // not destroy the fleet-wide menu from one app's routine rollback.
        $migration->down();

        $this->assertTrue(Schema::connection('menu_db')->hasTable('filament_topbar_menu_items'));
    }
}
