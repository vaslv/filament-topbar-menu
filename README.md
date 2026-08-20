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
- 🌙 **Truly Filament-native look** — built from Filament's own topbar/dropdown and sidebar components, so it's pixel-identical to the panel's `topNavigation()` menu (dark mode, active-item highlight, spacing); the only CSS the package ships is one media query and the two rules that place the mobile button
- 📱 **Responsive** — rendered twice: dropdowns in the topbar on desktop, and on mobile a slide-in panel of its own — the panel's own sidebar, down to the class names — opened by its own button next to the ☰, so the menu is never buried below the panel's navigation (an in-sidebar variant is one method call away)
- 🌍 **Translatable** — ships with 13 languages out of the box (see [Translations](#translations)); add your own
- 🪝 Rendered through the official `PanelsRenderHook::TOPBAR_LOGO_AFTER` and `TOPBAR_START` render hooks — no layout overrides

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

    // Drop the mobile copy of the menu (see "Responsive behavior"):
    ->mobile(false)

    // Put the mobile copy in the panel's own sidebar, below its navigation,
    // instead of in a slide-in panel of its own:
    ->mobileSidebar()

    // Render the mobile copy at a different panel render hook:
    ->mobileRenderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_START)
```

### Responsive behavior

The menu renders **twice** — once for desktop, once for mobile — and each copy is
hidden where the other one shows. That is not a workaround: it is how Filament
renders its own `topNavigation()` menu too, because below `lg` it hides the whole
`.fi-topbar-start` container the desktop copy sits in.

| Viewport | Render hook | Markup | Groups |
| --- | --- | --- | --- |
| ≥ 1024px (`lg`) | `TOPBAR_LOGO_AFTER` | `<ul class="fi-topbar-nav-groups">` | dropdowns |
| < 1024px | `TOPBAR_START` | Filament's `fi-sidebar`, behind its own topbar button | collapsible lists |

On mobile the menu becomes **a second sidebar with a button of its own**, sitting
right next to the ☰ that opens the panel's: same slide-in panel, same width, same
transition, same offset below the topbar, same dark mode — because it is built
from Filament's own `fi-sidebar` classes rather than a layout of ours. Two
buttons, two menus, and you pick which one to open; only one is ever open at a
time. The button turns primary when the page you are on is in this menu, and
turns into an ✕ while the menu is open. Tapping a link or the backdrop closes it,
as does <kbd>Esc</kbd>.

The alternative is `->mobileSidebar()`, which appends the menu to the panel's own
navigation inside its sidebar instead — one list, no second button. It reads well
on a panel with a short navigation; on a long one the menu ends up below every
navigation item and has to be scrolled to, which is why it is not the default.
Both variants render the same rows, so what you see in one you see in the other.

Three details worth knowing:

- **The CSS the package ships is one media query plus two rules**, and each one
  is there because Filament's precompiled `theme.css` has no equivalent: it ships
  only the utilities Filament itself uses, and each of its `lg:fi-hidden` rules is
  scoped to a specific parent, so nothing there hides an arbitrary element on
  desktop. The other two rules place the button — Filament offers no render hook
  between its sidebar buttons and the logo, so the copy renders at `TOPBAR_START`
  and pulls those buttons one flex slot forward to sit to their right — and put
  the backdrop one layer below the topbar, so it never covers the ✕ that closes
  it. All of it is inline in the view: no asset to build, nothing to re-run on an
  update, and publishing the views (`--tag=filament-topbar-menu-views`) is enough
  to change any of it.
- **The panel is teleported to the body.** A `position: fixed` panel rendered
  inside the topbar would be trapped in its stacking context and clipped by its
  `overflow-x: clip`; in the body it is also a child of the element carrying
  `fi-body-has-topbar`, which is what offsets a sidebar below the topbar rather
  than over it.
- **A panel with `navigation(false)` has no sidebar at all**, so `->mobileSidebar()`
  has nothing to render into there. The default slide-in panel is unaffected — it
  brings its own.

Turn the mobile copy off with `->mobile(false)`. The case for it: a panel that
moved the desktop menu to a hook of its own outside `.fi-topbar-start` (e.g.
`TOPBAR_END`) **and** un-hid `.fi-topbar-nav-groups` with a CSS rule of its own
would otherwise show both copies at once below `lg`.

## Managing menu items

Menu items live in the `filament_topbar_menu_items` table and are managed through the included Filament resource:

- **Label**, **icon** (any Filament-supported icon, e.g. `heroicon-o-link`), **favicon URL** and **embedded favicon** — an item shows one marker: an embedded favicon wins over a favicon URL, which wins over the icon name
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
form: the `url` and `favicon_url` fields must be plain `http(s)` links (a
`javascript:` or `data:` link is rejected there, never rendered into the topbar),
an embedded `favicon_data` must be a base64 `data:` URI of a whitelisted image
type within `favicon_max_bytes`, route parameters must be scalar, visibility
rules must be well-shaped, and the tree may be at most two levels deep. Export is gated behind the resource's *viewAny* permission and import
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

For external links the plugin can resolve the site's favicon and store it — as a link in the `favicon_url` column, or as the image itself in `favicon_data` — so **no remote HTTP request ever happens while the menu renders**.

Resolution strategy (`FaviconResolver`), most specific first:

1. Parse the `<link rel="icon">` tags of the page the item links to, so a
   sub-page that declares its own icon (a Horizon or Telescope dashboard, a docs
   section) keeps it instead of the site-wide one. Quoted and unquoted
   attributes are both read, so a minified page is not skipped. Candidates are
   ranked by `rel` before document order — `icon` / `shortcut icon` first, then
   `apple-touch-icon`, then the rest — because a page commonly declares its
   180×180 home-screen tile first, and that is not the icon a topbar renders at
   ~16px.
2. Try `favicon.ico`, `favicon.svg` and `favicon.png` in the directory the link
   points at — `/admin/favicon.ico` for an item linking to `/admin`,
   `/docs/favicon.svg` for one linking to `/docs/page.html`.
3. Fall back to the same three names at the host root, `https://host/favicon.ico`
   first.

Step 2 is the one a browser does not do, and it exists for single-page apps: a
Directus or Nova panel mounted on a sub-path inserts its `<link rel="icon">` from
JavaScript, so the HTML this resolver reads declares nothing, while the host root
of such a deployment is often an API that answers `404`. The icon still sits in
the app's own directory. When the link already points at the host root, steps 2
and 3 are the same URLs and each name is requested once.

The non-`.ico` names matter for the same reason: a site that ships only
`favicon.svg` declares it in markup, and a page this resolver cannot read leaves
guessing as the only route to it. `apple-touch-icon.png` is deliberately not
guessed at — a site that has one virtually always declares it, where step 1
already finds it.

At most five declared icons are probed per page, so a page cannot dictate an
unbounded number of outbound requests. Relative hrefs are resolved against the
document's `<base href>` when it declares one (again, the SPA case: a shell with
`<base href="/admin/">` means `href="favicon.ico"` is `/admin/favicon.ico`, not
the site-wide icon) and otherwise against the URL that actually answered, after
redirects.

