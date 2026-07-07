<?php

namespace Vaslv\FilamentTopbarMenu;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

class TopbarMenu
{
    /**
     * The cached menu tree: active root items (ordered by `sort`) with
     * their active children eager loaded.
     *
     * @return Collection<int, TopbarMenuItem>
     */
    public function items(): Collection
    {
        $items = Cache::remember(
            $this->cacheKey(),
            config('filament-topbar-menu.cache_ttl', 3600),
            fn (): Collection => $this->query(),
        );

        // The menu renders on every panel page, so it must never trust an unusable
        // cache entry (see isUsableTree): drop it and rebuild from the database so
        // the menu self-heals instead of 500-ing the whole panel.
        if ($this->isUsableTree($items)) {
            return $items;
        }

        $this->flushCache();

        return $this->query();
    }

    /**
     * The menu tree straight from the database: active root items (ordered by
     * `sort`) with their active children eager loaded.
     *
     * @return Collection<int, TopbarMenuItem>
     */
    protected function query(): Collection
    {
        return TopbarMenuItem::query()
            ->root()
            ->active()
            ->ordered()
            ->with('activeChildren')
            ->get();
    }

    /**
     * Whether a cached value is a clean collection of models. A serializing cache
     * store (Redis, file, database, memcached) can hand back
     * __PHP_Incomplete_Class objects when the payload can't be reconstituted —
     * after a deploy (an autoload/opcache gap) or under a shared, misconfigured
     * store — which would otherwise fatal the typed render path.
     */
    protected function isUsableTree(mixed $items): bool
    {
        return $items instanceof Collection
            && $items->every(fn ($item): bool => $item instanceof TopbarMenuItem);
    }

    /**
     * The menu tree filtered by per-item visibility rules for the given
     * user. Visibility is evaluated per request and is never cached.
     *
     * @return Collection<int, TopbarMenuItem>
     */
    public function visibleItems(?Authenticatable $user = null): Collection
    {
        return $this->items()
            ->filter(fn (TopbarMenuItem $item): bool => $item->isVisibleTo($user))
            ->values();
    }

    public function flushCache(): void
    {
        Cache::forget($this->cacheKey());
    }

    public function cacheKey(): string
    {
        return config('filament-topbar-menu.cache_key', 'filament-topbar-menu.items');
    }
}
