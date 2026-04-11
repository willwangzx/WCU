<?php

return [
    'cors' => [
        // Keep this list tight in production. Add your Cloudflare Pages domain
        // and your custom domain after DNS is ready.
        'allowed_origins' => [
            'https://your-project.pages.dev',
            'https://www.example.com',
        ],
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'wcu_applications',
        'user' => 'wcu_app',
        'password' => 'replace-with-a-strong-password',
        'charset' => 'utf8mb4',
    ],
    'email' => [
        'enabled' => false,
        'admin' => 'admissions@example.com',
        'from_address' => 'noreply@example.com',
        'from_name' => 'WCU Admissions Office',
    ],
];
