# Filament Topbar Menu

[![Packagist Version](https://img.shields.io/packagist/v/vaslv/filament-topbar-menu)](https://packagist.org/packages/vaslv/filament-topbar-menu)
[![CI](https://github.com/vaslv/filament-topbar-menu/actions/workflows/tests.yml/badge.svg)](https://github.com/vaslv/filament-topbar-menu/actions/workflows/tests.yml)
[![Plumb score](https://plumbphp.dev/badges/vaslv/filament-topbar-menu/composite.svg)](https://plumbphp.dev/vaslv/filament-topbar-menu)

A [Filament 5](https://filamentphp.com) plugin that adds a configurable menu to the panel topbar, right after the logo. Use it to link to your other services (external URLs) or to pages of the current Laravel application (named routes) — with dropdowns, favicons, per-item visibility, and caching out of the box.

- 🔗 **External URLs and internal Laravel routes** (with route parameters)
- 📂 **Nested items** — a top-level item with children becomes a dropdown group: a pure toggle, like Filament's own top-navigation groups
- 🖼️ **Icons and favicons** — auto-resolve favicons for external links (never at render time); a favicon replaces the item's icon
- ⚡ **Cached** — no database query on page render; the cache is flushed automatically on changes
- 🛠️ **Full Filament resource** — create, edit, delete, drag-and-drop reordering, activate/deactivate
- 🌙 **Truly Filament-native look** — built from Filament's own topbar/dropdown components, so it's pixel-identical to the panel's `topNavigation()` menu (dark mode, active-item highlight, spacing) with zero custom CSS
- 🌍 **Translatable** — ships with 13 languages out of the box (see [Translations](#translations)); add your own
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

The menu is hidden below the `lg` (1024px) breakpoint, for two independent
reasons:

1. The default `TOPBAR_LOGO_AFTER` hook renders it inside Filament's
   `.fi-topbar-start` region, which Filament itself hides below `lg` — the same
   as its logo and sidebar controls.
2. The menu's own root element is `<ul class="fi-topbar-nav-groups">`, Filament's
   top-navigation list class, which is `hidden lg:flex` in Filament's own CSS.

Because of (2), **changing the render hook alone does not make the menu appear on
narrow viewports** — at `TOPBAR_START` or `TOPBAR_END` it renders outside
`.fi-topbar-start`, but its own `<ul>` is still `display: none` below 1024px.

To show it there, register the menu at a hook outside `.fi-topbar-start` and add
a CSS rule that undoes the hiding. A default install has no stylesheet of its
own, so inject one from the panel:

```php
use Filament\View\PanelsRenderHook;

$panel
    ->plugin(TopbarMenuPlugin::make()->renderHook(PanelsRenderHook::TOPBAR_END))
    ->renderHook(
        PanelsRenderHook::STYLES_AFTER,
        fn (): string => '<style>.fi-topbar-nav-groups { display: flex; }</style>',
    );
```

Two things to expect, both coming from Filament's own CSS rather than from this
package:

- The same class is used by Filament's native top navigation, so on a panel with
  `topNavigation()` the rule un-hides that menu below `lg` as well.
- Filament puts `flex-wrap` and the row gap inside its `lg` media query, so below
  that width the menu is a single non-wrapping row inside `.fi-topbar-ctn`, which
  clips horizontal overflow. Add `flex-wrap: wrap` to the rule (or keep the menu
  short) if your items do not fit.

Utility classes (`flex`, `items-center`, …) are not an option here: Filament's
precompiled theme contains only its own `.fi-*` rules, so utilities exist only in
panels with a custom Filament theme whose Tailwind build scans the published
views.

## Managing menu items

Menu items live in the `filament_topbar_menu_items` table and are managed through the included Filament resource:

- **Label**, **icon** (any Filament-supported icon, e.g. `heroicon-o-link`) and **favicon URL** — an item shows one marker, and a favicon URL always wins over the icon name
- **Link type** — external `URL` or internal `Laravel route` (with optional route parameters)
- **Target** — open in the same tab or a new tab
- **Parent item** — items with a parent are shown in the parent's dropdown. The
  menu renders exactly two levels. **Deleting an item deletes its children with
  it** (a database-level cascade, no extra confirmation). Giving a parent item a
  parent of its own keeps the rows but drops them off the menu: its children land
  on a third level, which is never rendered
- **Active** toggle and **sort** order (rows can also be reordered by drag & drop)

The table lists the items the way the topbar renders them: each top-level item at
its `sort` position with its children right underneath it, ties broken by id.
`sort` is a position among siblings rather than a rank across the whole table, so
the two only look the same while the menu is flat. Clicking a column header sorts
by that column as usual; dragging a row writes new `sort` values but never
changes an item's parent — use the **Parent item** field for that.
- **Visibility** — everyone, authenticated users only, or guests only

### View page

Each item has a read-only **View** page — the link it resolves to, the marker
the topbar renders, every visibility rule (audience, a `roles` restriction, "hide
on its own domain") and the timestamps. The items table gets a **View** row
action, the edit page a View button, and both creating and saving an item land on
that page instead of the edit form (where Filament leaves you in both cases
without it).

The page is the only place a `roles` restriction is visible in the panel: the
form deliberately does not manage that key, so an item restricted to roles by an
import or a direct write shows its roles here instead of quietly reading as
*Everyone*.

Turn it off to keep the plain list / create / edit resource:

```dotenv
FILAMENT_TOPBAR_MENU_VIEW_PAGE=false
```

or `'enable_view_page' => false` in the config. Off, no View action is rendered
anywhere and saving lands where Filament puts it by default.

The route is registered only while the option is on, so re-run
`php artisan route:cache` after flipping it — and after the update that first
brings the page in — if your panel caches routes. Until you do, the package
notices that the cached route collection has no View route and reports the page
as unavailable, so a stale cache costs you the View button rather than a
`RouteNotFoundException`. A panel that configured its own
`resourceCreatePageRedirect()` / `resourceEditPageRedirect()` keeps its choice.

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

The resource's **Route name** picker lists your application's named routes, minus
every name containing `livewire` — Livewire's own internal endpoints are never
menu targets. The match is a substring, so a route of yours whose name happens to
contain that word is hidden from the picker too; set `route` directly (as below)
for such an item.

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
navigate: while a parent has at least one child *visible to the current user*, its
own `url`/`route` is ignored, so to make a landing page reachable add it as an
explicit child item.

Visibility is evaluated per request, so this can flip: if every child is inactive
or hidden from the current user, the parent stops being a dropdown and renders as
an ordinary link using its own `url`/`route` — and disappears entirely when it has
none.

Two levels is the whole depth: a child of a child is never rendered. Moving a
group under another group therefore takes its children off the menu — the rows
stay in the table, they just stop being rendered — while deleting a group really
does delete its children (`parent_id` cascades on delete).

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
['auth' => true]                    // authenticated users only
['guest' => true]                   // guests only
['roles' => ['admin', 'ops']]       // users with any of these roles
                                    // (requires a hasAnyRole() method on your user model,
                                    //  e.g. from spatie/laravel-permission)
['hide_on_current_domain' => true]  // hide the item on the service its URL points at
                                    // (URL items only — see "Hiding each service's own link")
```

Visibility is evaluated per request — it is never baked into the cache.

The form manages the audience (`auth`/`guest`) and the domain rule; `roles` is
set through an import or directly on the record and is preserved by every save.
The View page shows all of them, so a role restriction stays visible in the panel
even though no form field owns it.

## Export & import

The list page has **Export** and **Import** header actions for moving the whole
menu between installs (e.g. staging → production) or keeping it as a backup.

- **Export** downloads a JSON file with every item and all of its settings —
  hierarchy, URLs/routes with parameters, targets, icons, favicons, sort order,
  active state, and visibility rules (including `roles`).
- **Import** accepts such a file and recreates the items. By default they are
  **added to** the existing menu, each keeping the `sort` value it had in the
  file — so they interleave with the current items instead of landing at the
  end; re-sort afterwards, or enable *"Replace the current menu"* in the import
  dialog to wipe the menu first. The whole file is validated before anything is
  written, and the import runs in a single transaction — a broken file never
  deletes or half-imports anything.

Because the file is untrusted input, import re-applies the same guards as the
form: URLs and favicon URLs must be plain `http(s)` links (a `javascript:` or
`data:` link is rejected, never rendered into the topbar), route parameters must
be scalar, visibility rules must be well-shaped, and the tree may be at most two
levels deep. Export is gated behind the resource's *viewAny* permission and import
behind *create*; the replace option only appears for users the *deleteAny* policy
allows.

The file contains no database ids (hierarchy is expressed by nesting), so an
export from one application imports cleanly into another. Unknown keys are
ignored, so a file from a newer plugin version still imports as long as its
export format version is unchanged. Values are copied verbatim, though, so an
absolute `url` still points at the source environment's host and a `route` name
that does not exist in the target application resolves to nothing (the item is
skipped at render time). A relative `url` such as `/horizon` — what the demo
seeder writes — is not portable at all: the import requires a full `http(s)` URL
with a host and rejects the whole file if one is missing.

## Favicons

For external links the plugin can resolve the site's favicon and store it in the `favicon_url` column, so **no remote HTTP request ever happens while the menu renders**.

Resolution strategy (`FaviconResolver`), most specific first:

1. Parse the `<link rel="icon">` tags of the page the item links to, so a
   sub-page that declares its own icon (a Horizon or Telescope dashboard, a docs
   section) keeps it instead of the site-wide one.
2. Try `favicon.ico` in the directory the link points at — `/admin/favicon.ico`
   for an item linking to `/admin`, `/docs/favicon.ico` for one linking to
   `/docs/page.html`.
3. Fall back to the conventional `https://host/favicon.ico`.

Step 2 is the one a browser does not do, and it exists for single-page apps: a
Directus or Nova panel mounted on a sub-path inserts its `<link rel="icon">` from
JavaScript, so the HTML this resolver reads declares nothing, while the host root
of such a deployment is often an API that answers `404`. The icon still sits in
the app's own directory. When the link already points at the host root, steps 2
and 3 are the same URL and only one request is made.

At most five declared icons are probed per page, so a page cannot dictate an
unbounded number of outbound requests. Relative hrefs are resolved against the
document's `<base href>` when it declares one (again, the SPA case: a shell with
`<base href="/admin/">` means `href="favicon.ico"` is `/admin/favicon.ico`, not
the site-wide icon) and otherwise against the URL that actually answered, after
redirects.

Resolution runs **anonymously**, with no session or cookies. A page behind auth
answers `401`/`403`, so its own icon is unreachable; the root `favicon.ico` is
stored instead when that one answers `2xx` with a non-empty body whose
`Content-Type` is absent or mentions `image`, `icon` or `octet-stream` (an HTML
error page is rejected), and otherwise nothing is stored at all — paste the icon
URL into the Favicon URL field by hand for those items.

The resolver also treats every linked site as untrusted and **refuses to fetch
private, loopback, link-local and reserved addresses** (including cloud metadata
endpoints), re-checking every redirect hop and every numeric spelling of an
address. A menu item pointing at an internal-only host therefore never gets an
auto-resolved favicon: fill the field by hand, or serve the icon from a publicly
reachable URL.

Ways to resolve favicons:

- The **"Fetch favicon" suffix button** on the Favicon URL field in the create/edit form.
- The **"Fetch favicons" bulk action** in the items table, for the rows you select.
- The artisan command:

```bash
# Fill favicons for items that don't have one yet:
# (items that already carry one are left alone — use --force to re-resolve them)
php artisan filament-topbar-menu:refresh-favicons

# Re-resolve all favicons (including existing ones):
php artisan filament-topbar-menu:refresh-favicons --force

# Only specific items (still skips the ones that already have a favicon,
# so combine with --force to re-resolve them):
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
    'connection' => env('FILAMENT_TOPBAR_MENU_DB_CONNECTION') ?: null,
    'cache_key' => 'filament-topbar-menu.items',
    'cache_ttl' => 3600,
    'enable_favicons' => true,
    'favicon_request_timeout' => 5,
    // The resource's read-only View page: a `{record}` route, a View row
    // action, and create/edit saves landing on that page (see "View page"
    // above). Set FILAMENT_TOPBAR_MENU_VIEW_PAGE=false for a plain
    // list/create/edit resource.
    'enable_view_page' => env('FILAMENT_TOPBAR_MENU_VIEW_PAGE', true),
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
connection. On a fresh install plain `php artisan migrate` is enough — the
package migration reads the `connection` config itself, so only one app needs
it pending for the table to be created. To create the table explicitly,
without touching any migration repository, use the dedicated command:

```bash
php artisan filament-topbar-menu:create-table
```

It creates the menu table on the configured connection when it is missing and
does nothing otherwise, so it is safe to keep in every app's deploy script.
Do **not** use `php artisan migrate --database=menu`: that relocates the
migration repository to the `menu` connection and replays *every* pending
application migration against the shared database.

Each app keeps its own cache, which is flushed locally whenever that app edits
an item — flush the others (`TopbarMenu::flushCache()`) or wait out `cache_ttl`
for cross-app edits to appear.

### Switching an existing install

On an existing install the package migration is already recorded in the app's
own migrations table, so after setting `FILAMENT_TOPBAR_MENU_DB_CONNECTION`
plain `php artisan migrate` will *skip* it and the menu table never appears on
the new connection. The whole switch is handled at deploy time: set the env
variable and add one command to the deploy script, after `php artisan migrate`:

```bash
php artisan filament-topbar-menu:create-table --copy-from-default
```

It creates the table on the new connection, and `--copy-from-default` then
moves the data: when the dedicated menu is still **empty** and this app's
default connection still has a menu table, the local menu is copied over
(validated like an import, in a transaction, cache flushed). A populated
dedicated menu is never touched — the copy refuses to merge a second app's
local menu into an already-seeded shared menu. Both steps are idempotent, so
the command can stay in the deploy script permanently.

Until the command has run, a missing menu table does not take the app down:
the topbar renders an empty menu and reports the underlying error to the
app's exception handler. The failure is never cached, so the menu reappears
on the first request after the table exists.

The switch can also be finished from the panel, without console access: when
the table is missing, the menu items page renders an explanation instead of
failing, and users allowed to create menu items get a **Create menu table**
header action that performs the same create-and-copy as the deploy command. It
is the only action offered in that state — Export, Import and Create are hidden
while the table is missing, and come back once it exists.

The old table stays behind on the previous connection — drop it manually once
you have verified the move. For moves the copy flag does not cover (e.g.
seeding an already-populated shared menu), use the Export/Import actions on
the menu items page.

### Hiding each service's own link

A fleet-shared menu usually lists every service of the fleet — including the
one currently being viewed. Enable **"Hide on its own domain"** on a menu item
(or set `visibility.hide_on_current_domain` to `true`) and the item disappears
on the service whose host matches the item's URL, while every other service
keeps showing it. Add a service to the menu once and each app automatically
renders "everyone but me" — no per-app menus, no self-links.

The rule applies to **URL items only**. Laravel route items always render;
the key is ignored on them (a route by definition lives on the current app,
hiding it would be a different feature). The admin table marks items the flag
hides on the current host with a crossed-eye badge — the item is hidden here,
not deleted, and stays visible on the fleet's other services.

Host matching compares service identity, not URLs:

- Case-insensitive; a trailing dot and a `www.` prefix are ignored on both
  sides (`www.a.example.com` ≡ `a.example.com`).
- Subdomains are significant: `a.example.com` ≠ `b.example.com`.
- The scheme is not compared (`http://` ≡ `https://`), and the default ports
  80/443 equal an absent port. A **non-standard** port is part of the
  identity, so a dev fleet on `localhost:8000` / `localhost:8001` works.
- Punycode and unicode spellings of one domain match (via `ext-intl`, when
  installed).

A seeder example for a fleet of three services:

```php
foreach ([
    'Accounts'  => 'https://accounts.example.com',
    'Billing'   => 'https://billing.example.com',
    'Analytics' => 'https://analytics.example.com',
] as $label => $url) {
    TopbarMenuItem::create([
        'label' => $label,
        'type' => TopbarMenuItem::TYPE_URL,
        'url' => $url,
        'visibility' => ['hide_on_current_domain' => true],
    ]);
}
```

Older package versions on other apps in the fleet simply ignore the unknown
key, so the flag can be rolled out without synchronized upgrades.

#### Behind a reverse proxy

The current host comes from Laravel's `request()->getHost()` (and, for
non-standard ports, `request()->getPort()`). Behind a reverse proxy or load
balancer (nginx, Traefik, …) that is only correct when trusted proxies are
configured and the proxy forwards `X-Forwarded-Host` — plus `X-Forwarded-Port`
(or `X-Forwarded-Proto`) when the fleet runs on non-standard ports — otherwise
Laravel sees the internal container host/port and the flag silently never
matches.
This is the consuming app's configuration (see the
[Laravel docs on trusted proxies](https://laravel.com/docs/requests#configuring-trusted-proxies)),
not the package's.

Note that a spoofed `Host` header only changes which menu items appear in the
response served to the spoofing client itself — the flag is a display rule,
not a security boundary, and grants no access either way.

### Operating notes for a shared menu

- **Rollback is guarded.** When `connection` is set, the package migration's
  `down()` is a no-op: a routine `migrate:rollback` in one app must not drop
  the menu of the whole fleet. Dropping a shared menu table is a deliberate
  manual step.
- **Keep package versions in sync.** The create-only migration never alters an
  existing table, so future schema changes ship as separate migrations. Apps
  pinned to different package versions writing to one shared table are
  unsupported — upgrade them together.
- **Restrict who edits.** Every app with the resource enabled can edit the
  shared menu. In apps that should only *render* it, disable the management UI
  with `TopbarMenuPlugin::make()->resource(false)`; read-only database
  credentials for the menu connection make a good second layer.
- **Test suites.** Laravel's `RefreshDatabase` only wraps the app's default
  connection in a transaction. If `FILAMENT_TOPBAR_MENU_DB_CONNECTION` leaks
  into the test environment, feature tests write straight into the real shared
  menu — and an import with `replace` wipes it. Unset the variable in
  `phpunit.xml` or point it at a dedicated test connection.

## Caching

The menu tree is cached under `cache_key` for `cache_ttl` seconds, so rendering the topbar performs **zero database queries**. The cache is flushed automatically whenever an item is created, updated, deleted, or reordered, and by an import.

The **"Flush menu cache"** header action on the items page (next to Export and
Import) does it from the panel. It is there for the writes outside this
package's reach — a row written by a sibling app on a shared menu database, a
seeder, hand-written SQL, a cache store restored from a backup. Pressing it
costs nothing: the next render rebuilds the snapshot from the database.

From code:

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

The entire interface (resource form, table, infolist, actions, notifications and
the artisan command output) is translatable. The package ships with:

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
(Pint + PHPStan) on every pull request and on pushes to `main`.

## License

The MIT License (MIT). See [LICENSE](LICENSE).
