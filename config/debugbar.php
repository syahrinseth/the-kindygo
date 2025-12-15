<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Debugbar Enabled
    |--------------------------------------------------------------------------
    |
    | Enable the debugbar based on an environment variable. Defaults to
    | the app debug flag when not explicitly set.
    |
    */
    'enabled' => env('DEBUGBAR_ENABLED', env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    */
    'storage' => [
        'enabled' => true,
        'driver' => 'file',
        'path' => storage_path('debugbar'),
    ],
];
