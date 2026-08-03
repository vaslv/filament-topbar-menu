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
        'record' => 'Datensatz',
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
        'favicon_data' => [
            'label' => 'Eingebettetes Favicon',
            'upload' => 'Symbol hochladen',
            'helper' => 'Wird im Menü selbst gespeichert, nicht auf einer Festplatte. Nützlich, wenn das Symbol nicht aus dem Browser jedes Besuchers erreichbar ist — eine Seite hinter einer Anmeldung oder ein rein interner Dienst. Ein eingebettetes Symbol hat Vorrang vor der Favicon-URL.',
        ],
        'visibility' => [
            'label' => 'Sichtbar für',
            'placeholder' => 'Alle',
            'helper' => 'Rollenbasierte Sichtbarkeit (der Schlüssel „roles“) bleibt erhalten, wenn sie direkt am Datensatz gesetzt ist.',
            'roles' => 'Auf Rollen beschränkt',
            'options' => [
                'auth' => 'Nur angemeldete Benutzer',
                'guest' => 'Nur Gäste',
            ],
        ],
        'hide_on_current_domain' => [
            'label' => 'Auf eigener Domain ausblenden',
            'helper' => 'Der Eintrag wird auf dem Dienst nicht angezeigt, dessen Domain mit der des Links übereinstimmt — für ein gemeinsames Menü mehrerer Dienste.',
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
        'hidden_on_current_domain' => 'Auf dieser Domain ausgeblendet',
        'hidden_on_current_domain_tooltip' => 'Auf dieser Domain ausgeblendet; auf den übrigen Diensten der Flotte sichtbar.',
        'resolved_url' => 'Aufgelöster Link',
        'created_at' => 'Erstellt',
        'updated_at' => 'Aktualisiert',
    ],

    'filters' => [
        'type' => 'Typ',
        'is_active' => 'Aktiv',
    ],

    'actions' => [
        'fetch_favicon' => 'Favicon abrufen',
        'fetch_favicon_tooltip' => 'Favicon aus der obigen URL ermitteln',
        'fetch_favicons' => 'Favicons abrufen',
        'embed_favicon' => 'Abrufen und einbetten',
        'embed_favicon_tooltip' => 'Das Favicon von der obigen URL herunterladen und das Bild selbst speichern',
        'clear_favicon_data' => 'Entfernen',
        'export' => 'Exportieren',
        'import' => 'Importieren',
        'create_table' => 'Menütabelle erstellen',
        'flush_cache' => 'Menü-Cache leeren',
    ],

    'missing_table' => [
        'title' => 'Menütabelle nicht gefunden',
        'description' => 'Die Menütabelle existiert auf der konfigurierten Datenbankverbindung noch nicht — meist direkt nach dem Umschalten von FILAMENT_TOPBAR_MENU_DB_CONNECTION. Die Schaltfläche oben erstellt sie; ein noch auf der Standardverbindung dieser App vorhandenes Menü wird automatisch übernommen.',
    ],

    'import' => [
        'description' => 'Laden Sie eine zuvor aus diesem Menü exportierte JSON-Datei hoch. Einträge werden an das bestehende Menü angehängt, sofern das Ersetzen nicht aktiviert ist.',
        'file' => 'Exportdatei (JSON)',
        'replace' => 'Aktuelles Menü ersetzen',
        'replace_helper' => 'Alle vorhandenen Menüeinträge werden vor dem Import gelöscht.',
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon ermittelt',
        'favicon_embedded' => 'Favicon heruntergeladen und eingebettet',
        'favicon_not_found' => 'Kein Favicon gefunden',
        'favicon_upload_rejected' => 'Die Datei wurde nicht gespeichert: erforderlich ist ein Bild von höchstens :size',
        'enter_url_first' => 'Bitte zuerst eine externe URL eingeben',
        'favicons_resolved' => ':count Favicon(s) ermittelt',
        'favicons_overridden' => 'Bei :count Eintrag/Einträgen überschreibt ein eingebettetes Symbol den Link — die Topbar zeigt weiterhin dieses.',
        'import_success' => ':count Menüeintrag/-einträge importiert',
        'import_invalid' => 'Die Datei ist kein gültiger Menü-Export',
        'cache_flushed' => 'Menü-Cache geleert',
        'table_created' => 'Menütabelle erstellt',
        'table_copied' => ':count Menüeintrag/-einträge von der Standardverbindung kopiert',
    ],

    'command' => [
        'disabled' => 'Die Favicon-Auflösung ist deaktiviert (filament-topbar-menu.enable_favicons); nichts zu tun.',
        'nothing_to_refresh' => 'Keine Menüeinträge benötigen eine Favicon-Aktualisierung.',
        'not_found' => 'nicht gefunden',
        'embedded' => 'eingebettet (:type)',
        'resolved_summary' => ':resolved von :total Favicon(s) ermittelt.',
        'table_created' => 'Menütabelle ":table" wurde auf der Verbindung ":connection" erstellt.',
        'table_exists' => 'Menütabelle ":table" existiert bereits auf der Verbindung ":connection"; nichts zu tun.',
        'column_added' => 'Spalte ":column" zur Menütabelle ":table" hinzugefügt.',
        'copied' => ':count Menüeintrag/-einträge von der Standardverbindung nach ":connection" kopiert.',
        'copy_skipped_existing' => 'Kopieren übersprungen: Das Menü auf ":connection" enthält bereits Einträge; das lokale Menü dieser App wird nicht hineingemischt.',
        'copy_skipped_no_dedicated' => 'Kopieren übersprungen: Keine dedizierte Verbindung konfiguriert; das Menü liegt bereits auf der Standardverbindung.',
        'copy_skipped_no_source' => 'Kopieren übersprungen: Auf der Standardverbindung gibt es keine Menütabelle zum Kopieren.',
    ],

];
