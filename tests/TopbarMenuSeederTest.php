<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Vaslv\FilamentTopbarMenu\Database\Seeders\TopbarMenuSeeder;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\TopbarMenu;

class TopbarMenuSeederTest extends TestCase
{
    public function test_it_seeds_a_demo_menu_tree(): void
    {
        $this->seed(TopbarMenuSeeder::class);

        $this->assertSame(6, TopbarMenuItem::query()->root()->count());

        $documentation = TopbarMenuItem::query()
            ->root()
            ->where('label', 'Documentation')
            ->firstOrFail();

        $this->assertSame(
            ['Laravel Docs', 'Filament Docs', 'GitHub'],
            $documentation->activeChildren->pluck('label')->all(),
        );

        $dashboard = TopbarMenuItem::query()->where('label', 'Dashboard')->firstOrFail();
        $this->assertSame(TopbarMenuItem::TYPE_ROUTE, $dashboard->type);
        $this->assertSame('filament.admin.pages.dashboard', $dashboard->route);

        $status = TopbarMenuItem::query()->where('label', 'Status')->firstOrFail();
        $this->assertSame(['auth' => true], $status->visibility);
        $this->assertSame(TopbarMenuItem::TARGET_BLANK, $status->resolveTarget());

        $adminTools = TopbarMenuItem::query()->where('label', 'Admin Tools')->firstOrFail();
        $this->assertSame(['roles' => ['admin']], $adminTools->visibility);
    }

    public function test_reseeding_does_not_duplicate_items(): void
    {
        $this->seed(TopbarMenuSeeder::class);
        $count = TopbarMenuItem::query()->count();

        $this->seed(TopbarMenuSeeder::class);

        $this->assertSame($count, TopbarMenuItem::query()->count());
    }

    public function test_the_menu_builds_from_seeded_data(): void
    {
        $this->seed(TopbarMenuSeeder::class);

        $labels = app(TopbarMenu::class)->items()->pluck('label');

        $this->assertContains('Documentation', $labels);
        $this->assertNotContains('Old Link (inactive)', $labels);
    }
}
