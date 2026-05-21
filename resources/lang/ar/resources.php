<?php

return [
    'booking' => [
        'label' => 'الموعد',
        'label_plural' => 'المواعيد',
        'nav_label' => 'المواعيد',
        'empty_heading' => 'لا توجد مواعيد بعد',
        'empty_description' => 'أنشئ موعدًا أو انتظر وصول حجوزات واتساب.',
    ],

    'visit' => [
        'label' => 'الزيارة',
        'label_plural' => 'الزيارات',
        'nav_label' => 'الزيارات',
        'empty_heading' => 'لا توجد زيارات بعد',
        'empty_description' => 'تُنشأ الزيارات تلقائيًا عند تسجيل دخول الحجز. استخدم صفحة الحجوزات لبدء مسار المريض.',
    ],

    'patient' => [
        'label' => 'المريض',
        'label_plural' => 'المرضى',
        'nav_label' => 'المرضى',
        'empty_heading' => 'لا يوجد مرضى بعد',
        'empty_description' => 'تُنشأ سجلات المرضى تلقائيًا عند إجراء حجز. يمكنك أيضًا إضافة مريض يدويًا من هنا.',
    ],

    'doctor' => [
        'label' => 'الطبيب',
        'label_plural' => 'الأطباء',
        'nav_label' => 'الأطباء',
        'empty_heading' => 'لا يوجد أطباء بعد',
        'empty_description' => 'أضف ملف طبيب لتمكين الحجوزات والجداول وتتبع التعويضات.',
    ],

    'branch' => [
        'label' => 'الفرع',
        'label_plural' => 'الفروع',
        'nav_label' => 'الفروع',
    ],

    'branch_availability_rule' => [
        'label' => 'قاعدة توفر الفرع',
        'label_plural' => 'قواعد توفر الفرع',
        'nav_label' => 'جدول المواعيد',
    ],

    'branch_blackout' => [
        'label' => 'تعطيل الفرع',
        'label_plural' => 'تعطيلات الفرع',
        'nav_label' => 'إغلاقات العيادة',
        'empty_heading' => 'لا توجد إغلاقات بعد',
        'empty_description' => 'أضف إغلاقات العيادة لمنع الحجز في التواريخ غير المتاحة.',
    ],

    'clinic_item' => [
        'label' => 'صنف العيادة',
        'label_plural' => 'أصناف العيادة',
        'nav_label' => 'أصناف العيادة',
        'empty_heading' => 'لا توجد أصناف بعد',
        'empty_description' => 'أصناف العيادة هي المستهلكات والأدوية والخدمات المستخدمة خلال الزيارات. تظهر الأصناف المتعقَّبة ضمن المخزون.',
    ],

    'clinic_item_stock' => [
        'label' => 'مخزون صنف العيادة',
        'label_plural' => 'مخزون أصناف العيادة',
        'nav_label' => 'مخزون أصناف العيادة',
    ],

    'clinic_package' => [
        'label' => 'باقة العيادة',
        'label_plural' => 'باقات العيادة',
        'nav_label' => 'الباقات',
    ],

    'clinic_stock_movement' => [
        'label' => 'حركة المخزون',
        'label_plural' => 'حركات المخزون',
        'nav_label' => 'حركات المخزون',
    ],

    'visit_stock_request' => [
        'label' => 'طلب مخزون للزيارة',
        'label_plural' => 'طلبات المخزون للزيارات',
        'nav_label' => 'طلبات المخزون',
    ],

    'doctor_compensation_ledger' => [
        'label' => 'سجل تعويض الطبيب',
        'label_plural' => 'سجلات تعويض الأطباء',
        'nav_label' => 'سجلات تعويض الأطباء',
    ],

    'doctor_compensation_profile' => [
        'label' => 'ملف تعويض الطبيب',
        'label_plural' => 'ملفات تعويض الأطباء',
        'nav_label' => 'ملفات تعويض الأطباء',
    ],

    'follow_up_plan' => [
        'label' => 'خطة المتابعة',
        'label_plural' => 'خطط المتابعة',
        'nav_label' => 'خطط المتابعة',
    ],

    'journal_entry' => [
        'label' => 'قيد محاسبي',
        'label_plural' => 'القيود المحاسبية',
        'nav_label' => 'القيود المحاسبية',
        'empty_heading' => 'لا توجد قيود محاسبية بعد',
        'empty_description' => 'تُرحَّل معظم القيود تلقائيًا عند تسجيل المدفوعات والمصروفات وحركات المخزون. القيود اليدوية متاحة للتسويات.',
    ],

    'chart_of_account' => [
        'label' => 'الحساب',
        'label_plural' => 'دليل الحسابات',
        'nav_label' => 'دليل الحسابات',
    ],

    'accounting_period' => [
        'label' => 'الفترة المحاسبية',
        'label_plural' => 'الفترات المحاسبية',
        'nav_label' => 'الفترات المحاسبية',
    ],

    'bank_reconciliation' => [
        'label' => 'التسوية البنكية',
        'label_plural' => 'التسويات البنكية',
        'nav_label' => 'التسويات البنكية',
    ],

    'expense' => [
        'label' => 'المصروف',
        'label_plural' => 'المصروفات',
        'nav_label' => 'المصروفات',
        'empty_heading' => 'لا توجد مصروفات بعد',
        'empty_description' => 'سجّل فواتير الموردين هنا. حدّد حساب الدفع للسداد فورًا، أو اتركه فارغًا لتقييده على الذمم الدائنة.',
    ],

    'vendor' => [
        'label' => 'المورد',
        'label_plural' => 'الموردون',
        'nav_label' => 'الموردون',
    ],
];
