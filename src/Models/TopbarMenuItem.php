<?php

namespace Vaslv\FilamentTopbarMenu\Models;

use BladeUI\Icons\Exceptions\SvgNotFound;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Vaslv\FilamentTopbarMenu\TopbarMenu;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $label
 * @property string $type
 * @property string|null $url
 * @property string|null $route
 * @property array<string, mixed>|null $route_parameters
 * @property string $target
 * @property string|null $icon
 * @property string|null $favicon_url
 * @property bool $is_active
 * @property int $sort
 * @property array<string, mixed>|null $visibility
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TopbarMenuItem|null $parent
 * @property-read Collection<int, TopbarMenuItem> $children
 * @property-read Collection<int, TopbarMenuItem> $activeChildren
 */
class TopbarMenuItem extends Model
{
    public const TYPE_URL = 'url';

    public const TYPE_ROUTE = 'route';

    public const TARGET_SELF = '_self';

    public const TARGET_BLANK = '_blank';

    protected $fillable = [
        'parent_id',
        'label',
        'type',
        'url',
        'route',
        'route_parameters',
        'target',
        'icon',
        'favicon_url',
        'is_active',
        'sort',
        'visibility',
    ];

    protected $attributes = [
        'type' => self::TYPE_URL,
        'target' => self::TARGET_SELF,
        'is_active' => true,
        'sort' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'route_parameters' => 'array',
            'visibility' => 'array',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return config('filament-topbar-menu.table_name', 'filament_topbar_menu_items');
    }

    public function getConnectionName(): ?string
    {
        // An explicitly assigned connection (Model::on(), setConnection() — as
        // used by tenancy or maintenance code) always wins. Otherwise the
        // configured value routes every menu query to a dedicated connection,
        // which lets several apps share one menu database (see the `connection`
        // config option); null falls through to the app's default connection.
        return parent::getConnectionName() ?? config('filament-topbar-menu.connection');
    }

    protected static function booted(): void
    {
        $flushCache = fn () => app(TopbarMenu::class)->flushCache();

        static::saved($flushCache);
        static::deleted($flushCache);
    }

    /**
     * @return BelongsTo<TopbarMenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<TopbarMenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<TopbarMenuItem, $this>
     */
    public function activeChildren(): HasMany
    {
        return $this->children()->active()->ordered();
    }

