<?php

return [
    // Opt-in: existing production reservations keep their Kizeo workflow.
    'enabled' => env('VEHICLE_ACTAS_ENABLED', false),
    'mail_enabled' => env('VEHICLE_ACTAS_MAIL_ENABLED', false),
    'calendar_enabled' => env('VEHICLE_ACTAS_CALENDAR_ENABLED', false),
    'disk' => 'local',
    'form_id' => '1165545',
    'agreement_version' => 'kizeo-1165545-2026-10-28',
];
