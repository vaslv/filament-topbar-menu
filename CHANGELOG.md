# Changelog

All notable changes to `filament-topbar-menu` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **The menu now renders on mobile.** Below Filament's `lg` breakpoint (1024px)
  the menu was simply not there: the `TOPBAR_LOGO_AFTER` hook it renders into
  sits inside `.fi-topbar-start`, which Filament hides on narrow viewports, and
  the menu's own `<ul class="fi-topbar-nav-groups">` is `hidden lg:flex` in
  Filament's CSS on top of that — so no render-hook change alone could bring it
  back. It is now rendered a second time, at `PanelsRenderHook::TOPBAR_START`,
  with each copy hidden where the other one shows.
  - **A second sidebar with a button of its own**, next to the ☰ that opens the
    panel's: the same slide-in panel, width, transition, offset below the topbar
    and dark mode, because it is built from Filament's own `fi-sidebar` classes
    rather than a layout of ours. Two buttons, two menus, and only one open at a
    time — the menu is never below the panel's navigation, where a long
    navigation would leave it to be scrolled to. The button turns primary when
    the page you are on is in this menu and into an ✕ while the menu is open;
    a link, the backdrop or `Esc` closes it.
  - **An in-sidebar variant** is one call away: `->mobileSidebar()` appends the
    menu to the panel's own navigation instead, as one list. Worth it on a panel
    with a short navigation, where one list reads better than two menus.
  - **Rows are Filament's own sidebar items** in both variants, so the menu
    matches the panel's navigation exactly: same spacing, active-item highlight,
    dark mode, and the native "tap a link, the menu closes" behavior. A group
    becomes a collapsible list (the dropdown of the desktop copy), keeping its
    own icon or favicon and remembering its collapsed state the way Filament's
    navigation groups do — under a prefixed key, so collapsing a menu group never
    collapses a panel navigation group of the same name.
  - **No tree markers.** Filament draws a dot joined by connecting lines in place
    of a missing icon for a *grouped* sidebar item; in a menu where only some
    items carry a favicon that reads as two kinds of marker in one list, so an
    iconless child keeps an empty icon slot, the way it does in the desktop
    dropdown. Favicons, of course, survive.
  - **One media query and two rules are the only CSS the package ships.**
    Filament's precompiled theme carries only the utilities Filament itself uses,
    and every `lg:fi-hidden` rule in it is scoped to a specific parent, so nothing
    there hides an arbitrary element on desktop. The other two rules place the
    button to the right of the ☰ — Filament offers no render hook between its
    sidebar buttons and the logo, so the copy renders before them and pulls them
    one flex slot forward — and keep the backdrop one layer below the topbar, so
    it never covers the ✕ that closes it. All of it is inline in the view: no
    asset to build, nothing to re-run on an update, and publishing the views is
    enough to change any of it.
  - **Three new plugin methods:** `->mobile(false)` drops the mobile copy — for
    a panel that already un-hides the topbar copy below `lg` with CSS of its
    own — `->mobileSidebar()` switches to the in-sidebar variant, and
    `->mobileRenderHook()` moves the copy anywhere, winning over the hook the
    variant would pick.
  - **Two new translation keys** (`mobile.open`, `mobile.close`), shipped in all
    13 languages: the accessible labels of the mobile buttons.

## [1.11.0]

### Added

