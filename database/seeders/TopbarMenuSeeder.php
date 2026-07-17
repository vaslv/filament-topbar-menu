<?php

namespace Vaslv\FilamentTopbarMenu\Database\Seeders;

use Illuminate\Database\Seeder;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

/**
 * Seeds a demo menu that exercises every feature of the plugin: external URL
 * links, an internal route link, a dropdown group with children, per-item
 * targets and icons, visibility rules (auth / guest / roles) and an inactive
 * item. Intended for trying the package out in a host app:
 *
 *   php artisan db:seed --class="Vaslv\FilamentTopbarMenu\Database\Seeders\TopbarMenuSeeder"
 *
 * Safe to re-run: items are matched by parent + label and updated in place.
 */
class TopbarMenuSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->items() as $index => $definition) {
            $this->seedItem($definition, null, $index * 10);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function items(): array
    {
        return [
            [
                // Rendered only when the host app has a panel with this route
                // name (the default `admin` panel dashboard); otherwise the
                // item is skipped gracefully instead of breaking the page.
                'label' => 'Dashboard',
                'type' => TopbarMenuItem::TYPE_ROUTE,
                'route' => 'filament.admin.pages.dashboard',
                'icon' => 'heroicon-o-home',
            ],
            [
                // A top-level item with children renders as a dropdown group;
                // the group label is a pure toggle, its own url is not used.
                'label' => 'Documentation',
                'icon' => 'heroicon-o-book-open',
                'children' => [
                    [
                        'label' => 'Laravel Docs',
                        'url' => 'https://laravel.com/docs',
                        'target' => TopbarMenuItem::TARGET_BLANK,
                    ],
                    [
                        'label' => 'Filament Docs',
                        'url' => 'https://filamentphp.com/docs',
                        'target' => TopbarMenuItem::TARGET_BLANK,
                    ],
                    [
                        'label' => 'GitHub',
                        'url' => 'https://github.com',
                        'target' => TopbarMenuItem::TARGET_BLANK,
                        'icon' => 'heroicon-o-code-bracket',
                    ],
                ],
            ],
            [
                'label' => 'Status',
                'url' => 'https://status.laravel.com',
                'target' => TopbarMenuItem::TARGET_BLANK,
                'icon' => 'heroicon-o-signal',
                'visibility' => ['auth' => true],
            ],
            [
                'label' => 'Help Center',
                'url' => 'https://laravel.com/docs/installation',
                'target' => TopbarMenuItem::TARGET_BLANK,
                'icon' => 'heroicon-o-lifebuoy',
                'visibility' => ['guest' => true],
            ],
            [
                // Requires a hasAnyRole() method on the user model (e.g. from
                // spatie/laravel-permission); hidden otherwise (fail closed).
                'label' => 'Admin Tools',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'visibility' => ['roles' => ['admin']],
                'children' => [
                    [
                        'label' => 'Telescope',
                        'url' => '/telescope',
                    ],
                    [
                        'label' => 'Horizon',
                        'url' => '/horizon',
                    ],
                ],
            ],
            [
                'label' => 'Old Link (inactive)',
                'url' => 'https://example.com/legacy',
                'is_active' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function seedItem(array $definition, ?int $parentId, int $sort): void
    {
        /** @var list<array<string, mixed>> $children */
        $children = $definition['children'] ?? [];
        unset($definition['children']);

        /** @var array<model-property<TopbarMenuItem>, mixed> $attributes */
        $attributes = $definition + ['sort' => $sort];

        $item = TopbarMenuItem::query()->updateOrCreate(
            ['parent_id' => $parentId, 'label' => $definition['label']],
            $attributes,
        );

        foreach ($children as $index => $child) {
            $this->seedItem($child, $item->id, $index * 10);
        }
    }
}