Resolution runs **anonymously**, with no session or cookies. A page behind auth
answers `401`/`403`, so its own icon is unreachable; a conventional location is
stored instead when one of them answers `2xx` with a non-empty body whose
`Content-Type` is absent or mentions `image`, `icon` or `octet-stream` (an HTML
error page is rejected), and otherwise nothing is stored at all — paste the icon
URL into the Favicon URL field by hand for those items. When the *whole host*
sits behind auth (an HTTP Basic realm in front of everything, say), even the icon
file itself answers `401` and no amount of searching can reach it: save the icon
from your own browser and upload it to the item instead — see below.

The resolver also treats every linked site as untrusted and **refuses to fetch
private, loopback, link-local and reserved addresses** (including cloud metadata
endpoints), re-checking every redirect hop and every numeric spelling of an
address — including the IPv4 addresses that hide inside an IPv6 one
(`::ffff:127.0.0.1`, `::127.0.0.1`, the NAT64 `64:ff9b::7f00:1`). A menu item
pointing at an internal-only host therefore never gets an auto-resolved
favicon: fill the field by hand, or serve the icon from a publicly reachable
URL.

### Link or image: two ways to store a favicon

A favicon URL is a **link**. Nothing fetches it at render time — but the
*visitor's browser* does, once per item, on every panel page. That is fine for a
public site and useless for anything else: a service behind a login answers
`401` to a visitor who is not signed into it, an internal-only host does not
answer them at all, and either way the menu shows a broken image. It also means
every panel user quietly requests every host in your menu.

