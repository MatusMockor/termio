<?php

declare(strict_types=1);

return [
    'defaults' => [
        'lead_time_hours' => (int) env('RESERVATION_LEAD_TIME_HOURS', 1),
        'max_days_in_advance' => (int) env('RESERVATION_MAX_DAYS_IN_ADVANCE', 30),
        'slot_interval_minutes' => (int) env('RESERVATION_SLOT_INTERVAL_MINUTES', 30),
    ],
    'limits' => [
        'lead_time_hours' => [
            'min' => 0,
            'max' => 720,
        ],
        'max_days_in_advance' => [
            'min' => 1,
            'max' => 365,
        ],
        'slot_interval_minutes' => [
            'min' => 5,
            'max' => 120,
            'multiple_of' => 5,
        ],
    ],
];
