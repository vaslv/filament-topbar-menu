<?php

return [

    'labels' => [
        'model' => 'Topbar menu item',
        'plural_model' => 'Topbar menu',
        'navigation' => 'Topbar menu',
    ],

    'sections' => [
        'link' => 'Link',
        'appearance' => 'Appearance & behavior',
        'record' => 'Record',
    ],

    'fields' => [
        'label' => 'Label',
        'parent' => [
            'label' => 'Parent item',
            'placeholder' => 'None (top level)',
            'helper' => "Items with a parent are shown in the parent's dropdown.",
        ],
        'type' => [
            'label' => 'Link type',
            'options' => [
                'url' => 'URL',
                'route' => 'Laravel route',
            ],
        ],
        'target' => [
            'label' => 'Target',
            'options' => [
                'self' => 'Same tab',
                'blank' => 'New tab',
            ],
        ],
        'url' => [
            'label' => 'URL',
            'placeholder' => 'https://example.com',
        ],
        'route' => [
            'label' => 'Route name',
        ],
        'route_parameters' => [
            'key' => 'Parameter',
            'value' => 'Value',
        ],
        'icon' => [
            'label' => 'Icon',
            'placeholder' => 'heroicon-o-link',
            'helper' => 'Any icon name supported by Filament, e.g. a Heroicon. Browse the full list at :link.',
        ],
        'favicon_url' => [
            'label' => 'Favicon URL',
        ],
        'favicon_data' => [
            'label' => 'Embedded favicon',
            'upload' => 'Upload an icon',
            'helper' => 'Stored in the menu itself, not on a disk. Use it when the icon is not reachable from every visitor\'s browser — a site behind a login, or an internal-only service. An embedded icon overrides the favicon URL.',
        ],
        'visibility' => [
            'label' => 'Visible to',
            'placeholder' => 'Everyone',
            'helper' => 'Role-based visibility (the "roles" key) is preserved when set directly on the record.',
            'roles' => 'Restricted to roles',
            'options' => [
                'auth' => 'Authenticated users only',
                'guest' => 'Guests only',
            ],
        ],
        'hide_on_current_domain' => [
            'label' => 'Hide on its own domain',
            'helper' => 'The item is not shown on the service whose domain matches the link — for one menu shared across several services.',
        ],
        'sort' => [
            'label' => 'Sort',
            'helper' => 'Lower values are shown first. You can also drag rows in the list.',
        ],
        'is_active' => [
            'label' => 'Active',
        ],
    ],

    'columns' => [
        'parent' => 'Parent',
        'type' => 'Type',
        'target' => 'Target',
        'is_active' => 'Active',
        'sort' => 'Sort',
        'hidden_on_current_domain' => 'Hidden on this domain',
        'hidden_on_current_domain_tooltip' => 'Hidden on this domain; still visible on the fleet\'s other services.',
        'resolved_url' => 'Resolved link',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
    ],

    'filters' => [
        'type' => 'Type',
        'is_active' => 'Active',
    ],

    'actions' => [
        'fetch_favicon' => 'Fetch favicon',
        'fetch_favicon_tooltip' => 'Resolve the favicon from the URL above',
        'fetch_favicons' => 'Fetch favicons',
        'embed_favicon' => 'Fetch and embed',
        'embed_favicon_tooltip' => 'Download the favicon from the URL above and store the image itself',
        'clear_favicon_data' => 'Remove',
        'export' => 'Export',
        'import' => 'Import',
        'create_table' => 'Create menu table',
        'flush_cache' => 'Flush menu cache',
    ],

    'missing_table' => [
        'title' => 'Menu table not found',
        'description' => 'The menu table does not exist on the configured database connection yet — usually right after switching FILAMENT_TOPBAR_MENU_DB_CONNECTION. The button above creates it; a menu still present on this app\'s default connection is copied over automatically.',
    ],

    'import' => [
        'description' => 'Upload a JSON file previously exported from this menu. Items are appended to the existing menu unless you enable the replace option.',
        'file' => 'Export file (JSON)',
        'replace' => 'Replace the current menu',
        'replace_helper' => 'All existing menu items are deleted before importing.',
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon resolved',
        'favicon_embedded' => 'Favicon downloaded and embedded',
        'favicon_not_found' => 'No favicon found',
        'favicon_upload_rejected' => 'That file was not stored: it must be an image of at most :size',
        'enter_url_first' => 'Enter an external URL first',
        'favicons_resolved' => ':count favicon(s) resolved',
        'favicons_overridden' => 'An embedded icon overrides the link on :count item(s) — the topbar keeps showing it.',
        'import_success' => ':count menu item(s) imported',
        'import_invalid' => 'The file is not a valid topbar menu export',
        'cache_flushed' => 'Menu cache flushed',
        'table_created' => 'Menu table created',
        'table_copied' => ':count menu item(s) copied from the default connection',
    ],

    'command' => [
        'disabled' => 'Favicon resolution is disabled (filament-topbar-menu.enable_favicons); nothing to do.',
        'nothing_to_refresh' => 'No menu items need a favicon refresh.',
        'not_found' => 'not found',
        'embedded' => 'embedded (:type)',
        'resolved_summary' => 'Resolved :resolved of :total favicon(s).',
        'table_created' => 'Menu table ":table" created on the ":connection" connection.',
        'table_exists' => 'Menu table ":table" already exists on the ":connection" connection; nothing to do.',
        'column_added' => 'Column ":column" added to the menu table ":table".',
        'copied' => 'Copied :count menu item(s) from the default connection to ":connection".',
        'copy_skipped_existing' => 'Copy skipped: the menu on ":connection" already has items; not merging this app\'s local menu into it.',
        'copy_skipped_no_dedicated' => 'Copy skipped: no dedicated connection is configured; the menu already lives on the default connection.',
        'copy_skipped_no_source' => 'Copy skipped: the default connection has no menu table to copy from.',
    ],

];
