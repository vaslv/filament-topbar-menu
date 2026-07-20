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
            'helper' => 'هر نام آیکونی که Filament پشتیبانی می‌کند، مثلاً یک Heroicon.',
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
    ],

    'command' => [
        'disabled' => 'تشخیص فاوآیکون غیرفعال است (filament-topbar-menu.enable_favicons)؛ کاری برای انجام نیست.',
        'nothing_to_refresh' => 'هیچ آیتم منویی به تازه‌سازی فاوآیکون نیاز ندارد.',
        'not_found' => 'یافت نشد',
        'resolved_summary' => ':resolved از :total فاوآیکون تشخیص داده شد.',
    ],

];
