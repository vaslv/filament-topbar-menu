<?php

return [

    'labels' => [
        'model' => 'Üst menü öğesi',
        'plural_model' => 'Üst menü',
        'navigation' => 'Üst menü',
    ],

    'sections' => [
        'link' => 'Bağlantı',
        'appearance' => 'Görünüm ve davranış',
    ],

    'fields' => [
        'label' => 'Etiket',
        'parent' => [
            'label' => 'Üst öğe',
            'placeholder' => 'Yok (en üst düzey)',
            'helper' => 'Üst öğesi olan öğeler, üst öğenin açılır menüsünde gösterilir.',
        ],
        'type' => [
            'label' => 'Bağlantı türü',
            'options' => [
                'url' => 'URL',
                'route' => 'Laravel rotası',
            ],
        ],
        'target' => [
            'label' => 'Açılacağı yer',
            'options' => [
                'self' => 'Aynı sekme',
                'blank' => 'Yeni sekme',
            ],
        ],
        'url' => [
            'label' => 'URL',
            'placeholder' => 'https://example.com',
        ],
        'route' => [
            'label' => 'Rota adı',
        ],
        'route_parameters' => [
            'key' => 'Parametre',
            'value' => 'Değer',
        ],
        'icon' => [
            'label' => 'Simge',
            'placeholder' => 'heroicon-o-link',
            'helper' => 'Filament tarafından desteklenen herhangi bir simge adı, örneğin bir Heroicon. Tüm listeye :link adresinden göz atabilirsiniz.',
        ],
        'favicon_url' => [
            'label' => 'Favicon URL\'si',
        ],
        'visibility' => [
            'label' => 'Kimlere görünür',
            'placeholder' => 'Herkes',
            'helper' => 'Rol tabanlı görünürlük ("roles" anahtarı), doğrudan kayıt üzerinde ayarlandığında korunur.',
            'options' => [
                'auth' => 'Yalnızca oturum açmış kullanıcılar',
                'guest' => 'Yalnızca ziyaretçiler',
            ],
        ],
        'sort' => [
            'label' => 'Sıralama',
            'helper' => 'Daha küçük değerler önce gösterilir. Listede satırları sürükleyerek de sıralayabilirsiniz.',
        ],
        'is_active' => [
            'label' => 'Etkin',
        ],
    ],

    'columns' => [
        'parent' => 'Üst öğe',
        'type' => 'Tür',
        'target' => 'Açılacağı yer',
        'is_active' => 'Etkin',
        'sort' => 'Sıralama',
    ],

    'filters' => [
        'type' => 'Tür',
        'is_active' => 'Etkin',
    ],

    'actions' => [
        'fetch_favicon' => 'Favicon getir',
        'fetch_favicon_tooltip' => 'Yukarıdaki URL\'den favicon\'u çözümle',
        'fetch_favicons' => 'Favicon\'ları getir',
        'export' => 'Dışa aktar',
        'import' => 'İçe aktar',
    ],

    'import' => [
        'description' => 'Daha önce bu menüden dışa aktarılmış bir JSON dosyası yükleyin. Değiştirme seçeneğini etkinleştirmediğiniz sürece öğeler mevcut menüye eklenir.',
        'file' => 'Dışa aktarma dosyası (JSON)',
        'replace' => 'Mevcut menüyü değiştir',
        'replace_helper' => 'İçe aktarmadan önce mevcut tüm menü öğeleri silinir.',
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon çözümlendi',
        'favicon_updated' => 'Favicon güncellendi',
        'favicon_not_found' => 'Favicon bulunamadı',
        'enter_url_first' => 'Önce harici bir URL girin',
        'favicons_resolved' => ':count favicon çözümlendi',
        'import_success' => ':count menü öğesi içe aktarıldı',
        'import_invalid' => 'Dosya geçerli bir üst menü dışa aktarımı değil',
    ],

    'command' => [
        'disabled' => 'Favicon çözümleme devre dışı (filament-topbar-menu.enable_favicons); yapılacak bir şey yok.',
        'nothing_to_refresh' => 'Favicon yenilemesi gereken menü öğesi yok.',
        'not_found' => 'bulunamadı',
        'resolved_summary' => ':total favicon\'dan :resolved tanesi çözümlendi.',
    ],

];
