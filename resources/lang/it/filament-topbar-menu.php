<?php

return [

    'labels' => [
        'model' => 'Voce del menu superiore',
        'plural_model' => 'Menu superiore',
        'navigation' => 'Menu superiore',
    ],

    'sections' => [
        'link' => 'Collegamento',
        'appearance' => 'Aspetto e comportamento',
        'record' => 'Record',
    ],

    'fields' => [
        'label' => 'Etichetta',
        'parent' => [
            'label' => 'Voce padre',
            'placeholder' => 'Nessuna (livello principale)',
            'helper' => 'Le voci con un padre vengono mostrate nel menu a discesa del padre.',
        ],
        'type' => [
            'label' => 'Tipo di collegamento',
            'options' => [
                'url' => 'URL',
                'route' => 'Route Laravel',
            ],
        ],
        'target' => [
            'label' => 'Apri in',
            'options' => [
                'self' => 'Stessa scheda',
                'blank' => 'Nuova scheda',
            ],
        ],
        'url' => [
            'label' => 'URL',
            'placeholder' => 'https://example.com',
        ],
        'route' => [
            'label' => 'Nome della route',
        ],
        'route_parameters' => [
            'key' => 'Parametro',
            'value' => 'Valore',
        ],
        'icon' => [
            'label' => 'Icona',
            'placeholder' => 'heroicon-o-link',
            'helper' => 'Qualsiasi nome di icona supportato da Filament, ad esempio una Heroicon. Consulta l\'elenco completo su :link.',
        ],
        'favicon_url' => [
            'label' => 'URL della favicon',
        ],
        'visibility' => [
            'label' => 'Visibile a',
            'placeholder' => 'Tutti',
            'helper' => 'La visibilità basata sui ruoli (chiave "roles") viene preservata se impostata direttamente sul record.',
            'roles' => 'Limitato ai ruoli',
            'options' => [
                'auth' => 'Solo utenti autenticati',
                'guest' => 'Solo ospiti',
            ],
        ],
        'hide_on_current_domain' => [
            'label' => 'Nascondi sul proprio dominio',
            'helper' => 'L\'elemento non viene mostrato sul servizio il cui dominio coincide con quello del link — per un menu condiviso tra più servizi.',
        ],
        'sort' => [
            'label' => 'Ordine',
            'helper' => 'I valori più bassi vengono mostrati per primi. Puoi anche trascinare le righe nell\'elenco.',
        ],
        'is_active' => [
            'label' => 'Attivo',
        ],
    ],

    'columns' => [
        'parent' => 'Padre',
        'type' => 'Tipo',
        'target' => 'Apri in',
        'is_active' => 'Attivo',
        'sort' => 'Ordine',
        'hidden_on_current_domain' => 'Nascosto su questo dominio',
        'hidden_on_current_domain_tooltip' => 'Nascosto su questo dominio; visibile sugli altri servizi della flotta.',
        'resolved_url' => 'Link risolto',
        'created_at' => 'Creato',
        'updated_at' => 'Aggiornato',
    ],

    'filters' => [
        'type' => 'Tipo',
        'is_active' => 'Attivo',
    ],

    'actions' => [
        'fetch_favicon' => 'Recupera favicon',
        'fetch_favicon_tooltip' => 'Determina la favicon dall\'URL sopra indicato',
        'fetch_favicons' => 'Recupera favicon',
        'export' => 'Esporta',
        'import' => 'Importa',
        'create_table' => 'Crea tabella del menu',
    ],

    'missing_table' => [
        'title' => 'Tabella del menu non trovata',
        'description' => 'La tabella del menu non esiste ancora sulla connessione al database configurata — di solito subito dopo il passaggio di FILAMENT_TOPBAR_MENU_DB_CONNECTION. Il pulsante qui sopra la crea; un menu ancora presente sulla connessione predefinita di questa app viene copiato automaticamente.',
    ],

    'import' => [
        'description' => 'Carica un file JSON esportato in precedenza da questo menu. Le voci vengono aggiunte al menu esistente, a meno che non attivi l\'opzione di sostituzione.',
        'file' => 'File di esportazione (JSON)',
        'replace' => 'Sostituisci il menu attuale',
        'replace_helper' => 'Tutte le voci di menu esistenti vengono eliminate prima dell\'importazione.',
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon individuata',
        'favicon_updated' => 'Favicon aggiornata',
        'favicon_not_found' => 'Nessuna favicon trovata',
        'enter_url_first' => 'Inserisci prima un URL esterno',
        'favicons_resolved' => ':count favicon individuate',
        'import_success' => ':count voci di menu importate',
        'import_invalid' => 'Il file non è un\'esportazione valida del menu superiore',
        'table_created' => 'Tabella del menu creata',
        'table_copied' => ':count voci di menu copiate dalla connessione predefinita',
    ],

    'command' => [
        'disabled' => 'La risoluzione delle favicon è disattivata (filament-topbar-menu.enable_favicons); niente da fare.',
        'nothing_to_refresh' => 'Nessuna voce di menu richiede l\'aggiornamento della favicon.',
        'not_found' => 'non trovata',
        'resolved_summary' => 'Individuate :resolved favicon su :total.',
        'table_created' => 'La tabella del menu ":table" è stata creata sulla connessione ":connection".',
        'table_exists' => 'La tabella del menu ":table" esiste già sulla connessione ":connection"; niente da fare.',
        'copied' => 'Copiate :count voci di menu dalla connessione predefinita a ":connection".',
        'copy_skipped_existing' => 'Copia saltata: il menu su ":connection" contiene già delle voci; il menu locale di questa app non viene unito.',
        'copy_skipped_no_dedicated' => 'Copia saltata: nessuna connessione dedicata configurata; il menu si trova già sulla connessione predefinita.',
        'copy_skipped_no_source' => 'Copia saltata: la connessione predefinita non ha una tabella del menu da cui copiare.',
    ],

];
