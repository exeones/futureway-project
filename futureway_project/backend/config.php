<?php
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'careertestdb',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'jwt_secret' => 'futureway-super-secret-key-change-me',
        'admin_email' => 'admin@futureway.md',
        'admin_password' => 'admin123',
        'rate_limit_file' => __DIR__ . '/storage/login_attempts.json',
        'rate_limit_max_attempts' => 5,
        'rate_limit_window_seconds' => 600,
        'sandbox_webhook_secret' => 'futureway-paynet-sandbox',
        'cors_origin' => '*',
    ],
];
