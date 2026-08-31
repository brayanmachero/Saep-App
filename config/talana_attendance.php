<?php

return [
    // Remitente dedicado del informe diario de asistencia. Mantiene aislados
    // los demás correos transaccionales de la configuración global de correo.
    'from' => [
        'address' => env('TALANA_ASISTENCIA_FROM_ADDRESS', 'notificaciones@saep.cl'),
        'name' => env('TALANA_ASISTENCIA_FROM_NAME', 'SAEP · Asistencia'),
    ],

    // Destinatarios exclusivos del informe de asistencia de LTS Quilicura.
    // Un --email manual (como el usado por el reporte de Peñón) no hereda
    // estas copias para evitar mezclar ambos informes.
    'recipients' => [
        'to' => env('TALANA_ASISTENCIA_EMAIL') ?: 'sgarcia@saep.cl',
        'cc' => env('TALANA_ASISTENCIA_CC') ?: 'jrodriguez@saep.cl,bmachero@saep.cl',
    ],
];
