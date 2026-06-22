<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Módulo Comercial - Cotizador Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for quotation system (EST and SUB modalities)
    |
    */

    'default_modality' => 'EST',

    'modalities' => [
        'EST' => [
            'name' => 'Servicios Temporales',
            'description' => 'Servicios de Personal Temporal',
            'margin' => 10,
            'sis_percentage' => 1.49,
            'mutual_percentage' => 1.27,
            'cesantia_percentage' => 8.33,
        ],
        'SUB' => [
            'name' => 'Subcontratación',
            'description' => 'Subcontratación de Servicios',
            'margin' => 14,
            'sis_percentage' => 1.78,
            'mutual_percentage' => 2.5,
            'cesantia_percentage' => 11.43,
            'vacation_factor' => 1.75,
        ],
    ],

    'government_apis' => [
        'bcch' => [
            'user' => env('BCCH_API_USER'),
            'pass' => env('BCCH_API_PASS'),
            'uf_series' => env('BCCH_UF_SERIES', 'F073.UFF.PRE.Z.D'),
            'timeout' => 30,
        ],
        'sueldo_minimo' => [
            'url' => env('SUELDO_MINIMO_API_URL'),
            'timeout' => 30,
        ],
        'mindicador' => [
            'enabled' => env('COMERCIAL_MINDICADOR_ENABLED', true),
            'timeout' => 30,
        ],
    ],

    'api' => [
        'enabled' => env('COMERCIAL_API_ENABLED', false),
        'token' => env('COMERCIAL_API_TOKEN'),
        'allow_query_token' => env('COMERCIAL_API_ALLOW_QUERY_TOKEN', false),
        'default_estados' => ['vigente', 'aprobada'],
        'default_limit' => 500,
        'throttle' => env('COMERCIAL_API_THROTTLE', '60,1'),
    ],

    'pdf' => [
        'logo_path' => public_path('images/saep-logo.png'),
        'font_family' => 'Arial',
        'font_size' => 10,
        'page_orientation' => 'P', // P for Portrait, L for Landscape
    ],

    'email' => [
        'from' => env('COMERCIAL_EMAIL_FROM', env('MAIL_FROM_ADDRESS')),
        'from_name' => env('COMERCIAL_EMAIL_FROM_NAME', 'SAEP - Sistema de Cotizaciones'),
    ],

    'quotation' => [
        'default_currency' => 'CLP',
        'default_validity_days' => 30,
        'version_prefix' => 'COTIZ',
    ],

    'audit' => [
        'track_changes' => true,
        'log_user_id' => true,
    ],
];