    /**
     * @param  Builder<TopbarMenuItem>  $query
     * @return Builder<TopbarMenuItem>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<TopbarMenuItem>  $query
     * @return Builder<TopbarMenuItem>
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<TopbarMenuItem>  $query
     * @return Builder<TopbarMenuItem>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    /**
     * Order a flat result set the way the rendered menu reads it: every root at
     * its own position, its children immediately after it, each level by the
     * `sort`/`id` keys ordered() applies within one level.
     *
     * `sort` is a position among SIBLINGS, not a rank across the whole table,
     * so ordering flat by that column drops one group's children between
     * another group's roots. The group a row belongs to therefore sorts by its
     * root's position first, and only then does the row sort within its group.
     *
     * Two levels are what the menu renders, but the table lists a third when
     * one exists — giving a group a parent of its own keeps the rows and drops
     * them off the menu, and this table is where an admin goes to find them. So
     * the group is keyed by the row's ROOT ancestor, up to two hops away, and a
     * third-level row sits under the second-level one it hangs off instead of
     * being scattered among unrelated roots by its parent's `sort`. Deeper than
     * that the grouping degrades to the same interleaving, which is no worse
     * than not looking up ancestors at all.
     *
     * The ancestor lookups are correlated subqueries rather than a self-join on
     * purpose: this runs on the admin table, which also searches and sorts on
     * columns of this same table — and on `parent.label`, for which Filament
     * adds a join of its own. More copies of the table in the FROM clause would
     * make those references ambiguous. The cost is a handful of lookups per
     * row, which a menu-sized table can afford; the render path keeps using
     * ordered() per level and never runs this.
     *
     * @param  Builder<TopbarMenuItem>  $query
     * @return Builder<TopbarMenuItem>
     */
    public function scopeTreeOrdered(Builder $query): Builder
    {
        $grammar = $query->getQuery()->getGrammar();

        $table = $grammar->wrapTable($this->getTable());
        $parent = $grammar->wrapTable('topbar_menu_parent');
        $grandparent = $grammar->wrapTable('topbar_menu_grandparent');

        $own = fn (string $column): string => $table.'.'.$grammar->wrap($column);

        // The value `$column` has on the row's parent, or null for a root.
        $ofParent = fn (string $column): string => sprintf(
            '(select %s.%s from %s as %s where %s.%s = %s)',
            $parent, $grammar->wrap($column),
            $table, $parent,
            $parent, $grammar->wrap('id'), $own('parent_id'),
        );

        // Null unless the row is a third level: what its parent hangs off.
        $grandparentId = $ofParent('parent_id');

        $ofGrandparent = fn (string $column): string => sprintf(
            '(select %s.%s from %s as %s where %s.%s = %s)',
            $grandparent, $grammar->wrap($column),
            $table, $grandparent,
            $grandparent, $grammar->wrap('id'), $grandparentId,
        );

        // The row's root ancestor — itself, its parent or its grandparent.
        // coalesce(), not a nulls-first assumption: the lookups are null above
        // the row's own depth, and where a null sorts differs between databases.
        $rootKey = fn (string $column): string => sprintf(
            'coalesce(%s, %s, %s)',
            $ofGrandparent($column), $ofParent($column), $own($column),
        );

        // The second-level item the row belongs to: itself when the row is one,
        // its parent when the row is a third level. Roots never reach this key,
        // the depth marker before it having already placed them.
        $branchKey = fn (string $column): string => sprintf(
            'case when %s is null then %s else %s end',
            $grandparentId, $own($column), $ofParent($column),
        );

        $isBelow = fn (string $expression): string => sprintf(
            'case when %s is null then 0 else 1 end',
            $expression,
        );

        return $query
            ->orderByRaw($rootKey('sort'))
            ->orderByRaw($rootKey('id'))
            // Within a group the root comes before everything under it,
            ->orderByRaw($isBelow($own('parent_id')))
            // its second-level items follow in their own order,
            ->orderByRaw($branchKey('sort'))
            ->orderByRaw($branchKey('id'))
            // and each of those comes before the rows hanging off it.
            ->orderByRaw($isBelow($grandparentId))
            ->orderBy($this->qualifyColumn('sort'))
            ->orderBy($this->qualifyColumn('id'));
    }

    /**
     * Build the final URL of the item, depending on its type.
     */
    public function resolveUrl(): ?string
    {
        if ($this->type === self::TYPE_ROUTE) {
            if (blank($this->route) || ! Route::has($this->route)) {
                return null;
            }

            try {
                return route($this->route, $this->route_parameters ?? []);
            } catch (\Throwable) {
                // A required route parameter is missing or invalid (a missing
                // one throws UrlGenerationException; a non-scalar value throws a
                // stringification ErrorException). Skip the item instead of
                // throwing — the menu renders on every panel page, so a single
                // bad item would otherwise 500 the whole panel.
                return null;
            }
        }

        return $this->url;
    }

    /**
     * The link target. The per-item choice is authoritative: "Same tab"
     * (_self) always opens in the same tab and "New tab" (_blank) always opens
     * in a new one. The `open_external_links_in_new_tab` config only sets the
     * default value of the target field for newly created items (see the
     * resource form); it never overrides an explicit choice at render time.
     */
    public function resolveTarget(): string
    {
        return $this->target === self::TARGET_BLANK
            ? self::TARGET_BLANK
            : self::TARGET_SELF;
    }

