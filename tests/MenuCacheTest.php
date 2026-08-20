<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
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

    public function test_the_menu_is_resolved_once_per_request_however_often_it_is_rendered(): void
    {
        TopbarMenuItem::create(['label' => 'Home', 'type' => 'url', 'url' => '/']);

        // Warm it, then watch. The plugin renders the menu at two hooks on
        // every panel page, so the second copy used to repeat the whole
        // pipeline: a cache read, a snapshot validation and a full rehydration.
        $first = TopbarMenu::items();

        Cache::forget(TopbarMenu::cacheKey());

        // Nothing reaches the store or the database on the second call, so
        // dropping the entry underneath it changes nothing.
        $second = TopbarMenu::items();

        $this->assertCount(1, $second);
        $this->assertSame($first->first(), $second->first(), 'Both copies must render from the same models.');
    }

    public function test_a_write_during_the_request_invalidates_the_in_request_menu_too(): void
    {
        TopbarMenuItem::create(['label' => 'First', 'type' => 'url', 'url' => '/a']);

        $this->assertCount(1, TopbarMenu::items());

        // Saving flushes through the model's saved hook. A memo that survived
        // that would let the rest of the request render the pre-save tree.
        TopbarMenuItem::create(['label' => 'Second', 'type' => 'url', 'url' => '/b']);

        $this->assertCount(2, TopbarMenu::items());
    }

    public function test_the_resolved_menu_does_not_outlive_the_request(): void
    {
        TopbarMenuItem::create(['label' => 'Home', 'type' => 'url', 'url' => '/']);

        $first = TopbarMenu::items()->first();

        // What Octane does between requests, both halves of it: the container
        // drops scoped instances and the facades drop their resolved ones. A
        // singleton binding would have carried this request's models into the
        // next one regardless.
        $this->app->forgetScopedInstances();
        Facade::clearResolvedInstances();

        $this->assertNotSame($first, TopbarMenu::items()->first());
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

    public function test_the_cache_key_names_the_storage_it_holds(): void
    {
        $default = TopbarMenu::cacheKey();

        $this->assertStringContainsString('filament_topbar_menu_items', $default);
        $this->assertStringContainsString('default', $default);

        // Both halves of "where the menu lives" are read from config at call
        // time, so both belong in the key.
        config(['filament-topbar-menu.table_name' => 'other_menu_items']);
        $this->assertNotSame($default, TopbarMenu::cacheKey());

        config(['filament-topbar-menu.table_name' => 'filament_topbar_menu_items']);
        config(['filament-topbar-menu.connection' => 'menu']);
        $this->assertNotSame($default, TopbarMenu::cacheKey());
    }

    public function test_switching_table_does_not_serve_the_previous_tables_menu(): void
    {
        TopbarMenuItem::create(['label' => 'On the default table', 'type' => 'url', 'url' => '/a']);

        $this->assertSame('On the default table', TopbarMenu::items()->first()?->label);

        // Point the package at a second table holding a different menu. Under
        // one global key the snapshot above answered for this one too, until it
        // expired — the wrong menu, from the wrong database, with no write to
        // flush it.
        DB::statement('CREATE TABLE other_menu_items AS SELECT * FROM filament_topbar_menu_items WHERE 1 = 0');
        config(['filament-topbar-menu.table_name' => 'other_menu_items']);

        TopbarMenuItem::create(['label' => 'On the other table', 'type' => 'url', 'url' => '/b']);

        $this->assertSame('On the other table', TopbarMenu::items()->first()?->label);
    }

    public function test_flushing_targets_the_key_the_menu_is_actually_cached_under(): void
    {
        config(['filament-topbar-menu.table_name' => 'other_menu_items']);
        DB::statement('CREATE TABLE other_menu_items AS SELECT * FROM filament_topbar_menu_items WHERE 1 = 0');

        TopbarMenuItem::create(['label' => 'Item', 'type' => 'url', 'url' => '/a']);
        TopbarMenu::items();

        $this->assertTrue(Cache::has(TopbarMenu::cacheKey()));

        TopbarMenu::flushCache();

        $this->assertFalse(Cache::has(TopbarMenu::cacheKey()));
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

    public function test_the_hide_on_current_domain_flag_works_after_cache_rehydration(): void
    {
        TopbarMenuItem::create([
            'label' => 'Self',
            'type' => 'url',
            'url' => 'https://a.example.com',
            'visibility' => ['hide_on_current_domain' => true],
        ]);
        TopbarMenuItem::create([
            'label' => 'Sibling',
            'type' => 'url',
            'url' => 'https://b.example.com',
            'visibility' => ['hide_on_current_domain' => true],
        ]);

        // Prime the cache, then read again so the items come back rehydrated
        // from the cached snapshot: `visibility` must survive the
        // getAttributes()/casts round-trip (historically the package's most
        // fragile path), and the domain check must run per request — never be
        // baked into the cache.
        TopbarMenu::items();

        $this->bindRequest('https://a.example.com/admin');

        DB::enableQueryLog();
        $visible = TopbarMenu::visibleItems(null);
        DB::disableQueryLog();

        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame(['Sibling'], $visible->pluck('label')->all());

        // The same cached tree on another host shows the other item.
        $this->bindRequest('https://b.example.com/admin');

        $this->assertSame(['Self'], TopbarMenu::visibleItems(null)->pluck('label')->all());
    }

    public function test_enabling_the_flag_flushes_the_cache_and_hides_the_item_end_to_end(): void
    {
        $this->bindRequest('https://a.example.com/admin');

        $item = TopbarMenuItem::create([
            'label' => 'Self',
            'type' => 'url',
            'url' => 'https://a.example.com',
        ]);

        $this->assertSame(['Self'], TopbarMenu::visibleItems(null)->pluck('label')->all());
        $this->assertTrue(Cache::has(TopbarMenu::cacheKey()));

        // What the admin form's save does under the hood. The saved hook
        // flushes the cache, so the very next render drops the item — no
        // stale-TTL window.
        $item->update([
            'visibility' => TopbarMenuItem::applyHideOnCurrentDomain($item->visibility, true),
        ]);

        $this->assertFalse(Cache::has(TopbarMenu::cacheKey()));
        $this->assertSame([], TopbarMenu::visibleItems(null)->pluck('label')->all());
    }

    public function test_a_flagged_group_with_its_own_current_host_url_hides_the_whole_subtree(): void
    {
        // Deliberate semantics: a "group about this service" is not needed on
        // this service — the flagged parent is filtered out in visibleItems(),
        // taking its children with it.
        $parent = TopbarMenuItem::create([
            'label' => 'This service',
            'type' => 'url',
            'url' => 'https://a.example.com',
            'visibility' => ['hide_on_current_domain' => true],
        ]);
        TopbarMenuItem::create([
            'label' => 'Child',
            'type' => 'url',
            'url' => 'https://b.example.com',
            'parent_id' => $parent->id,
        ]);

        $this->bindRequest('https://a.example.com/admin');

        $this->assertCount(0, TopbarMenu::visibleItems(null));

        $this->bindRequest('https://b.example.com/admin');

        $this->assertSame(['This service'], TopbarMenu::visibleItems(null)->pluck('label')->all());
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
