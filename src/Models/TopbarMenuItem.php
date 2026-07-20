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
