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

    'connection' => env('FILAMENT_TOPBAR_MENU_DB_CONNECTION'),

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
    */

    'enable_favicons' => true,

    'favicon_request_timeout' => 5,

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
