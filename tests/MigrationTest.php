<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Support\Facades\Schema;
use Vaslv\FilamentTopbarMenu\Support\MenuTableSchema;

class MigrationTest extends TestCase
{
    public function test_migration_creates_the_menu_items_table(): void
    {
        $this->assertTrue(Schema::hasTable('filament_topbar_menu_items'));
    }

    public function test_table_has_the_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('filament_topbar_menu_items', [
            'id',
            'parent_id',
            'label',
            'type',
            'url',
            'route',
            'route_parameters',
            'target',
            'icon',
            'favicon_url',
            'favicon_data',
            'is_active',
            'sort',
            'visibility',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_the_favicon_data_migration_adds_the_column_to_an_existing_table(): void
    {
        Schema::table('filament_topbar_menu_items', function ($table): void {
            $table->dropColumn('favicon_data');
        });

        $this->assertFalse(Schema::hasColumn('filament_topbar_menu_items', 'favicon_data'));

        $migration = include __DIR__.'/../database/migrations/add_favicon_data_to_filament_topbar_menu_items_table.php';

        // The upgrade path: an install whose menu table predates the column
        // has the create migration already recorded as ran, so only this one
        // can put the column there.
        $migration->up();

        $this->assertTrue(Schema::hasColumn('filament_topbar_menu_items', 'favicon_data'));
    }

    public function test_the_favicon_data_migration_is_idempotent(): void
    {
        $migration = include __DIR__.'/../database/migrations/add_favicon_data_to_filament_topbar_menu_items_table.php';

        // Running against a table that already has the column must not throw:
        // several apps may share one menu database and each records the
        // migration separately.
        $migration->up();

        $this->assertTrue(Schema::hasColumn('filament_topbar_menu_items', 'favicon_data'));
    }

    public function test_the_favicon_data_rollback_spares_a_shared_menu(): void
    {
        $migration = include __DIR__.'/../database/migrations/add_favicon_data_to_filament_topbar_menu_items_table.php';

        config()->set('filament-topbar-menu.connection', 'testing');

        $migration->down();

        // A dedicated connection may be one menu shared by several apps, each
        // recording this migration separately. One app rolling back must not
        // take the column away from the others.
        $this->assertTrue(Schema::hasColumn('filament_topbar_menu_items', 'favicon_data'));

        config()->set('filament-topbar-menu.connection', null);

        $migration->down();

        $this->assertFalse(Schema::hasColumn('filament_topbar_menu_items', 'favicon_data'));
    }

    public function test_adding_the_column_reports_what_it_did(): void
    {
        $schema = new MenuTableSchema;

        // Already present: nothing to do, and it says so.
        $this->assertFalse($schema->addFaviconData());

        Schema::table('filament_topbar_menu_items', function ($table): void {
            $table->dropColumn('favicon_data');
        });

        $this->assertTrue($schema->addFaviconData());

        Schema::drop('filament_topbar_menu_items');

        // No table at all is the create migration's business, so this stays a
        // no-op rather than throwing.
        $this->assertFalse($schema->addFaviconData());
    }

    public function test_the_create_table_command_also_brings_an_existing_table_up_to_date(): void
    {
        Schema::table('filament_topbar_menu_items', function ($table): void {
            $table->dropColumn('favicon_data');
        });

        // The command exists for installs where `migrate` is not how the schema
        // arrives (a menu moved to a dedicated connection, where the package
        // migration is already recorded as ran). Creating the table is not
        // enough there — every later column has to be applied too.
        $this->artisan('filament-topbar-menu:create-table')->assertSuccessful();

        $this->assertTrue(Schema::hasColumn('filament_topbar_menu_items', 'favicon_data'));
    }

    public function test_rollback_drops_the_table_when_the_menu_uses_the_default_connection(): void
    {
        $migration = include __DIR__.'/../database/migrations/create_filament_topbar_menu_items_table.php';

        // With no dedicated connection configured the table belongs to this
        // app alone, so down() keeps its normal drop semantics.
        $migration->down();

        $this->assertFalse(Schema::hasTable('filament_topbar_menu_items'));
    }
}
