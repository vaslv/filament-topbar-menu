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
        'record' => 'Registro',
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
            'roles' => 'Restringido a roles',
            'options' => [
                'auth' => 'Solo usuarios autenticados',
                'guest' => 'Solo invitados',
            ],
        ],
        'hide_on_current_domain' => [
            'label' => 'Ocultar en su propio dominio',
            'helper' => 'El elemento no se muestra en el servicio cuyo dominio coincide con el del enlace — para un menú compartido entre varios servicios.',
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
        'hidden_on_current_domain' => 'Oculto en este dominio',
        'hidden_on_current_domain_tooltip' => 'Oculto en este dominio; visible en los demás servicios de la flota.',
        'resolved_url' => 'Enlace resuelto',
        'created_at' => 'Creado',
        'updated_at' => 'Actualizado',
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
        'create_table' => 'Crear tabla del menú',
    ],

    'missing_table' => [
        'title' => 'Tabla del menú no encontrada',
        'description' => 'La tabla del menú aún no existe en la conexión de base de datos configurada — normalmente justo después de cambiar FILAMENT_TOPBAR_MENU_DB_CONNECTION. El botón de arriba la crea; un menú que siga presente en la conexión predeterminada de esta aplicación se copia automáticamente.',
    ],

    'import' => [
        'description' => 'Sube un archivo JSON exportado previamente desde este menú. Los elementos se añaden al menú existente salvo que actives el reemplazo.',
        'file' => 'Archivo de exportación (JSON)',
        'replace' => 'Reemplazar el menú actual',
        'replace_helper' => 'Todos los elementos de menú existentes se eliminarán antes de importar.',
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon resuelto',
        'favicon_not_found' => 'No se encontró ningún favicon',
        'enter_url_first' => 'Introduce primero una URL externa',
        'favicons_resolved' => ':count favicon(s) resuelto(s)',
        'import_success' => ':count elemento(s) de menú importado(s)',
        'import_invalid' => 'El archivo no es una exportación de menú válida',
        'table_created' => 'Tabla del menú creada',
        'table_copied' => ':count elemento(s) del menú copiado(s) desde la conexión predeterminada',
    ],

    'command' => [
        'disabled' => 'La resolución de favicons está desactivada (filament-topbar-menu.enable_favicons); nada que hacer.',
        'nothing_to_refresh' => 'Ningún elemento del menú necesita actualizar su favicon.',
        'not_found' => 'no encontrado',
        'resolved_summary' => 'Resueltos :resolved de :total favicon(s).',
        'table_created' => 'La tabla del menú «:table» se creó en la conexión «:connection».',
        'table_exists' => 'La tabla del menú «:table» ya existe en la conexión «:connection»; nada que hacer.',
        'copied' => 'Se copiaron :count elemento(s) del menú desde la conexión predeterminada a «:connection».',
        'copy_skipped_existing' => 'Copia omitida: el menú en «:connection» ya tiene elementos; no se fusiona el menú local de esta aplicación.',
        'copy_skipped_no_dedicated' => 'Copia omitida: no hay una conexión dedicada configurada; el menú ya vive en la conexión predeterminada.',
        'copy_skipped_no_source' => 'Copia omitida: la conexión predeterminada no tiene tabla de menú desde la que copiar.',
    ],

];
