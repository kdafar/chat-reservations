<?php

// config/messages.php
return [
    'defaults' => [
        // Branch selection
        'branch.choose_header' => [
            'en' => 'Choose your branch',
            'ar' => 'اختر الفرع',
        ],
        'branch.choose_body' => [
            'en' => 'Please select a branch:',
            'ar' => 'يرجى اختيار الفرع:',
        ],
        'branch.choose_button' => [
            'en' => 'View Branches',
            'ar' => 'عرض الفروع',
        ],

        // Party size
        'party.ask' => [
            'en' => "Great! What's your party size?",
            'ar' => 'رائع! كم عدد الأشخاص؟',
        ],
        'party.error' => [
            'en' => 'Please choose a valid size (1–12).',
            'ar' => 'يرجى اختيار عدد صحيح (1–12).',
        ],

        // Dates
        'date.pick_header' => ['en' => 'Pick a date', 'ar' => 'اختر التاريخ'],
        'date.pick_body' => ['en' => 'Select your reservation date:', 'ar' => 'اختر تاريخ الحجز:'],
        'date.pick_button' => ['en' => 'View Dates', 'ar' => 'عرض التواريخ'],

        // Times
        'time.pick_header' => ['en' => 'Pick a time', 'ar' => 'اختر الوقت'],
        'time.pick_body' => ['en' => 'Available times:', 'ar' => 'الأوقات المتاحة:'],
        'time.pick_button' => ['en' => 'View Times', 'ar' => 'عرض الأوقات'],
        'time.none' => ['en' => 'No slots for that date. Please select another date.', 'ar' => 'لا توجد مواعيد في هذا التاريخ. يرجى اختيار تاريخ آخر.'],
        'hold.taken' => ['en' => 'Oops, that time was just taken. Please pick another time.', 'ar' => 'عذراً، تم حجز هذا الوقت للتو. يرجى اختيار وقت آخر.'],

        // Review & confirm
        'review.summary' => [
            'en' => "Confirm booking:\nBranch: {branch}\nParty: {size}\nDate:  {date}\nTime:  {time}",
            'ar' => "تأكيد الحجز:\nالفرع: {branch}\nعدد الأشخاص: {size}\nالتاريخ: {date}\nالوقت: {time}",
        ],
        'confirm.ok_text' => [
            'en' => "BOOKING CONFIRMED!\nParty: {size}\nDate:  {date}\nTime:  {time}\nBranch: {branch}\nCode:  {code}\n\nThank you for choosing Barfres.",
            'ar' => "تم تأكيد الحجز!\nعدد الأشخاص: {size}\nالتاريخ: {date}\nالوقت: {time}\nالفرع: {branch}\nالرمز: {code}\n\nشكراً لاختيارك بارفريس.",
        ],
        'confirm.tail' => [
            'en' => "Booked! Code: {code}\nIf you need changes, just say 'change'.",
            'ar' => "تم الحجز! الرمز: {code}\nلتعديل الحجز اكتب «تعديل».",
        ],

        // Existing booking gate
        'gate.active' => [
            'en' => "You already have a booking: {branch} on {date} at {time}.\nWould you like to change it or make a new booking?",
            'ar' => "لديك حجز قائم: {branch} بتاريخ {date} الساعة {time}.\nهل تريد تعديله أم إنشاء حجز جديد؟",
        ],
        'cancel.done' => ['en' => 'Your booking has been cancelled.', 'ar' => 'تم إلغاء الحجز.'],
        'finale.confirm' => [
            'en' => 'Booking confirmed\nCode: {code}\nWhen: {datetime}\nParty: {party}\n\nYour pass: {pass_url}',
            'ar' => 'تم تأكيد الحجز\nالرمز: {code}\nالتاريخ والوقت: {datetime}\nالأفراد: {party}\n\nبطاقتك: {pass_url}',
        ],
    ],
];
