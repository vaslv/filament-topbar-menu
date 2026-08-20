<?php

namespace Vaslv\FilamentTopbarMenu\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\TopbarMenu;

/**
 * Serializes the whole menu tree into a portable array payload and restores it
 * back. Used by the export/import header actions on the list page.
 *
 * The payload deliberately contains no database ids: hierarchy is expressed by
 * nesting `children`, so a file exported from one install imports cleanly into
 * another. Unknown keys on imported items are ignored, which lets newer
 * exports (with extra fields) load into older installs.
 */
class MenuTransfer
{
    /**
     * Bump when the payload structure changes incompatibly; import() rejects
     * files with a different format number instead of guessing.
     */
    public const FORMAT = 1;

    public const PLUGIN = 'vaslv/filament-topbar-menu';

    /**
     * String columns (label, url, route, icon, favicon_url) are VARCHAR(255).
     * Reject over-length imported values up front rather than letting the
     * database truncate them silently (MySQL non-strict) or throw a raw driver
     * exception (MySQL strict) that the import action cannot report cleanly.
     *
     * `favicon_data` is exempt — it is a mediumText column holding an embedded
     * image, bounded by `favicon_max_bytes` instead.
     *
     * Aliased to the model's constant rather than spelled again: the form, the
     * schema and the favicon resolver all have to agree on this number.
     */
    protected const MAX_STRING_LENGTH = TopbarMenuItem::URL_MAX_LENGTH;

    /**
     * The topbar renders exactly two levels — root items and one level of
     * children (see menu.blade.php / TopbarMenu::snapshot). Grandchildren would
     * import "successfully" yet never render and could not be re-parented
     * through the form, so a deeper tree is rejected instead of silently lost.
     */
    protected const MAX_DEPTH = 2;

    /**
     * @return array<string, mixed>
     */
    public function export(): array
    {
        $byParent = [];

        foreach (TopbarMenuItem::query()->ordered()->get() as $item) {
            $byParent[$item->parent_id ?? 0][] = $item;
        }

        return [
            'plugin' => self::PLUGIN,
            'format' => self::FORMAT,
            'items' => $this->exportLevel($byParent, 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return int The number of created items.
     *
     * @throws InvalidArgumentException When the payload is not a valid menu export.
     */
    public function import(array $payload, bool $replace = false): int
    {
        if (($payload['plugin'] ?? null) !== self::PLUGIN || ! is_array($payload['items'] ?? null)) {
            throw new InvalidArgumentException('The payload is not a filament-topbar-menu export.');
        }

        if (($payload['format'] ?? null) !== self::FORMAT) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported export format [%s]; this version reads format [%d].',
                json_encode($payload['format'] ?? null),
                self::FORMAT,
            ));
        }

        /** @var array<int, mixed> $items */
        $items = array_values($payload['items']);

        $this->validateItems($items, 'items', 1);

        // Wrap the import in a transaction on the model's own connection, not
        // the default one — otherwise a menu stored on a dedicated connection
        // would run its writes outside the transaction entirely.
        $connection = (new TopbarMenuItem)->getConnectionName();

        $created = DB::connection($connection)->transaction(function () use ($items, $replace): int {
            if ($replace) {
                TopbarMenuItem::query()->delete();
            }

            // Suppress the model's per-save cache flush: it would run once per
            // created item, all inside the transaction (so it cannot help
            // concurrent readers, who would just re-cache the pre-commit tree),
            // and the single post-commit flush below is the one that matters.
            return TopbarMenuItem::withoutEvents(fn (): int => $this->createItems($items, null));
        });

        // The mass delete and the withoutEvents create both fire no model
        // events, so nothing flushed the cache — flush explicitly here so the
        // rendered menu never serves the pre-import tree.
        app(TopbarMenu::class)->flushCache();

        return $created;
    }

    /**
     * Copy the app's local menu — the menu table on the default connection —
     * onto the configured dedicated connection. The model resolves its
     * connection from config at call time, so the export temporarily points
     * the config at the default connection; the finally restores it even when
     * the export throws, so a failure can never leave the whole package
     * routed to the wrong database. Callers guard the preconditions (a
     * dedicated connection is configured, its menu is empty, the source
     * table exists) — see the create-table command and the list page.
     *
     * @return int The number of copied items.
     *
     * @throws InvalidArgumentException When a local row fails the import's validation.
     */
    public function copyFromDefaultConnection(): int
    {
        $dedicated = config('filament-topbar-menu.connection');

        config(['filament-topbar-menu.connection' => null]);

        try {
            $payload = $this->export();
        } finally {
            config(['filament-topbar-menu.connection' => $dedicated]);
        }

        return $this->import($payload);
    }

