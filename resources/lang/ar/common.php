<?php

return [
    'user_badge' => [
        'all_branches' => 'جميع الفروع',
        'no_branch' => 'لا يوجد فرع مخصص',
        'multiple_branches' => ':count فروع',
    ],

    'nav' => [
        'clinic_operations' => 'العيادة — العمليات',
        'clinic_scheduling' => 'العيادة — الجدولة',
        'clinic_inventory' => 'العيادة — المخزون',
        'clinic_finance' => 'العيادة — المالية',
        'clinic_reports' => 'العيادة — التقارير',
        'clinic_setup' => 'العيادة — الإعدادات',
        'clinic_tools' => 'العيادة — الأدوات',
        'clinic_compliance' => 'العيادة — الامتثال',
        'accounting' => 'المحاسبة',
        'insurance' => 'التأمين',
        'inpatient' => 'التنويم',
    ],

    'actions' => [
        'create' => 'إنشاء',
        'edit' => 'تعديل',
        'delete' => 'حذف',
        'save' => 'حفظ',
        'cancel' => 'إلغاء',
        'view' => 'عرض',
        'refresh' => 'تحديث',
        'export' => 'تصدير',
        'import' => 'استيراد',
        'print' => 'طباعة',
        'close' => 'إغلاق',
        'submit' => 'إرسال',
        'back' => 'رجوع',
        'next' => 'التالي',
        'previous' => 'السابق',
        'search' => 'بحث',
        'filter' => 'تصفية',
        'reset' => 'إعادة تعيين',
        'apply' => 'تطبيق',
        'confirm' => 'تأكيد',
        'yes' => 'نعم',
        'no' => 'لا',
        'help' => 'مساعدة',
        'language' => 'اللغة',
    ],

    'visit_status' => [
        'draft' => 'مسودة',
        'awaiting_doctor' => 'في انتظار الطبيب',
        'in_progress' => 'قيد التنفيذ',
        'awaiting_stock' => 'في انتظار المخزون',
        'awaiting_payment' => 'في انتظار الدفع',
        'completed' => 'مكتملة',
        'cancelled' => 'ملغاة',
        'no_show' => 'لم يحضر',
    ],

    'booking_status' => [
        'draft' => 'مسودة',
        'hold' => 'معلق',
        'confirmed' => 'مؤكد',
        'checked_in' => 'تم تسجيل الدخول',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
        'no_show' => 'لم يحضر',
    ],

    'payment_method' => [
        'cash' => 'نقدًا',
        'knet' => 'كي نت',
        'card' => 'بطاقة ائتمان',
        'link' => 'رابط دفع',
        'transfer' => 'تحويل بنكي',
        'wallet' => 'محفظة',
        'other' => 'أخرى',
    ],

    'payment_status' => [
        'pending' => 'قيد الانتظار',
        'paid' => 'مدفوع',
        'refunded' => 'مسترد',
        'failed' => 'فشل',
        'cancelled' => 'ملغي',
    ],

    'je_status' => [
        'draft' => 'مسودة',
        'posted' => 'مرحّل',
        'reversed' => 'معكوس',
    ],

    'period_status' => [
        'open' => 'مفتوحة',
        'closed' => 'مغلقة',
    ],

    'stock_movement' => [
        'restock' => 'إعادة تخزين',
        'consume' => 'استهلاك',
        'adjust' => 'تسوية',
        'transfer' => 'تحويل',
    ],

    'fields' => [
        'name' => 'الاسم',
        'name_en' => 'الاسم (بالإنجليزية)',
        'name_ar' => 'الاسم (بالعربية)',
        'code' => 'الرمز',
        'description' => 'الوصف',
        'status' => 'الحالة',
        'branch' => 'الفرع',
        'doctor' => 'الطبيب',
        'patient' => 'المريض',
        'phone' => 'الهاتف',
        'amount' => 'المبلغ',
        'date' => 'التاريخ',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'آخر تعديل',
        'notes' => 'ملاحظات',
        'is_active' => 'نشط',
    ],

    'misc' => [
        'all' => 'الكل',
        'none' => 'لا شيء',
        'unknown' => 'غير معروف',
        'optional' => 'اختياري',
        'required' => 'مطلوب',
        'enabled' => 'مفعّل',
        'disabled' => 'معطّل',
        'system' => 'النظام',
        'today' => 'اليوم',
        'yesterday' => 'الأمس',
        'currency' => 'د.ك',
    ],

    'locale' => [
        'switch_to_arabic' => 'التبديل إلى العربية',
        'switch_to_english' => 'التبديل إلى الإنجليزية',
        'arabic' => 'العربية',
        'english' => 'English',
    ],
];
