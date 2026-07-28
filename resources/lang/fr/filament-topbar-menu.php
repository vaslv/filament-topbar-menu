<?php

return [

    'labels' => [
        'model' => 'Élément du menu supérieur',
        'plural_model' => 'Menu supérieur',
        'navigation' => 'Menu supérieur',
    ],

    'sections' => [
        'link' => 'Lien',
        'appearance' => 'Apparence et comportement',
    ],

    'fields' => [
        'label' => 'Libellé',
        'parent' => [
            'label' => 'Élément parent',
            'placeholder' => 'Aucun (premier niveau)',
            'helper' => 'Les éléments ayant un parent apparaissent dans le menu déroulant du parent.',
        ],
        'type' => [
            'label' => 'Type de lien',
            'options' => [
                'url' => 'URL',
                'route' => 'Route Laravel',
            ],
        ],
        'target' => [
            'label' => 'Cible',
            'options' => [
                'self' => 'Même onglet',
                'blank' => 'Nouvel onglet',
            ],
        ],
        'url' => [
            'label' => 'URL',
            'placeholder' => 'https://example.com',
        ],
        'route' => [
            'label' => 'Nom de la route',
        ],
        'route_parameters' => [
            'key' => 'Paramètre',
            'value' => 'Valeur',
        ],
        'icon' => [
            'label' => 'Icône',
            'placeholder' => 'heroicon-o-link',
            'helper' => "N'importe quel nom d'icône pris en charge par Filament, par exemple une Heroicon. Parcourez la liste complète sur :link.",
        ],
        'favicon_url' => [
            'label' => 'URL du favicon',
        ],
        'visibility' => [
            'label' => 'Visible par',
            'placeholder' => 'Tout le monde',
            'helper' => 'La visibilité par rôles (la clé « roles ») est conservée lorsqu\'elle est définie directement sur l\'enregistrement.',
            'options' => [
                'auth' => 'Utilisateurs authentifiés uniquement',
                'guest' => 'Invités uniquement',
            ],
        ],
        'sort' => [
            'label' => 'Ordre',
            'helper' => 'Les valeurs les plus faibles sont affichées en premier. Vous pouvez aussi glisser-déposer les lignes dans la liste.',
        ],
        'is_active' => [
            'label' => 'Actif',
        ],
    ],

    'columns' => [
        'parent' => 'Parent',
        'type' => 'Type',
        'target' => 'Cible',
        'is_active' => 'Actif',
        'sort' => 'Ordre',
    ],

    'filters' => [
        'type' => 'Type',
        'is_active' => 'Actif',
    ],

    'actions' => [
        'fetch_favicon' => 'Récupérer le favicon',
        'fetch_favicon_tooltip' => "Résoudre le favicon à partir de l'URL ci-dessus",
        'fetch_favicons' => 'Récupérer les favicons',
        'export' => 'Exporter',
        'import' => 'Importer',
        'create_table' => 'Créer la table du menu',
    ],

    'missing_table' => [
        'title' => 'Table du menu introuvable',
        'description' => 'La table du menu n\'existe pas encore sur la connexion de base de données configurée — généralement juste après le changement de FILAMENT_TOPBAR_MENU_DB_CONNECTION. Le bouton ci-dessus la crée ; un menu encore présent sur la connexion par défaut de cette application est copié automatiquement.',
    ],

    'import' => [
        'description' => 'Téléversez un fichier JSON précédemment exporté depuis ce menu. Les éléments sont ajoutés au menu existant, sauf si le remplacement est activé.',
        'file' => "Fichier d'export (JSON)",
        'replace' => 'Remplacer le menu actuel',
        'replace_helper' => "Tous les éléments de menu existants seront supprimés avant l'import.",
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon résolu',
        'favicon_updated' => 'Favicon mis à jour',
        'favicon_not_found' => 'Aucun favicon trouvé',
        'enter_url_first' => "Saisissez d'abord une URL externe",
        'favicons_resolved' => ':count favicon(s) résolu(s)',
        'import_success' => ':count élément(s) de menu importé(s)',
        'import_invalid' => "Le fichier n'est pas un export de menu valide",
        'table_created' => 'Table du menu créée',
        'table_copied' => ':count élément(s) de menu copié(s) depuis la connexion par défaut',
    ],

    'command' => [
        'disabled' => 'La résolution des favicons est désactivée (filament-topbar-menu.enable_favicons) ; rien à faire.',
        'nothing_to_refresh' => "Aucun élément de menu n'a besoin d'une mise à jour de favicon.",
        'not_found' => 'introuvable',
        'resolved_summary' => ':resolved favicon(s) résolu(s) sur :total.',
        'table_created' => 'La table du menu « :table » a été créée sur la connexion « :connection ».',
        'table_exists' => 'La table du menu « :table » existe déjà sur la connexion « :connection » ; rien à faire.',
        'copied' => ':count élément(s) de menu copié(s) depuis la connexion par défaut vers « :connection ».',
        'copy_skipped_existing' => 'Copie ignorée : le menu sur « :connection » contient déjà des éléments ; le menu local de cette application n\'y est pas fusionné.',
        'copy_skipped_no_dedicated' => 'Copie ignorée : aucune connexion dédiée n\'est configurée ; le menu se trouve déjà sur la connexion par défaut.',
        'copy_skipped_no_source' => 'Copie ignorée : la connexion par défaut n\'a pas de table de menu à copier.',
    ],

];
