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
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'wcu_applications',
            'user' => 'wcu_app',
            'password' => '',
            'charset' => 'utf8mb4',
            'sqlite_path' => '',
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

function sqlite_schema_exists(PDO $pdo): bool
{
    $statement = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'applications' LIMIT 1");
    return $statement !== false && $statement->fetchColumn() !== false;
}

function initialize_sqlite_schema(PDO $pdo): void
{
    if (sqlite_schema_exists($pdo)) {
        return;
    }

    $schemaPath = dirname(__DIR__) . '/sql/schema.sqlite.sql';
    if (!is_file($schemaPath)) {
        throw new RuntimeException('SQLite schema file is missing.');
    }

    $schema = file_get_contents($schemaPath);
    if ($schema === false || trim($schema) === '') {
        throw new RuntimeException('SQLite schema file could not be read.');
    }

    $pdo->exec($schema);
}

function get_database_connection(): PDO
{
    $database = load_config()['database'] ?? [];
    $driver = strtolower((string) ($database['driver'] ?? 'mysql'));

    if ($driver === 'sqlite') {
        $sqlitePath = trim((string) ($database['sqlite_path'] ?? ''));
        if ($sqlitePath === '') {
            throw new RuntimeException('SQLite database path is not configured.');
        }

        $directory = dirname($sqlitePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create SQLite database directory.');
        }

        $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        initialize_sqlite_schema($pdo);
        return $pdo;
    }

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

function get_database_driver(): string
{
    $database = load_config()['database'] ?? [];
    return strtolower((string) ($database['driver'] ?? 'mysql'));
}
