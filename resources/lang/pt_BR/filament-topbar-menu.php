<?php

return [

    'labels' => [
        'model' => 'Item do menu superior',
        'plural_model' => 'Menu superior',
        'navigation' => 'Menu superior',
    ],

    'sections' => [
        'link' => 'Link',
        'appearance' => 'Aparência e comportamento',
        'record' => 'Registro',
    ],

    'fields' => [
        'label' => 'Rótulo',
        'parent' => [
            'label' => 'Item pai',
            'placeholder' => 'Nenhum (nível superior)',
            'helper' => 'Itens com um pai são exibidos no menu suspenso do pai.',
        ],
        'type' => [
            'label' => 'Tipo de link',
            'options' => [
                'url' => 'URL',
                'route' => 'Rota do Laravel',
            ],
        ],
        'target' => [
            'label' => 'Abrir em',
            'options' => [
                'self' => 'Mesma aba',
                'blank' => 'Nova aba',
            ],
        ],
        'url' => [
            'label' => 'URL',
            'placeholder' => 'https://example.com',
        ],
        'route' => [
            'label' => 'Nome da rota',
        ],
        'route_parameters' => [
            'key' => 'Parâmetro',
            'value' => 'Valor',
        ],
        'icon' => [
            'label' => 'Ícone',
            'placeholder' => 'heroicon-o-link',
            'helper' => 'Qualquer nome de ícone suportado pelo Filament, por exemplo, um Heroicon. Veja a lista completa em :link.',
        ],
        'favicon_url' => [
            'label' => 'URL do favicon',
        ],
        'visibility' => [
            'label' => 'Visível para',
            'placeholder' => 'Todos',
            'helper' => 'A visibilidade baseada em papéis (chave "roles") é preservada quando definida diretamente no registro.',
            'roles' => 'Restrito a papéis',
            'options' => [
                'auth' => 'Apenas usuários autenticados',
                'guest' => 'Apenas visitantes',
            ],
        ],
        'hide_on_current_domain' => [
            'label' => 'Ocultar no próprio domínio',
            'helper' => 'O item não é exibido no serviço cujo domínio coincide com o do link — para um menu compartilhado entre vários serviços.',
        ],
        'sort' => [
            'label' => 'Ordem',
            'helper' => 'Valores menores são exibidos primeiro. Você também pode arrastar as linhas na lista.',
        ],
        'is_active' => [
            'label' => 'Ativo',
        ],
    ],

    'columns' => [
        'parent' => 'Pai',
        'type' => 'Tipo',
        'target' => 'Abrir em',
        'is_active' => 'Ativo',
        'sort' => 'Ordem',
        'hidden_on_current_domain' => 'Oculto neste domínio',
        'hidden_on_current_domain_tooltip' => 'Oculto neste domínio; visível nos demais serviços da frota.',
        'resolved_url' => 'Link resolvido',
        'created_at' => 'Criado em',
        'updated_at' => 'Atualizado em',
    ],

    'filters' => [
        'type' => 'Tipo',
        'is_active' => 'Ativo',
    ],

    'actions' => [
        'fetch_favicon' => 'Buscar favicon',
        'fetch_favicon_tooltip' => 'Resolver o favicon a partir da URL acima',
        'fetch_favicons' => 'Buscar favicons',
        'export' => 'Exportar',
        'import' => 'Importar',
        'create_table' => 'Criar tabela do menu',
        'flush_cache' => 'Limpar o cache do menu',
    ],

    'missing_table' => [
        'title' => 'Tabela do menu não encontrada',
        'description' => 'A tabela do menu ainda não existe na conexão de banco de dados configurada — geralmente logo após a troca de FILAMENT_TOPBAR_MENU_DB_CONNECTION. O botão acima a cria; um menu ainda presente na conexão padrão deste aplicativo é copiado automaticamente.',
    ],

    'import' => [
        'description' => 'Envie um arquivo JSON exportado anteriormente deste menu. Os itens são adicionados ao menu existente, a menos que a opção de substituição esteja ativada.',
        'file' => 'Arquivo de exportação (JSON)',
        'replace' => 'Substituir o menu atual',
        'replace_helper' => 'Todos os itens de menu existentes são excluídos antes da importação.',
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon resolvido',
        'favicon_not_found' => 'Nenhum favicon encontrado',
        'enter_url_first' => 'Informe primeiro uma URL externa',
        'favicons_resolved' => ':count favicon(s) resolvido(s)',
        'import_success' => ':count item(ns) de menu importado(s)',
        'import_invalid' => 'O arquivo não é uma exportação válida do menu superior',
        'cache_flushed' => 'Cache do menu limpo',
        'table_created' => 'Tabela do menu criada',
        'table_copied' => ':count item(ns) de menu copiado(s) da conexão padrão',
    ],

    'command' => [
        'disabled' => 'A resolução de favicons está desativada (filament-topbar-menu.enable_favicons); nada a fazer.',
        'nothing_to_refresh' => 'Nenhum item de menu precisa de atualização de favicon.',
        'not_found' => 'não encontrado',
        'resolved_summary' => ':resolved de :total favicon(s) resolvido(s).',
        'table_created' => 'A tabela do menu ":table" foi criada na conexão ":connection".',
        'table_exists' => 'A tabela do menu ":table" já existe na conexão ":connection"; nada a fazer.',
        'copied' => ':count item(ns) de menu copiado(s) da conexão padrão para ":connection".',
        'copy_skipped_existing' => 'Cópia ignorada: o menu em ":connection" já tem itens; o menu local deste aplicativo não será mesclado.',
        'copy_skipped_no_dedicated' => 'Cópia ignorada: nenhuma conexão dedicada configurada; o menu já vive na conexão padrão.',
        'copy_skipped_no_source' => 'Cópia ignorada: a conexão padrão não tem tabela de menu para copiar.',
    ],

];
