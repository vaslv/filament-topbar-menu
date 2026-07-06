<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Support\Facades\Schema;

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
            'is_active',
            'sort',
            'visibility',
            'created_at',
            'updated_at',
        ]));
    }
}
