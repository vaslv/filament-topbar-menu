<?php

return [

    'labels' => [
        'model' => 'آیتم منوی نوار بالا',
        'plural_model' => 'منوی نوار بالا',
        'navigation' => 'منوی نوار بالا',
    ],

    'sections' => [
        'link' => 'پیوند',
        'appearance' => 'ظاهر و رفتار',
    ],

    'fields' => [
        'label' => 'برچسب',
        'parent' => [
            'label' => 'آیتم والد',
            'placeholder' => 'هیچ‌کدام (سطح بالا)',
            'helper' => 'آیتم‌های دارای والد در منوی کشویی والد نمایش داده می‌شوند.',
        ],
        'type' => [
            'label' => 'نوع پیوند',
            'options' => [
                'url' => 'URL',
                'route' => 'مسیر Laravel',
            ],
        ],
        'target' => [
            'label' => 'باز شدن در',
            'options' => [
                'self' => 'همان زبانه',
                'blank' => 'زبانه جدید',
            ],
        ],
        'url' => [
            'label' => 'URL',
            'placeholder' => 'https://example.com',
        ],
        'route' => [
            'label' => 'نام مسیر',
        ],
        'route_parameters' => [
            'key' => 'پارامتر',
            'value' => 'مقدار',
        ],
        'icon' => [
            'label' => 'آیکون',
            'placeholder' => 'heroicon-o-link',
            'helper' => 'هر نام آیکونی که Filament پشتیبانی می‌کند، مثلاً یک Heroicon. فهرست کامل را در :link ببینید.',
        ],
        'favicon_url' => [
            'label' => 'آدرس فاوآیکون',
        ],
        'visibility' => [
            'label' => 'قابل مشاهده برای',
            'placeholder' => 'همه',
            'helper' => 'نمایش بر اساس نقش (کلید «roles») هنگام تنظیم مستقیم روی رکورد حفظ می‌شود.',
            'options' => [
                'auth' => 'فقط کاربران واردشده',
                'guest' => 'فقط مهمانان',
            ],
        ],
        'hide_on_current_domain' => [
            'label' => 'پنهان کردن در دامنه خودش',
            'helper' => 'این مورد در سرویسی که دامنه‌اش با دامنه پیوند یکسان است نمایش داده نمی‌شود — برای منوی مشترک چند سرویس.',
        ],
        'sort' => [
            'label' => 'ترتیب',
            'helper' => 'مقادیر کوچک‌تر ابتدا نمایش داده می‌شوند. همچنین می‌توانید ردیف‌ها را در فهرست بکشید.',
        ],
        'is_active' => [
            'label' => 'فعال',
        ],
    ],

    'columns' => [
        'parent' => 'والد',
        'type' => 'نوع',
        'target' => 'باز شدن در',
        'is_active' => 'فعال',
        'sort' => 'ترتیب',
        'hidden_on_current_domain' => 'در این دامنه پنهان است',
        'hidden_on_current_domain_tooltip' => 'در این دامنه پنهان است؛ در سایر سرویس‌های ناوگان دیده می‌شود.',
    ],

    'filters' => [
        'type' => 'نوع',
        'is_active' => 'فعال',
    ],

    'actions' => [
        'fetch_favicon' => 'دریافت فاوآیکون',
        'fetch_favicon_tooltip' => 'تشخیص فاوآیکون از URL بالا',
        'fetch_favicons' => 'دریافت فاوآیکون‌ها',
        'export' => 'برون‌بری',
        'import' => 'درون‌ریزی',
        'create_table' => 'ایجاد جدول منو',
    ],

    'missing_table' => [
        'title' => 'جدول منو یافت نشد',
        'description' => 'جدول منو هنوز روی اتصال پایگاه‌داده پیکربندی‌شده وجود ندارد — معمولاً بلافاصله پس از تغییر FILAMENT_TOPBAR_MENU_DB_CONNECTION. دکمه بالا آن را ایجاد می‌کند؛ منویی که هنوز روی اتصال پیش‌فرض این برنامه باشد به‌صورت خودکار کپی می‌شود.',
    ],

    'import' => [
        'description' => 'یک فایل JSON که قبلاً از این منو برون‌بری شده است بارگذاری کنید. آیتم‌ها به منوی موجود افزوده می‌شوند مگر اینکه گزینه جایگزینی را فعال کنید.',
        'file' => 'فایل برون‌بری (JSON)',
        'replace' => 'جایگزینی منوی فعلی',
        'replace_helper' => 'همه آیتم‌های موجود منو پیش از درون‌ریزی حذف می‌شوند.',
    ],

    'notifications' => [
        'favicon_resolved' => 'فاوآیکون تشخیص داده شد',
        'favicon_updated' => 'فاوآیکون به‌روزرسانی شد',
        'favicon_not_found' => 'فاوآیکونی یافت نشد',
        'enter_url_first' => 'ابتدا یک URL خارجی وارد کنید',
        'favicons_resolved' => ':count فاوآیکون تشخیص داده شد',
        'import_success' => ':count آیتم منو درون‌ریزی شد',
        'import_invalid' => 'این فایل یک برون‌بری معتبر از منوی نوار بالا نیست',
        'table_created' => 'جدول منو ایجاد شد',
        'table_copied' => ':count آیتم منو از اتصال پیش‌فرض کپی شد',
    ],

    'command' => [
        'disabled' => 'تشخیص فاوآیکون غیرفعال است (filament-topbar-menu.enable_favicons)؛ کاری برای انجام نیست.',
        'nothing_to_refresh' => 'هیچ آیتم منویی به تازه‌سازی فاوآیکون نیاز ندارد.',
        'not_found' => 'یافت نشد',
        'resolved_summary' => ':resolved از :total فاوآیکون تشخیص داده شد.',
        'table_created' => 'جدول منو «:table» روی اتصال «:connection» ایجاد شد.',
        'table_exists' => 'جدول منو «:table» از قبل روی اتصال «:connection» وجود دارد؛ کاری برای انجام نیست.',
        'copied' => ':count آیتم منو از اتصال پیش‌فرض به «:connection» کپی شد.',
        'copy_skipped_existing' => 'کپی انجام نشد: منوی روی «:connection» از قبل آیتم دارد؛ منوی محلی این برنامه با آن ادغام نمی‌شود.',
        'copy_skipped_no_dedicated' => 'کپی انجام نشد: اتصال اختصاصی پیکربندی نشده است؛ منو از قبل روی اتصال پیش‌فرض قرار دارد.',
        'copy_skipped_no_source' => 'کپی انجام نشد: روی اتصال پیش‌فرض جدول منویی برای کپی وجود ندارد.',
    ],

];
