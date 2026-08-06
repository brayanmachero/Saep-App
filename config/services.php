<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'kizeo' => [
        'token'                    => env('KIZEO_API_TOKEN'),
        'url'                      => env('KIZEO_API_URL', 'https://www.kizeoforms.com/rest/v3'),
        'notify_email'             => env('KIZEO_NOTIFY_EMAIL', 'bmachero@saep.cl'),
        'webhook_secret'           => env('KIZEO_WEBHOOK_SECRET'),
        'webhook_require_secret'   => env('KIZEO_WEBHOOK_REQUIRE_SECRET', false),
        'vehicle_form_id'          => env('KIZEO_VEHICLE_FORM_ID'),
        'vehicle_recipient_user_id' => env('KIZEO_VEHICLE_RECIPIENT_USER_ID'),
        'vehicle_reservation_code_field' => env('KIZEO_VEHICLE_RESERVATION_CODE_FIELD', 'codigo_de_reserva_saep'),
        'vehicle_plate_list_id'    => env('KIZEO_VEHICLE_PLATE_LIST_ID', '486495'),
        'vehicle_plate_field'      => env('KIZEO_VEHICLE_PLATE_FIELD', 'lista'),
        'charla_form_id'              => env('KIZEO_CHARLA_FORM_ID'),
        'charla_sharepoint_folder'    => env('KIZEO_CHARLA_SHAREPOINT_FOLDER', 'Charlas SST'),
        'observacion_form_id'         => env('KIZEO_OBSERVACION_FORM_ID'),
        'observacion_sharepoint_folder' => env('KIZEO_OBSERVACION_SHAREPOINT_FOLDER', 'Observaciones Conducta'),
        'inspeccion_form_id'          => env('KIZEO_INSPECCION_FORM_ID', '973787'),
        'inspeccion_sharepoint_folder' => env('KIZEO_INSPECCION_SHAREPOINT_FOLDER', 'Inspecciones SST'),
        'visita_form_id'              => env('KIZEO_VISITA_FORM_ID'),
        'visita_sharepoint_folder'    => env('KIZEO_VISITA_SHAREPOINT_FOLDER', 'Visitas Terreno'),
        'accidente_form_id'           => env('KIZEO_ACCIDENTE_FORM_ID'),
        'accidente_sharepoint_folder' => env('KIZEO_ACCIDENTE_SHAREPOINT_FOLDER', 'Accidentes SST'),
        'declaracion_form_id'            => env('KIZEO_DECLARACION_FORM_ID'),
        'declaracion_sharepoint_folder'  => env('KIZEO_DECLARACION_SHAREPOINT_FOLDER', 'Declaraciones SST'),
        'cphs_form_id'                   => env('KIZEO_CPHS_FORM_ID'),
        'cphs_sharepoint_folder'         => env('KIZEO_CPHS_SHAREPOINT_FOLDER', 'Reuniones CPHS'),
        'personal_vigente_list_id'       => env('KIZEO_PERSONAL_VIGENTE_LIST_ID'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', '/denuncia-ley-karin/auth/callback'),
    ],

    'microsoft_graph' => [
        'tenant_id'           => env('MSGRAPH_TENANT_ID'),
        'client_id'           => env('MSGRAPH_CLIENT_ID'),
        'client_secret'       => env('MSGRAPH_CLIENT_SECRET'),
        'sharepoint_host'     => env('MSGRAPH_SHAREPOINT_HOST', 'saepcl.sharepoint.com'),
        'sharepoint_site'     => env('MSGRAPH_SHAREPOINT_SITE', 'PDR'),
        'root_folder'         => env('MSGRAPH_ROOT_FOLDER', 'Actas Vehiculos'),
        'contratacion_site'   => env('CONTRATACION_SHAREPOINT_SITE', 'RRH'),
        'contratacion_folder' => env('CONTRATACION_SHAREPOINT_FOLDER', 'Postulantes Documents'),
    ],

    // Inicio de sesion delegado para el portal publico de reservas de vehiculos.
    // No comparte credenciales con Microsoft Graph/SharePoint, que usa client credentials.
    'reservas_vehiculos_microsoft' => [
        'tenant_id' => env('VEHICLE_RESERVATION_MICROSOFT_TENANT_ID'),
        'client_id' => env('VEHICLE_RESERVATION_MICROSOFT_CLIENT_ID'),
        'client_secret' => env('VEHICLE_RESERVATION_MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('VEHICLE_RESERVATION_MICROSOFT_REDIRECT_URI'),
        'allowed_domain' => env('VEHICLE_RESERVATION_MICROSOFT_ALLOWED_DOMAIN', 'saep.cl'),
        'require_approved_requester' => env('VEHICLE_RESERVATION_REQUIRE_APPROVED_REQUESTER', false),
    ],

    // Tiempo operativo que se bloquea antes y despues de cada reserva para
    // absorber atrasos de entrega, traslado o devolucion del vehiculo.
    'reservas_vehiculos' => [
        'buffer_minutes' => (int) env('VEHICLE_RESERVATION_BUFFER_MINUTES', 60),
        'public_calendar_url' => env('VEHICLE_RESERVATION_PUBLIC_CALENDAR_URL'),
        'teams_webhook_url' => env('TEAMS_BODEGA_RESERVAS_WEBHOOK_URL'),
    ],

    // Calendario compartido de Bodega. Usa exclusivamente la aplicación de
    // reservas: sus credenciales no se comparten con SharePoint/documentos.
    'reservas_vehiculos_calendar' => [
        'enabled' => env('VEHICLE_RESERVATION_CALENDAR_ENABLED', false),
        'mailbox' => env('VEHICLE_RESERVATION_CALENDAR_MAILBOX'),
        'calendar_id' => env('VEHICLE_RESERVATION_CALENDAR_ID'),
        'timezone' => env('VEHICLE_RESERVATION_CALENDAR_TIMEZONE', 'Pacific SA Standard Time'),
        'tenant_id' => env('VEHICLE_RESERVATION_CALENDAR_TENANT_ID', env('VEHICLE_RESERVATION_MICROSOFT_TENANT_ID')),
        'client_id' => env('VEHICLE_RESERVATION_CALENDAR_CLIENT_ID', env('VEHICLE_RESERVATION_MICROSOFT_CLIENT_ID')),
        'client_secret' => env('VEHICLE_RESERVATION_CALENDAR_CLIENT_SECRET', env('VEHICLE_RESERVATION_MICROSOFT_CLIENT_SECRET')),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    'talana' => [
        'base_url'    => env('TALANA_API_BASE_URL', 'https://talana.com/es/api'),
        'auth_scheme' => env('TALANA_API_AUTH_SCHEME', 'token'),
        'token'       => env('TALANA_API_TOKEN'),
        'alerta_email' => env('TALANA_ALERTA_EMAIL'),
        'alerta_dias'  => (int) env('TALANA_ALERTA_DIAS', 30),
        'empresas'    => [
            (int) env('TALANA_EMPRESA_SAEP_ID', 1039)    => 'SAEP',
            (int) env('TALANA_EMPRESA_SAEP_EST_ID', 1081) => 'SAEP EST',
        ],
    ],

    'google_drive' => [
        'credentials_path' => env('GOOGLE_DRIVE_CREDENTIALS_PATH', 'google-credentials.json'),
        'folder_id'        => env('GOOGLE_DRIVE_FOLDER_ID'),
    ],

    'grafana' => [
        'url'           => env('GRAFANA_URL', ''),
        'dashboard_uid' => env('GRAFANA_DASHBOARD_UID', 'talana-saep'),
    ],

];
