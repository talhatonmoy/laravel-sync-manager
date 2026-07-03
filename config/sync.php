<?php

return [
    'source_path' => env('SYNC_MANAGER_SOURCE_PATH', base_path()),

    'storage_root' => env('SYNC_MANAGER_STORAGE_ROOT', storage_path('app/private/sync-manager')),

    'security' => [
        'strict_mode' => (bool) env('SYNC_MANAGER_SECURITY_STRICT', true),
        'dangerous_patterns' => [
            '.env',
            '.env.*',
            '*.sqlite',
            '*.sqlite3',
            '*.db',
            'auth.json',
            'phpunit.xml',
            'phpunit.xml.dist',
            'storage/logs/*.log',
            '.git/*',
            '*.key',
            '*.pem',
            'artisan',
            'composer.json',
            'composer.lock',
        ],
        'allow_override' => (bool) env('SYNC_MANAGER_SECURITY_ALLOW_OVERRIDE', false),

        /*
        | Paths allowed for receiver writes. Paths not under one of these
        | prefixes are rejected unless the array is empty (allow-all).
        */
        'writable_subtrees' => [],
    ],

    'ignore' => [
        'file_name' => '.syncignore',
        'defaults' => [
            'vendor/',
            'node_modules/',
            '.git/',
            'storage/logs/',
            'storage/framework/cache/',
            'storage/framework/sessions/',
            'storage/framework/testing/',
            'storage/app/private/',
            'storage/app/testing-sync/',
            'bootstrap/cache/',
            '.env',
            '.editorconfig',
            '.syncignore',
            '.env.example',
            'README.md',
            'phpunit.xml',
            'phpunit.xml.dist',
            'vite.config.js',
            'package.json',
            'package-lock.json',
            'composer.json',
            'composer.lock',
            'artisan',
            '.gitattributes',
            '.gitignore',
            '.npmrc',
        ],
    ],

    'target' => [
        'name' => env('SYNC_MANAGER_TARGET_NAME', env('APP_NAME', 'target')),
        'url' => env('SYNC_MANAGER_TARGET_URL'),
        'api_key' => env('SYNC_MANAGER_TARGET_API_KEY', env('SYNC_MANAGER_API_KEY')),
        'source_app_id' => env('SYNC_MANAGER_SOURCE_APP_ID', env('APP_NAME', 'deploycar')),
    ],

    'targets' => [],

    'receiver' => [
        /*
        | WARNING: enabling exposes sync receiver endpoints. Defaults to false
        | — explicitly opt in on the receiver machine (typically production).
        | A strong, unique api_key is also required.
        */
        'enabled' => (bool) env('SYNC_MANAGER_RECEIVER_ENABLED', false),
        'route_prefix' => env('SYNC_MANAGER_ROUTE_PREFIX', 'sync'),
        'api_key' => env('SYNC_MANAGER_API_KEY', 'change-me'),
    ],

    'protocol' => [
        'nonce_ttl_seconds' => (int) env('SYNC_MANAGER_NONCE_TTL', 300),
        'clock_skew_seconds' => (int) env('SYNC_MANAGER_CLOCK_SKEW', 300),
    ],

    'transport' => [
        'timeout' => (int) env('SYNC_MANAGER_TIMEOUT', 30),
        'retry_times' => (int) env('SYNC_MANAGER_RETRY_TIMES', 3),
        'retry_sleep_ms' => (int) env('SYNC_MANAGER_RETRY_SLEEP_MS', 500),
        'verify_ssl' => (bool) env('SYNC_MANAGER_VERIFY_SSL', true),
    ],

    'objects' => [
        'directory' => env('SYNC_MANAGER_OBJECT_DIRECTORY', 'objects'),
        'max_size_bytes' => (int) env('SYNC_MANAGER_OBJECT_MAX_SIZE', 50 * 1024 * 1024),
    ],

    'locking' => [
        'enabled' => (bool) env('SYNC_MANAGER_LOCKING_ENABLED', true),
        'ttl' => (int) env('SYNC_MANAGER_LOCK_TTL', 600),
        'key' => env('SYNC_MANAGER_LOCK_KEY', 'sync-manager:operation'),
    ],

    'ui' => [
        'enabled' => (bool) env('SYNC_MANAGER_UI_ENABLED', true),
        'route_prefix' => env('SYNC_MANAGER_UI_PREFIX', 'sync'),
        'default_strategy' => env('SYNC_MANAGER_DEFAULT_STRATEGY', 'preview'),
        'poll_interval_ms' => (int) env('SYNC_MANAGER_POLL_INTERVAL_MS', 1500),
        /*
        | When true, the UI gate check (viewSyncManager) applies in ALL
        | environments including local. Default false keeps backward
        | compatibility (local bypasses the gate).
        */
        'require_auth' => (bool) env('SYNC_MANAGER_UI_REQUIRE_AUTH', false),
    ],

    'advanced' => [
        'conflict_detection' => (bool) env('SYNC_MANAGER_CONFLICT_DETECTION', false),
        /*
        | When true (the default), outbound sync HTTP requests to private,
        | loopback, or link-local IPs are blocked as a Server-Side Request
        | Forgery mitigation.
        */
        'block_private_ips' => (bool) env('SYNC_MANAGER_BLOCK_PRIVATE_IPS', true),
        'notifications' => [
            'email' => env('SYNC_MANAGER_NOTIFY_EMAIL'),
            'webhook' => env('SYNC_MANAGER_NOTIFY_WEBHOOK'),
        ],
    ],

    'queue' => [
        'enabled' => (bool) env('SYNC_MANAGER_QUEUE_ENABLED', false),
        'connection' => env('SYNC_MANAGER_QUEUE_CONNECTION'),
        'queue' => env('SYNC_MANAGER_QUEUE_NAME', 'sync-manager'),
    ],

    'schedule' => [
        'enabled' => (bool) env('SYNC_MANAGER_SCHEDULE_ENABLED', false),
        'frequency' => env('SYNC_MANAGER_SCHEDULE_FREQUENCY', 'hourly'),
        'queue' => (bool) env('SYNC_MANAGER_SCHEDULE_QUEUE', true),
        'all_targets' => (bool) env('SYNC_MANAGER_SCHEDULE_ALL_TARGETS', true),
    ],
];
