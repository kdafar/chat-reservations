<?php

return [
    'booking' => [
        'created' => [
            'title' => 'تم استلام حجز جديد',
            'body' => 'الحجز :code بتاريخ :date الساعة :time — :branch',
            'action_view' => 'عرض الحجز',
        ],
    ],

    'consultation_paid' => [
        'title' => 'مريض جاهز — تم دفع رسوم الاستشارة',
        'body' => ':patient في انتظار الكشف الآن (:branch).',
        'action_open' => 'فتح قائمة الانتظار',
    ],
];
