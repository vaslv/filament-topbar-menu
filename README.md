# Filament Topbar Menu

A [Filament 5](https://filamentphp.com) plugin that adds a configurable menu to the panel topbar, right after the logo. Use it to link to your other services (external URLs) or to pages of the current Laravel application (named routes) — with dropdowns, favicons, per-item visibility, and caching out of the box.

- 🔗 **External URLs and internal Laravel routes** (with route parameters)
- 📂 **Nested items** — a top-level item can stay a link *and* open a dropdown with its children
- 🖼️ **Icons and favicons** — auto-resolve favicons for external links (never at render time)
- ⚡ **Cached** — no database query on page render; the cache is flushed automatically on changes
- 🛠️ **Full Filament resource** — create, edit, delete, drag-and-drop reordering, activate/deactivate
- 🌙 **Truly Filament-native look** — built from Filament's own topbar/dropdown components, so it's pixel-identical to the panel's `topNavigation()` menu (dark mode, active-item highlight, spacing) with zero custom CSS
- 🌍 **Translatable** — ships with English, Russian, German, Spanish and French; add your own
- 🪝 Rendered through the official `PanelsRenderHook::TOPBAR_LOGO_AFTER` render hook — no layout overrides

## Requirements

- PHP 8.2+
- Filament ^5.0

## Installation

Install the package via Composer:

```bash
composer require vaslv/filament-topbar-menu
```

The package migration is loaded automatically. Run it:

```bash
php artisan migrate
```

The menu is rendered with Filament's own topbar and dropdown components, so it
inherits your theme automatically — there are no assets to build or publish.

Optionally publish the migration and config instead of using the bundled ones:

```bash
php artisan vendor:publish --tag=filament-topbar-menu-migrations
php artisan vendor:publish --tag=filament-topbar-menu-config
```

## Registering the plugin

Add the plugin to your panel in your `PanelProvider` (e.g. `app/Providers/Filament/AdminPanelProvider.php`):

```php
use Vaslv\FilamentTopbarMenu\TopbarMenuPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(TopbarMenuPlugin::make());
}
```

That's it — the menu renders in the topbar right after the logo, and a **Topbar Menu** resource appears in the panel navigation for managing the items.

### Plugin options

```php
TopbarMenuPlugin::make()
    // Hide the management resource on this panel (e.g. a public panel
    // that should only display the menu):
    ->resource(false)

    // Put the resource into a navigation group / position:
    ->resourceNavigationGroup('Settings')
    ->resourceNavigationSort(10)

    // Render the menu at a different panel render hook:
    ->renderHook(\Filament\View\PanelsRenderHook::TOPBAR_START)
```

### Responsive behavior

The default `TOPBAR_LOGO_AFTER` hook renders the menu inside Filament's
`.fi-topbar-start` region, which Filament itself hides below the `lg` (1024px)
breakpoint — the same as its logo/sidebar controls. So with the default hook the
menu is visible on desktop widths and hidden on smaller screens, consistent with
the rest of the Filament topbar.

If you need the menu on narrow viewports, register it at a hook that stays visible
there, e.g. `->renderHook(\Filament\View\PanelsRenderHook::TOPBAR_END)`.

## Managing menu items

Menu items live in the `filament_topbar_menu_items` table and are managed through the included Filament resource:

- **Label**, **icon** (any Filament-supported icon, e.g. `heroicon-o-link`) and **favicon URL**
- **Link type** — external `URL` or internal `Laravel route` (with optional route parameters)
- **Target** — open in the same tab or a new tab
- **Parent item** — items with a parent are shown in the parent's dropdown
- **Active** toggle and **sort** order (rows can also be reordered by drag & drop)
- **Visibility** — everyone, authenticated users only, or guests only

### Demo data

To try the menu out quickly, seed a demo tree that exercises every feature —
external links, an internal route link, a dropdown group, visibility rules and
an inactive item:

```bash
php artisan db:seed --class="Vaslv\FilamentTopbarMenu\Database\Seeders\TopbarMenuSeeder"
```

The seeder is idempotent: items are matched by parent + label, so re-running it
updates the demo items in place instead of duplicating them.

The role-restricted example group ("Admin Tools") is only seeded when your user
model can evaluate roles — i.e. it has a `hasAnyRole()` method, e.g. from
spatie/laravel-permission. Without roles support the package hides
role-restricted items from everyone (it fails closed), so the seeder skips the
example instead of seeding an item nobody can see — and removes it again on
re-run if roles support has gone away.

### Example: external links

```php
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

TopbarMenuItem::create([
    'label' => 'Grafana',
    'type' => 'url',
    'url' => 'https://grafana.example.com',
    'target' => '_blank',
]);
```

The per-item **target** is authoritative: "Same tab" (`_self`) always opens in the same tab and "New tab" (`_blank`) always opens in a new one. The `open_external_links_in_new_tab` config only decides the *default* value of the target field when you create a new item in the resource (default: new tab) — it never overrides an explicit choice.

### Example: internal route links

```php
TopbarMenuItem::create([
    'label' => 'Orders',
    'type' => 'route',
    'route' => 'filament.admin.resources.orders.index',
]);

TopbarMenuItem::create([
    'label' => 'Monthly report',
    'type' => 'route',
    'route' => 'reports.show',
    'route_parameters' => ['report' => 'monthly'],
]);
```

If a named route no longer exists, the item is skipped instead of breaking the page.

### Example: a dropdown menu

A top-level item with children renders as a Filament dropdown group — exactly like
the panel's native top navigation. The group label is a pure dropdown toggle and
the children are its links. Like Filament's own groups, the toggle itself does not
navigate: if a parent has children, its own `url`/`route` is ignored, so to make a
landing page reachable add it as an explicit child item.

```php
$services = TopbarMenuItem::create([
    'label' => 'Services',
    'type' => 'url', // a group with children is a toggle; its own url is not used
]);

TopbarMenuItem::create([
    'label' => 'Analytics',
    'type' => 'url',
    'url' => 'https://analytics.example.com',
    'parent_id' => $services->id,
]);

TopbarMenuItem::create([
    'label' => 'Admin dashboard',
    'type' => 'route',
    'route' => 'filament.admin.pages.dashboard',
    'parent_id' => $services->id,
]);
```

### Visibility rules

The `visibility` JSON column supports:

```php
['auth' => true]              // authenticated users only
['guest' => true]             // guests only
['roles' => ['admin', 'ops']] // users with any of these roles
                              // (requires a hasAnyRole() method on your user model,
                              //  e.g. from spatie/laravel-permission)
```

Visibility is evaluated per request — it is never baked into the cache.

## Export & import

The list page has **Export** and **Import** header actions for moving the whole
menu between installs (e.g. staging → production) or keeping it as a backup.

- **Export** downloads a JSON file with every item and all of its settings —
  hierarchy, URLs/routes with parameters, targets, icons, favicons, sort order,
  active state, and visibility rules (including `roles`).
- **Import** accepts such a file and recreates the items. By default the items
  are **appended** to the existing menu; enable *"Replace the current menu"* in
  the import dialog to wipe the menu first. The whole file is validated before
  anything is written, and the import runs in a single transaction — a broken
  file never deletes or half-imports anything.

Because the file is untrusted input, import re-applies the same guards as the
form: URLs and favicon URLs must be plain `http(s)` links (a `javascript:` or
`data:` link is rejected, never rendered into the topbar), route parameters must
be scalar, visibility rules must be well-shaped, and the tree may be at most two
levels deep. Export is gated behind the resource's *view* permission and import
behind *create*; the replace option only appears for users the *delete* policy
allows.

The file contains no database ids (hierarchy is expressed by nesting), so an
export from one application imports cleanly into another. Unknown keys are
ignored, so a file from a newer plugin version still imports as long as its
export format version is unchanged.

## Favicons

For external links the plugin can resolve the site's favicon and store it in the `favicon_url` column, so **no remote HTTP request ever happens while the menu renders**.

Resolution strategy (`FaviconResolver`):

1. Try the conventional `https://host/favicon.ico`.
2. Fall back to parsing `<link rel="icon">` tags from the page HTML.

Ways to resolve favicons:

- The **"Fetch favicon" suffix button** on the Favicon URL field in the create/edit form.
- The **"Fetch favicon" row action** and the **bulk action** in the items table.
- The artisan command:

```bash
# Fill favicons for items that don't have one yet:
php artisan filament-topbar-menu:refresh-favicons

# Re-resolve all favicons (including existing ones):
php artisan filament-topbar-menu:refresh-favicons --force

# Only specific items:
php artisan filament-topbar-menu:refresh-favicons --id=1 --id=2
```

Disable the whole feature with `'enable_favicons' => false` in the config — the actions and the command become no-ops.

## Configuration

```php
// config/filament-topbar-menu.php

return [
    'table_name' => 'filament_topbar_menu_items',
    // Database connection for the menu table. Unset it resolves to null and
    // follows the app's default connection (the one set by DB_CONNECTION), so
    // no separate menu database is assumed and existing installs keep working
    // unchanged after an update. Set the optional FILAMENT_TOPBAR_MENU_DB_CONNECTION
    // env variable to a dedicated connection to keep the menu in a separate,
    // possibly shared, database (see "Shared menu across projects" below).
    'connection' => env('FILAMENT_TOPBAR_MENU_DB_CONNECTION'),
    'cache_key' => 'filament-topbar-menu.items',
    'cache_ttl' => 3600,
    'enable_favicons' => true,
    'favicon_request_timeout' => 5,
    // Default value of the target field for new items. The per-item choice
    // ("Same tab" / "New tab") always wins at render time; this is only a default.
    'open_external_links_in_new_tab' => true,
];
```

## Shared menu across projects

By default no separate menu database is assumed — the menu lives on the app's
default connection. To move it onto a dedicated connection (defined in
`config/database.php`), set the `FILAMENT_TOPBAR_MENU_DB_CONNECTION` env variable.
Point several apps at the **same** menu database and they all render one
centrally managed menu:

```php
// config/database.php
'connections' => [
    'menu' => [
        'driver' => 'mysql',
        // ...credentials for the shared menu database...
    ],
],

// .env (in every app that shares the menu)
FILAMENT_TOPBAR_MENU_DB_CONNECTION=menu
```

The migration, every model query and the import transaction all follow this
connection. Run the migration once against the shared database (for example
`php artisan migrate --database=menu`, or simply `php artisan migrate` since the
package migration reads the `connection` config itself). Each app keeps its own
cache, which is flushed locally whenever that app edits an item — flush the
others (`TopbarMenu::flushCache()`) or wait out `cache_ttl` for cross-app edits
to appear.

## Caching

The menu tree is cached under `cache_key` for `cache_ttl` seconds, so rendering the topbar performs **zero database queries**. The cache is flushed automatically whenever an item is created, updated, deleted, or reordered. To flush it manually:

```php
use Vaslv\FilamentTopbarMenu\Facades\TopbarMenu;

TopbarMenu::flushCache();
```

## Customizing the views

The Blade templates work out of the box; publish them only if you want to change the markup:

```bash
php artisan vendor:publish --tag=filament-topbar-menu-views
```

Views are published to `resources/views/vendor/filament-topbar-menu`.

## Translations

The entire interface (resource form, table, actions, notifications, the artisan
command output and the menu's ARIA labels) is translatable. The package ships
with:

- English (`en`)
- Arabic (`ar`)
- German (`de`)
- Spanish (`es`)
- Persian (`fa`)
- French (`fr`)
- Indonesian (`id`)
- Italian (`it`)
- Dutch (`nl`)
- Brazilian Portuguese (`pt_BR`)
- Russian (`ru`)
- Turkish (`tr`)
- Chinese, Simplified (`zh_CN`)

The language follows the application locale (`app()->setLocale(...)`), so nothing
needs to be configured — set your app locale and the menu is translated.

To add another language or tweak the wording, publish the translation files:

```bash
php artisan vendor:publish --tag=filament-topbar-menu-translations
```

They are published to `lang/vendor/filament-topbar-menu/{locale}/filament-topbar-menu.php`.
To add a new language, copy the `en` file to a new locale folder (e.g. `pl/`) and
translate the values.

## Testing & code quality

```bash
composer test        # PHPUnit
composer lint         # apply Laravel Pint code style
composer lint:test    # check code style without changing files
composer analyse      # PHPStan (level 6, via Larastan)
composer check        # lint:test + analyse + test (what CI runs)
```

CI runs the full test matrix (PHP 8.2 / 8.3 / 8.4) plus a code-quality job
(Pint + PHPStan) on every push and pull request.

## License

The MIT License (MIT). See [LICENSE](LICENSE).
