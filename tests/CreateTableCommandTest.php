<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

class CreateTableCommandTest extends TestCase
{
    public function test_creates_the_table_on_a_newly_configured_dedicated_connection(): void
    {
        // The exact state after switching an existing install: the package
        // migration already ran (and is recorded) on the default connection,
        // while the freshly configured dedicated connection has no menu table
        // — `php artisan migrate` would skip the migration and never create it.
        $this->configureDedicatedConnection();

        $this->assertFalse(Schema::connection('menu_db')->hasTable('filament_topbar_menu_items'));

        $this->artisan('filament-topbar-menu:create-table')
            ->expectsOutputToContain('created')
            ->assertSuccessful();

        $this->assertTrue(Schema::connection('menu_db')->hasTable('filament_topbar_menu_items'));

        // Menu queries must work again immediately after the command.
        TopbarMenuItem::create(['label' => 'Home', 'url' => 'https://example.com']);
        $this->assertSame(1, TopbarMenuItem::query()->count());
    }

    public function test_recreates_a_missing_table_on_the_default_connection(): void
    {
        Schema::drop('filament_topbar_menu_items');

        $this->artisan('filament-topbar-menu:create-table')->assertSuccessful();

        $this->assertTrue(Schema::hasTable('filament_topbar_menu_items'));
    }

    public function test_is_a_no_op_when_the_table_already_exists(): void
    {
        TopbarMenuItem::create(['label' => 'Existing', 'url' => 'https://example.com']);

        $this->artisan('filament-topbar-menu:create-table')
            ->expectsOutputToContain('already exists')
            ->assertSuccessful();

        // Rerunning must never touch existing data.
        $this->assertSame(['Existing'], TopbarMenuItem::query()->pluck('label')->all());
    }

    public function test_copies_the_menu_from_the_default_connection_when_switching(): void
    {
        $this->requiresTheDefaultSqliteConnection();

        $root = TopbarMenuItem::create(['label' => 'Root', 'url' => 'https://example.com', 'sort' => 1]);
        TopbarMenuItem::create(['label' => 'Child', 'url' => 'https://example.com/child', 'parent_id' => $root->id]);

        $this->configureDedicatedConnection();

        $this->artisan('filament-topbar-menu:create-table --copy-from-default')
            ->expectsOutputToContain('Copied 2')
            ->assertSuccessful();

        $rows = DB::connection('menu_db')->table('filament_topbar_menu_items')->get();

        $this->assertCount(2, $rows);
        // The hierarchy must survive the copy on the dedicated connection.
        $this->assertSame(
            $rows->firstWhere('label', 'Root')->id,
            $rows->firstWhere('label', 'Child')->parent_id,
        );

        // The source menu on the default connection stays untouched — dropping
        // it after a verified move is a manual step.
        $this->assertSame(2, DB::connection('testing')->table('filament_topbar_menu_items')->count());
    }

    public function test_rerunning_with_copy_never_duplicates_the_shared_menu(): void
    {
        TopbarMenuItem::create(['label' => 'Root', 'url' => 'https://example.com']);

        $this->configureDedicatedConnection();

        $this->artisan('filament-topbar-menu:create-table --copy-from-default')->assertSuccessful();

        // Second deploy: the shared menu already has items, so the copy must
        // refuse to merge this app's local menu into it again.
        $this->artisan('filament-topbar-menu:create-table --copy-from-default')
            ->expectsOutputToContain('already has items')
            ->assertSuccessful();

        $this->assertSame(1, DB::connection('menu_db')->table('filament_topbar_menu_items')->count());
    }

    public function test_copy_without_a_dedicated_connection_warns_and_changes_nothing(): void
    {
        TopbarMenuItem::create(['label' => 'Local', 'url' => 'https://example.com']);

        $this->artisan('filament-topbar-menu:create-table --copy-from-default')
            ->expectsOutputToContain('no dedicated connection')
            ->assertSuccessful();

        $this->assertSame(['Local'], TopbarMenuItem::query()->pluck('label')->all());
    }

    protected function configureDedicatedConnection(): void
    {
        config()->set('database.connections.menu_db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('filament-topbar-menu.connection', 'menu_db');
    }
}
