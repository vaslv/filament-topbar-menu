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
        return Cache::remember(
            $this->cacheKey(),
            config('filament-topbar-menu.cache_ttl', 3600),
            fn (): Collection => TopbarMenuItem::query()
                ->root()
                ->active()
                ->ordered()
                ->with('activeChildren')
                ->get(),
        );
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
