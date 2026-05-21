<?php

return [
    'sections' => [
        'professional_profile' => 'الملف المهني',
        'assignment' => 'التعيين',
        'assignment_description' => 'أين يعمل هذا الطبيب؟',
        'work_schedule' => 'جدول العمل',
        'work_schedule_description' => 'حدّد المناوبات الأسبوعية لهذا الطبيب.',
    ],

    'fields' => [
        'avatar' => [
            'label' => 'صورة الملف الشخصي',
        ],
        'name' => [
            'label' => 'اسم الطبيب',
            'placeholder' => 'د. فلان الفلاني',
        ],
        'specialty' => [
            'label' => 'التخصص',
        ],
        'license_number' => [
            'label' => 'رقم الترخيص الطبي',
        ],
        'email' => [
            'label' => 'البريد الإلكتروني (تسجيل الدخول)',
            'helper' => 'يُستخدم لإنشاء حساب دخول الطبيب. يجب أن يكون فريدًا.',
            'locked_helper' => 'مرتبط بحساب الدخول. لا يمكن تغييره.',
        ],
        'phone' => [
            'label' => 'الهاتف',
        ],
        'consultation_fee' => [
            'label' => 'رسوم الاستشارة',
            'helper' => 'يجب أن تكون أكبر من 0. الدينار الكويتي يستخدم 3 منازل عشرية.',
        ],
        'partner' => [
            'label' => 'العيادة (الشريك)',
        ],
        'primary_branch' => [
            'label' => 'الفرع الرئيسي',
        ],
        'room' => [
            'label' => 'الغرفة',
            'helper' => 'اختياري. يجب أن تكون الغرفة تابعة للفرع المحدد. يمكن تخصيص الغرفة لطبيب واحد فقط.',
        ],
        'linked_user' => [
            'label' => 'المستخدم المرتبط (تسجيل الدخول)',
            'helper' => 'اختياري. اربط هذا الطبيب بمستخدم في النظام للصلاحيات وتسجيل دخول الطبيب.',
            'auto_create_note' => 'سيتم إنشاء حساب دخول تلقائيًا بالبريد الإلكتروني أعلاه، وتعيين دور "clinic_doctor".',
        ],
        'weekly_slots' => [
            'label' => 'الفترات الأسبوعية',
            'helper' => 'نصيحة: أنشئ يومًا واحدًا ثم انقر زر "استنساخ" لنسخه بسرعة إلى الأيام الأخرى.',
        ],
        'start_time' => [
            'label' => 'وقت البداية',
        ],
        'end_time' => [
            'label' => 'وقت النهاية',
        ],
        'is_active' => [
            'label' => 'الطبيب متاح للحجز',
        ],
        'user' => [
            'label' => 'المستخدم',
        ],
    ],

    'columns' => [
        'branch' => 'الفرع',
        'room' => 'الغرفة',
        'user' => 'المستخدم',
        'shifts' => 'المناوبات',
        'active' => 'نشط',
        'fee' => 'الرسوم',
        'days_suffix' => 'أيام',
    ],

    'filters' => [
        'branch' => 'الفرع',
    ],

    'actions' => [
        'link_user' => 'ربط بمستخدم',
        'unlink_user' => 'إلغاء ربط المستخدم',
        'link_user_modal_heading' => 'ربط الطبيب بمستخدم',
        'delete' => [
            'modal_heading' => 'حذف الطبيب',
            'modal_description' => 'سيتم حذف الطبيب نهائيًا. لا يمكن التراجع عن هذا الإجراء.',
            'modal_description_with_user' => 'سيتم حذف الطبيب نهائيًا، بالإضافة إلى حساب الدخول الخاص به (:email)، ولن يتمكن من تسجيل الدخول بعد ذلك. لا يمكن التراجع عن هذا الإجراء.',
            'submit' => 'حذف الطبيب وحساب الدخول',
        ],
    ],

    'notifications' => [
        'user_created' => [
            'title' => 'تم إنشاء حساب الدخول',
            'body' => 'البريد: :email — كلمة المرور المؤقتة: :password (انسخها الآن، لن تظهر مرة أخرى).',
        ],
    ],

    'options' => [
        'days' => [
            'sunday' => 'الأحد',
            'monday' => 'الإثنين',
            'tuesday' => 'الثلاثاء',
            'wednesday' => 'الأربعاء',
            'thursday' => 'الخميس',
            'friday' => 'الجمعة',
            'saturday' => 'السبت',
        ],
        'specialty_suggestions' => [
            'general_practice' => 'طب عام',
            'cardiology' => 'أمراض القلب',
            'dermatology' => 'الجلدية',
            'pediatrics' => 'طب الأطفال',
            'dentistry' => 'طب الأسنان',
            'orthopedics' => 'العظام',
        ],
    ],
];
