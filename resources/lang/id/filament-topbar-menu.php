<?php

return [

    'labels' => [
        'model' => 'Item menu bilah atas',
        'plural_model' => 'Menu bilah atas',
        'navigation' => 'Menu bilah atas',
    ],

    'sections' => [
        'link' => 'Tautan',
        'appearance' => 'Tampilan & perilaku',
    ],

    'fields' => [
        'label' => 'Label',
        'parent' => [
            'label' => 'Item induk',
            'placeholder' => 'Tidak ada (tingkat atas)',
            'helper' => 'Item yang memiliki induk ditampilkan di menu tarik-turun induknya.',
        ],
        'type' => [
            'label' => 'Jenis tautan',
            'options' => [
                'url' => 'URL',
                'route' => 'Route Laravel',
            ],
        ],
        'target' => [
            'label' => 'Buka di',
            'options' => [
                'self' => 'Tab yang sama',
                'blank' => 'Tab baru',
            ],
        ],
        'url' => [
            'label' => 'URL',
            'placeholder' => 'https://example.com',
        ],
        'route' => [
            'label' => 'Nama route',
        ],
        'route_parameters' => [
            'key' => 'Parameter',
            'value' => 'Nilai',
        ],
        'icon' => [
            'label' => 'Ikon',
            'placeholder' => 'heroicon-o-link',
            'helper' => 'Nama ikon apa pun yang didukung Filament, misalnya Heroicon. Lihat daftar lengkapnya di :link.',
        ],
        'favicon_url' => [
            'label' => 'URL favicon',
        ],
        'visibility' => [
            'label' => 'Terlihat oleh',
            'placeholder' => 'Semua orang',
            'helper' => 'Visibilitas berbasis peran (kunci "roles") tetap dipertahankan jika diatur langsung pada record.',
            'options' => [
                'auth' => 'Hanya pengguna terautentikasi',
                'guest' => 'Hanya tamu',
            ],
        ],
        'sort' => [
            'label' => 'Urutan',
            'helper' => 'Nilai yang lebih kecil ditampilkan lebih dulu. Anda juga dapat menyeret baris di daftar.',
        ],
        'is_active' => [
            'label' => 'Aktif',
        ],
    ],

    'columns' => [
        'parent' => 'Induk',
        'type' => 'Jenis',
        'target' => 'Buka di',
        'is_active' => 'Aktif',
        'sort' => 'Urutan',
    ],

    'filters' => [
        'type' => 'Jenis',
        'is_active' => 'Aktif',
    ],

    'actions' => [
        'fetch_favicon' => 'Ambil favicon',
        'fetch_favicon_tooltip' => 'Tentukan favicon dari URL di atas',
        'fetch_favicons' => 'Ambil favicon',
        'export' => 'Ekspor',
        'import' => 'Impor',
    ],

    'import' => [
        'description' => 'Unggah file JSON yang sebelumnya diekspor dari menu ini. Item ditambahkan ke menu yang ada kecuali Anda mengaktifkan opsi penggantian.',
        'file' => 'File ekspor (JSON)',
        'replace' => 'Ganti menu saat ini',
        'replace_helper' => 'Semua item menu yang ada dihapus sebelum impor.',
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon berhasil ditentukan',
        'favicon_updated' => 'Favicon diperbarui',
        'favicon_not_found' => 'Favicon tidak ditemukan',
        'enter_url_first' => 'Masukkan URL eksternal terlebih dahulu',
        'favicons_resolved' => ':count favicon berhasil ditentukan',
        'import_success' => ':count item menu diimpor',
        'import_invalid' => 'File ini bukan ekspor menu bilah atas yang valid',
    ],

    'command' => [
        'disabled' => 'Pengambilan favicon dinonaktifkan (filament-topbar-menu.enable_favicons); tidak ada yang dilakukan.',
        'nothing_to_refresh' => 'Tidak ada item menu yang memerlukan pembaruan favicon.',
        'not_found' => 'tidak ditemukan',
        'resolved_summary' => ':resolved dari :total favicon berhasil ditentukan.',
    ],

];
