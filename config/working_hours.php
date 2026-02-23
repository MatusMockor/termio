<?php

declare(strict_types=1);

return [
    'day_of_week' => [
        'min' => 0,
        'max' => 6,
    ],
    'time_reference_date' => env('WORKING_HOURS_TIME_REFERENCE_DATE'),
    'default_time_reference_date' => '1970-01-01',
];
