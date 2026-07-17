<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Vaslv\FilamentTopbarMenu\Database\Seeders\TopbarMenuSeeder;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\Tests\Fixtures\RoleAwareUser;
use Vaslv\FilamentTopbarMenu\TopbarMenu;

class TopbarMenuSeederTest extends TestCase
{
    public function test_it_seeds_a_demo_menu_tree(): void
    {
        $this->seed(TopbarMenuSeeder::class);

        $this->assertSame(5, TopbarMenuItem::query()->root()->count());

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
    }

    public function test_the_roles_example_is_skipped_when_the_user_model_cannot_evaluate_roles(): void
    {
        // The testbench default user model has no hasAnyRole() method, so a
        // role-restricted item would be hidden from everyone (fail closed) —
        // a broken-looking demo. The seeder must leave it out.
        $this->seed(TopbarMenuSeeder::class);

        $this->assertNull(TopbarMenuItem::query()->where('label', 'Admin Tools')->first());
        $this->assertNull(TopbarMenuItem::query()->where('label', 'Telescope')->first());
    }

    public function test_the_roles_example_is_seeded_when_the_user_model_supports_roles(): void
    {
        config(['auth.providers.users.model' => RoleAwareUser::class]);

        $this->seed(TopbarMenuSeeder::class);

        $adminTools = TopbarMenuItem::query()
            ->root()
            ->where('label', 'Admin Tools')
            ->firstOrFail();

        $this->assertSame(['roles' => ['admin']], $adminTools->visibility);
        $this->assertSame(
            ['Telescope', 'Horizon'],
            $adminTools->activeChildren->pluck('label')->all(),
        );
    }

    public function test_reseeding_without_roles_support_removes_the_roles_example(): void
    {
        config(['auth.providers.users.model' => RoleAwareUser::class]);
        $this->seed(TopbarMenuSeeder::class);

        config(['auth.providers.users.model' => null]);
        $this->seed(TopbarMenuSeeder::class);

        $this->assertNull(TopbarMenuItem::query()->where('label', 'Admin Tools')->first());
        $this->assertNull(TopbarMenuItem::query()->where('label', 'Telescope')->first());
        $this->assertNull(TopbarMenuItem::query()->where('label', 'Horizon')->first());
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
