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
            'helper' => 'Any icon name supported by Filament, e.g. a Heroicon.',
        ],
        'favicon_url' => [
            'label' => 'Favicon URL',
        ],
        'visibility' => [
            'label' => 'Visible to',
            'placeholder' => 'Everyone',
            'helper' => 'Role-based visibility (the "roles" key) is preserved when set directly on the record.',
            'options' => [
                'auth' => 'Authenticated users only',
                'guest' => 'Guests only',
            ],
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
    ],

    'filters' => [
        'type' => 'Type',
        'is_active' => 'Active',
    ],

    'actions' => [
        'fetch_favicon' => 'Fetch favicon',
        'fetch_favicon_tooltip' => 'Resolve the favicon from the URL above',
        'fetch_favicons' => 'Fetch favicons',
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon resolved',
        'favicon_updated' => 'Favicon updated',
        'favicon_not_found' => 'No favicon found',
        'enter_url_first' => 'Enter an external URL first',
        'favicons_resolved' => ':count favicon(s) resolved',
    ],

    'command' => [
        'disabled' => 'Favicon resolution is disabled (filament-topbar-menu.enable_favicons); nothing to do.',
        'nothing_to_refresh' => 'No menu items need a favicon refresh.',
        'not_found' => 'not found',
        'resolved_summary' => 'Resolved :resolved of :total favicon(s).',
    ],

];
