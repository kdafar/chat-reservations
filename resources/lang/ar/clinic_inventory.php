<?php

return [

    'clinic_item' => [
        'sections' => [
            'item_details' => 'تفاصيل الصنف',
            'inventory_units' => 'المخزون والوحدات',
            'inventory_units_description' => 'تكوين كيفية تخزين هذا الصنف واستهلاكه.',
            'pricing' => 'التسعير (لكل وحدة استخدام)',
        ],
        'fields' => [
            'branch' => 'الفرع',
            'type' => 'النوع',
            'active' => 'مفعل',
            'name_en' => 'الاسم (إنجليزي)',
            'name_ar' => 'الاسم (عربي)',
            'track_stock' => 'تتبع المخزون',
            'stock_unit' => 'وحدة التخزين',
            'usage_unit' => 'وحدة الاستخدام (أساسية)',
            'conversion_factor' => 'معامل التحويل',
            'consume_step' => 'خطوة الاستهلاك',
            'is_billable' => 'قابل للفوترة للمريض',
            'default_cost' => 'التكلفة الافتراضية',
            'default_price' => 'السعر الافتراضي',
            'name' => 'الاسم',
            'stock_q' => 'مخزون؟',
            'unit' => 'الوحدة',
            'cost' => 'التكلفة',
            'price' => 'السعر',
        ],
        'placeholders' => [
            'stock_unit' => 'مثال: علبة، قارورة',
            'usage_unit' => 'مثال: قرص، مل، وحدة',
        ],
        'helpers' => [
            'branch' => 'اتركه فارغًا للصنف المشترك الذي يمكن استخدامه في جميع الفروع.',
            'track_stock' => 'تفعيل تتبع المخزون الدقيق لهذه المادة الاستهلاكية.',
            'stock_unit' => 'كيف تشتريه من الموردين.',
            'usage_unit' => 'يستهلك الأطباء هذه الوحدة. التكلفة/السعر لكل هذه الوحدة.',
            'conversion_factor' => 'كم عدد وحدات الاستخدام في 1 وحدة تخزين؟ (مثال: 1 علبة = 50 قرص)',
            'consume_step' => 'خطوة زيادة موصى بها للاستخدام (مثال: 0.5 لـ مل).',
            'default_cost' => 'التكلفة لكل وحدة استخدام (مثال: التكلفة لكل مل / وحدة).',
            'default_price' => 'السعر لكل وحدة استخدام.',
        ],
        'types' => [
            'consumable' => 'مادة استهلاكية',
            'service' => 'خدمة',
        ],
        'shared' => 'مشترك',
        'filter_stockable' => 'قابل للتخزين',
    ],

    'clinic_item_stock' => [
        'sections' => [
            'stock_base' => 'المخزون (الوحدات الأساسية)',
        ],
        'fields' => [
            'branch' => 'الفرع',
            'clinic_item' => 'صنف العيادة',
            'qty_on_hand_base' => 'الكمية المتوفرة (أساسية)',
            'min_threshold_base' => 'حد المخزون المنخفض (أساسي)',
            'bin_location' => 'موقع التخزين',
            'item' => 'الصنف',
            'on_hand_base' => 'المتوفر (أساسي)',
            'threshold' => 'الحد',
            'bin' => 'الموقع',
            'qty_stock_units' => 'الكمية (وحدات التخزين)',
            'qty_base' => 'الكمية (وحدات أساسية)',
            'notes' => 'ملاحظات',
        ],
        'helpers' => [
            'qty_stock_units' => 'أدخل عدد العلب/القوارير/الزجاجات. سيتم التحويل إلى الأساسي باستخدام معامل التحويل.',
            'qty_base' => 'أو أدخل الكمية الأساسية مباشرة (مل/وحدات/قطع).',
        ],
        'actions' => [
            'receive_stock' => 'استلام مخزون',
        ],
        'notifications' => [
            'enter_qty' => 'الرجاء إدخال الكمية (وحدات تخزين) أو الكمية (وحدات أساسية)',
            'received_success' => 'تم استلام المخزون بنجاح',
        ],
    ],

    'clinic_package' => [
        'sections' => [
            'package' => 'الباقة',
            'package_items' => 'أصناف الباقة',
            'package_items_description' => 'حدد أصناف العيادة المطلوبة (الكمية الأساسية). تستخدم هذه لبناء طلب المخزون عندما يختار الطبيب الباقة.',
        ],
        'fields' => [
            'branch' => 'الفرع',
            'active' => 'مفعل',
            'name_en' => 'الاسم (إنجليزي)',
            'name_ar' => 'الاسم (عربي)',
            'default_price' => 'السعر الافتراضي',
            'clinic_item' => 'صنف العيادة',
            'qty_base' => 'الكمية (أساسية)',
            'consumable' => 'مادة استهلاكية',
            'name' => 'الاسم',
            'price' => 'السعر',
            'items' => 'الأصناف',
        ],
        'helpers' => [
            'branch' => 'اتركه فارغًا لجعله عالميًا (متاح لجميع الفروع).',
            'consumable' => 'إذا كان غير مفعل، فهو "غير استهلاكي" ويمكن أن يكون للعلم فقط.',
        ],
        'actions' => [
            'add_item' => 'إضافة صنف',
        ],
        'global' => 'عام',
    ],

    'clinic_stock_movement' => [
        'fields' => [
            'at' => 'في',
            'branch' => 'الفرع',
            'item' => 'الصنف',
            'type' => 'النوع',
            'delta_base' => 'التغيير (أساسي)',
            'before' => 'قبل',
            'after' => 'بعد',
            'by_user_id' => 'بواسطة (معرف المستخدم)',
            'related_type' => 'النوع المرتبط',
            'related_id' => 'المعرف المرتبط',
            'notes' => 'ملاحظات',
        ],
        'types' => [
            'restock' => 'إعادة تخزين',
            'consume' => 'استهلاك',
            'adjustment' => 'تسوية',
        ],
    ],

    'visit_stock_request' => [
        'sections' => [
            'request' => 'الطلب',
        ],
        'fields' => [
            'visit' => 'الزيارة',
            'branch' => 'الفرع',
            'requested_by' => 'مقدم الطلب',
            'fulfilled_by' => 'تم التنفيذ بواسطة',
            'fulfilled_at' => 'تاريخ التنفيذ',
            'items_availability' => 'الأصناف والتوفر',
            'req_by' => 'الطالب',
            'time' => 'الوقت',
            'fulfillment_notes' => 'ملاحظات التنفيذ',
            'resume_visit_status' => 'استئناف حالة الزيارة',
            'reason' => 'السبب',
        ],
        'statuses' => [
            'pending' => 'معلق',
            'fulfilled' => 'تم التنفيذ',
            'cancelled' => 'ملغى',
        ],
        'resume_options' => [
            'awaiting_doctor' => 'بانتظار الطبيب (الطابور)',
            'in_progress' => 'قيد التنفيذ (الغرفة)',
        ],
        'helpers' => [
            'resume_status' => 'إلى أين يذهب المريض بعد وصول هذا المخزون؟',
        ],
        'actions' => [
            'fulfill' => 'تنفيذ',
            'cancel' => 'إلغاء',
        ],
        'notifications' => [
            'fulfilled_title' => 'تم تنفيذ طلب المخزون',
            'fulfilled_body' => 'تم استهلاك الأصناف وتحديث الزيارة.',
            'fulfill_failed_title' => 'فشل التنفيذ',
        ],
        'empty_items' => 'لا توجد أصناف',
        'visit_prefix' => 'زيارة رقم ',
    ],
];
