<?php

return [
    'sections' => [
        'visit_context' => 'بيانات الزيارة',
        'follow_up' => 'المتابعة',
        'financial_snapshot' => 'لقطة مالية',
    ],

    'fields' => [
        'appointment' => [
            'label' => 'الموعد',
            'helper' => 'يُنشأ تلقائيًا عند تسجيل دخول الموعد عادةً.',
        ],
        'room' => [
            'label' => 'الغرفة',
        ],
        'branch' => [
            'label' => 'الفرع',
        ],
        'patient' => [
            'label' => 'المريض',
        ],
        'doctor' => [
            'label' => 'الطبيب',
        ],
        'status' => [
            'label' => 'الحالة',
        ],
        'source' => [
            'label' => 'المصدر',
            'placeholder' => 'الويب / واتساب / مكالمة / حضور مباشر / الاستقبال',
            'helper' => 'لأغراض التتبع فقط.',
        ],
        'booking_code' => [
            'label' => 'رمز الحجز',
            'helper' => 'لقطة من الموعد (اختياري).',
        ],
        'checked_in_at' => [
            'label' => 'وقت تسجيل الدخول',
        ],
        'queued_at' => [
            'label' => 'وقت الإدراج في القائمة',
        ],
        'accepted_at' => [
            'label' => 'وقت القبول',
        ],
        'accepted_by' => [
            'label' => 'قُبل بواسطة',
        ],
        'service_started_at' => [
            'label' => 'وقت بدء الخدمة',
        ],
        'completed_at' => [
            'label' => 'وقت الإكمال',
        ],
        'follow_up_date' => [
            'label' => 'تاريخ المتابعة',
        ],
        'auto_create_follow_up_booking' => [
            'label' => 'إنشاء حجز متابعة تلقائيًا',
            'helper' => 'عند التفعيل، يُنشئ النظام حجزًا معلّقًا في تاريخ/وقت المتابعة.',
        ],
        'notes' => [
            'label' => 'ملاحظات داخلية',
        ],
        'fees_total' => [
            'label' => 'إجمالي الأتعاب',
            'helper' => 'يُحتسب تلقائيًا كمجموع (visit_charges.line_total). أي تعديل يدوي يُلغى — حيث يقوم VisitCostingService::compute() بإعادة الكتابة.',
        ],
        'discount_total' => [
            'label' => 'إجمالي الخصم',
            'helper' => 'خصم على مستوى الزيارة يُطبَّق على الفاتورة. يُخصم من الربح والرصيد المتبقي.',
        ],
        'items_cost_total' => [
            'label' => 'إجمالي تكلفة الأصناف',
        ],
        'items_price_total' => [
            'label' => 'إجمالي سعر الأصناف',
        ],
        'packages_price_total' => [
            'label' => 'إجمالي سعر الباقات',
        ],
        'profit_total' => [
            'label' => 'إجمالي الربح',
        ],
        'computed_at' => [
            'label' => 'وقت الاحتساب',
        ],
        'computed_version' => [
            'label' => 'إصدار الاحتساب',
        ],
        'financial_helper_content' => 'يُحتسب بواسطة VisitCostingService (لقطة تدقيق).',
    ],

    'columns' => [
        'id' => '#',
        'checked_in' => 'تسجيل الدخول',
        'service_started' => 'بدء الخدمة',
        'patient' => 'المريض',
        'doctor' => 'الطبيب',
        'branch' => 'الفرع',
        'room' => 'الغرفة',
        'queued' => 'في القائمة',
        'accepted' => 'مقبول',
        'accepted_by' => 'قُبل بواسطة',
        'code' => 'الرمز',
        'source' => 'المصدر',
        'fees' => 'الأتعاب',
        'profit' => 'الربح',
        'computed' => 'محتسب',
        'version' => 'الإصدار',
    ],

    'filters' => [
        'doctor' => 'الطبيب',
        'clinic_branch' => 'فرع العيادة',
        'accepted_question' => 'مقبول؟',
    ],

    'actions' => [
        'mark_service_started' => 'وضع علامة بدء الخدمة',
        'sync_follow_up_plan' => 'مزامنة خطة المتابعة',
        'recompute_financials' => 'إعادة احتساب البيانات المالية',
        'open_visit' => 'فتح الزيارة',
    ],

    'notifications' => [
        'service_start_captured' => 'تم تسجيل وقت بدء الخدمة',
        'completion_time_captured' => 'تم تسجيل وقت الإكمال',
        'auto_capture_failed' => 'فشل التسجيل التلقائي لطوابع وقت الزيارة',
        'financial_snapshot_computed' => 'تم احتساب اللقطة المالية',
        'financial_snapshot_failed' => 'فشل احتساب اللقطة المالية',
        'not_allowed' => 'غير مسموح',
        'service_marked_started' => 'تم تعيين الخدمة كمبدوءة',
        'follow_up_synced' => 'تمت مزامنة خطة المتابعة',
        'follow_up_sync_failed' => 'فشل مزامنة خطة المتابعة',
        'snapshot_recomputed' => 'تمت إعادة احتساب اللقطة المالية',
        'snapshot_recompute_failed' => 'فشل إعادة احتساب اللقطة',
        'check_logs' => 'يرجى مراجعة السجلات والمحاولة مرة أخرى.',
    ],

    'options' => [
        'status' => [
            'created' => 'تم الإنشاء',
            'awaiting_doctor' => 'في انتظار الطبيب',
            'awaiting_stock' => 'في انتظار المخزون',
            'in_progress' => 'قيد التنفيذ',
            'awaiting_payment' => 'في انتظار الدفع',
            'completed' => 'مكتملة',
            'cancelled' => 'ملغاة',
            'no_show' => 'لم يحضر',
        ],
    ],
];
