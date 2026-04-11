<?php

declare(strict_types=1);

function load_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'cors' => [
            'allowed_origins' => [],
        ],
        'database' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'wcu_applications',
            'user' => 'wcu_app',
            'password' => '',
            'charset' => 'utf8mb4',
        ],
        'email' => [
            'enabled' => false,
            'admin' => '',
            'from_address' => '',
            'from_name' => 'WCU Admissions Office',
        ],
        'admin' => [
            'username' => 'admin',
            'password_hash' => '',
            'session_name' => 'wcu_admin_session',
        ],
    ];

    $configFile = dirname(__DIR__) . '/config.php';
    $loaded = [];

    if (is_file($configFile)) {
        $loaded = require $configFile;
        if (!is_array($loaded)) {
            throw new RuntimeException('server/config.php must return an array.');
        }
    }

    $config = array_replace_recursive($defaults, $loaded);
    return $config;
}

function get_allowed_origins(): array
{
    $origins = load_config()['cors']['allowed_origins'] ?? [];
    return array_values(array_filter(array_map('trim', $origins)));
}

function get_request_origin(): string
{
    return trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
}

function origin_is_allowed(string $origin): bool
{
    $allowedOrigins = get_allowed_origins();

    if ($origin === '') {
        return true;
    }

    if ($allowedOrigins === []) {
        return true;
    }

    return in_array('*', $allowedOrigins, true) || in_array($origin, $allowedOrigins, true);
}

function apply_cors_headers(): void
{
    $origin = get_request_origin();
    if ($origin !== '' && origin_is_allowed($origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Accept, Content-Type');
}

function handle_preflight(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'OPTIONS') {
        return;
    }

    apply_cors_headers();
    http_response_code(204);
    exit;
}

function respond_json(int $statusCode, array $payload): never
{
    apply_cors_headers();
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function read_request_payload(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    if ($_POST !== []) {
        return $_POST;
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    parse_str($raw, $parsed);
    return is_array($parsed) ? $parsed : [];
}

function get_database_connection(): PDO
{
    $database = load_config()['database'] ?? [];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $database['host'] ?? '127.0.0.1',
        (int) ($database['port'] ?? 3306),
        $database['name'] ?? 'wcu_applications',
        $database['charset'] ?? 'utf8mb4'
    );

    return new PDO($dsn, $database['user'] ?? '', $database['password'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
