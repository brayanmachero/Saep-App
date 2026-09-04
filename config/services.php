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

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v25.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
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
        // Catálogo maestro de Inventario. SAEP publica sus variantes activas
        // en esta lista avanzada; Kizeo nunca escribe de vuelta al catálogo.
        'inventory_catalog_list_id' => env('KIZEO_INVENTORY_CATALOG_LIST_ID', '500434'),
        'charla_form_id'              => env('KIZEO_CHARLA_FORM_ID'),
        'charla_sharepoint_folder'    => env('KIZEO_CHARLA_SHAREPOINT_FOLDER', 'Charlas SST'),
        'charla_export_id'            => env('KIZEO_CHARLA_EXPORT_ID', '1438365'),
        'charla_export_name'          => env('KIZEO_CHARLA_EXPORT_NAME', 'Formato charla Prevención Riesgo'),
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
        // Lista avanzada Trabajadores por CDD. Talana manda; Kizeo solo recibe.
        'personal_cdd_list_id'           => env('KIZEO_PERSONAL_CDD_LIST_ID', '501626'),
        // Guardas para impedir bajas si la fuente Talana llegó incompleta o antigua.
        'personal_cdd_minimum_count'     => env('KIZEO_PERSONAL_CDD_MINIMUM_COUNT', 1500),
        'personal_cdd_max_source_age_minutes' => env('KIZEO_PERSONAL_CDD_MAX_SOURCE_AGE_MINUTES', 480),
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
        'default_duration_minutes' => (int) env('VEHICLE_RESERVATION_DEFAULT_DURATION_MINUTES', 60),
        // Desde esta hora el portal solo permite reservas desde el dia siguiente.
        'same_day_cutoff_hour' => (int) env('VEHICLE_RESERVATION_SAME_DAY_CUTOFF_HOUR', 16),
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
        // Limita el reporte diario de asistencia a un centro sin afectar la
        // sincronización corporativa de Talana. Vacío mantiene el alcance global.
        'asistencia_centro_costo' => env('TALANA_ASISTENCIA_CENTRO_COSTO'),
        // Permite limitar el reporte a una razón social de Talana, por ejemplo
        // 1081 para SAEP EST. Debe usarse junto al centro cuando corresponda.
        'asistencia_empresa_id' => env('TALANA_ASISTENCIA_EMPRESA_ID')
            ? (int) env('TALANA_ASISTENCIA_EMPRESA_ID')
            : null,
        // Segundo reporte diario, independiente de Quilicura.
        'asistencia_centro_costo_penon' => env('TALANA_ASISTENCIA_CENTRO_COSTO_PENON') ?: 'LTS PEÑON EST',
        'asistencia_empresa_id_penon' => (int) (env('TALANA_ASISTENCIA_EMPRESA_ID_PENON') ?: 1081),
        'asistencia_penon_email' => env('TALANA_ASISTENCIA_PENON_EMAIL') ?: 'fortiz@saep.cl',
        'asistencia_penon_cc' => env('TALANA_ASISTENCIA_PENON_CC') ?: 'jrodriguez@saep.cl,bmachero@saep.cl',
        'empresas'    => [
            (int) env('TALANA_EMPRESA_SAEP_ID', 1039)    => 'SAEP',
            (int) env('TALANA_EMPRESA_SAEP_EST_ID', 1081) => 'SAEP EST',
        ],
    ],

    // API de consulta para Wallmar / LTS Peñón. Nunca se comparten las
    // credenciales Talana: esta interfaz sirve únicamente marcas que SAEP ya
    // sincronizó en su propia base de datos.
    'wallmar_attendance' => [
        'api_key' => env('WALLMAR_PENON_API_KEY'),
        'center_codes' => array_filter(array_map(
            'trim',
            explode(',', (string) env('WALLMAR_PENON_CENTER_CODES', 'LTS PEÑON EST,LTS FLEX PEÑON EST'))
        )),
        'center_label' => env('WALLMAR_PENON_CENTER_LABEL', 'LTS FLEX PEÑON EST'),
        'minimum_date' => env('WALLMAR_PENON_MINIMUM_DATE', '2026-08-01'),
        'max_days_per_request' => (int) env('WALLMAR_PENON_MAX_DAYS_PER_REQUEST', 31),
        'max_page_size' => (int) env('WALLMAR_PENON_MAX_PAGE_SIZE', 100),
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
