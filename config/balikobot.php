<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default connection
    |--------------------------------------------------------------------------
    |
    | Default connection name, if not specified it will use first one.
    |
    */

    'default' => env('BALIKOBOT_DEFAULT_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Balikobot connections
    |--------------------------------------------------------------------------
    |
    | Here you may provide the multiple API connections
    |
    */

    'connections' => [
        'default' => [
            'api_user'   => env('BALIKOBOT_API_USER'),
            'api_key'   => env('BALIKOBOT_API_KEY'),
        ],
    ],
];