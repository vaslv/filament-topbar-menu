<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Vaslv\FilamentTopbarMenu\Facades\TopbarMenu;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

class MenuCacheTest extends TestCase
{
    public function test_inactive_items_are_not_included(): void
    {
        TopbarMenuItem::create(['label' => 'Active', 'type' => 'url', 'url' => '/a']);
        TopbarMenuItem::create(['label' => 'Inactive', 'type' => 'url', 'url' => '/b', 'is_active' => false]);

        $items = TopbarMenu::items();

        $this->assertCount(1, $items);
        $this->assertSame('Active', $items->first()->label);
    }

    public function test_inactive_children_are_not_included(): void
    {
        $parent = TopbarMenuItem::create(['label' => 'Parent', 'type' => 'url', 'url' => '/']);

        TopbarMenuItem::create(['label' => 'Visible child', 'type' => 'url', 'url' => '/a', 'parent_id' => $parent->id]);
        TopbarMenuItem::create(['label' => 'Hidden child', 'type' => 'url', 'url' => '/b', 'parent_id' => $parent->id, 'is_active' => false]);

        $children = TopbarMenu::items()->first()->activeChildren;

        $this->assertCount(1, $children);
        $this->assertSame('Visible child', $children->first()->label);
    }

    public function test_children_are_grouped_under_their_parent_and_ordered_by_sort(): void
    {
        $parent = TopbarMenuItem::create(['label' => 'Services', 'type' => 'url', 'url' => '/', 'sort' => 0]);
        $other = TopbarMenuItem::create(['label' => 'Standalone', 'type' => 'url', 'url' => '/s', 'sort' => 1]);

        TopbarMenuItem::create(['label' => 'Second', 'type' => 'url', 'url' => '/2', 'parent_id' => $parent->id, 'sort' => 20]);
        TopbarMenuItem::create(['label' => 'First', 'type' => 'url', 'url' => '/1', 'parent_id' => $parent->id, 'sort' => 10]);

        $items = TopbarMenu::items();

        // Only root items on the top level.
        $this->assertCount(2, $items);
        $this->assertSame(['Services', 'Standalone'], $items->pluck('label')->all());

        // Children grouped under their parent, ordered by sort.
        $this->assertSame(
            ['First', 'Second'],
            $items->first()->activeChildren->pluck('label')->all(),
        );
        $this->assertCount(0, $items->last()->activeChildren);
    }

