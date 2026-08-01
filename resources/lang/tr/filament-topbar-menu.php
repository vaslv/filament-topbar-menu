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
        'record' => 'Kayıt',
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
            'roles' => 'Rollerle sınırlı',
            'options' => [
                'auth' => 'Yalnızca oturum açmış kullanıcılar',
                'guest' => 'Yalnızca ziyaretçiler',
            ],
        ],
        'hide_on_current_domain' => [
            'label' => 'Kendi alan adında gizle',
            'helper' => 'Öğe, alan adı bağlantının alan adıyla eşleşen serviste gösterilmez — birden çok servisin ortak menüsü için.',
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
        'hidden_on_current_domain' => 'Bu alan adında gizli',
        'hidden_on_current_domain_tooltip' => 'Bu alan adında gizli; filonun diğer servislerinde görünür.',
        'resolved_url' => 'Çözümlenen bağlantı',
        'created_at' => 'Oluşturulma',
        'updated_at' => 'Güncellenme',
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
        'create_table' => 'Menü tablosunu oluştur',
    ],

    'missing_table' => [
        'title' => 'Menü tablosu bulunamadı',
        'description' => 'Menü tablosu, yapılandırılmış veritabanı bağlantısında henüz mevcut değil — genellikle FILAMENT_TOPBAR_MENU_DB_CONNECTION değiştirildikten hemen sonra. Yukarıdaki düğme tabloyu oluşturur; bu uygulamanın varsayılan bağlantısında hâlâ duran bir menü otomatik olarak kopyalanır.',
    ],

    'import' => [
        'description' => 'Daha önce bu menüden dışa aktarılmış bir JSON dosyası yükleyin. Değiştirme seçeneğini etkinleştirmediğiniz sürece öğeler mevcut menüye eklenir.',
        'file' => 'Dışa aktarma dosyası (JSON)',
        'replace' => 'Mevcut menüyü değiştir',
        'replace_helper' => 'İçe aktarmadan önce mevcut tüm menü öğeleri silinir.',
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon çözümlendi',
        'favicon_not_found' => 'Favicon bulunamadı',
        'enter_url_first' => 'Önce harici bir URL girin',
        'favicons_resolved' => ':count favicon çözümlendi',
        'import_success' => ':count menü öğesi içe aktarıldı',
        'import_invalid' => 'Dosya geçerli bir üst menü dışa aktarımı değil',
        'table_created' => 'Menü tablosu oluşturuldu',
        'table_copied' => 'Varsayılan bağlantıdan :count menü öğesi kopyalandı',
    ],

    'command' => [
        'disabled' => 'Favicon çözümleme devre dışı (filament-topbar-menu.enable_favicons); yapılacak bir şey yok.',
        'nothing_to_refresh' => 'Favicon yenilemesi gereken menü öğesi yok.',
        'not_found' => 'bulunamadı',
        'resolved_summary' => ':total favicon\'dan :resolved tanesi çözümlendi.',
        'table_created' => '":table" menü tablosu ":connection" bağlantısında oluşturuldu.',
        'table_exists' => '":table" menü tablosu ":connection" bağlantısında zaten mevcut; yapılacak bir şey yok.',
        'copied' => 'Varsayılan bağlantıdan ":connection" bağlantısına :count menü öğesi kopyalandı.',
        'copy_skipped_existing' => 'Kopyalama atlandı: ":connection" üzerindeki menüde zaten öğeler var; bu uygulamanın yerel menüsü onunla birleştirilmedi.',
        'copy_skipped_no_dedicated' => 'Kopyalama atlandı: özel bir bağlantı yapılandırılmamış; menü zaten varsayılan bağlantıda.',
        'copy_skipped_no_source' => 'Kopyalama atlandı: varsayılan bağlantıda kopyalanacak bir menü tablosu yok.',
    ],

];
