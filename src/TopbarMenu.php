<?php

namespace Vaslv\FilamentTopbarMenu;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

class TopbarMenu
{
    /**
     * The menu tree: active root items (ordered by `sort`) with their active
     * children eager loaded.
     *
     * The tree is cached as plain scalar arrays — never as live Eloquent models —
     * and rehydrated into models on read. Caching model objects is fragile: a
     * serializing store (Redis, file, database) can hand them back as
     * __PHP_Incomplete_Class after a deploy or under a shared store, which would
     * fatal the typed render path on every panel page. Scalars always round-trip,
     * so that failure mode cannot occur.
     *
     * @return Collection<int, TopbarMenuItem>
     */
    public function items(): Collection
    {
        $cached = $this->remember();

        if (! $this->isValidSnapshot($cached)) {
            // A stale entry from a release that cached models instead of arrays,
            // or a corrupted value. Replace it with a fresh snapshot rather than
            // letting the render path choke on an unusable payload.
            $cached = $this->snapshot();

            Cache::put($this->cacheKey(), $cached, config('filament-topbar-menu.cache_ttl', 3600));
        }

        return $this->hydrate($cached);
    }

    /**
     * The menu tree filtered by per-item visibility rules for the given user.
     * Visibility is evaluated per request and is never cached.
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

    /**
     * The cached snapshot, or a freshly computed one on a miss. Returns mixed on
     * purpose: a store can hand back anything (e.g. an older release's model
     * collection), which the caller validates before trusting.
     */
    protected function remember(): mixed
    {
        return Cache::remember(
            $this->cacheKey(),
            config('filament-topbar-menu.cache_ttl', 3600),
            fn (): array => $this->snapshot(),
        );
    }

    /**
     * A serialization-safe snapshot of the menu tree: each active root item's raw
     * attributes plus its active children's raw attributes. Only scalars, so it
     * round-trips through any cache store without class resolution.
     *
     * @return list<array{attributes: array<string, mixed>, children: list<array{attributes: array<string, mixed>}>}>
     */
    protected function snapshot(): array
    {
        return $this->query()
            ->map(fn (TopbarMenuItem $item): array => [
                'attributes' => $item->getAttributes(),
                'children' => $item->activeChildren
                    ->map(fn (TopbarMenuItem $child): array => ['attributes' => $child->getAttributes()])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Rebuild the tree of models from a snapshot. Models are created with
     * newFromBuilder (no queries, no events) and each root's active children are
     * reattached as a relation, so the view keeps working entirely off models.
     *
     * @param  list<array{attributes: array<string, mixed>, children: list<array{attributes: array<string, mixed>}>}>  $snapshot
     * @return Collection<int, TopbarMenuItem>
     */
    protected function hydrate(array $snapshot): Collection
    {
        // Rebuild inside withoutEvents so rehydrating cached models never fires
        // `retrieved` — they were not retrieved from the database. This keeps a
        // cache hit free of model events (and any queries a host app's listener
        // might run) in addition to the query-free newFromBuilder path.
        /** @var list<TopbarMenuItem> $roots */
        $roots = TopbarMenuItem::withoutEvents(
            fn (): array => array_map(fn (array $node): TopbarMenuItem => $this->hydrateNode($node), $snapshot),
        );

        return new Collection($roots);
    }

    /**
     * Rebuild a single root model from its snapshot node and reattach its active
     * children as a relation.
     *
     * @param  array{attributes: array<string, mixed>, children: list<array{attributes: array<string, mixed>}>}  $node
     */
    protected function hydrateNode(array $node): TopbarMenuItem
    {
        $item = (new TopbarMenuItem)->newFromBuilder($node['attributes']);

        $item->setRelation('activeChildren', TopbarMenuItem::hydrate(
            array_map(fn (array $child): array => $child['attributes'], $node['children']),
        ));

        return $item;
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
     * Whether a cached value is a snapshot this class wrote — a list of nodes,
     * each an array with an `attributes` array and a `children` array. Anything
     * else (a stale model collection from an older release, a corrupted entry) is
     * rejected so the tree is rebuilt from the database instead.
     *
     * @phpstan-assert-if-true list<array{attributes: array<string, mixed>, children: list<array{attributes: array<string, mixed>}>}> $cached
     */
    protected function isValidSnapshot(mixed $cached): bool
    {
        if (! is_array($cached)) {
            return false;
        }

        foreach ($cached as $node) {
            if (! is_array($node)
                || ! is_array($node['attributes'] ?? null)
                || ! is_array($node['children'] ?? null)) {
                return false;
            }

            // Descend into children too: a shaped-but-poisoned entry with a
            // non-array child would otherwise reach hydrate's typed closure and
            // throw, defeating the whole point of validating here.
            foreach ($node['children'] as $child) {
                if (! is_array($child) || ! is_array($child['attributes'] ?? null)) {
                    return false;
                }
            }
        }

        return true;
    }
}
