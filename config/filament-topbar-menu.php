<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Table name
    |--------------------------------------------------------------------------
    |
    | The database table used to store topbar menu items.
    |
    */

    'table_name' => 'filament_topbar_menu_items',

    /*
    |--------------------------------------------------------------------------
    | Database connection
    |--------------------------------------------------------------------------
    |
    | The database connection used to store menu items. Left unset, it resolves
    | to null and follows the application's default connection — the one set by
    | DB_CONNECTION — so no separate menu database is assumed and every existing
    | install keeps working unchanged after an update. Set the optional
    | FILAMENT_TOPBAR_MENU_DB_CONNECTION env variable (or edit this value) to a
    | dedicated connection (defined in config/database.php) to keep the menu in
    | a separate database — for example, one database shared by several apps so
    | they all render a single, centrally managed menu.
    |
    | When a non-default connection is used, publish and run the migration on
    | that connection (its schema must contain the menu items table).
    |
    */

    // `?:` normalizes a present-but-blank env value ('') to null, so a stray
    // `FILAMENT_TOPBAR_MENU_DB_CONNECTION=` line cannot produce a '' connection
    // name that only works by accident of DatabaseManager treating '' as falsy.
    'connection' => env('FILAMENT_TOPBAR_MENU_DB_CONNECTION') ?: null,

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | The menu tree is cached so no database query runs on every page render.
    | The cache is flushed automatically whenever a menu item is created,
    | updated, deleted or reordered.
    |
    */

    'cache_key' => 'filament-topbar-menu.items',

    'cache_ttl' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Favicons
    |--------------------------------------------------------------------------
    |
    | When enabled, favicons for external links can be resolved (on demand,
    | never during render) and stored in the `favicon_url` column. Use the
    | "Fetch favicon" action in the resource, or run:
    |
    |   php artisan filament-topbar-menu:refresh-favicons
    |
    | `favicon_request_timeout` is the timeout in seconds of a SINGLE outbound
    | request, not of resolving one item: resolving fetches the linked page, then
    | up to five icons it declares, then the root /favicon.ico — each of which
    | may follow redirects — so the worst case for one item is a multiple of
    | this value.
    |
    */

    'enable_favicons' => true,

    'favicon_request_timeout' => 5,

    /*
    |--------------------------------------------------------------------------
    | View page
    |--------------------------------------------------------------------------
    |
    | The read-only "View" page of a menu item: the resource gets a `{record}`
    | route, the items table gets a View row action, and both creating an item
    | and saving an edit land on that page instead of the edit form — where
    | Filament otherwise leaves you in both cases. Enabled by default.
    |
    | The landing page is not forced: a panel that configured its own
    | `resourceCreatePageRedirect()` / `resourceEditPageRedirect()` keeps that
    | choice, and a user the view policy denies is never redirected there.
    |
    | Turn it off with FILAMENT_TOPBAR_MENU_VIEW_PAGE=false to keep the plain
    | list/create/edit resource — no route, no View action anywhere.
    |
    | The route is only registered while this is enabled, so a panel whose
    | routes are cached must be re-cached (`php artisan route:cache`) after
    | flipping it — and after the update that first brings the page in. Until
    | then the package sees that the cached route collection has no View route
    | and reports the page as unavailable, so a stale cache costs the View
    | button rather than throwing on a URL it cannot generate.
    |
    */

    'enable_view_page' => env('FILAMENT_TOPBAR_MENU_VIEW_PAGE', true),

    /*
    |--------------------------------------------------------------------------
    | External links
    |--------------------------------------------------------------------------
    |
    | The default value of the "Target" field when creating a new menu item:
    | when enabled, new items default to opening in a new tab. This is only a
    | default — the per-item choice ("Same tab" / "New tab") is always honored
    | at render time and is never overridden.
    |
    */

    'open_external_links_in_new_tab' => true,

];