    /**
     * Whether this item points at the page currently being viewed, so the menu
     * can highlight it the same way Filament highlights its own active items.
     * Route items match by route name; URL items match by normalized path.
     */
    public function isActive(): bool
    {
        if ($this->type === self::TYPE_ROUTE) {
            return filled($this->route)
                && Route::has($this->route)
                && request()->routeIs($this->route);
        }

        $url = $this->resolveUrl();

        if ($url === null) {
            return false;
        }

        $itemPath = rtrim((string) strtok($url, '?'), '/');

        return $itemPath !== '' && $itemPath === rtrim(request()->url(), '/');
    }

    /**
     * Whether this item or any of its visible children is active — used to mark
     * a dropdown group active when one of its links is the current page.
     */
    public function isBranchActive(?Authenticatable $user = null): bool
    {
        if ($this->isActive()) {
            return true;
        }

        return $this->visibleChildren($user)->contains(fn (self $child): bool => $child->isActive());
    }

    /**
     * The `visibility` JSON column supports the following keys:
     *
     * - `auth`  (bool)  — only show to authenticated users
     * - `guest` (bool)  — only show to guests
     * - `roles` (array) — only show to users with any of the given roles
     *                     (requires a `hasAnyRole()` method on the user model,
     *                     e.g. from spatie/laravel-permission)
     * - `hide_on_current_domain` (bool) — hide the item when its URL points at
     *                     the host serving the current request. Lets one shared
     *                     menu database serve a fleet of apps where each app
     *                     hides its own link. URL items only; the key is
     *                     ignored on route items (see pointsAtCurrentHost()).
     *
     * Decision tree (keep in sync when editing this method):
     *
     *   isVisibleTo(user):
     *     visibility empty? ──────────→ SHOW
     *     auth=true and guest? ───────→ HIDE
     *     guest=true and logged in? ──→ HIDE
     *     hide_on_current_domain
     *       and pointsAtCurrentHost()?→ HIDE   ← strictly BEFORE roles: the
     *                                            roles branch ends in an early
     *                                            return, so a later check would
     *                                            be unreachable for role-
     *                                            restricted items
     *     roles set? ─→ hasAnyRole ───→ RESULT (early return)
     *     otherwise ──────────────────→ SHOW
     */
    public function isVisibleTo(?Authenticatable $user): bool
    {
        $visibility = $this->visibility;

        if (blank($visibility)) {
            return true;
        }

        if (($visibility['auth'] ?? false) && $user === null) {
            return false;
        }

        if (($visibility['guest'] ?? false) && $user !== null) {
            return false;
        }

        if ($this->isHiddenOnCurrentDomain()) {
            return false;
        }

        $roles = $visibility['roles'] ?? [];

        if (filled($roles)) {
            // Fail closed: a role restriction we cannot evaluate — no user, or a
            // user model without hasAnyRole() (e.g. spatie/laravel-permission not
            // installed) — hides the item rather than silently showing a
            // role-restricted link to everyone.
            if ($user === null || ! method_exists($user, 'hasAnyRole')) {
                return false;
            }

            return $user->hasAnyRole($roles);
        }

        return true;
    }

    /**
     * @return Collection<int, TopbarMenuItem>
     */
    public function visibleChildren(?Authenticatable $user = null): Collection
    {
        return $this->activeChildren
            ->filter(fn (self $child): bool => $child->isVisibleTo($user))
            ->values();
    }

    /**
     * Whether the item is hidden on the host serving the current request: the
     * `hide_on_current_domain` visibility flag is set AND the item's URL points
     * at that host. Shared by isVisibleTo() and the admin table's
     * "hidden on this domain" badge so the two never disagree.
     */
    public function isHiddenOnCurrentDomain(): bool
    {
        return (bool) ($this->visibility['hide_on_current_domain'] ?? false)
            && $this->pointsAtCurrentHost();
    }

