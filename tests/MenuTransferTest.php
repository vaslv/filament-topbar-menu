<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Vaslv\FilamentTopbarMenu\Database\Seeders\TopbarMenuSeeder;
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
            'javascript, mixed case' => ['JaVaScRiPt:alert(1)'],
            // A browser strips tab and newline before it reads the scheme, so
            // this navigates as `javascript:` while parse_url() sees a path.
            'javascript, split by a newline' => ["java\nscript:alert(1)"],
            'data scheme' => ['data:text/html,<script>alert(1)</script>'],
            'vbscript scheme' => ['vbscript:msgbox(1)'],
            'file scheme' => ['file:///etc/passwd'],
            // Scheme without a host: parses as scheme+path, browsers read the
            // href differently than PHP, and the hide_on_current_domain flag
            // could never match it.
            'scheme-only, no host' => ['http:foo'],
            'scheme with empty host' => ['http://'],
        ];
    }

    /**
     * Links that are not http(s) and are imported anyway, because the render
     * path renders them.
     *
     * The import used to refuse these on the reasoning that keeping them out
     * left pointsAtCurrentHost()'s no-host branches defensive-only. That
     * tidiness cost the round trip: the package's own seeder writes
     * `/telescope` and `/horizon`, so a seeded menu produced an export its own
     * import rejected — and with it `create-table --copy-from-default`. The
     * property worth keeping is the scheme allowlist, and that is unchanged.
     *
     * @return array<string, array{string}>
     */
    public static function relativeAndNonHttpUrlProvider(): array
    {
        return [
            'root-relative' => ['/admin/x'],
            'path-relative' => ['reports/monthly'],
            'scheme-relative' => ['//cdn.example.com/x'],
            'mailto scheme' => ['mailto:team@example.com'],
            'tel scheme' => ['tel:+123456789'],
        ];
    }

    public function test_a_menu_this_package_produced_survives_its_own_export_and_import(): void
    {
        // The invariant the round trip actually needs: whatever the package
        // writes, it can read back. Anything else takes `create-table
        // --copy-from-default` and the list page's setup action with it, since
        // both are an export followed by an import.
        (new TopbarMenuSeeder)->run();

        $before = $this->transfer()->export();
        $count = TopbarMenuItem::query()->count();

        $this->transfer()->import($before, replace: true);

        $this->assertSame($count, TopbarMenuItem::query()->count());
        $this->assertEquals($before, $this->transfer()->export());
    }

    public function test_a_third_level_survives_the_round_trip(): void
    {
        // A third level is reachable through the form — give a group that
        // already has children a parent of its own — and the items table lists
        // it on purpose. Export walked it and the import then refused the file
        // export had just written.
        $root = TopbarMenuItem::create(['label' => 'Root', 'url' => 'https://a.test']);
        $mid = TopbarMenuItem::create(['label' => 'Mid', 'url' => 'https://b.test', 'parent_id' => $root->id]);
        TopbarMenuItem::create(['label' => 'Leaf', 'url' => 'https://c.test', 'parent_id' => $mid->id]);

        $payload = $this->transfer()->export();

        $this->assertArrayHasKey('children', $payload['items'][0]['children'][0]);

        $this->transfer()->import($payload, replace: true);

        $leaf = TopbarMenuItem::query()->where('label', 'Leaf')->firstOrFail();

        $this->assertSame('Mid', $leaf->parent?->label);
        $this->assertSame('Root', $leaf->parent?->parent?->label);
    }

    public function test_import_still_refuses_a_tree_nested_past_the_structural_bound(): void
    {
        // The bound is a recursion guard, not the rendering depth — but it is
        // still a bound.
        $item = ['label' => 'Deep', 'url' => 'https://example.com'];

        for ($level = 0; $level < 12; $level++) {
            $item = ['label' => "Level {$level}", 'url' => 'https://example.com', 'children' => [$item]];
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds the maximum nesting depth');

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [$item],
        ]);
    }

    #[DataProvider('relativeAndNonHttpUrlProvider')]
    public function test_import_accepts_a_link_the_render_path_renders(string $url): void
    {
        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                ['label' => 'Link', 'url' => $url],
            ],
        ]);

        $item = TopbarMenuItem::query()->firstOrFail();

        $this->assertSame($url, $item->url);
        $this->assertSame($url, $item->resolveUrl(), 'An imported link the menu cannot render is a silent loss.');
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

    public function test_an_embedded_favicon_survives_an_export_import_round_trip(): void
    {
        $data = TopbarMenuItem::faviconDataFromBytes(self::PNG);

        TopbarMenuItem::query()->create([
            'label' => 'Embedded',
            'url' => 'https://example.com',
            'favicon_data' => $data,
        ]);

        $payload = $this->transfer()->export();

        TopbarMenuItem::query()->delete();

        $this->transfer()->import($payload);

        // The icon travels inside the file, which is the point: the export is
        // portable to an install that cannot reach the original host either.
        $this->assertSame($data, TopbarMenuItem::query()->where('label', 'Embedded')->firstOrFail()->favicon_data);
    }

    public function test_import_rejects_an_embedded_favicon_that_is_not_a_whitelisted_image(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[items.0.favicon_data]');

        // An import file is untrusted input whose values land in an <img src>
        // on every panel page, so the type whitelist applies here too.
        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                [
                    'label' => 'Icon',
                    'url' => 'https://example.com',
                    'favicon_data' => 'data:text/html;base64,'.base64_encode('<script>alert(1)</script>'),
                ],
            ],
        ]);
    }

    public function test_import_rejects_an_embedded_favicon_that_could_break_out_of_the_attribute(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[items.0.favicon_data]');

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                [
                    'label' => 'Icon',
                    'url' => 'https://example.com',
                    'favicon_data' => 'data:image/png;base64,"><script>alert(1)</script>',
                ],
            ],
        ]);
    }

    public function test_import_rejects_an_embedded_favicon_over_the_byte_cap(): void
    {
        config()->set('filament-topbar-menu.favicon_max_bytes', strlen(self::PNG) - 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[items.0.favicon_data]');

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                [
                    'label' => 'Icon',
                    'url' => 'https://example.com',
                    'favicon_data' => 'data:image/png;base64,'.base64_encode(self::PNG),
                ],
            ],
        ]);
    }

    public function test_import_rejects_a_non_string_embedded_favicon(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[items.0.favicon_data]');

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                ['label' => 'Icon', 'url' => 'https://example.com', 'favicon_data' => ['not', 'a', 'string']],
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

    public function test_import_rejects_a_non_boolean_hide_on_current_domain_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[items.0.visibility.hide_on_current_domain]');

        $this->transfer()->import([
            'plugin' => MenuTransfer::PLUGIN,
            'format' => MenuTransfer::FORMAT,
            'items' => [
                [
                    'label' => 'Poison',
                    'url' => 'https://example.com',
                    'visibility' => ['hide_on_current_domain' => 'yes'],
                ],
            ],
        ]);
    }

    public function test_the_hide_on_current_domain_flag_survives_an_export_import_round_trip(): void
    {
        TopbarMenuItem::create([
            'label' => 'Analytics',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://a.example.com',
            'visibility' => ['hide_on_current_domain' => true],
        ]);

        $payload = $this->transfer()->export();

        $this->assertSame(
            ['hide_on_current_domain' => true],
            $payload['items'][0]['visibility'],
        );

        TopbarMenuItem::query()->delete();

        $this->transfer()->import($payload);

        $this->assertSame(
            ['hide_on_current_domain' => true],
            TopbarMenuItem::query()->sole()->visibility,
        );
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
