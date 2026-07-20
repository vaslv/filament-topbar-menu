<?php

return [

    'labels' => [
        'model' => '顶栏菜单项',
        'plural_model' => '顶栏菜单',
        'navigation' => '顶栏菜单',
    ],

    'sections' => [
        'link' => '链接',
        'appearance' => '外观与行为',
    ],

    'fields' => [
        'label' => '标签',
        'parent' => [
            'label' => '父级菜单项',
            'placeholder' => '无（顶层）',
            'helper' => '拥有父级的菜单项会显示在父级的下拉菜单中。',
        ],
        'type' => [
            'label' => '链接类型',
            'options' => [
                'url' => 'URL',
                'route' => 'Laravel 路由',
            ],
        ],
        'target' => [
            'label' => '打开方式',
            'options' => [
                'self' => '当前标签页',
                'blank' => '新标签页',
            ],
        ],
        'url' => [
            'label' => 'URL',
            'placeholder' => 'https://example.com',
        ],
        'route' => [
            'label' => '路由名称',
        ],
        'route_parameters' => [
            'key' => '参数',
            'value' => '值',
        ],
        'icon' => [
            'label' => '图标',
            'placeholder' => 'heroicon-o-link',
            'helper' => 'Filament 支持的任意图标名称，例如 Heroicon。可在 :link 查看完整列表。',
        ],
        'favicon_url' => [
            'label' => '网站图标 URL',
        ],
        'visibility' => [
            'label' => '可见范围',
            'placeholder' => '所有人',
            'helper' => '基于角色的可见性（"roles" 键）在直接设置于记录上时会被保留。',
            'options' => [
                'auth' => '仅登录用户',
                'guest' => '仅访客',
            ],
        ],
        'sort' => [
            'label' => '排序',
            'helper' => '数值越小越靠前。也可以在列表中拖动行进行排序。',
        ],
        'is_active' => [
            'label' => '启用',
        ],
    ],

    'columns' => [
        'parent' => '父级',
        'type' => '类型',
        'target' => '打开方式',
        'is_active' => '启用',
        'sort' => '排序',
    ],

    'filters' => [
        'type' => '类型',
        'is_active' => '启用',
    ],

    'actions' => [
        'fetch_favicon' => '获取网站图标',
        'fetch_favicon_tooltip' => '根据上方的 URL 解析网站图标',
        'fetch_favicons' => '获取网站图标',
        'export' => '导出',
        'import' => '导入',
    ],

    'import' => [
        'description' => '上传之前从此菜单导出的 JSON 文件。除非启用替换选项，否则菜单项将追加到现有菜单中。',
        'file' => '导出文件（JSON）',
        'replace' => '替换当前菜单',
        'replace_helper' => '导入前将删除所有现有菜单项。',
    ],

    'notifications' => [
        'favicon_resolved' => '网站图标已解析',
        'favicon_updated' => '网站图标已更新',
        'favicon_not_found' => '未找到网站图标',
        'enter_url_first' => '请先输入外部 URL',
        'favicons_resolved' => '已解析 :count 个网站图标',
        'import_success' => '已导入 :count 个菜单项',
        'import_invalid' => '该文件不是有效的顶栏菜单导出文件',
    ],

    'command' => [
        'disabled' => '网站图标解析已禁用（filament-topbar-menu.enable_favicons），无事可做。',
        'nothing_to_refresh' => '没有需要刷新网站图标的菜单项。',
        'not_found' => '未找到',
        'resolved_summary' => '已解析 :resolved / :total 个网站图标。',
    ],

];
