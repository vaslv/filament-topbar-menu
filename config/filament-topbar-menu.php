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
    | When enabled, links pointing to a different host than the application
    | are opened in a new tab, even when their target is set to `_self`.
    |
    */

    'open_external_links_in_new_tab' => true,

];
