<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Appointment Timeslot Restrictions
    |--------------------------------------------------------------------------
    |
    | These settings control the timeslot restrictions for appointments,
    | similar to Calendly's scheduling system.
    |
    */

    // Working hours (24-hour format)
    'working_hours_start' => env('APPOINTMENT_WORKING_HOURS_START', '09:00'),
    'working_hours_end' => env('APPOINTMENT_WORKING_HOURS_END', '16:30'),

    // Lunch break (24-hour format)
    'lunch_break_start' => env('APPOINTMENT_LUNCH_BREAK_START', '12:00'),
    'lunch_break_end' => env('APPOINTMENT_LUNCH_BREAK_END', '13:00'),

    // Time slot duration in minutes
    'slot_duration_minutes' => env('APPOINTMENT_SLOT_DURATION', 30),

    // Buffer time between appointments in minutes
    // This prevents back-to-back appointments
    'buffer_time_minutes' => env('APPOINTMENT_BUFFER_TIME', 15),

    // Maximum number of appointments per day
    'max_appointments_per_day' => env('APPOINTMENT_MAX_PER_DAY', 10),

    // Minimum notice time in hours
    // Clients must book at least this many hours in advance
    'minimum_notice_hours' => env('APPOINTMENT_MINIMUM_NOTICE', 24),

    /*
    |--------------------------------------------------------------------------
    | Daily Appointment Limits Per Type
    |--------------------------------------------------------------------------
    |
    | Maximum number of patients (booked + walk-in) that can be catered
    | per day for each appointment type.
    |
    */
    'daily_limits' => [
        'Vaccination' => env('APPOINTMENT_LIMIT_VACCINATION', 40),
        'Deworming' => env('APPOINTMENT_LIMIT_DEWORMING', 40),
        'Check-up' => env('APPOINTMENT_LIMIT_CHECKUP', 40),
        'Consultation' => env('APPOINTMENT_LIMIT_CONSULTATION', 40),
        'Castration' => env('APPOINTMENT_LIMIT_CASTRATION', 40),
        'Minor Surgery' => env('APPOINTMENT_LIMIT_MINOR_SURGERY', 40),
        // Default limit for other types
        'default' => env('APPOINTMENT_LIMIT_DEFAULT', 40),
    ],
];