    public function test_the_menu_is_cached_and_no_query_runs_on_subsequent_renders(): void
    {
        TopbarMenuItem::create(['label' => 'Item', 'type' => 'url', 'url' => '/']);

        TopbarMenu::items();

        $this->assertTrue(Cache::has(TopbarMenu::cacheKey()));

        DB::enableQueryLog();
        TopbarMenu::items();
        DB::disableQueryLog();

        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_the_cache_is_flushed_when_an_item_is_created(): void
    {
        TopbarMenu::items();
        $this->assertTrue(Cache::has(TopbarMenu::cacheKey()));

        TopbarMenuItem::create(['label' => 'New', 'type' => 'url', 'url' => '/']);

        $this->assertFalse(Cache::has(TopbarMenu::cacheKey()));
        $this->assertCount(1, TopbarMenu::items());
    }

    public function test_the_cache_is_flushed_when_an_item_is_updated(): void
    {
        $item = TopbarMenuItem::create(['label' => 'Old', 'type' => 'url', 'url' => '/']);

        TopbarMenu::items();
        $this->assertTrue(Cache::has(TopbarMenu::cacheKey()));

        $item->update(['label' => 'Updated']);

        $this->assertFalse(Cache::has(TopbarMenu::cacheKey()));
        $this->assertSame('Updated', TopbarMenu::items()->first()->label);
    }

    public function test_the_cache_is_flushed_when_an_item_is_deleted(): void
    {
        $item = TopbarMenuItem::create(['label' => 'Doomed', 'type' => 'url', 'url' => '/']);

        TopbarMenu::items();
        $this->assertTrue(Cache::has(TopbarMenu::cacheKey()));

        $item->delete();

        $this->assertFalse(Cache::has(TopbarMenu::cacheKey()));
        $this->assertCount(0, TopbarMenu::items());
    }

    public function test_a_poisoned_cache_payload_is_rebuilt_from_the_database(): void
    {
        TopbarMenuItem::create(['label' => 'Real', 'type' => 'url', 'url' => '/']);

        // A serializing store can hand back a collection of __PHP_Incomplete_Class
        // objects; a non-model element stands in for that here. The menu must drop
        // the unusable entry and rebuild from the database instead of trusting it.
        Cache::put(TopbarMenu::cacheKey(), new EloquentCollection([new \stdClass]), 3600);

        $items = TopbarMenu::items();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(TopbarMenuItem::class, $items->first());
        $this->assertSame('Real', $items->first()->label);
    }

    public function test_visible_items_survive_a_poisoned_cache_payload(): void
    {
        TopbarMenuItem::create(['label' => 'Real', 'type' => 'url', 'url' => '/']);

        // Regression for the production fatal: visibleItems() filtered the cached
        // tree through a closure typed `TopbarMenuItem $item`, so an incomplete
        // object 500-ed every panel page. It must now degrade gracefully.
        Cache::put(TopbarMenu::cacheKey(), new EloquentCollection([new \stdClass]), 3600);

        $visible = TopbarMenu::visibleItems(null);

        $this->assertSame(['Real'], $visible->pluck('label')->all());
    }

    public function test_the_cache_stores_a_serialization_safe_array_not_models(): void
    {
        $parent = TopbarMenuItem::create(['label' => 'Parent', 'type' => 'url', 'url' => '/']);
        TopbarMenuItem::create(['label' => 'Child', 'type' => 'url', 'url' => '/c', 'parent_id' => $parent->id]);

        TopbarMenu::items();

        // The tree is cached as plain arrays — never live models — so a serializing
        // store cannot hand back __PHP_Incomplete_Class objects.
        $cached = Cache::get(TopbarMenu::cacheKey());

        $this->assertIsArray($cached);
        $this->assertSame('Parent', $cached[0]['attributes']['label']);
        $this->assertSame('Child', $cached[0]['children'][0]['attributes']['label']);
    }

    public function test_cached_items_rehydrate_into_working_models_with_children(): void
    {
        $parent = TopbarMenuItem::create(['label' => 'Services', 'type' => 'url', 'url' => 'https://s.example.com']);
        TopbarMenuItem::create(['label' => 'Analytics', 'type' => 'url', 'url' => 'https://a.example.com', 'parent_id' => $parent->id]);

        // Prime the cache, then read again so the tree comes back from the cached
        // array and is rebuilt into models — no query, full model behavior.
        TopbarMenu::items();

        DB::enableQueryLog();
        $items = TopbarMenu::items();
        DB::disableQueryLog();

        $this->assertCount(0, DB::getQueryLog());

        $root = $items->first();
        $this->assertInstanceOf(TopbarMenuItem::class, $root);
        $this->assertSame('https://s.example.com', $root->resolveUrl());

        $child = $root->activeChildren->first();
        $this->assertInstanceOf(TopbarMenuItem::class, $child);
        $this->assertSame('Analytics', $child->label);
        $this->assertSame('https://a.example.com', $child->resolveUrl());
    }

    public function test_a_stale_model_collection_cache_is_replaced_with_an_array(): void
    {
        TopbarMenuItem::create(['label' => 'Real', 'type' => 'url', 'url' => '/']);

        // Simulate a cache written by an older release that stored live models.
        Cache::put(TopbarMenu::cacheKey(), TopbarMenuItem::query()->root()->get(), 3600);

        $items = TopbarMenu::items();

        $this->assertSame(['Real'], $items->pluck('label')->all());
        $this->assertIsArray(Cache::get(TopbarMenu::cacheKey()));
    }

    public function test_a_shaped_but_poisoned_cache_entry_does_not_fatal_the_render_path(): void
    {
        TopbarMenuItem::create(['label' => 'Real', 'type' => 'url', 'url' => '/']);

        // Top-level shape is valid but a child is not an array — this must be
        // rejected before it reaches hydrate's typed closure, then rebuilt from
        // the database, so the render path (visibleItems) never fatals.
        Cache::put(TopbarMenu::cacheKey(), [
            ['attributes' => ['id' => 1, 'label' => 'Poison', 'type' => 'url', 'url' => '/x'], 'children' => ['not-an-array']],
        ], 3600);

        $visible = TopbarMenu::visibleItems(null);

        $this->assertSame(['Real'], $visible->pluck('label')->all());
    }

    public function test_a_cache_hit_does_not_fire_the_retrieved_event(): void
    {
        TopbarMenuItem::create(['label' => 'Real', 'type' => 'url', 'url' => '/']);

        // Prime the cache (the miss reads from the DB), then listen for retrieved.
        TopbarMenu::items();

        $retrieved = 0;
        TopbarMenuItem::retrieved(function () use (&$retrieved): void {
            $retrieved++;
        });

        // A cache hit rehydrates from the cached array and must not fire the
        // model's retrieved event — nothing was retrieved from the database.
        TopbarMenu::items();

        $this->assertSame(0, $retrieved);
    }

    public function test_visible_items_respect_visibility_rules(): void
    {
        TopbarMenuItem::create(['label' => 'Public', 'type' => 'url', 'url' => '/']);
        TopbarMenuItem::create(['label' => 'Members', 'type' => 'url', 'url' => '/m', 'visibility' => ['auth' => true]]);

        $this->assertSame(['Public'], TopbarMenu::visibleItems(null)->pluck('label')->all());

        $user = new GenericUser(['id' => 1]);

        $this->assertSame(['Public', 'Members'], TopbarMenu::visibleItems($user)->pluck('label')->all());
    }
}
