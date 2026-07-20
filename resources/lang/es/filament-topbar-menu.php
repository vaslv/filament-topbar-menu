<?php

return [

    'labels' => [
        'model' => 'Elemento del menú superior',
        'plural_model' => 'Menú superior',
        'navigation' => 'Menú superior',
    ],

    'sections' => [
        'link' => 'Enlace',
        'appearance' => 'Apariencia y comportamiento',
    ],

    'fields' => [
        'label' => 'Etiqueta',
        'parent' => [
            'label' => 'Elemento padre',
            'placeholder' => 'Ninguno (nivel superior)',
            'helper' => 'Los elementos con un padre se muestran en el desplegable del padre.',
        ],
        'type' => [
            'label' => 'Tipo de enlace',
            'options' => [
                'url' => 'URL',
                'route' => 'Ruta de Laravel',
            ],
        ],
        'target' => [
            'label' => 'Destino',
            'options' => [
                'self' => 'Misma pestaña',
                'blank' => 'Nueva pestaña',
            ],
        ],
        'url' => [
            'label' => 'URL',
            'placeholder' => 'https://example.com',
        ],
        'route' => [
            'label' => 'Nombre de la ruta',
        ],
        'route_parameters' => [
            'key' => 'Parámetro',
            'value' => 'Valor',
        ],
        'icon' => [
            'label' => 'Icono',
            'placeholder' => 'heroicon-o-link',
            'helper' => 'Cualquier nombre de icono compatible con Filament, por ejemplo un Heroicon. Consulta la lista completa en :link.',
        ],
        'favicon_url' => [
            'label' => 'URL del favicon',
        ],
        'visibility' => [
            'label' => 'Visible para',
            'placeholder' => 'Todos',
            'helper' => 'La visibilidad por roles (la clave «roles») se conserva cuando se define directamente en el registro.',
            'options' => [
                'auth' => 'Solo usuarios autenticados',
                'guest' => 'Solo invitados',
            ],
        ],
        'sort' => [
            'label' => 'Orden',
            'helper' => 'Los valores menores se muestran primero. También puedes arrastrar las filas en la lista.',
        ],
        'is_active' => [
            'label' => 'Activo',
        ],
    ],

    'columns' => [
        'parent' => 'Padre',
        'type' => 'Tipo',
        'target' => 'Destino',
        'is_active' => 'Activo',
        'sort' => 'Orden',
    ],

    'filters' => [
        'type' => 'Tipo',
        'is_active' => 'Activo',
    ],

    'actions' => [
        'fetch_favicon' => 'Obtener favicon',
        'fetch_favicon_tooltip' => 'Resolver el favicon a partir de la URL de arriba',
        'fetch_favicons' => 'Obtener favicons',
        'export' => 'Exportar',
        'import' => 'Importar',
    ],

    'import' => [
        'description' => 'Sube un archivo JSON exportado previamente desde este menú. Los elementos se añaden al menú existente salvo que actives el reemplazo.',
        'file' => 'Archivo de exportación (JSON)',
        'replace' => 'Reemplazar el menú actual',
        'replace_helper' => 'Todos los elementos de menú existentes se eliminarán antes de importar.',
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon resuelto',
        'favicon_updated' => 'Favicon actualizado',
        'favicon_not_found' => 'No se encontró ningún favicon',
        'enter_url_first' => 'Introduce primero una URL externa',
        'favicons_resolved' => ':count favicon(s) resuelto(s)',
        'import_success' => ':count elemento(s) de menú importado(s)',
        'import_invalid' => 'El archivo no es una exportación de menú válida',
    ],

    'command' => [
        'disabled' => 'La resolución de favicons está desactivada (filament-topbar-menu.enable_favicons); nada que hacer.',
        'nothing_to_refresh' => 'Ningún elemento del menú necesita actualizar su favicon.',
        'not_found' => 'no encontrado',
        'resolved_summary' => 'Resueltos :resolved de :total favicon(s).',
    ],

];
