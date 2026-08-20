<?php

namespace Vaslv\FilamentTopbarMenu;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

class TopbarMenu
{
    /**
     * The tree resolved for this request, or null before it has been.
     *
     * The plugin registers the menu at TWO render hooks — the desktop copy and
     * the mobile one — so every panel page ran the whole pipeline twice: a cache
     * read, a snapshot validation, and a full rehydration into fresh models.
     * The second pass could only ever produce what the first one did.
     *
     * Reusing the models is also what makes the per-instance memos on
     * TopbarMenuItem (displayIcon, isActive, visibleChildren) pay off across
     * both copies rather than within one of them.
     *
     * The binding is scoped rather than a singleton (see the service provider),
     * so this lives exactly as long as the request that filled it.
     *
     * @var Collection<int, TopbarMenuItem>|null
     */
    protected ?Collection $resolved = null;

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
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        try {
            $cached = $this->remember();

            if (! $this->isValidSnapshot($cached)) {
                // A stale entry from a release that cached models instead of arrays,
                // or a corrupted value. Replace it with a fresh snapshot rather than
                // letting the render path choke on an unusable payload.
                $cached = $this->snapshot();

                Cache::put($this->cacheKey(), $cached, config('filament-topbar-menu.cache_ttl', 3600));
            }

            return $this->resolved = $this->hydrate($cached);
        } catch (Throwable $e) {
            // The menu is chrome rendered on every panel page — it must never
            // take the whole app down. Degrade to an empty menu and report the
            // error instead of failing every request. Nothing is cached on this
            // path — Cache::remember stores only a successful snapshot — so the
            // menu reappears on the first request after the fault is cleared.
            //
            // Throwable, not QueryException: the ways this call fails are not
            // limited to a query, and every one of them lands on the same
            // render hook.
            //
            //   - the menu table does not exist yet (the typical trigger: an
            //     install switched to a dedicated connection and the
            //     create-table deploy step has not run) — a QueryException;
            //   - `connection` names a connection config/database.php does not
            //     define, which is the same switch half-deployed one step
            //     earlier — an InvalidArgumentException from DatabaseManager;
            //   - the cache store itself is down (Redis refusing connections),
            //     which reaches this method through Cache::remember.
            //
            // hydrate() is inside the try for the same reason: it resolves the
            // model's connection to build the query grammar, so it throws on a
            // misconfigured connection even when the snapshot came from cache.
            report($e);

            // Not memoized: a fault is not an answer to keep for the rest of
            // the request, and the second render hook costs nothing to retry.
            return new Collection;
        }
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

    /**
     * Drop the cached snapshot.
     *
     * Best-effort by design: this runs from the model's saved/deleted hooks, so
     * a store that is down would otherwise turn every menu write — and every
     * import — into a 500. There is nothing to invalidate on a store that
     * cannot answer, and items() degrades to an empty menu on the read side for
     * the same fault, so swallowing the failure here loses nothing that was
     * still working.
     */
    public function flushCache(): void
    {
        // The in-request memo goes first and unconditionally. Every write in
        // this package flushes through here, so a save during the request must
        // not leave the pre-save tree standing for whatever renders next.
        $this->resolved = null;

        try {
            Cache::forget($this->cacheKey());
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The cache key of the menu snapshot, scoped to the storage it was built
     * from.
     *
     * The configured key alone was not enough. Both halves of "where the menu
     * lives" — `connection` and `table_name` — are read from config at call
     * time, and the model's own docblock points out that an explicitly assigned
     * connection (Model::on(), as tenancy code uses) wins over both. So the key
     * has to name what it holds, or switching either one serves the previous
     * database's menu until the entry expires, and an app that swaps the
     * connection per tenant serves whichever tenant happened to write the entry
     * first — including that tenant's inactive rows and role-restricted items,
     * since visibility is applied after the cache, not before it.
     *
     * Not a complete tenancy story: an app that scopes the menu with a global
     * scope rather than a connection is invisible from here and still needs a
     * tenant-aware cache store or its own `cache_key`. Naming the connection and
     * the table covers what this package itself can vary.
     */
    public function cacheKey(): string
    {
        $model = new TopbarMenuItem;

        return implode(':', [
            config('filament-topbar-menu.cache_key', 'filament-topbar-menu.items'),
            $model->getConnectionName() ?? 'default',
            $model->getTable(),
        ]);
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
