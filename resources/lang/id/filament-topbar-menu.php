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
        'record' => 'Data',
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
        'favicon_data' => [
            'label' => 'Favicon tertanam',
            'upload' => 'Unggah ikon',
            'helper' => 'Disimpan di dalam menu itu sendiri, bukan di disk. Gunakan bila ikon tidak dapat diakses dari peramban setiap pengunjung — situs di balik login atau layanan khusus internal. Ikon tertanam lebih diutamakan daripada URL favicon.',
        ],
        'visibility' => [
            'label' => 'Terlihat oleh',
            'placeholder' => 'Semua orang',
            'helper' => 'Visibilitas berbasis peran (kunci "roles") tetap dipertahankan jika diatur langsung pada record.',
            'roles' => 'Dibatasi untuk peran',
            'options' => [
                'auth' => 'Hanya pengguna terautentikasi',
                'guest' => 'Hanya tamu',
            ],
        ],
        'hide_on_current_domain' => [
            'label' => 'Sembunyikan di domainnya sendiri',
            'helper' => 'Item tidak ditampilkan pada layanan yang domainnya sama dengan domain tautan — untuk satu menu bersama beberapa layanan.',
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
        'hidden_on_current_domain' => 'Disembunyikan di domain ini',
        'hidden_on_current_domain_tooltip' => 'Disembunyikan di domain ini; tetap terlihat di layanan armada lainnya.',
        'resolved_url' => 'Tautan hasil',
        'created_at' => 'Dibuat',
        'updated_at' => 'Diperbarui',
    ],

    'filters' => [
        'type' => 'Jenis',
        'is_active' => 'Aktif',
    ],

    'actions' => [
        'fetch_favicon' => 'Ambil favicon',
        'fetch_favicon_tooltip' => 'Tentukan favicon dari URL di atas',
        'fetch_favicons' => 'Ambil favicon',
        'embed_favicon' => 'Ambil dan tanam',
        'embed_favicon_tooltip' => 'Unduh favicon dari URL di atas dan simpan gambarnya sendiri',
        'clear_favicon_data' => 'Hapus',
        'export' => 'Ekspor',
        'import' => 'Impor',
        'create_table' => 'Buat tabel menu',
        'flush_cache' => 'Bersihkan cache menu',
    ],

    'missing_table' => [
        'title' => 'Tabel menu tidak ditemukan',
        'description' => 'Tabel menu belum ada pada koneksi basis data yang dikonfigurasi — biasanya tepat setelah mengganti FILAMENT_TOPBAR_MENU_DB_CONNECTION. Tombol di atas akan membuatnya; menu yang masih ada pada koneksi bawaan aplikasi ini akan disalin secara otomatis.',
    ],

    'import' => [
        'description' => 'Unggah file JSON yang sebelumnya diekspor dari menu ini. Item ditambahkan ke menu yang ada kecuali Anda mengaktifkan opsi penggantian.',
        'file' => 'File ekspor (JSON)',
        'replace' => 'Ganti menu saat ini',
        'replace_helper' => 'Semua item menu yang ada dihapus sebelum impor.',
    ],

    'notifications' => [
        'favicon_resolved' => 'Favicon berhasil ditentukan',
        'favicon_embedded' => 'Favicon diunduh dan ditanam',
        'favicon_not_found' => 'Favicon tidak ditemukan',
        'favicon_upload_rejected' => 'Berkas tidak disimpan: harus berupa gambar berukuran maksimal :size',
        'enter_url_first' => 'Masukkan URL eksternal terlebih dahulu',
        'favicons_resolved' => ':count favicon berhasil ditentukan',
        'favicons_overridden' => 'Pada :count item, ikon tertanam lebih diutamakan daripada tautan — topbar tetap menampilkannya.',
        'import_success' => ':count item menu diimpor',
        'import_invalid' => 'File ini bukan ekspor menu bilah atas yang valid',
        'cache_flushed' => 'Cache menu dibersihkan',
        'table_created' => 'Tabel menu dibuat',
        'table_copied' => ':count item menu disalin dari koneksi bawaan',
    ],

    'command' => [
        'disabled' => 'Pengambilan favicon dinonaktifkan (filament-topbar-menu.enable_favicons); tidak ada yang dilakukan.',
        'nothing_to_refresh' => 'Tidak ada item menu yang memerlukan pembaruan favicon.',
        'not_found' => 'tidak ditemukan',
        'embedded' => 'ditanam (:type)',
        'resolved_summary' => ':resolved dari :total favicon berhasil ditentukan.',
        'table_created' => 'Tabel menu ":table" dibuat pada koneksi ":connection".',
        'table_exists' => 'Tabel menu ":table" sudah ada pada koneksi ":connection"; tidak ada yang perlu dilakukan.',
        'column_added' => 'Kolom ":column" ditambahkan ke tabel menu ":table".',
        'copied' => ':count item menu disalin dari koneksi bawaan ke ":connection".',
        'copy_skipped_existing' => 'Penyalinan dilewati: menu pada ":connection" sudah berisi item; menu lokal aplikasi ini tidak digabungkan.',
        'copy_skipped_no_dedicated' => 'Penyalinan dilewati: tidak ada koneksi khusus yang dikonfigurasi; menu sudah berada pada koneksi bawaan.',
        'copy_skipped_no_source' => 'Penyalinan dilewati: koneksi bawaan tidak memiliki tabel menu untuk disalin.',
    ],

    'mobile' => [
        'open' => 'Buka menu',
        'close' => 'Tutup menu',
    ],

];
