<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Cashfree Credentials
     |--------------------------------------------------------------------------
     |
     | These are the API keys/credentials used to authenticate requests with Cashfree.
     | You can obtain these from your Cashfree Merchant Dashboard.
     |
     */
    'app_id' => env('CASHFREE_APP_ID'),

    'secret_key' => env('CASHFREE_SECRET_KEY'),

    /*
     |--------------------------------------------------------------------------
     | Cashfree Environment
     |--------------------------------------------------------------------------
     |
     | Set to 'production' for live payments or 'sandbox' for testing.
     |
     */
    'environment' => env('CASHFREE_ENV', 'sandbox'),

    /*
     |--------------------------------------------------------------------------
     | Cashfree API Version
     |--------------------------------------------------------------------------
     |
     | Cashfree uses dates for versioning. The default is '2023-08-01'.
     | Refer to Cashfree's documentation for api version changes.
     |
     */
    'api_version' => env('CASHFREE_API_VERSION', '2023-08-01'),

    /*
     |--------------------------------------------------------------------------
     | Logging Configurations
     |--------------------------------------------------------------------------
     |
     | Set logging_enabled to true to log API requests, responses, and webhook validations.
     | Specify a log_channel if you want to route Cashfree logs to a separate channel.
     |
     */
    'logging_enabled' => env('CASHFREE_LOGGING_ENABLED', true),

    'log_channel' => env('CASHFREE_LOG_CHANNEL'),

    /*
     |--------------------------------------------------------------------------
     | Retry Settings
     |--------------------------------------------------------------------------
     |
     | Number of retry attempts for transient network errors and backoff in milliseconds.
     |
     */
    'retry_attempts' => env('CASHFREE_RETRY_ATTEMPTS', 3),
    'retry_backoff_ms' => env('CASHFREE_RETRY_BACKOFF_MS', 500),
];
