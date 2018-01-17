<?php
return [

    /*
     |--------------------------------------------------------------------------
     | Instance URL
     |--------------------------------------------------------------------------
     |
     | This is the base url of the instance in Salesforce your application resides on
     |
     */
    'instance' => 'https://cs81.salesforce.com',

    'app_name' => '',

    'api_name' => '',

    'enable_oauth' => true,

    'environment' => env('SALESFORCE_ENVIRONMENT', 'development'),

    'oauth' => [

        'development' => [

            'consumer_key' => env('DEVELOPMENT_SF_CONSUMER_KEY'),

            'consumer_secret' => env('DEVELOPMENT_SF_CONSUMER_SECRET'),

            'sf_base_url' => env('DEVELOPMENT_SF_BASE_URL', 'https://cs81.salesforce.com'),

            'callback' => str_contains(env('DEVELOPMENT_SF_CALLBACK_ROUTE'), 'http') || empty(env('DEVELOPMENT_SF_CALLBACK_ROUTE'))?
                env('DEVELOPMENT_SF_CALLBACK_ROUTE') :
                route(env('DEVELOPMENT_SF_CALLBACK_ROUTE')),

            'username' =>  env('DEVELOPMENT_SF_USERNAME'),

            'password' => env('DEVELOPMENT_SF_PASSWORD'),

        ],

        'live' => [

            'consumer_key' => env('LIVE_SF_CONSUMER_KEY'),

            'consumer_secret' => env('LIVE_SF_CONSUMER_SECRET'),

            'sf_base_url' => env('LIVE_SF_BASE_URL', 'https://cs81.salesforce.com'),

            'callback' => str_contains(env('LIVE_SF_CALLBACK_ROUTE'), 'http') || empty(env('LIVE_SF_CALLBACK_ROUTE')) ?
                env('LIVE_SF_CALLBACK_ROUTE') :
                route(env('LIVE_SF_CALLBACK_ROUTE')),

            'username' =>  env('LIVE_SF_USERNAME'),

            'password' => env('LIVE_SF_USERNAME'),


        ],

    ],
];