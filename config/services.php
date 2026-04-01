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
        'vehicle_form_id'          => env('KIZEO_VEHICLE_FORM_ID'),
        'charla_form_id'              => env('KIZEO_CHARLA_FORM_ID'),
        'charla_sharepoint_folder'    => env('KIZEO_CHARLA_SHAREPOINT_FOLDER', 'Charlas SST'),
        'observacion_form_id'         => env('KIZEO_OBSERVACION_FORM_ID'),
        'observacion_sharepoint_folder' => env('KIZEO_OBSERVACION_SHAREPOINT_FOLDER', 'Observaciones Conducta'),
        'inspeccion_form_id'          => env('KIZEO_INSPECCION_FORM_ID'),
        'inspeccion_sharepoint_folder' => env('KIZEO_INSPECCION_SHAREPOINT_FOLDER', 'Inspecciones SST'),
        'visita_form_id'              => env('KIZEO_VISITA_FORM_ID'),
        'visita_sharepoint_folder'    => env('KIZEO_VISITA_SHAREPOINT_FOLDER', 'Visitas Terreno'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', '/denuncia-ley-karin/auth/callback'),
    ],

    'microsoft_graph' => [
        'tenant_id'        => env('MSGRAPH_TENANT_ID'),
        'client_id'        => env('MSGRAPH_CLIENT_ID'),
        'client_secret'    => env('MSGRAPH_CLIENT_SECRET'),
        'sharepoint_host'  => env('MSGRAPH_SHAREPOINT_HOST', 'saepcl.sharepoint.com'),
        'sharepoint_site'  => env('MSGRAPH_SHAREPOINT_SITE', 'PDR'),
        'root_folder'      => env('MSGRAPH_ROOT_FOLDER', 'Actas Vehiculos'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

];
