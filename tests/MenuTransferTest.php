<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\Support\MenuTransfer;
use Vaslv\FilamentTopbarMenu\TopbarMenu;

class MenuTransferTest extends TestCase
{
    protected function transfer(): MenuTransfer
    {
        return $this->app->make(MenuTransfer::class);
    }

    protected function seedMenu(): void
    {
        $group = TopbarMenuItem::create([
            'label' => 'Docs',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://docs.example.com',
            'icon' => 'heroicon-o-book-open',
            'sort' => 1,
        ]);

        TopbarMenuItem::create([
            'parent_id' => $group->id,
            'label' => 'Admin guide',
            'type' => TopbarMenuItem::TYPE_ROUTE,
            'route' => 'admin.guide',
            'route_parameters' => ['section' => 'intro'],
            'target' => TopbarMenuItem::TARGET_BLANK,
            'sort' => 5,
            'visibility' => ['auth' => true, 'roles' => ['admin']],
        ]);

        TopbarMenuItem::create([
            'label' => 'Blog',
            'url' => 'https://blog.example.com',
            'favicon_url' => 'https://blog.example.com/favicon.ico',
            'is_active' => false,
            'sort' => 2,
        ]);
    }

    public function test_export_produces_a_nested_ordered_payload_without_ids(): void
    {
        $this->seedMenu();

        $payload = $this->transfer()->export();

        $this->assertSame(MenuTransfer::PLUGIN, $payload['plugin']);
        $this->assertSame(MenuTransfer::FORMAT, $payload['format']);
        $this->assertCount(2, $payload['items']);

        [$docs, $blog] = $payload['items'];

        $this->assertSame('Docs', $docs['label']);
        $this->assertSame('Blog', $blog['label']);
        $this->assertArrayNotHasKey('id', $docs);
        $this->assertArrayNotHasKey('parent_id', $docs);
        $this->assertArrayNotHasKey('children', $blog);

        $this->assertCount(1, $docs['children']);
        $child = $docs['children'][0];

        $this->assertSame('Admin guide', $child['label']);
        $this->assertSame(TopbarMenuItem::TYPE_ROUTE, $child['type']);
        $this->assertSame(['section' => 'intro'], $child['route_parameters']);
        $this->assertSame(['auth' => true, 'roles' => ['admin']], $child['visibility']);
        $this->assertSame(TopbarMenuItem::TARGET_BLANK, $child['target']);
    }

    public function test_a_round_trip_recreates_the_menu(): void
    {
        $this->seedMenu();

        $payload = $this->transfer()->export();

        TopbarMenuItem::query()->delete();

        $created = $this->transfer()->import($payload);

        $this->assertSame(3, $created);
        $this->assertSame(3, TopbarMenuItem::count());

        $docs = TopbarMenuItem::query()->where('label', 'Docs')->firstOrFail();
        $child = TopbarMenuItem::query()->where('label', 'Admin guide')->firstOrFail();
        $blog = TopbarMenuItem::query()->where('label', 'Blog')->firstOrFail();

        $this->assertSame($docs->id, $child->parent_id);
        $this->assertNull($docs->parent_id);
        $this->assertSame(['section' => 'intro'], $child->route_parameters);
        $this->assertSame(['auth' => true, 'roles' => ['admin']], $child->visibility);
        $this->assertSame(TopbarMenuItem::TARGET_BLANK, $child->target);
        $this->assertFalse($blog->is_active);
        $this->assertSame('https://blog.example.com/favicon.ico', $blog->favicon_url);
    }

