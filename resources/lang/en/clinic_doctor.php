<?php

return [
    'sections' => [
        'professional_profile' => 'Professional Profile',
        'assignment' => 'Assignment',
        'assignment_description' => 'Where does this doctor work?',
        'work_schedule' => 'Work Schedule',
        'work_schedule_description' => 'Define the weekly shifts for this doctor.',
    ],

    'fields' => [
        'avatar' => [
            'label' => 'Profile Photo',
        ],
        'name' => [
            'label' => 'Doctor Name',
            'placeholder' => 'Dr. John Doe',
        ],
        'specialty' => [
            'label' => 'Specialty',
        ],
        'license_number' => [
            'label' => 'Medical License #',
        ],
        'email' => [
            'label' => 'Email (Login)',
            'helper' => 'Used to create the doctor\'s login account. Must be unique.',
            'locked_helper' => 'Linked to the login account. Cannot be changed.',
        ],
        'phone' => [
            'label' => 'Phone',
        ],
        'consultation_fee' => [
            'label' => 'Consultation Fee',
            'helper' => 'Must be greater than 0. KWD uses 3 decimals.',
        ],
        'partner' => [
            'label' => 'Clinic (Partner)',
        ],
        'primary_branch' => [
            'label' => 'Primary Branch',
        ],
        'room' => [
            'label' => 'Room',
            'helper' => 'Optional. Room must belong to the selected branch. A room can be assigned to only one doctor.',
        ],
        'linked_user' => [
            'label' => 'Linked User (Login)',
            'helper' => 'Optional. Link this doctor to a system user for permissions and doctor login.',
            'auto_create_note' => 'A login account will be created automatically with the email above, and the "clinic_doctor" role assigned.',
        ],
        'weekly_slots' => [
            'label' => 'Weekly Slots',
            'helper' => 'Tip: Create one day, then click the "Clone" (duplicate) button to quickly copy it to other days.',
        ],
        'start_time' => [
            'label' => 'Start Time',
        ],
        'end_time' => [
            'label' => 'End Time',
        ],
        'is_active' => [
            'label' => 'Doctor is Available for Booking',
        ],
        'user' => [
            'label' => 'User',
        ],
    ],

    'columns' => [
        'branch' => 'Branch',
        'room' => 'Room',
        'user' => 'User',
        'shifts' => 'Shifts',
        'active' => 'Active',
        'fee' => 'Fee',
        'days_suffix' => 'days',
    ],

    'filters' => [
        'branch' => 'Branch',
    ],

    'actions' => [
        'link_user' => 'Link to User',
        'unlink_user' => 'Unlink User',
        'link_user_modal_heading' => 'Link Doctor to a User',
        'delete' => [
            'modal_heading' => 'Delete doctor',
            'modal_description' => 'This will permanently delete the doctor. This cannot be undone.',
            'modal_description_with_user' => 'This will permanently delete the doctor AND their login account (:email). They will no longer be able to sign in. This cannot be undone.',
            'submit' => 'Delete doctor & login',
        ],
    ],

    'notifications' => [
        'user_created' => [
            'title' => 'Login account created',
            'body' => 'Email: :email — Temporary password: :password (copy now; it won\'t be shown again).',
        ],
    ],

    'options' => [
        'days' => [
            'sunday' => 'Sunday',
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
        ],
        'specialty_suggestions' => [
            'general_practice' => 'General Practice',
            'cardiology' => 'Cardiology',
            'dermatology' => 'Dermatology',
            'pediatrics' => 'Pediatrics',
            'dentistry' => 'Dentistry',
            'orthopedics' => 'Orthopedics',
        ],
    ],
];