    /**
     * Whether the item's URL points at the host serving the current request.
     *
     * Decision tree (keep in sync when editing this method):
     *
     *   pointsAtCurrentHost():
     *     type = route? ─────────────→ false (the rule is for external links
     *                                         only — never route items)
     *     resolveUrl() blank? ───────→ false (nothing to compare; a URL-less
     *                                         dropdown group still renders)
     *     parse_url() === false? ────→ false (an undeterminable domain is not
     *                                         a match — show, don't hide)
     *     has a host? ───────────────→ normalizeHost(item) == normalizeHost(request)
     *     no host, no scheme? ───────→ true  (a relative URL is, by
     *                                         definition, the current domain)
     *     no host, has a scheme? ────→ false (mailto:/tel: — not a domain)
     *
     * The no-host branches are defensive only: the form (`->url()` requires
     * `://`) and the import (http(s) URLs with a host only) never store such
     * values, so they can only arrive via a seeder or manual SQL.
     *
     * Outside an HTTP context (artisan, queues) the request host falls back to
     * the app's configured URL, so the comparison stays deterministic.
     */
    public function pointsAtCurrentHost(): bool
    {
        // An explicit type guard, not a side effect of route() generating
        // current-host URLs: hiding a route item must never happen, even when
        // the key was planted on one through a seeder or import.
        if ($this->type === self::TYPE_ROUTE) {
            return false;
        }

        $url = $this->resolveUrl();

        if (blank($url)) {
            return false;
        }

        $parts = parse_url((string) $url);

        if ($parts === false) {
            return false;
        }

        $host = $parts['host'] ?? null;

        if ($host !== null) {
            $request = request();

            return static::normalizeHost($host, $parts['port'] ?? null)
                === static::normalizeHost($request->getHost(), (int) $request->getPort() ?: null);
        }

        return ! isset($parts['scheme']);
    }

