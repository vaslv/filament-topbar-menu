# Changelog

All notable changes to `filament-topbar-menu` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
