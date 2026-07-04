<?php

return [
    'source_path' => env('SYNC_MANAGER_SOURCE_PATH', base_path()),

    'storage_root' => env('SYNC_MANAGER_STORAGE_ROOT', storage_path('app/private/sync-manager')),

    'security' => [
        'strict_mode' => (bool) env('SYNC_MANAGER_SECURITY_STRICT', true),
        'dangerous_patterns' => [
            '.env', '.env.*', '*.sqlite', '*.sqlite3', '*.db',
            'auth.json', 'phpunit.xml', 'phpunit.xml.dist',
            'storage/logs/*.log', '.git/*', '*.key', '*.pem',
            'artisan', 'composer.json', 'composer.lock',
        ],
        'allow_override' => (bool) env('SYNC_MANAGER_SECURITY_ALLOW_OVERRIDE', false),
        'writable_subtrees' => [],
    ],

    'ignore' => [
        'file_name' => '.syncignore',
        'defaults' => [
            'vendor/', 'node_modules/', '.git/', 'storage/logs/',
            'storage/framework/cache/', 'storage/framework/sessions/',
            'storage/framework/testing/', 'storage/app/private/',
            'storage/app/testing-sync/', 'bootstrap/cache/',
            '.env', '.editorconfig', '.syncignore', '.env.example',
            'README.md', 'phpunit.xml', 'phpunit.xml.dist',
            'vite.config.js', 'package.json', 'package-lock.json',
            'composer.json', 'composer.lock', 'artisan',
            '.gitattributes', '.gitignore', '.npmrc',
        ],
    ],

    'target' => [
        'name' => env('SYNC_MANAGER_TARGET_NAME', env('APP_NAME', 'target')),
        'url' => env('SYNC_MANAGER_TARGET_URL'),
        'api_key' => env('SYNC_MANAGER_TARGET_API_KEY', env('SYNC_MANAGER_API_KEY')),
        'source_app_id' => env('SYNC_MANAGER_SOURCE_APP_ID', env('APP_NAME', 'deploycar')),
    ],

    'receiver' => [
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
];
