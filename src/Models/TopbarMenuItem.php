<?php

namespace Vaslv\FilamentTopbarMenu\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Route;
use Vaslv\FilamentTopbarMenu\TopbarMenu;

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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function activeChildren(): HasMany
    {
        return $this->children()->active()->ordered();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

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
     * Build the final target of the item, taking the
     * `open_external_links_in_new_tab` option into account.
     */
    public function resolveTarget(): string
    {
        if ($this->target === self::TARGET_BLANK) {
            return self::TARGET_BLANK;
        }

        if (
            config('filament-topbar-menu.open_external_links_in_new_tab', true)
            && $this->isExternalUrl()
        ) {
            return self::TARGET_BLANK;
        }

        return $this->target ?: self::TARGET_SELF;
    }

    public function isExternalUrl(): bool
    {
        if ($this->type !== self::TYPE_URL || blank($this->url)) {
            return false;
        }

        $itemAuthority = static::normalizeAuthority($this->url);

        if ($itemAuthority === null) {
            return false;
        }

        return $itemAuthority !== static::normalizeAuthority((string) config('app.url'));
    }

    /**
     * Normalize a URL to a comparable "host:port" authority: lower-cased host
     * with the port made explicit (default 80/443 filled in from the scheme),
     * so that letter case and default vs. explicit ports don't cause false
     * external/internal classifications. Returns null for host-less URLs.
     */
    public static function normalizeAuthority(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (blank($host)) {
            return null;
        }

        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?: 'https'));
        $port = parse_url($url, PHP_URL_PORT) ?: ($scheme === 'http' ? 80 : 443);

        return strtolower($host) . ':' . $port;
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
