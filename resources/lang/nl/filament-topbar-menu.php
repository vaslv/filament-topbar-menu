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
        'record' => 'Record',
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
            'helper' => 'Elke pictogramnaam die door Filament wordt ondersteund, bijvoorbeeld een Heroicon. Bekijk de volledige lijst op :link.',
        ],
        'favicon_url' => [
            'label' => 'Favicon-URL',
        ],
        'visibility' => [
            'label' => 'Zichtbaar voor',
            'placeholder' => 'Iedereen',
            'helper' => 'Op rollen gebaseerde zichtbaarheid (de sleutel "roles") blijft behouden wanneer deze rechtstreeks op het record is ingesteld.',
            'roles' => 'Beperkt tot rollen',
            'options' => [
                'auth' => 'Alleen ingelogde gebruikers',
                'guest' => 'Alleen gasten',
            ],
        ],
        'hide_on_current_domain' => [
            'label' => 'Verbergen op eigen domein',
            'helper' => 'Het item wordt niet getoond op de service waarvan het domein overeenkomt met dat van de link — voor één gedeeld menu over meerdere services.',
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
        'hidden_on_current_domain' => 'Verborgen op dit domein',
        'hidden_on_current_domain_tooltip' => 'Verborgen op dit domein; zichtbaar op de overige services van de vloot.',
        'resolved_url' => 'Uiteindelijke link',
        'created_at' => 'Aangemaakt',
        'updated_at' => 'Bijgewerkt',
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
        'create_table' => 'Menutabel aanmaken',
    ],

    'missing_table' => [
        'title' => 'Menutabel niet gevonden',
        'description' => 'De menutabel bestaat nog niet op de geconfigureerde databaseverbinding — meestal direct na het omschakelen van FILAMENT_TOPBAR_MENU_DB_CONNECTION. De knop hierboven maakt de tabel aan; een menu dat nog op de standaardverbinding van deze app staat, wordt automatisch gekopieerd.',
    ],

    'import' => [
        'description' => 'Upload een JSON-bestand dat eerder uit dit menu is geëxporteerd. Items worden aan het bestaande menu toegevoegd, tenzij je de vervangoptie inschakelt.',
        'file' => 'Exportbestand (JSON)',
        'replace' => 'Huidige menu vervangen',
        'replace_helper' => 'Alle bestaande menu-items worden vóór het importeren verwijderd.',
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon gevonden',
        'favicon_not_found' => 'Geen favicon gevonden',
        'enter_url_first' => 'Voer eerst een externe URL in',
        'favicons_resolved' => ':count favicon(s) gevonden',
        'import_success' => ':count menu-item(s) geïmporteerd',
        'import_invalid' => 'Het bestand is geen geldige export van het topbalkmenu',
        'table_created' => 'Menutabel aangemaakt',
        'table_copied' => ':count menu-item(s) gekopieerd van de standaardverbinding',
    ],

    'command' => [
        'disabled' => 'Favicon-resolutie is uitgeschakeld (filament-topbar-menu.enable_favicons); niets te doen.',
        'nothing_to_refresh' => 'Geen menu-items hebben een favicon-update nodig.',
        'not_found' => 'niet gevonden',
        'resolved_summary' => ':resolved van :total favicon(s) gevonden.',
        'table_created' => 'Menutabel ":table" is aangemaakt op de verbinding ":connection".',
        'table_exists' => 'Menutabel ":table" bestaat al op de verbinding ":connection"; niets te doen.',
        'copied' => ':count menu-item(s) gekopieerd van de standaardverbinding naar ":connection".',
        'copy_skipped_existing' => 'Kopiëren overgeslagen: het menu op ":connection" bevat al items; het lokale menu van deze app wordt er niet mee samengevoegd.',
        'copy_skipped_no_dedicated' => 'Kopiëren overgeslagen: er is geen speciale verbinding geconfigureerd; het menu staat al op de standaardverbinding.',
        'copy_skipped_no_source' => 'Kopiëren overgeslagen: de standaardverbinding heeft geen menutabel om uit te kopiëren.',
    ],

];
