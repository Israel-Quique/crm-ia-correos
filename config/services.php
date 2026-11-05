<?php

return [
    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2:3b'),
        'timeout' => env('OLLAMA_TIMEOUT', 300),
        'temperature' => env('OLLAMA_TEMPERATURE', 0.2),
        'num_predict' => env('OLLAMA_NUM_PREDICT', 512),
    ],
    'postar' => [
        'url' => env('EMS_CALCULATOR_API_URL', 'https://postar.correos.gob.bo/api/calcular'),
        'timeout' => env('POSTAR_TIMEOUT', 15),
        'verify_ssl' => env('EMS_CALCULATOR_VERIFY_SSL', true),
    ],
    'delivery_express' => [
        'url' => env('DELIVERY_EXPRESS_TARIFF_API_URL', 'https://dev.correos.gob.bo:18100/api/tarifarios/delivery-express'),
        'token' => env('DELIVERY_EXPRESS_TARIFF_API_TOKEN'),
        'timeout' => env('DELIVERY_EXPRESS_TIMEOUT', 15),
        'verify_ssl' => env('DELIVERY_EXPRESS_VERIFY_SSL', true),
    ],
    'crm_api' => [
        'url' => env('CRM_API_URL'),
        'token' => env('CRM_API_TOKEN'),
        'timeout' => env('CRM_API_TIMEOUT', 60),
    ],
    'postal' => [
        'siop_url' => env('PAQUETES_EVENTOS_API_URL', 'https://dev.correos.gob.bo:18100/api/paquetes-eventos'),
        'siop_token' => env('PAQUETES_EVENTOS_API_TOKEN'),
        'sitra_url' => env('TRACKING_API_URL', 'https://sitra.correos.gob.bo/api/tracking/paquetes'),
        'sitra_token' => env('TRACKING_SQLSERVER_TOKEN'),
        'timeout' => env('POSTAL_API_TIMEOUT', 90),
        'verify_ssl' => filter_var(env('POSTAL_API_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN),
    ],
    'cds' => [
        'url' => env('CDS_API_URL'),
        'token' => env('CDS_API_TOKEN'),
        'timeout' => env('CDS_API_TIMEOUT', 20),
        'verify_ssl' => filter_var(env('CDS_API_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN),
    ],
    'whatsapp' => [
        'api_version' => env('WHATSAPP_API_VERSION', 'v23.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'sender_number' => env('WHATSAPP_SENDER_NUMBER'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'timeout' => env('WHATSAPP_TIMEOUT', 20),
        'ca_bundle' => env('WHATSAPP_CA_BUNDLE', storage_path('certs/cacert.pem')),
        'test_mode' => filter_var(env('WHATSAPP_TEST_MODE', true), FILTER_VALIDATE_BOOLEAN),
        'test_recipient' => env('WHATSAPP_TEST_RECIPIENT', env('WHATSAPP_TEST_MODE_NUMBER', '64229403')),
    ],

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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
