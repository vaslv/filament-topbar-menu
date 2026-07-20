<?php

return [

    'labels' => [
        'model' => 'Topbar-Menüeintrag',
        'plural_model' => 'Topbar-Menü',
        'navigation' => 'Topbar-Menü',
    ],

    'sections' => [
        'link' => 'Link',
        'appearance' => 'Darstellung & Verhalten',
    ],

    'fields' => [
        'label' => 'Bezeichnung',
        'parent' => [
            'label' => 'Übergeordneter Eintrag',
            'placeholder' => 'Keiner (oberste Ebene)',
            'helper' => 'Einträge mit einem übergeordneten Eintrag erscheinen in dessen Dropdown.',
        ],
        'type' => [
            'label' => 'Linktyp',
            'options' => [
                'url' => 'URL',
                'route' => 'Laravel-Route',
            ],
        ],
        'target' => [
            'label' => 'Ziel',
            'options' => [
                'self' => 'Gleicher Tab',
                'blank' => 'Neuer Tab',
            ],
        ],
        'url' => [
            'label' => 'URL',
            'placeholder' => 'https://example.com',
        ],
        'route' => [
            'label' => 'Routenname',
        ],
        'route_parameters' => [
            'key' => 'Parameter',
            'value' => 'Wert',
        ],
        'icon' => [
            'label' => 'Symbol',
            'placeholder' => 'heroicon-o-link',
            'helper' => 'Ein beliebiger von Filament unterstützter Symbolname, z. B. ein Heroicon. Die vollständige Liste findest du unter :link.',
        ],
        'favicon_url' => [
            'label' => 'Favicon-URL',
        ],
        'visibility' => [
            'label' => 'Sichtbar für',
            'placeholder' => 'Alle',
            'helper' => 'Rollenbasierte Sichtbarkeit (der Schlüssel „roles“) bleibt erhalten, wenn sie direkt am Datensatz gesetzt ist.',
            'options' => [
                'auth' => 'Nur angemeldete Benutzer',
                'guest' => 'Nur Gäste',
            ],
        ],
        'sort' => [
            'label' => 'Sortierung',
            'helper' => 'Kleinere Werte werden zuerst angezeigt. Zeilen lassen sich in der Liste auch per Drag & Drop sortieren.',
        ],
        'is_active' => [
            'label' => 'Aktiv',
        ],
    ],

    'columns' => [
        'parent' => 'Übergeordnet',
        'type' => 'Typ',
        'target' => 'Ziel',
        'is_active' => 'Aktiv',
        'sort' => 'Sortierung',
    ],

    'filters' => [
        'type' => 'Typ',
        'is_active' => 'Aktiv',
    ],

    'actions' => [
        'fetch_favicon' => 'Favicon abrufen',
        'fetch_favicon_tooltip' => 'Favicon aus der obigen URL ermitteln',
        'fetch_favicons' => 'Favicons abrufen',
        'export' => 'Exportieren',
        'import' => 'Importieren',
    ],

    'import' => [
        'description' => 'Laden Sie eine zuvor aus diesem Menü exportierte JSON-Datei hoch. Einträge werden an das bestehende Menü angehängt, sofern das Ersetzen nicht aktiviert ist.',
        'file' => 'Exportdatei (JSON)',
        'replace' => 'Aktuelles Menü ersetzen',
        'replace_helper' => 'Alle vorhandenen Menüeinträge werden vor dem Import gelöscht.',
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon ermittelt',
        'favicon_updated' => 'Favicon aktualisiert',
        'favicon_not_found' => 'Kein Favicon gefunden',
        'enter_url_first' => 'Bitte zuerst eine externe URL eingeben',
        'favicons_resolved' => ':count Favicon(s) ermittelt',
        'import_success' => ':count Menüeintrag/-einträge importiert',
        'import_invalid' => 'Die Datei ist kein gültiger Menü-Export',
    ],

    'command' => [
        'disabled' => 'Die Favicon-Auflösung ist deaktiviert (filament-topbar-menu.enable_favicons); nichts zu tun.',
        'nothing_to_refresh' => 'Keine Menüeinträge benötigen eine Favicon-Aktualisierung.',
        'not_found' => 'nicht gefunden',
        'resolved_summary' => ':resolved von :total Favicon(s) ermittelt.',
    ],

];
