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
            'helper' => "N'importe quel nom d'icône pris en charge par Filament, par exemple une Heroicon.",
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
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon résolu',
        'favicon_updated' => 'Favicon mis à jour',
        'favicon_not_found' => 'Aucun favicon trouvé',
        'enter_url_first' => "Saisissez d'abord une URL externe",
        'favicons_resolved' => ':count favicon(s) résolu(s)',
    ],

    'command' => [
        'disabled' => 'La résolution des favicons est désactivée (filament-topbar-menu.enable_favicons) ; rien à faire.',
        'nothing_to_refresh' => "Aucun élément de menu n'a besoin d'une mise à jour de favicon.",
        'not_found' => 'introuvable',
        'resolved_summary' => ':resolved favicon(s) résolu(s) sur :total.',
    ],

];