    /**
     * Normalize a host for the current-domain comparison: hosts differing only
     * in case, a trailing dot, a `www.` prefix, punycode vs unicode spelling,
     * or IPv6 zero-compression identify the same service. Subdomains stay
     * significant. The port is part of the identity only when it is
     * non-standard — 80/443 and an absent port are equivalent, while
     * localhost:8000 and localhost:8001 are two different services (a dev
     * fleet on ports of one host must not all hide).
     */
    protected static function normalizeHost(string $host, ?int $port): string
    {
        $host = rtrim(strtolower($host), '.');

        // An IPv6 literal (bracketed by parse_url and Request::getHost alike)
        // spells one address many ways — `[::1]` vs `[0:0:0:0:0:0:0:1]` — so
        // canonicalize through the binary form. A colon never appears in a
        // DNS name, so this branch and the name logic below are disjoint.
        $bare = trim($host, '[]');

        if (str_contains($bare, ':') && filter_var($bare, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $host = '['.inet_ntop((string) inet_pton($bare)).']';
        } else {
            if (function_exists('idn_to_ascii')) {
                // Non-transitional UTS #46, matching how browsers resolve the
                // host (WHATWG URL): deviation characters like `ß` keep their
                // punycode identity instead of being folded to `ss`. Explicit
                // flags pin this across the ICU builds consumers run on.
                $ascii = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

                // Fail open: a host idn_to_ascii cannot convert is compared
                // as-is rather than breaking the whole visibility check.
                if (is_string($ascii) && $ascii !== '') {
                    $host = $ascii;
                }
            }

            if (str_starts_with($host, 'www.')) {
                $host = substr($host, 4);
            }
        }

        if ($port !== null && $port !== 80 && $port !== 443) {
            $host .= ':'.$port;
        }

        return $host;
    }

    /**
     * The audience mode ('auth' | 'guest' | null) represented by a visibility
     * array. Used by the resource form's visibility Select. Keys other than
     * `auth`/`guest` (e.g. `roles`) do not map to a mode and are ignored here.
     *
     * @param  array<string, mixed>|null  $visibility
     */
    public static function visibilityModeFromArray(?array $visibility): ?string
    {
        return match (true) {
            ($visibility['auth'] ?? false) === true => 'auth',
            ($visibility['guest'] ?? false) === true => 'guest',
            default => null,
        };
    }

    /**
     * Apply an audience mode ('auth' | 'guest' | null) onto an existing
     * visibility array, preserving every other key (notably `roles`) so that
     * editing an item through the form never silently drops role restrictions.
     * Returns null when the resulting array is empty.
     *
     * @param  array<string, mixed>|null  $current
     * @return array<string, mixed>|null
     */
    public static function applyVisibilityMode(?array $current, ?string $mode): ?array
    {
        $visibility = $current ?? [];

        unset($visibility['auth'], $visibility['guest']);

        if ($mode === 'auth') {
            $visibility['auth'] = true;
        } elseif ($mode === 'guest') {
            $visibility['guest'] = true;
        }

        return $visibility === [] ? null : $visibility;
    }

    /**
     * Apply the "hide on current domain" toggle onto an existing visibility
     * array, preserving every other key. Callers chain this over the result of
     * applyVisibilityMode() — never over the original array independently, or
     * the second helper would clobber the first one's result. Returns null when
     * the resulting array is empty.
     *
     * @param  array<string, mixed>|null  $current
     * @return array<string, mixed>|null
     */
    public static function applyHideOnCurrentDomain(?array $current, bool $enabled): ?array
    {
        $visibility = $current ?? [];

        if ($enabled) {
            $visibility['hide_on_current_domain'] = true;
        } else {
            unset($visibility['hide_on_current_domain']);
        }

        return $visibility === [] ? null : $visibility;
    }

    /**
     * The visual marker of the item exactly as the topbar renders it: the
     * favicon URL when one is set AND URL-shaped (Filament renders only
     * slash-containing strings as an <img>; see below), otherwise the validated
     * icon name — so a favicon always replaces the icon. Shared by every surface
     * that shows the marker — the topbar, its dropdowns, the admin table and
     * the View page — so none of them can disagree with the rendered menu.
     */
    public function displayIcon(): ?string
    {
        // Only a URL-shaped favicon may reach Filament's icon slot: it renders
        // a slash-containing string as an <img>, but treats anything else as a
        // registered icon NAME and throws SvgNotFound — which 500s every panel
        // page, since the menu renders on all of them. Every write path in this
        // package stores a URL, so a slash-less value comes from somewhere else:
        // a fleet-shared menu table written by a sibling app, a seeder, or SQL.
        if (filled($this->favicon_url) && str_contains($this->favicon_url, '/')) {
            return $this->favicon_url;
        }

        return static::safeIconName($this->icon);
    }

    /**
     * Validate a free-text icon name before handing it to Filament. An unknown
     * name would otherwise throw SvgNotFound and 500 the page, so callers get
     * null instead and can fall back to no icon. Favicons are images and never
     * go through here.
     */
    public static function safeIconName(?string $icon): ?string
    {
        if (blank($icon)) {
            return null;
        }

        // Filament renders any string containing "/" as an <img src="...">
        // instead of an SVG icon (generate_icon_html), so a free-text or
        // imported "icon" holding a URL would load an arbitrary external image
        // in every visitor's browser. Images belong in favicon_url; this field
        // only accepts registered icon names, which never contain slashes.
        if (str_contains($icon, '/')) {
            return null;
        }

        try {
            \Filament\Support\generate_icon_html($icon);

            return $icon;
        } catch (SvgNotFound) {
            // Expected for a typo in the free-text field: no icon, no 500.
            return null;
        } catch (\Throwable $e) {
            // Anything else (misregistered icon set, broken blade-icons cache,
            // filesystem permissions) is a real fault — keep the page alive but
            // surface it instead of silently blanking every icon.
            report($e);

            return null;
        }
    }
}
