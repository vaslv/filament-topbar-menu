<?php

return [

    'labels' => [
        'model' => 'عنصر قائمة الشريط العلوي',
        'plural_model' => 'قائمة الشريط العلوي',
        'navigation' => 'قائمة الشريط العلوي',
    ],

    'sections' => [
        'link' => 'الرابط',
        'appearance' => 'المظهر والسلوك',
    ],

    'fields' => [
        'label' => 'التسمية',
        'parent' => [
            'label' => 'العنصر الأب',
            'placeholder' => 'بدون (المستوى الأعلى)',
            'helper' => 'العناصر التي لها عنصر أب تظهر في القائمة المنسدلة للعنصر الأب.',
        ],
        'type' => [
            'label' => 'نوع الرابط',
            'options' => [
                'url' => 'رابط URL',
                'route' => 'مسار Laravel',
            ],
        ],
        'target' => [
            'label' => 'فتح في',
            'options' => [
                'self' => 'نفس علامة التبويب',
                'blank' => 'علامة تبويب جديدة',
            ],
        ],
        'url' => [
            'label' => 'الرابط',
            'placeholder' => 'https://example.com',
        ],
        'route' => [
            'label' => 'اسم المسار',
        ],
        'route_parameters' => [
            'key' => 'المعامل',
            'value' => 'القيمة',
        ],
        'icon' => [
            'label' => 'الأيقونة',
            'placeholder' => 'heroicon-o-link',
            'helper' => 'أي اسم أيقونة يدعمه Filament، مثل أيقونات Heroicon. تصفح القائمة الكاملة على :link.',
        ],
        'favicon_url' => [
            'label' => 'رابط أيقونة الموقع (favicon)',
        ],
        'visibility' => [
            'label' => 'مرئي لـ',
            'placeholder' => 'الجميع',
            'helper' => 'يتم الحفاظ على الرؤية حسب الأدوار (مفتاح "roles") عند تعيينها مباشرة على السجل.',
            'options' => [
                'auth' => 'المستخدمون المسجّلون فقط',
                'guest' => 'الزوار فقط',
            ],
        ],
        'sort' => [
            'label' => 'الترتيب',
            'helper' => 'القيم الأصغر تظهر أولاً. يمكنك أيضاً سحب الصفوف في القائمة.',
        ],
        'is_active' => [
            'label' => 'مفعّل',
        ],
    ],

    'columns' => [
        'parent' => 'الأب',
        'type' => 'النوع',
        'target' => 'فتح في',
        'is_active' => 'مفعّل',
        'sort' => 'الترتيب',
    ],

    'filters' => [
        'type' => 'النوع',
        'is_active' => 'مفعّل',
    ],

    'actions' => [
        'fetch_favicon' => 'جلب أيقونة الموقع',
        'fetch_favicon_tooltip' => 'تحديد أيقونة الموقع من الرابط أعلاه',
        'fetch_favicons' => 'جلب أيقونات المواقع',
        'export' => 'تصدير',
        'import' => 'استيراد',
    ],

    'import' => [
        'description' => 'ارفع ملف JSON تم تصديره سابقاً من هذه القائمة. تُضاف العناصر إلى القائمة الحالية ما لم تُفعّل خيار الاستبدال.',
        'file' => 'ملف التصدير (JSON)',
        'replace' => 'استبدال القائمة الحالية',
        'replace_helper' => 'تُحذف جميع عناصر القائمة الحالية قبل الاستيراد.',
    ],

    'notifications' => [
        'favicon_resolved' => 'تم تحديد أيقونة الموقع',
        'favicon_updated' => 'تم تحديث أيقونة الموقع',
        'favicon_not_found' => 'لم يتم العثور على أيقونة الموقع',
        'enter_url_first' => 'أدخل رابطاً خارجياً أولاً',
        'favicons_resolved' => 'تم تحديد :count من أيقونات المواقع',
        'import_success' => 'تم استيراد :count من عناصر القائمة',
        'import_invalid' => 'الملف ليس تصديراً صالحاً لقائمة الشريط العلوي',
    ],

    'command' => [
        'disabled' => 'تحديد أيقونات المواقع معطّل (filament-topbar-menu.enable_favicons)؛ لا يوجد ما يمكن فعله.',
        'nothing_to_refresh' => 'لا توجد عناصر قائمة تحتاج إلى تحديث أيقونة الموقع.',
        'not_found' => 'غير موجود',
        'resolved_summary' => 'تم تحديد :resolved من أصل :total من أيقونات المواقع.',
    ],

];
