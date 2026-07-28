<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Support\Facades\Schema;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\TopbarMenu;

/**
 * The topbar is rendered on every panel page, so a missing menu table — the
 * window between switching FILAMENT_TOPBAR_MENU_DB_CONNECTION and the
 * create-table deploy step — must degrade to an empty menu, never a 500.
 */
class MissingTableResilienceTest extends TestCase
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
}
