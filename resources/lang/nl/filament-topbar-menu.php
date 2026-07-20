<?php

return [

    'labels' => [
        'model' => 'Topbalk-menu-item',
        'plural_model' => 'Topbalkmenu',
        'navigation' => 'Topbalkmenu',
    ],

    'sections' => [
        'link' => 'Link',
        'appearance' => 'Weergave & gedrag',
    ],

    'fields' => [
        'label' => 'Label',
        'parent' => [
            'label' => 'Bovenliggend item',
            'placeholder' => 'Geen (hoogste niveau)',
            'helper' => 'Items met een bovenliggend item worden getoond in het uitklapmenu van dat item.',
        ],
        'type' => [
            'label' => 'Linktype',
            'options' => [
                'url' => 'URL',
                'route' => 'Laravel-route',
            ],
        ],
        'target' => [
            'label' => 'Openen in',
            'options' => [
                'self' => 'Zelfde tabblad',
                'blank' => 'Nieuw tabblad',
            ],
        ],
        'url' => [
            'label' => 'URL',
            'placeholder' => 'https://example.com',
        ],
        'route' => [
            'label' => 'Routenaam',
        ],
        'route_parameters' => [
            'key' => 'Parameter',
            'value' => 'Waarde',
        ],
        'icon' => [
            'label' => 'Pictogram',
            'placeholder' => 'heroicon-o-link',
            'helper' => 'Elke pictogramnaam die door Filament wordt ondersteund, bijvoorbeeld een Heroicon.',
        ],
        'favicon_url' => [
            'label' => 'Favicon-URL',
        ],
        'visibility' => [
            'label' => 'Zichtbaar voor',
            'placeholder' => 'Iedereen',
            'helper' => 'Op rollen gebaseerde zichtbaarheid (de sleutel "roles") blijft behouden wanneer deze rechtstreeks op het record is ingesteld.',
            'options' => [
                'auth' => 'Alleen ingelogde gebruikers',
                'guest' => 'Alleen gasten',
            ],
        ],
        'sort' => [
            'label' => 'Volgorde',
            'helper' => 'Lagere waarden worden eerst getoond. Je kunt de rijen in de lijst ook verslepen.',
        ],
        'is_active' => [
            'label' => 'Actief',
        ],
    ],

    'columns' => [
        'parent' => 'Bovenliggend',
        'type' => 'Type',
        'target' => 'Openen in',
        'is_active' => 'Actief',
        'sort' => 'Volgorde',
    ],

    'filters' => [
        'type' => 'Type',
        'is_active' => 'Actief',
    ],

    'actions' => [
        'fetch_favicon' => 'Favicon ophalen',
        'fetch_favicon_tooltip' => 'Bepaal de favicon op basis van de bovenstaande URL',
        'fetch_favicons' => 'Favicons ophalen',
        'export' => 'Exporteren',
        'import' => 'Importeren',
    ],

    'import' => [
        'description' => 'Upload een JSON-bestand dat eerder uit dit menu is geëxporteerd. Items worden aan het bestaande menu toegevoegd, tenzij je de vervangoptie inschakelt.',
        'file' => 'Exportbestand (JSON)',
        'replace' => 'Huidige menu vervangen',
        'replace_helper' => 'Alle bestaande menu-items worden vóór het importeren verwijderd.',
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon gevonden',
        'favicon_updated' => 'Favicon bijgewerkt',
        'favicon_not_found' => 'Geen favicon gevonden',
        'enter_url_first' => 'Voer eerst een externe URL in',
        'favicons_resolved' => ':count favicon(s) gevonden',
        'import_success' => ':count menu-item(s) geïmporteerd',
        'import_invalid' => 'Het bestand is geen geldige export van het topbalkmenu',
    ],

    'command' => [
        'disabled' => 'Favicon-resolutie is uitgeschakeld (filament-topbar-menu.enable_favicons); niets te doen.',
        'nothing_to_refresh' => 'Geen menu-items hebben een favicon-update nodig.',
        'not_found' => 'niet gevonden',
        'resolved_summary' => ':resolved van :total favicon(s) gevonden.',
    ],

];