    /**
     * @param  array<int, list<TopbarMenuItem>>  $byParent
     * @return list<array<string, mixed>>
     */
    protected function exportLevel(array $byParent, int $parentId): array
    {
        $level = [];

        foreach ($byParent[$parentId] ?? [] as $item) {
            $data = [
                'label' => $item->label,
                'type' => $item->type,
                'url' => $item->url,
                'route' => $item->route,
                'route_parameters' => $item->route_parameters,
                'target' => $item->target,
                'icon' => $item->icon,
                'favicon_url' => $item->favicon_url,
                'favicon_data' => $item->favicon_data,
                'is_active' => $item->is_active,
                'sort' => $item->sort,
                'visibility' => $item->visibility,
            ];

            $children = $this->exportLevel($byParent, $item->id);

            if ($children !== []) {
                $data['children'] = $children;
            }

            $level[] = $data;
        }

        return $level;
    }

    /**
     * Validate the full tree up front so a broken file never half-imports.
     * The exception message carries the path of the first invalid value
     * (e.g. `items.2.children.0.type`) to make hand-edited files fixable.
     *
     * Import is a trust boundary: the file is untrusted third-party input the
     * form's own validation never touched, yet its values flow straight into
     * the topbar rendered on every panel page. So this repeats the guarantees
     * the form and FaviconResolver enforce — an `url`/`favicon_url` must be a
     * plain http(s) link (blocking `javascript:`/`data:` hrefs and attribute or
     * CSS-`url()` breakouts), `route_parameters` must be scalar, and `visibility` must be
     * well-shaped — rather than trusting the file to be well-formed.
     *
     * @param  array<int, mixed>  $items
     *
     * @throws InvalidArgumentException
     */
    protected function validateItems(array $items, string $path, int $depth): void
    {
        foreach (array_values($items) as $index => $item) {
            $itemPath = "{$path}.{$index}";

            if (! is_array($item)) {
                throw new InvalidArgumentException("[{$itemPath}] must be an object.");
            }

            $label = $item['label'] ?? null;

            if (! is_string($label) || trim($label) === '' || mb_strlen($label) > self::MAX_STRING_LENGTH) {
                throw new InvalidArgumentException("[{$itemPath}.label] must be a non-empty string of at most ".self::MAX_STRING_LENGTH.' characters.');
            }

            if (! in_array($item['type'] ?? TopbarMenuItem::TYPE_URL, [TopbarMenuItem::TYPE_URL, TopbarMenuItem::TYPE_ROUTE], true)) {
                throw new InvalidArgumentException("[{$itemPath}.type] must be one of: url, route.");
            }

            if (! in_array($item['target'] ?? TopbarMenuItem::TARGET_SELF, [TopbarMenuItem::TARGET_SELF, TopbarMenuItem::TARGET_BLANK], true)) {
                throw new InvalidArgumentException("[{$itemPath}.target] must be one of: _self, _blank.");
            }

            foreach (['url', 'route', 'icon', 'favicon_url'] as $field) {
                $value = $item[$field] ?? null;

                if ($value === null) {
                    continue;
                }

                if (! is_string($value)) {
                    throw new InvalidArgumentException("[{$itemPath}.{$field}] must be a string or null.");
                }

                if (mb_strlen($value) > self::MAX_STRING_LENGTH) {
                    throw new InvalidArgumentException("[{$itemPath}.{$field}] must be at most ".self::MAX_STRING_LENGTH.' characters.');
                }
            }

            // `url` becomes an anchor href and `favicon_url` an image source,
            // both rendered to every panel user; only plain http(s) links are
            // safe there. filled() lets a dropdown group keep an empty url.
            //
            // Deliberately stricter than TopbarMenuItem::isRenderableUrl(), the
            // gate the render path applies: that one exists to stop a stored
            // value from executing and therefore tolerates the relative links
            // and `mailto:` a seeder legitimately writes, while an imported file
            // is third-party input that has no such history to respect. An
            // import writes what it was given; a renderer renders what it finds.
            if (filled($item['url'] ?? null) && ! $this->isSafeHttpUrl($item['url'])) {
                throw new InvalidArgumentException("[{$itemPath}.url] must be a http(s) URL.");
            }

            if (filled($item['favicon_url'] ?? null) && ! TopbarMenuItem::isStorableFaviconUrl($item['favicon_url'])) {
                throw new InvalidArgumentException("[{$itemPath}.favicon_url] must be a http(s) URL without quotes, angle brackets or whitespace.");
            }

            // An embedded icon is not length-checked with the VARCHAR fields
            // above — it is a `data:` URI in a mediumText column, thousands of
            // characters by nature. What it IS checked for is everything that
            // makes it renderable: a whitelisted image type, decodable base64,
            // and the configured byte cap. The file is untrusted input that ends
            // up in an <img src> on every panel page, so this is the same
            // guarantee the upload and the resolver enforce on the way in.
            $faviconData = $item['favicon_data'] ?? null;

            if ($faviconData !== null && ! is_string($faviconData)) {
                throw new InvalidArgumentException("[{$itemPath}.favicon_data] must be a string or null.");
            }

            if (filled($faviconData) && ! TopbarMenuItem::isStorableFaviconData($faviconData)) {
                throw new InvalidArgumentException(
                    "[{$itemPath}.favicon_data] must be a base64 data: URI of an image of at most "
                    .TopbarMenuItem::faviconMaxBytes().' bytes.',
                );
            }

            $this->validateRouteParameters($item['route_parameters'] ?? null, "{$itemPath}.route_parameters");
            $this->validateVisibility($item['visibility'] ?? null, "{$itemPath}.visibility");

            if (! is_bool($item['is_active'] ?? true)) {
                throw new InvalidArgumentException("[{$itemPath}.is_active] must be a boolean.");
            }

            if (! is_int($item['sort'] ?? 0)) {
                throw new InvalidArgumentException("[{$itemPath}.sort] must be an integer.");
            }

            $children = $item['children'] ?? [];

            if (! is_array($children)) {
                throw new InvalidArgumentException("[{$itemPath}.children] must be an array.");
            }

            if ($children !== [] && $depth >= self::MAX_DEPTH) {
                throw new InvalidArgumentException("[{$itemPath}.children] exceeds the maximum menu depth of ".self::MAX_DEPTH.' levels.');
            }

            $this->validateItems(array_values($children), "{$itemPath}.children", $depth + 1);
        }
    }

