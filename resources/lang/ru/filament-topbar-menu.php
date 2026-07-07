<?php

return [

    'labels' => [
        'model' => 'Пункт меню верхней панели',
        'plural_model' => 'Меню верхней панели',
        'navigation' => 'Меню верхней панели',
    ],

    'sections' => [
        'link' => 'Ссылка',
        'appearance' => 'Оформление и поведение',
    ],

    'fields' => [
        'label' => 'Название',
        'parent' => [
            'label' => 'Родительский пункт',
            'placeholder' => 'Нет (верхний уровень)',
            'helper' => 'Пункты с родителем отображаются в выпадающем меню родителя.',
        ],
        'type' => [
            'label' => 'Тип ссылки',
            'options' => [
                'url' => 'URL',
                'route' => 'Маршрут Laravel',
            ],
        ],
        'target' => [
            'label' => 'Открывать',
            'options' => [
                'self' => 'В текущей вкладке',
                'blank' => 'В новой вкладке',
            ],
        ],
        'url' => [
            'label' => 'URL',
            'placeholder' => 'https://example.com',
        ],
        'route' => [
            'label' => 'Имя маршрута',
        ],
        'route_parameters' => [
            'key' => 'Параметр',
            'value' => 'Значение',
        ],
        'icon' => [
            'label' => 'Иконка',
            'placeholder' => 'heroicon-o-link',
            'helper' => 'Любое имя иконки, поддерживаемое Filament, например Heroicon.',
        ],
        'favicon_url' => [
            'label' => 'URL фавикона',
        ],
        'visibility' => [
            'label' => 'Кому показывать',
            'placeholder' => 'Всем',
            'helper' => 'Видимость по ролям (ключ «roles») сохраняется, если задана напрямую у записи.',
            'options' => [
                'auth' => 'Только авторизованным',
                'guest' => 'Только гостям',
            ],
        ],
        'sort' => [
            'label' => 'Порядок',
            'helper' => 'Меньшие значения отображаются первыми. Также можно перетаскивать строки в списке.',
        ],
        'is_active' => [
            'label' => 'Активен',
        ],
    ],

    'columns' => [
        'parent' => 'Родитель',
        'type' => 'Тип',
        'target' => 'Открывать',
        'is_active' => 'Активен',
        'sort' => 'Порядок',
    ],

    'filters' => [
        'type' => 'Тип',
        'is_active' => 'Активен',
    ],

    'actions' => [
        'fetch_favicon' => 'Загрузить фавикон',
        'fetch_favicon_tooltip' => 'Определить фавикон по указанному выше URL',
        'fetch_favicons' => 'Загрузить фавиконы',
    ],

    'notifications' => [
        'favicon_resolved' => 'Фавикон определён',
        'favicon_updated' => 'Фавикон обновлён',
        'favicon_not_found' => 'Фавикон не найден',
        'enter_url_first' => 'Сначала укажите внешний URL',
        'favicons_resolved' => 'Определено фавиконов: :count',
    ],

    'command' => [
        'disabled' => 'Загрузка фавиконов отключена (filament-topbar-menu.enable_favicons); делать нечего.',
        'nothing_to_refresh' => 'Нет пунктов меню, требующих обновления фавикона.',
        'not_found' => 'не найдено',
        'resolved_summary' => 'Определено :resolved из :total фавиконов.',
    ],

];
