<?php

return [
    // default locale if session has no lang yet
    'default_locale' => 'en',
    'clinic_name' => env('WA_CLINIC_NAME', config('app.name', 'Clinic')),
    'flows' => [
        'header_en' => 'Clinic Appointment',
        'header_ar' => 'حجز موعد',
    ],

    'texts' => [
        'en' => [
            'invite_body' => "Welcome to :clinic! Let's book your appointment.",
            'choose_branch' => 'Choose a clinic / branch:',
            'party_size_q' => 'Great! How many patients / attendees?',
            'party_size_err' => 'Please send a number between 1 and 12, or choose from the buttons.',
            'date_pick_q' => 'Pick an appointment date:',
            'date_see_more' => 'See more',
            'date_err' => 'I couldn’t read that date. Please pick from the list.',
            'time_pick_q' => 'Pick an appointment time:',
            'time_err' => 'I couldn’t read that time. Please pick from the list.',
            'no_slots' => 'No slots available for that day. Try another date or reduce the number.',
            'review' => "Review your appointment:\nClinic: :branch\nCount: :size\nDate: :date\nTime: :time",
            'confirm' => 'Confirm & Book',
            'change' => 'Change',
            'race_lost' => 'That slot was just taken. Please pick another time.',
            'confirmed' => "Confirmed! Your booking code is: :code\nSee you soon 🏥",
            'thanks' => "If you need changes, just say 'change'.",
            'size_other' => 'Send the count (number 1–12):',
        ],
        'ar' => [
            'invite_body' => 'مرحبًا بكم في :clinic! لنحجز موعدك.',
            'choose_branch' => 'اختر العيادة / الفرع:',
            'party_size_q' => 'ممتاز! كم العدد؟',
            'party_size_err' => 'من فضلك أرسل رقمًا بين 1 و 12 أو اختر من الأزرار.',
            'date_pick_q' => 'اختر تاريخ الموعد:',
            'date_see_more' => 'المزيد',
            'date_err' => 'لم أفهم التاريخ. يرجى الاختيار من القائمة.',
            'time_pick_q' => 'اختر وقت الموعد:',
            'time_err' => 'لم أفهم الوقت. يرجى الاختيار من القائمة.',
            'no_slots' => 'لا توجد مواعيد متاحة لهذا اليوم. جرّب تاريخًا آخر أو قلّل العدد.',
            'review' => "مراجعة الموعد:\nالعيادة: :branch\nالعدد: :size\nالتاريخ: :date\nالوقت: :time",
            'confirm' => 'تأكيد الموعد',
            'change' => 'تغيير',
            'race_lost' => 'للأسف، تم حجز هذا الوقت قبل لحظات. اختر وقتًا آخر.',
            'confirmed' => "تم التأكيد! رقم الحجز: :code\nنراك قريبًا 🏥",
            'thanks' => "إذا أردت التعديل فقط اكتب 'تغيير'.",
            'size_other' => 'أرسل العدد (رقم 1–12):',
        ],
    ],

];
