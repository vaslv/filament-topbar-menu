<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Auth\GenericUser;
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

    public function test_visible_items_respect_visibility_rules(): void
    {
        TopbarMenuItem::create(['label' => 'Public', 'type' => 'url', 'url' => '/']);
        TopbarMenuItem::create(['label' => 'Members', 'type' => 'url', 'url' => '/m', 'visibility' => ['auth' => true]]);

        $this->assertSame(['Public'], TopbarMenu::visibleItems(null)->pluck('label')->all());

        $user = new GenericUser(['id' => 1]);

        $this->assertSame(['Public', 'Members'], TopbarMenu::visibleItems($user)->pluck('label')->all());
    }
}
