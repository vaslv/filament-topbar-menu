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
        'hide_on_current_domain' => [
            'label' => '在自身域名下隐藏',
            'helper' => '当服务的域名与链接域名一致时不显示该菜单项——用于多个服务共享同一菜单。',
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
        'hidden_on_current_domain' => '已在此域名下隐藏',
        'hidden_on_current_domain_tooltip' => '已在此域名下隐藏；在其他服务上仍然可见。',
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
        'create_table' => '创建菜单表',
    ],

    'missing_table' => [
        'title' => '未找到菜单表',
        'description' => '菜单表在配置的数据库连接上尚不存在——通常发生在切换 FILAMENT_TOPBAR_MENU_DB_CONNECTION 之后。点击上方按钮即可创建；如果本应用默认连接上仍有菜单，将自动复制过来。',
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
        'table_created' => '菜单表已创建',
        'table_copied' => '已从默认连接复制 :count 个菜单项',
    ],

    'command' => [
        'disabled' => '网站图标解析已禁用（filament-topbar-menu.enable_favicons），无事可做。',
        'nothing_to_refresh' => '没有需要刷新网站图标的菜单项。',
        'not_found' => '未找到',
        'resolved_summary' => '已解析 :resolved / :total 个网站图标。',
        'table_created' => '菜单表 “:table” 已在 “:connection” 连接上创建。',
        'table_exists' => '菜单表 “:table” 已存在于 “:connection” 连接上，无需操作。',
        'copied' => '已从默认连接复制 :count 个菜单项到 “:connection”。',
        'copy_skipped_existing' => '已跳过复制：“:connection” 上的菜单已有菜单项，不会合并本应用的本地菜单。',
        'copy_skipped_no_dedicated' => '已跳过复制：未配置专用连接，菜单已位于默认连接上。',
        'copy_skipped_no_source' => '已跳过复制：默认连接上没有可供复制的菜单表。',
    ],

];
