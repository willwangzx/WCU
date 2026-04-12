<?php

return [
    'cors' => [
        'allowed_origins' => [
            'https://your-project.pages.dev',
            'https://www.example.com',
        ],
    ],
    'database' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'wcu_applications',
        'user' => 'wcu_app',
        'password' => 'replace-with-a-strong-password',
        'charset' => 'utf8mb4',
        'sqlite_path' => '/var/lib/wcu-data/wcu.sqlite',
    ],
    'email' => [
        'enabled' => false,
        'admin' => 'admissions@example.com',
        'from_address' => 'noreply@example.com',
        'from_name' => 'WCU Admissions Office',
    ],
    'admin' => [
        'username' => 'admin',
        // Generate with:
        // php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
        'password_hash' => 'replace-with-password-hash',
        'session_name' => 'wcu_admin_session',
    ],
];