    /**
     * A menu link is only safe once rendered into an anchor href if it is a
     * plain http(s) URL; this blocks `javascript:`/`data:` and scheme-relative
     * or relative values, matching the resource form's `->url()` rule.
     */
    protected function isSafeHttpUrl(string $url): bool
    {
        if (! in_array(
            strtolower((string) parse_url($url, PHP_URL_SCHEME)),
            ['http', 'https'],
            true,
        )) {
            return false;
        }

        // The scheme alone is not enough: `http:foo` parses as scheme+path
        // with no host, so it would pass a scheme-only check while browsers
        // read the href differently than PHP does — and the
        // hide_on_current_domain flag could never match it. A real menu link
        // always names a host.
        return filled(parse_url($url, PHP_URL_HOST));
    }

    /**
     * Route parameters are spread into route() and cast to string when building
     * the URL, so every value must be a scalar or null. A nested array would
     * trigger "Array to string conversion" inside resolveUrl on every panel
     * page — the same fatal the import guards against.
     *
     * @throws InvalidArgumentException
     */
    protected function validateRouteParameters(mixed $value, string $path): void
    {
        if ($value === null) {
            return;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException("[{$path}] must be an object or null.");
        }

        foreach ($value as $key => $parameter) {
            if ($parameter !== null && ! is_scalar($parameter)) {
                throw new InvalidArgumentException("[{$path}.{$key}] must be a scalar or null.");
            }
        }
    }

    /**
     * The visibility array is consumed by TopbarMenuItem::isVisibleTo, which
     * passes `roles` straight to the user model's hasAnyRole(). Enforce the
     * shape the model expects (auth/guest/hide_on_current_domain booleans,
     * roles a list of strings) so
     * a hand-edited file can never throw a TypeError on every panel request.
     *
     * @throws InvalidArgumentException
     */
    protected function validateVisibility(mixed $value, string $path): void
    {
        if ($value === null) {
            return;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException("[{$path}] must be an object or null.");
        }

        foreach (['auth', 'guest', 'hide_on_current_domain'] as $flag) {
            if (isset($value[$flag]) && ! is_bool($value[$flag])) {
                throw new InvalidArgumentException("[{$path}.{$flag}] must be a boolean.");
            }
        }

        if (! isset($value['roles'])) {
            return;
        }

        if (! is_array($value['roles'])) {
            throw new InvalidArgumentException("[{$path}.roles] must be an array of strings.");
        }

        foreach ($value['roles'] as $role) {
            if (! is_string($role)) {
                throw new InvalidArgumentException("[{$path}.roles] must be an array of strings.");
            }
        }
    }

    /**
     * @param  array<int, mixed>  $items
     */
    protected function createItems(array $items, ?int $parentId): int
    {
        $created = 0;

        /** @var array<string, mixed> $item */
        foreach ($items as $item) {
            $record = TopbarMenuItem::query()->create([
                'parent_id' => $parentId,
                'label' => $item['label'],
                'type' => $item['type'] ?? TopbarMenuItem::TYPE_URL,
                'url' => $item['url'] ?? null,
                'route' => $item['route'] ?? null,
                'route_parameters' => $item['route_parameters'] ?? null,
                'target' => $item['target'] ?? TopbarMenuItem::TARGET_SELF,
                'icon' => $item['icon'] ?? null,
                'favicon_url' => $item['favicon_url'] ?? null,
                'favicon_data' => $item['favicon_data'] ?? null,
                'is_active' => $item['is_active'] ?? true,
                'sort' => $item['sort'] ?? 0,
                'visibility' => $item['visibility'] ?? null,
            ]);

            $created += 1 + $this->createItems(array_values($item['children'] ?? []), $record->id);
        }

        return $created;
    }
}