    public function test_import_appends_to_the_existing_menu_by_default(): void
    {
        TopbarMenuItem::create(['label' => 'Existing', 'url' => 'https://example.com']);

        $created = $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                ['label' => 'Imported', 'url' => 'https://imported.example.com'],
            ],
        ]);

        $this->assertSame(1, $created);
        $this->assertSame(2, TopbarMenuItem::count());
    }

    public function test_a_replace_import_deletes_the_existing_menu_first(): void
    {
        $this->seedMenu();

        $created = $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                ['label' => 'Only item', 'url' => 'https://only.example.com'],
            ],
        ], replace: true);

        $this->assertSame(1, $created);
        $this->assertSame(['Only item'], TopbarMenuItem::query()->pluck('label')->all());
    }

    public function test_a_replace_import_of_zero_items_flushes_the_menu_cache(): void
    {
        $this->seedMenu();

        $menu = $this->app->make(TopbarMenu::class);
        $menu->items();

        $this->assertTrue(Cache::has($menu->cacheKey()));

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [],
        ], replace: true);

        $this->assertFalse(Cache::has($menu->cacheKey()));
        $this->assertSame(0, TopbarMenuItem::count());
    }

    public function test_import_rejects_a_foreign_payload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a filament-topbar-menu export');

        $this->transfer()->import(['some' => 'json']);
    }

    public function test_import_rejects_an_unsupported_format_version(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported export format [99]');

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => 99,
            'items' => [],
        ]);
    }

    public function test_import_reports_the_path_of_the_first_invalid_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[items.0.children.1.type]');

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                [
                    'label' => 'Group',
                    'children' => [
                        ['label' => 'Fine', 'url' => 'https://example.com'],
                        ['label' => 'Broken', 'type' => 'teleport'],
                    ],
                ],
            ],
        ]);
    }

    public function test_an_invalid_file_never_touches_the_existing_menu_even_when_replacing(): void
    {
        $this->seedMenu();

        try {
            $this->transfer()->import([
                'plugin' => MenuTransfer::PLUGIN,
                'format' => MenuTransfer::FORMAT,
                'items' => [
                    ['label' => 'Fine', 'url' => 'https://example.com'],
                    ['label' => str_repeat('x', 256)],
                ],
            ], replace: true);

            $this->fail('An InvalidArgumentException should have been thrown.');
        } catch (InvalidArgumentException) {
            // Expected: validation runs before anything is deleted or created.
        }

        $this->assertSame(3, TopbarMenuItem::count());
    }

    public function test_import_ignores_unknown_item_keys_and_never_reuses_exported_ids(): void
    {
        $existing = TopbarMenuItem::create(['label' => 'Existing', 'url' => 'https://example.com']);

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                [
                    'label' => 'Imported',
                    'url' => 'https://imported.example.com',
                    'id' => $existing->id,
                    'parent_id' => $existing->id,
                    'some_future_field' => ['nested' => true],
                ],
            ],
        ]);

        $imported = TopbarMenuItem::query()->where('label', 'Imported')->firstOrFail();

        $this->assertNotSame($existing->id, $imported->id);
        $this->assertNull($imported->parent_id);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafeUrlProvider(): array
    {
        return [
            'javascript scheme' => ['javascript:alert(document.cookie)'],
            'data scheme' => ['data:text/html,<script>alert(1)</script>'],
            'scheme-relative' => ['//evil.example.com/x'],
            'root-relative' => ['/admin/x'],
        ];
    }

    #[DataProvider('unsafeUrlProvider')]
    public function test_import_rejects_a_non_http_url(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[items.0.url]');

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                ['label' => 'Evil', 'url' => $url],
            ],
        ]);
    }

    public function test_import_rejects_a_favicon_url_that_could_break_out_of_the_css_sink(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[items.0.favicon_url]');

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                [
                    'label' => 'Icon',
                    'url' => 'https://example.com',
                    'favicon_url' => "https://x.test/a.png') url('javascript:alert(1)",
                ],
            ],
        ]);
    }

    public function test_import_rejects_a_non_http_favicon_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[items.0.favicon_url]');

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                ['label' => 'Icon', 'url' => 'https://example.com', 'favicon_url' => 'data:image/png;base64,AAAA'],
            ],
        ]);
    }

    public function test_import_rejects_an_over_length_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[items.0.url]');

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                ['label' => 'Long', 'url' => 'https://example.com/'.str_repeat('a', 300)],
            ],
        ]);
    }

    public function test_import_rejects_a_non_scalar_route_parameter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[items.0.route_parameters.report]');

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                [
                    'label' => 'Report',
                    'type' => TopbarMenuItem::TYPE_ROUTE,
                    'route' => 'reports.show',
                    'route_parameters' => ['report' => ['nested']],
                ],
            ],
        ]);
    }

    public function test_import_rejects_a_malformed_visibility_roles_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[items.0.visibility.roles]');

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                [
                    'label' => 'Poison',
                    'url' => 'https://example.com',
                    'visibility' => ['roles' => ['x' => ['y' => 1]]],
                ],
            ],
        ]);
    }

    public function test_import_rejects_a_non_boolean_visibility_flag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[items.0.visibility.auth]');

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                ['label' => 'Poison', 'url' => 'https://example.com', 'visibility' => ['auth' => 'yes']],
            ],
        ]);
    }

    public function test_import_rejects_a_tree_deeper_than_two_levels(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[items.0.children.0.children]');

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                [
                    'label' => 'Root',
                    'url' => 'https://example.com',
                    'children' => [
                        [
                            'label' => 'Child',
                            'url' => 'https://example.com/child',
                            'children' => [
                                ['label' => 'Grandchild', 'url' => 'https://example.com/grandchild'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_import_accepts_a_255_character_multibyte_label(): void
    {
        $label = str_repeat('я', 255); // 255 characters, 510 bytes

        $created = $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                ['label' => $label, 'url' => 'https://example.com'],
            ],
        ]);

        $this->assertSame(1, $created);
        $this->assertSame($label, TopbarMenuItem::query()->firstOrFail()->label);
    }

    public function test_an_append_import_flushes_the_menu_cache(): void
    {
        TopbarMenuItem::create(['label' => 'Existing', 'url' => 'https://example.com']);

        $menu = $this->app->make(TopbarMenu::class);
        $menu->items();

        $this->assertTrue(Cache::has($menu->cacheKey()));

        // Import suppresses the per-item saved event, so the post-commit flush
        // is the only thing keeping the rendered menu in sync — assert it runs.
        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                ['label' => 'Imported', 'url' => 'https://imported.example.com'],
            ],
        ]);

        $this->assertFalse(Cache::has($menu->cacheKey()));
    }

    public function test_import_applies_the_model_defaults_to_minimal_items(): void
    {
        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                ['label' => 'Minimal', 'url' => 'https://example.com'],
            ],
        ]);

        $item = TopbarMenuItem::query()->where('label', 'Minimal')->firstOrFail();

        $this->assertSame(TopbarMenuItem::TYPE_URL, $item->type);
        $this->assertSame(TopbarMenuItem::TARGET_SELF, $item->target);
        $this->assertTrue($item->is_active);
        $this->assertSame(0, $item->sort);
        $this->assertNull($item->visibility);
    }
}