- **A favicon can now be stored as the image itself, not only as a link.** A
  favicon URL is fetched by every *visitor's* browser, once per item, on every
  panel page — which works for a public site and fails for everything else: a
  service behind a login answers `401` to a user who is not signed into it, an
  internal-only host does not answer at all, and the menu shows a broken image.
  The new `favicon_data` column holds the icon's own bytes as a `data:` URI, so
  nothing is fetched at render time by anyone. No file storage is involved — the
  icon lives in the menu row, survives a redeploy on a container with no
  persistent disk, travels through export/import and rides along in the cached
  snapshot. An embedded icon overrides `favicon_url` when both are set.
  - **In the form:** an "Upload an icon" field — the only route to a favicon
    behind HTTP Basic auth, where the resolver cannot reach the file either —
    with a "Fetch and embed" button that downloads what the resolver finds.
  - **In the items table:** nothing. Embedding deliberately has no bulk action —
    an embedded icon may be one an admin uploaded by hand, nothing can re-fetch
    it, and one click that overwrites a selection of them only opens one way.
    The existing "Fetch favicons" bulk action still stores links, and now says
    when an item it resolved keeps showing its embedded icon instead.
  - **On the command:** `--embed`. It fills `favicon_data` and reads the same
    column to decide which items still need one, so the two modes never
    overwrite each other's work.
  - **New config option** `favicon_max_bytes` (default 64 KB), capping the
    decoded image. The cap guards the cached snapshot more than the database,
    since the snapshot carries every item's attributes and is rebuilt on a miss.
  - **New migration** `add_favicon_data_to_filament_topbar_menu_items_table`,
    which existing installs need — the create migration is already recorded as
    ran there and never alters an existing table. It is idempotent and race-safe,
    so a menu database shared by several apps is fine.
  - Only whitelisted image types are stored or rendered (PNG, JPEG, GIF, WebP,
    AVIF, BMP, ICO, SVG), and the type is sniffed from the **bytes** rather than
    taken from a remote host's `Content-Type` or an upload's file name. The check
    runs on write *and* on render, so a row written by a sibling app on a shared
    menu table, a seeder or hand-written SQL cannot put something unrenderable
    into every panel page. Import applies the same rules to an untrusted file.

### Changed

- **`FaviconResolver`'s internals changed shape for subclasses.** The search now
  returns the bytes alongside the URL, so embedding does not download an icon a
  second time: `resolveFromHtml()` returns `array{url, body}` instead of `?string`,
  the new `locate()` does the same for the whole search, and `isValidFavicon()`
  is gone — `fetchFavicon()` replaces it, returning the body or null. Anything
  overriding those methods needs updating; `resolve()` itself is unchanged.
- **The import file size limit is 16 MB, up from 2 MB.** An embedded favicon
  travels inside the export as base64, so a menu of embedded icons is orders of
  magnitude larger than a menu of links — the old limit would have rejected a
  file this very panel had just exported.
- **`filament-topbar-menu:create-table` now also applies later columns**, not
  just the initial table. It exists for installs where `migrate` is not how the
  schema arrives, so creating the table was only half the job.

### Fixed

- **Declared icons are ranked by `rel`, not by the order the page lists them
  in.** A page commonly declares its `apple-touch-icon` first — a 180×180
  home-screen tile, several times the bytes of the real icon, and not what a
  topbar renders at ~16px. `icon` / `shortcut icon` now wins, then
  `apple-touch-icon`, then everything else; a lower-ranked icon is still used
  when it is all the page has.
- **Embedding no longer gives up on the first unusable candidate.** "Too big to
  store" and "not a format we render" are different answers from "this site has
  no icon", so the search now continues through the remaining declared icons and
  into the conventional locations. Storing a *link* is unaffected by the byte
  cap, as before.
- **An SVG whose root element sits past the first kilobyte is now recognised.**
  The type sniffer looked for `<svg` in the first 1024 bytes, but a file exported
  from Illustrator or Figma leads with a generator comment and a DOCTYPE that
  routinely push `<svg>` past that — so the format the whole feature exists to
  store was refused as "not an image". The window is 8 KB now.
- **An embedded favicon with trailing whitespace is refused.** PCRE's `$` also
  matches before a final newline, so `…;base64,AAAA\n` passed the gate and
  reached an `<img src>` / CSS `url('…')` sink that has no use for it.
- **An oversized value is rejected by length, in constant time.** The gate runs
  on every render of every item against a column the package treats as
  untrusted, and it used to reach a full anchored regex scan plus a
  multi-megabyte base64 decode before applying the cap.
- **The form's `favicon_data` is validated on the way in, not only on the way
  out.** A Hidden field's state is client-controlled, so a panel user could
  write any string — uncapped — into the column that rides inside the cached
  menu snapshot. Render-time validation kept it off the page; nothing kept it
  out of the database.
- **Removing an uploaded icon clears it.** Filament runs the same hook after
  unsetting the file, and the hook bailed out on the empty state — so the icon
  the admin had just removed stayed stored and stayed in the topbar.
- **`favicon_max_bytes` is clamped to what the column can hold** (12.58 MB
  decoded, the mediumText capacity once base64 inflates it). A larger configured
  value meant MySQL silently truncating the icon in non-strict mode.
- **The `favicon_data` migration runs outside a transaction.** PostgreSQL aborts
  the whole transaction on a duplicate-column error, so the race-recovery
  re-check would itself have thrown instead of no-oping.
