<?php

return [
    // Remitente dedicado del informe diario de asistencia. Mantiene aislados
    // los demás correos transaccionales de la configuración global de correo.
    'from' => [
        'address' => env('TALANA_ASISTENCIA_FROM_ADDRESS', 'notificaciones@saep.cl'),
        'name' => env('TALANA_ASISTENCIA_FROM_NAME', 'SAEP · Asistencia'),
    ],
];
