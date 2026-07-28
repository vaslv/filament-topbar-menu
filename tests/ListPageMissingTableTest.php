<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Schema;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DbSchema;
use ReflectionMethod;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource\Pages\ListTopbarMenuItems;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

/**
 * The list page is the panel-side recovery path for a missing menu table (the
 * window between switching FILAMENT_TOPBAR_MENU_DB_CONNECTION and the
 * create-table deploy step): it must render an explanation instead of a 500,
 * and its setup action must perform the same create-and-copy as the
 * `filament-topbar-menu:create-table --copy-from-default` command.
 */
class ListPageMissingTableTest extends TestCase
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

    public function test_the_page_content_replaces_the_records_table_when_the_menu_table_is_missing(): void
    {
        DbSchema::drop('filament_topbar_menu_items');

        $components = (new ListTopbarMenuItems)->content(Schema::make())->getComponents();

        // Rendering the records table would query the missing table and 500
        // the page — the content must degrade to an explanation instead.
        $this->assertCount(1, $components);
        $this->assertInstanceOf(EmptyState::class, $components[0]);
    }

    public function test_the_page_content_keeps_the_records_table_when_the_menu_table_exists(): void
    {
        $components = (new ListTopbarMenuItems)->content(Schema::make())->getComponents();

        $this->assertNotEmpty(array_filter(
            $components,
            fn ($component): bool => $component instanceof EmbeddedTable,
        ));
    }

    public function test_the_setup_action_creates_the_table_and_copies_the_local_menu_on_a_switch(): void
    {
        $root = TopbarMenuItem::create(['label' => 'Root', 'url' => 'https://example.com', 'sort' => 1]);
        TopbarMenuItem::create(['label' => 'Child', 'url' => 'https://example.com/child', 'parent_id' => $root->id]);

        // The switch scenario: a dedicated connection is configured and has no
        // menu table, while the local menu still sits on the default connection.
        config()->set('database.connections.menu_db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('filament-topbar-menu.connection', 'menu_db');

        $copied = (new ReflectionMethod(ListTopbarMenuItems::class, 'setUpMenuTable'))
            ->invoke(new ListTopbarMenuItems);

        $this->assertSame(2, $copied);

        $rows = DB::connection('menu_db')->table('filament_topbar_menu_items')->get();

        $this->assertCount(2, $rows);
        $this->assertSame(
            $rows->firstWhere('label', 'Root')->id,
            $rows->firstWhere('label', 'Child')->parent_id,
        );
    }

    public function test_the_setup_action_recreates_a_missing_table_on_the_default_connection_without_copying(): void
    {
        DbSchema::drop('filament_topbar_menu_items');

        $copied = (new ReflectionMethod(ListTopbarMenuItems::class, 'setUpMenuTable'))
            ->invoke(new ListTopbarMenuItems);

        $this->assertNull($copied);
        $this->assertTrue(DbSchema::hasTable('filament_topbar_menu_items'));
    }
}