- **The favicon resolver no longer misses sites that ship only a `favicon.svg`
  (or `.png`).** When a page declares nothing the resolver can read — the icon
  is inserted from JavaScript, or the page is behind auth and answers
  `401`/`403` — resolution falls back to guessing at conventional locations, and
  that guess was `favicon.ico` alone. A modern site commonly serves
  `favicon.svg` and nothing else, so those items stored no favicon at all. Each
  candidate directory (the link's own, then the host root) is now probed for
  `favicon.ico`, `favicon.svg` and `favicon.png`, in that order, keeping the
  existing preference for the link's own directory over the host root.
- **An icon declared with unquoted attributes is now read.** `<link rel=icon
  href=/favicon.svg>` is valid HTML and is what an HTML minifier emits, but the
  parser insisted on quotes and skipped such a tag — falling through to guessing
  at file names on a page that had said exactly where its icon was. The same
  applies to an unquoted `<base href>`. Attribute names are matched on their own
  boundary while reading, so `data-href` is not mistaken for `href`.

## [1.10.1]

### Security

- **An IPv4 address wrapped in an IPv6 one no longer slips past the favicon
  resolver's SSRF guard on PHP 8.2.** The guard asks `filter_var()` whether an
  address is outside the private and reserved ranges, and on PHP 8.2 that
  question is answered wrong for the wrapped forms: `::ffff:127.0.0.1` (and its
  `::ffff:7f00:1` spelling), `::ffff:10.0.0.1` and the deprecated
  `::127.0.0.1` are all reported public, so a menu item pointing at one of them
  reached loopback or the private network. PHP 8.3 added `::ffff:0:0/96` to its
  reserved list, which is why the hole existed only on the oldest supported
  runtime — and why the test suite caught it only in CI. The guard now reduces
  such an address to the IPv4 one it carries before judging it, on every
  version.
- **The NAT64 well-known prefix is refused**, on every PHP version. Where a
  NAT64 gateway is on the path — IPv6-only networks routinely have one —
  `64:ff9b::7f00:1` is how a host reaches 127.0.0.1, and no PHP release
  classifies that range as reserved.

## [1.10.0]

### Added

- **A "Flush menu cache" action on the items page**, beside Export and Import.
  Every write through this package already flushes the cached snapshot — the
  model hooks, the import, the reorder callback — so this is for the writes
  outside its reach: a row written by a sibling app on a shared menu database, a
  seeder, hand-written SQL, or a cache store restored from a backup. Until now
  those needed console access to `TopbarMenu::flushCache()`. Nothing is lost by
  pressing it, since the next render rebuilds the snapshot from the database.
  Shown to users the resource's *viewAny* policy allows — the snapshot is shared
  by every visitor of every panel — and hidden while the menu table is missing,
  where the setup action is the only one on offer and flushing would trade a
  stale menu for an empty one. Two new keys, `actions.flush_cache` and
  `notifications.cache_flushed`, in all 13 shipped locales.

### Fixed

- **A relative icon href is resolved against the document's `<base href>`.** The
  tag was ignored, so a shell served from a sub-path — `<base href="/admin/">`
  with `<link rel="icon" href="favicon.ico">`, which every single-page app
  framework emits — stored the site-wide `/favicon.ico` instead of the app's own
  `/admin/favicon.ico`: the same class of mistake as resolving against the
  pre-redirect URL. Only the first `<base>` counts, the way a browser reads it,
  and only an http(s) one with a host is honored — a `data:` or malformed base
  leaves the answering URL in place rather than producing candidates the SSRF
  check has no host to judge. A base pointing at another host is honored, as in
  a browser; every candidate built from it still goes through that check.
- **A trailing slash survives path normalization.** `/admin/` collapsed to
  `/admin`, which changes what every relative reference underneath it resolves
  to. This also affected a `Location` header of that shape.

### Changed

- **`favicon.ico` is now looked for in the directory the link points at before
  the host root.** An item linking to `/admin` tries `/admin/favicon.ico` first,
  one linking to `/docs/page.html` tries `/docs/favicon.ico`. A browser never
  does this — it learns about anything but the root from the markup — but that
  is not enough for a single-page app: a Directus or Nova panel on a sub-path
  inserts its `<link rel="icon">` from JavaScript, so the server-rendered shell
  declares no icon at all, and the host root of such a deployment is usually an
  API that answers `404`. The icon still sits in the app's own directory. Costs
  at most one extra request per item, only when the page's own icons produced
  nothing — because it declared none, or because the ones it declared did not
  answer — and none at all for a link to the host root, where the two
  candidates coincide.

- **The items table lists the menu in the order the topbar renders it.** Each
  top-level item sits at its `sort` position with its children directly beneath
  it. The table ordered flat by `sort`, but that column is a position among
  *siblings*, not a rank across the table: one group's children landed between
  another group's roots, and a page whose job is "what does the topbar look
  like" answered something else. Ties fall back to `id`, the same key
  `TopbarMenuItem::ordered()` uses when the menu itself is built. Sorting by a
  column header is unaffected — this is the order the page lands on.

  A group is keyed by its root ancestor two hops up, so the third level the
  table lists but the menu never renders — the rows left behind by giving a
  group a parent of its own — stays under the item it hangs off instead of
  sorting by its parent's `sort` into the middle of unrelated groups. This
  table is where those rows are found and fixed.

  Drag-and-drop reordering now shows that same order instead of switching to the
  flat one when it is turned on, which used to rearrange the rows the admin was
  about to drag. A drag still writes plain sequential `sort` values, so moving a
  row between groups changes its position among its own siblings only —
  reordering does not reparent, the Parent field does.

### Removed

- **The per-row "Fetch favicon" action in the items table.** Resolving a single
  item stays where that item is being worked on — the suffix button on the
  Favicon URL field — and the table keeps the bulk action for a selection. The
  row action put a third copy of the same operation on every row of a page whose
  job is the overview. Its now-unused `notifications.favicon_updated` string is
  dropped from all 13 locales; the bulk action and the field button keep their
  own (`favicons_resolved`, `favicon_resolved`, `favicon_not_found`). The artisan
  command is unchanged, and remains the way to re-resolve without clicking.

## [1.9.0]

### Added

- **View page for a menu item.** A read-only page showing the item's link, the
  URL it actually resolves to — generated for route items, empty for a broken
  route name — the favicon-over-icon marker the topbar renders, every visibility
  rule (including a `roles` restriction, which no form field owns and which the
  panel therefore had no way to show before) and the timestamps, plus a View row
  action in the items table and a
  View button on the edit page. Creating an item and saving an edit both land
  there instead of on the edit form, which is where Filament leaves you in
  both cases without the page, while a panel that configured its own
  `resourceCreatePageRedirect()` / `resourceEditPageRedirect()` keeps its
  choice. The `{record}` route is registered after `create`, so `/create`
  is not swallowed by it.

  On by default; `FILAMENT_TOPBAR_MENU_VIEW_PAGE=false` (or
  `'enable_view_page' => false`) keeps the plain list/create/edit resource,
  with no View action rendered anywhere. The route only exists while the option
  is on, so a panel with cached routes needs `route:cache` re-run after
  flipping it — including on the update that brings this page in. Until it is
  re-run the page reports itself unavailable rather than letting Filament
  generate the URL of a route the router does not know: that would throw on the
  items table and, because `CreateRecord` writes the record before building its
  redirect, turn a successful create into a "failed" save that invites a
  duplicate on retry. The check is deliberately skipped while the application is
  still booting, because Laravel loads the cached route file from a `booted`
  callback — asking the router during boot, when Filament registers each page's
  Livewire component, would strip the page off a healthy cached deploy.

- **The View page's strings in all 13 shipped locales.** Four new keys for the
  page itself (`sections.record`, `columns.resolved_url`, `columns.created_at`,
  `columns.updated_at`) and one for the roles row (`fields.visibility.roles`).

### Security

- **Numeric host spellings can no longer slip past the favicon resolver's SSRF
  guard.** The guard deliberately fails open for hosts that do not resolve, on
  the reasoning that the HTTP client cannot reach them either. That is only true
  for host *names*: curl also accepts hex, octal, dword and short IPv4 literals.
  Whether the system resolver recognizes those is a libc detail — on glibc
  `0177.0.0.1`, `2130706433` and `127.1` do resolve, so they were already
  refused as private addresses, while `0x7f.0.0.1` resolves nowhere and reached
  127.0.0.1 unchecked. The guard still asks the resolver first; what changed is
  the fallback, which no longer waves a host through just because nothing came
  back. A host that does not resolve is refused unless it is shaped like a DNS
  name — with `ext-intl` installed, Unicode names are folded to punycode first,
  so an IDN site stays reachable even though the resolver cannot look it up;
  without the extension such a host is refused, which is the safe direction.
  Zone-qualified IPv6 literals (`[fe80::1%25en0]`) took the same route out and
  are now validated as addresses. This mattered more after
  the resolution-order change below: a linked page's `<link rel="icon">` is read
  on every resolve now, so the address came from a third-party page rather than
  from the admin's own URL.
- **A linked page can no longer dictate how many outbound requests the resolver
  makes.** Every declared icon was probed with a full HTTP request (up to 4 hops
  × the configured timeout each), over a page body of up to 2 MB — enough
  declarations to hold a PHP worker for hours. Candidates are now deduplicated
  and capped at five per page.

### Fixed

- **A favicon that is not a URL no longer takes the panel down.** `displayIcon()`
  returned `favicon_url` verbatim, and Filament reads a string without a slash
  as an icon *name* — `SvgNotFound`, on every panel page, since the menu renders
  on all of them. Every write path in the package stores a URL, so such a value
  only arrives from a fleet-shared menu table written by a sibling app, a seeder
  or hand-written SQL. It is now skipped in favour of the item's icon name.
- **Relative icon hrefs are resolved against the URL that answered**, not the one
  requested. On the canonical `https://host/docs` → `https://host/docs/`
  redirect, `href="favicon.png"` resolved to `/favicon.png` — the site-wide icon
  the new resolution order exists to stop preferring.
- **The favicon of the linked page wins over the site-wide one.** The resolver
  tried `https://host/favicon.ico` first and only parsed the page markup when
  that 404'd, so every item on a host with a root icon got the same icon — a
  link to `/horizon` stored the main site's favicon instead of Horizon's. The
  page's own `<link rel="icon">` is now asked first and the root location is the
  fallback, which is the order a browser resolves a tab icon in. Resolution is
  still anonymous: a page behind auth answers 401/403, so its declared icon is
  unreachable and the root icon is stored — set those by hand.

  Items that already carry a favicon keep the one the old order picked: a plain
  `php artisan filament-topbar-menu:refresh-favicons` only fills empty ones. Run
  it with `--force` (or use the row/bulk action) to re-resolve an existing menu
  under the new order.
- **Favicons in a dropdown are no longer round-cropped.** Child items passed
  their favicon to Filament's `image` slot, an avatar-style circle
  (`border-radius` + `background-size: cover`) that cut the corners off a square
  icon. The favicon now goes through the icon slot, rendering as a plain, fully
  visible `<img>` at icon size — the same marker the top-level items already
  used.

## [1.8.1]

### Fixed

- **Favicons are visible again in the menu items table.** The table's visual
  column was bound to the `icon` attribute while its icon came from
  `displayIcon()`, and Filament's `IconColumn` renders the empty-cell
  placeholder for a blank column *state* before it ever consults the icon
  closure. Every item carrying a favicon but no Heroicon name — the normal
  shape of an external link — therefore showed an empty cell, while items that
  happened to have both did show their favicon. The column now takes
  `displayIcon()` as its state, so the table matches the rendered topbar in
  every case.

## [1.8.0]

### Added

- **"Hide on its own domain" option for menu items.** A per-item toggle (the
  `hide_on_current_domain` key in the `visibility` JSON column — no migration,
  fleet-safe) that hides a URL item on the service whose host matches the
  item's URL. One fleet-shared menu can now list every service while each app
  automatically renders "everyone but me". Host matching is case-insensitive,
  ignores `www.` and a trailing dot, treats subdomains as significant, equates
  the default ports 80/443 with an absent port (a non-standard port stays
  significant, so `localhost:8000` ≠ `localhost:8001`), does not compare the
  scheme, aligns punycode/unicode spellings via `ext-intl` when installed
  (non-transitional UTS #46, the way browsers resolve hosts), and
  canonicalizes IPv6 literals (`[2001:db8::1]` ≡ `[2001:0db8::0:1]`).
  The rule applies to URL items only — Laravel route items ignore the key.
  Visibility is still evaluated per request, never baked into the cache.
- **"Hidden on this domain" badge in the admin table.** Items the flag hides
  on the current host show a crossed-eye icon with a tooltip, so a vanished
  topbar entry reads as "hidden here, visible on the rest of the fleet" — not
  as a deletion.
- Import validation for the new key (booleans only), matching the existing
  visibility guarantees; the flag survives export/import round-trips.
- Translations of the new toggle, helper and badge for all 13 shipped locales,
  and a README section on fleet menus, including host-matching semantics and
  a note on trusted proxies / `X-Forwarded-Host` behind reverse proxies.

### Changed

- Import URL validation now also requires a host: a scheme-only value like
  `http:foo` used to pass the http(s) check while browsers read such an href
  differently than PHP parses it — and the new domain flag could never match
  it. Real menu links always name a host; files with hostless URLs are now
  rejected up front like other malformed values.

## [1.7.0]

### Added

- **`filament-topbar-menu:create-table` command.** Creates the menu table on
  the configured connection without consulting the migration repository. This
  closes the gap when an existing install switches to a dedicated connection
  via `FILAMENT_TOPBAR_MENU_DB_CONNECTION`: the package migration is already
  recorded in the app's own migrations table, so plain `php artisan migrate`
  skips it and the table never appears on the new connection. The command is
  idempotent (and race-safe against parallel first deploys of several apps
  sharing one menu database), so it is safe to keep in deploy scripts
  permanently. The table schema now lives in one shared class
  (`Support\MenuTableSchema`) used by both the migration and the command.
- **`--copy-from-default` flag on `create-table`.** Moves the data during the
  same deploy step: when the dedicated menu is still empty and the app's
  default connection still has a menu table, the local menu is copied onto
  the dedicated connection through the export/import pipeline (validated, in
  a transaction, cache flushed). A populated shared menu is never merged
  into, so a second app joining the fleet cannot corrupt it.
- **Panel-side recovery for a missing menu table.** When the menu table does
  not exist on the configured connection, the menu items list page renders an
  explanation instead of a 500, and users allowed to create menu items get a
  "Create menu table" header action (next to Export/Import) that performs the
  same create-and-copy as `create-table --copy-from-default` — so the switch
  can be finished from the panel, without console access. Export, Import and
  Create are hidden while the table is missing.

### Fixed

- **A missing menu table no longer 500s every panel page.** The topbar is
  rendered on every page; during a connection switch there is a window where
  the configured connection has no menu table yet. The menu now degrades to
  an empty topbar and reports the error to the app's exception handler
  instead of failing the request. The failure is never cached, so the menu
  reappears on the first request after the table exists.

## [1.6.1]

### Changed

- **One icon column in the menu items table.** The separate favicon and icon
  columns merged into a single column that renders exactly what the topbar
  shows: the favicon (as an image) when set, otherwise the validated Heroicon.
  The precedence rule now lives in one shared place —
  `TopbarMenuItem::displayIcon()` — used by both the table and the topbar view.

## [1.6.0]

### Added

- **Configurable database connection.** A new `connection` config option,
  settable via the optional `FILAMENT_TOPBAR_MENU_DB_CONNECTION` env variable,
  routes the menu table to a dedicated database connection. Left unset it
  resolves to `null` and follows the application's default connection (the one
  set by `DB_CONNECTION`), so no separate menu database is assumed and existing
  installs keep working unchanged after updating. Set it to point several apps
  at the same menu database and render one centrally managed menu across
  projects. The migration, every model query and the import transaction all
  follow the configured connection. See the "Shared menu across projects"
  section in the README, including the switch procedure for existing installs
  and the operating notes for a shared database.
- **Heroicon preview in the menu items table.** The admin table renders each
  item's validated Heroicon; a favicon takes precedence over the icon,
  mirroring the topbar.
- **heroicons.com link in the icon helper.** The icon field's helper text now
  links to the full Heroicons list, in every shipped locale.

### Changed

- A present-but-blank `FILAMENT_TOPBAR_MENU_DB_CONNECTION=` env line normalizes
  to `null` (default connection) instead of producing an empty connection name.
- An explicitly assigned model connection (`Model::on()`, `setConnection()`)
  now takes precedence over the configured menu connection.

### Security

- The package migration's `down()` is a no-op when a dedicated connection is
  configured, so a routine `migrate:rollback` in one app cannot drop a menu
  database shared by a fleet of apps; `up()` also tolerates two apps racing to
  create the table on their first deploy.
- Icon names containing `/` are rejected: Filament renders slash-strings as
  `<img src="...">`, which would let a URL smuggled into the icon field (for
  example via import) load an arbitrary external image in the admin table and
  topbar. Unexpected icon-rendering failures are now reported instead of
  silently swallowed.
- The icon helper text escapes the translated string before substituting the
  heroicons.com anchor, so an overridden or third-party locale cannot inject
  raw HTML into the admin panel.

## [1.5.1]

### Added

- **Eight more UI locales.** The package now ships translations for Arabic
  (`ar`), Persian (`fa`), Indonesian (`id`), Italian (`it`), Dutch (`nl`),
  Brazilian Portuguese (`pt_BR`), Turkish (`tr`) and Simplified Chinese
  (`zh_CN`), joining the existing `en`, `de`, `es`, `fr` and `ru`. Each locale
  covers the full key set (resource form, table, actions, notifications and the
  artisan command output).

## [1.5.0]

### Added

- **Export & import of the whole menu.** The list page gained Export and
  Import header actions. Export downloads the full menu tree — every item with
  all of its settings, including `route_parameters` and `visibility` rules —
  as a portable JSON file without database ids. Import validates the whole
  file up front, runs in a single transaction, and either appends to or (via a
  toggle) replaces the current menu. Files with unknown extra keys import
  cleanly, so exports from newer plugin versions stay usable.
  Because an import file is untrusted input, it is held to the same guards as
  the form: `url`/`favicon_url` must be plain `http(s)` links (blocking a
  stored `javascript:`/`data:` link or a CSS `url()` breakout in the rendered
  topbar), `route_parameters` must be scalar, `visibility` must be well-shaped,
  string values are length-checked, and the tree may be at most two levels
  deep. Export requires the resource's view permission and import its create
  permission; the replace option is limited to users the delete policy allows.

## [1.4.3]

### Changed

- **The demo seeder's role-restricted example ("Admin Tools") is now
  adaptive.** The package cannot assume the host app has a roles system, and a
  role-restricted item is hidden from everyone when the user model has no
  `hasAnyRole()` method (the package fails closed) — so the seeded example
  looked broken in apps without e.g. spatie/laravel-permission. The seeder now
  seeds it only when the user model supports roles, and removes a previously
  seeded example (matched by its exact seeded shape) when re-run after roles
  support is gone.

## [1.4.2]

### Fixed

- **The edit page no longer 500s ("Array to string conversion") for items with
  a visibility rule.** The "Visible to" Select was bound directly to the
  `visibility` JSON column, and Filament casts a Select's raw state through
  `OptionStateCast` (`strval()`) during form fill — before `formatStateUsing`
  runs — so any stored visibility array crashed the page. The form now edits a
  virtual `visibility_mode` string and the Create/Edit pages map it from and
  back onto the `visibility` array, still preserving keys the form does not
  manage (notably `roles`).
- **Dropdown groups can now be saved from the form.** `url`/`route` were
  unconditionally required for their link type, so a group (whose own link is
  never used) could not be saved without one. They are now optional for items
  that already have children.

## [1.4.1]

### Added

- **A demo seeder** (`Vaslv\FilamentTopbarMenu\Database\Seeders\TopbarMenuSeeder`)
  that seeds a menu tree exercising every feature of the plugin: external URL
  links, an internal route link, a dropdown group with children, per-item
  targets and icons, visibility rules (auth / guest / roles) and an inactive
  item. Idempotent — items are matched by parent + label, so re-running it
  updates the demo items in place instead of duplicating them. Run it with
  `php artisan db:seed --class="Vaslv\FilamentTopbarMenu\Database\Seeders\TopbarMenuSeeder"`.

## [1.4.0]

### Changed

- **The menu tree is now cached as plain arrays and rehydrated into models on
  read, instead of caching live Eloquent models.** Caching models is fragile: a
  serializing store (Redis, file, database) could hand them back as
  `__PHP_Incomplete_Class` after a deploy or under a shared store — which 1.3.1
  made non-fatal (a self-healing rebuild). Caching only scalars removes that
  failure mode at the root: the cached payload can always be reconstructed. No
  API change — `items()` / `visibleItems()` still return
  `Collection<int, TopbarMenuItem>`.

## [1.3.1]

### Fixed

- **The menu no longer 500s the whole panel when the cache returns an unusable
  payload.** A serializing cache store (Redis, file, database) can hand back
  `__PHP_Incomplete_Class` objects after a deploy or under a shared store, and
  `visibleItems()` then hit a `TypeError` on its typed filter closure — on every
  panel page. The cached tree is now validated and, when it isn't a clean
  collection of models, dropped and rebuilt straight from the database, so a
  poisoned entry self-heals instead of taking the panel down.

## [1.3.0]

### Security

- **The favicon resolver now treats external sites as untrusted.** It refuses to
  fetch private, loopback and link-local addresses (SSRF) — including the cloud
  metadata endpoint `169.254.169.254` — and re-validates every redirect hop, so a
  public site can no longer redirect the resolver onto an internal address. It
  also caps how much of a response body it reads into memory.
- **Resolved favicon URLs are validated before they are stored.** Only plain
  `http(s)` URLs within the storage column limit, with no characters that could
  break out of the panel's CSS `url('…')` context, are persisted — closing a
  stored CSS-injection vector through a dropdown item's favicon.
- **Menu item labels are escaped in the `refresh-favicons` command output**, so a
  stored label containing `<` or `>` can no longer be misparsed as a Symfony
  console style tag (completing the hardening started in 1.1.1, which covered only
  the translated "not found" label).

### Changed

- **`roles` visibility now fails closed.** A menu item restricted by `roles` is
  hidden when the restriction cannot be evaluated — a guest, or a user model
  without a `hasAnyRole()` method — instead of being shown to everyone.

  **Upgrade note:** if you set `roles` on items but your user model has no
  `hasAnyRole()` (e.g. spatie/laravel-permission is not installed), those items
  will now be hidden. Add the method (or the package) to keep them visible.
- **`data:` URI favicons are no longer stored**, and favicon resolution now
  follows at most 3 redirects — previously `data:` values were kept and redirects
  were followed automatically. Re-run `php artisan filament-topbar-menu:refresh-favicons`
  to re-resolve any item whose favicon was a `data:` URI.

## [1.2.1]

### Fixed

- A dropdown group no longer duplicates its parent's own link as an extra first
  row inside the dropdown. A parent with children is now a pure toggle (matching
  Filament's native groups); its own `url`/`route` is ignored. To keep a landing
  page reachable, add it as an explicit child item.

## [1.2.0]

### Changed

- **The menu is now rendered with Filament's own topbar and dropdown components**
  (`fi-topbar-nav-groups`, `x-filament-panels::topbar.item`, `x-filament::dropdown`,
  `x-filament::dropdown.list.item`) instead of custom markup and CSS. It is now
  pixel-identical to Filament's native `topNavigation()` menu: same spacing, dark
  mode, dropdown behavior, and it highlights the active item / active group.
- A parent item that has both a URL and children now renders as a dropdown group
  whose first entry is the parent's own link (matching Filament's grouping), rather
  than a link with a separate caret.

### Added

- Active-state highlighting: the item (or dropdown group) pointing at the current
  page is marked active, via new `TopbarMenuItem::isActive()` / `isBranchActive()`.

### Removed

- The bundled CSS asset and its `FilamentAsset` registration — there is no longer
  any custom CSS, so `php artisan filament:assets` is not needed for this plugin.
  The `resources/dist/filament-topbar-menu.css` file and the icon/chevron partials
  were removed.

## [1.1.1]

### Fixed

- Escape the `refresh-favicons` command's translated "not found" label before
  writing it to the console, so a custom/published translation containing `<` or
  `>` can't be misparsed as a Symfony console style tag.

### Added

- This changelog.

## [1.1.0]

### Changed

- **The per-item link target is now authoritative.** "Same tab" (`_self`) always
  opens in the same tab and "New tab" (`_blank`) always opens in a new one.
  Previously, external links were force-promoted to a new tab at render time when
  `open_external_links_in_new_tab` was enabled, even if their target was `_self`.

  The `open_external_links_in_new_tab` config option now only seeds the **default
  value** of the target field when creating a new item; it never overrides an
  explicit choice at render time.

  **Upgrade note:** if you relied on external links auto-opening in a new tab while
  their stored target was `_self`, set those items' target to "New tab" explicitly.

### Added

- Interface translations (`filament-topbar-menu` namespace): English, Russian,
  German, Spanish and French. The language follows the application locale, and the
  files are publishable with `--tag=filament-topbar-menu-translations`.

### Removed

- The internal helpers `TopbarMenuItem::isExternalUrl()` and
  `TopbarMenuItem::normalizeAuthority()`, which are no longer used now that the
  target is honored literally.

## [1.0.0]

- Initial release: configurable topbar menu for Filament 5 via
  `PanelsRenderHook::TOPBAR_LOGO_AFTER`, external/internal links, nested dropdowns,
  favicon resolution, per-item visibility, caching, and a management resource.
