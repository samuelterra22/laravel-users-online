<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Duration
    |--------------------------------------------------------------------------
    |
    | The default time (in seconds) that a user will be considered online
    | after their last activity. This can be overridden per user.
    |
    */

    'default_duration' => env('USERS_ONLINE_DURATION', 300), // 5 minutes

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix used for cache keys to avoid conflicts with other
    | cache entries in your application.
    |
    */

    'cache_prefix' => env('USERS_ONLINE_PREFIX', 'UserOnline'),

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | The cache store to use for storing online user data. If null,
    | the default cache store will be used.
    |
    */

    'cache_store' => env('USERS_ONLINE_CACHE_STORE', null),

    /*
    |--------------------------------------------------------------------------
    | User Data Fields
    |--------------------------------------------------------------------------
    |
    | The user model fields to store in cache. Only include necessary
    | fields to minimize memory usage.
    |
    */

    'user_fields' => [
        'id',
        'name',
        'email',
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic Cleanup
    |--------------------------------------------------------------------------
    |
    | Enable automatic cleanup of expired cache entries. Recommended
    | for production environments.
    |
    */

    'auto_cleanup' => env('USERS_ONLINE_AUTO_CLEANUP', true),

];
