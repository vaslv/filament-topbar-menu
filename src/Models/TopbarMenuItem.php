<?php

namespace Vaslv\FilamentTopbarMenu\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Routing\Exceptions\UrlGenerationException;
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
            } catch (UrlGenerationException) {
                // A required route parameter is missing or invalid. Skip the
                // item instead of throwing — the menu renders on every panel
                // page, so a single bad item would otherwise 500 the whole panel.
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
            if ($user === null) {
                return false;
            }

            if (method_exists($user, 'hasAnyRole')) {
                return $user->hasAnyRole($roles);
            }
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
}
