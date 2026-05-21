<?php

return [
    'booking' => [
        'created' => [
            'title' => 'New booking received',
            'body' => 'Booking :code on :date at :time — :branch',
            'action_view' => 'View booking',
        ],
    ],

    'consultation_paid' => [
        'title' => 'Patient ready — consultation paid',
        'body' => ':patient is now waiting in the queue (:branch).',
        'action_open' => 'Open Waiting Patients',
    ],
];