The alternative is to store the **image itself** — bytes and all — in the
`favicon_data` column, as a `data:` URI:

- Nothing is fetched at render time by anyone, so an icon that only the *server*
  can reach (or that only *you* can reach, if you upload it) still shows for
  every user.
- No file storage is involved. The icon lives in the menu row, which means it
  survives a redeploy on a container with no persistent disk, travels through
  export/import, and is carried by the cached snapshot like every other field.
- An embedded favicon **overrides** `favicon_url` when both are set.

The cost is size: the icon is base64, so it inflates by a third, and the cached
menu snapshot carries it. `favicon_max_bytes` (default 64 KB) caps the decoded
image; raise or lower it in the config. Panels with a strict CSP need
`img-src data:`.

Only whitelisted image types are ever stored or rendered — PNG, JPEG, GIF, WebP,
AVIF, BMP, ICO and SVG — and the type is taken from the **bytes**, not from the
`Content-Type` a remote host claims or a file name an upload carries. (SVG is on
that list because an SVG referenced by `<img>` is a passive image: scripts in it
do not run and it cannot reach the page embedding it.) The check runs when the
value is written *and* again when it is rendered, so a row written by a sibling
app on a shared menu database, a seeder or hand-written SQL cannot put something
unrenderable into the topbar.

### Ways to resolve favicons

Storing a **link**:

- The **"Fetch favicon" suffix button** on the Favicon URL field in the create/edit form.
- The **"Fetch favicons" bulk action** in the items table, for the rows you select.

Storing the **image**:

- The **"Upload an icon" field** in the create/edit form — for a site the server
  cannot reach either, which is the only route to a favicon behind HTTP Basic
  auth.
- The **"Fetch and embed" button** on that field: same search as above, but it
  downloads what it finds and stores the bytes.

There is deliberately **no bulk action for embedding**. An embedded icon may be
one an admin uploaded by hand, nothing can re-fetch it, and a single click that
overwrites a selection of them is a door that only opens one way. It is also the
expensive direction — a page fetch plus up to eleven candidate requests per item,
synchronously, inside the web request. Use the command below for the bulk case:
it skips items that already carry one unless `--force` says otherwise.

The bulk **"Fetch favicons"** action stores links only. When an item it resolves
also carries an embedded icon, the notification says so — the link is stored, but
the embedded copy is what the topbar keeps showing.

The artisan command does either:

```bash
# Fill favicons for items that don't have one yet:
# (items that already carry one are left alone — use --force to re-resolve them)
php artisan filament-topbar-menu:refresh-favicons

# Re-resolve all favicons (including existing ones):
php artisan filament-topbar-menu:refresh-favicons --force

# Only specific items (still skips the ones that already have a favicon,
# so combine with --force to re-resolve them):
php artisan filament-topbar-menu:refresh-favicons --id=1 --id=2

# Store the images themselves instead of links:
php artisan filament-topbar-menu:refresh-favicons --embed
```

`--embed` fills `favicon_data` and reads the same column to decide which items
still need one, so the two modes never overwrite each other's work.

Disable the whole feature with `'enable_favicons' => false` in the config — the actions and the command become no-ops. The upload field keeps working: it stores a file you chose, it does not fetch anything.

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
    // Cap on an EMBEDDED favicon, applied to the decoded image (see "Favicons").
    'favicon_max_bytes' => 65536,
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
- **Keep package versions in sync.** The create migration never alters an
  existing table, so schema changes ship as separate migrations — `favicon_data`
  is the first of them. Each is idempotent and race-safe, so several apps
  running it against one shared database is fine; apps pinned to *different*
  package versions writing to one shared table are not — upgrade them together.
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
