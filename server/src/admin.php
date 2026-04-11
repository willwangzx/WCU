<?php

declare(strict_types=1);

require_once __DIR__ . '/application.php';

function get_admin_config(): array
{
    $admin = load_config()['admin'] ?? [];

    return [
        'username' => trim((string) ($admin['username'] ?? 'admin')),
        'password_hash' => trim((string) ($admin['password_hash'] ?? '')),
        'session_name' => trim((string) ($admin['session_name'] ?? 'wcu_admin_session')),
    ];
}

function admin_credentials_configured(): bool
{
    $config = get_admin_config();
    return $config['username'] !== '' && $config['password_hash'] !== '';
}

function request_is_secure(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }

    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function start_admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $config = get_admin_config();
    session_name($config['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => request_is_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function get_admin_base_path(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'));
    $basePath = rtrim(dirname($scriptName), '/');
    return $basePath === '' || $basePath === '.' ? '/admin' : $basePath;
}

function admin_url(array $query = []): string
{
    $base = get_admin_base_path() . '/';
    if ($query === []) {
        return $base;
    }

    return $base . '?' . http_build_query($query);
}

function generate_csrf_token(): string
{
    start_admin_session();

    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['admin_csrf_token'];
}

function validate_csrf_token(?string $token): bool
{
    start_admin_session();
    $expected = (string) ($_SESSION['admin_csrf_token'] ?? '');
    return $expected !== '' && is_string($token) && hash_equals($expected, $token);
}

function is_admin_authenticated(): bool
{
    start_admin_session();
    return ($_SESSION['admin_authenticated'] ?? false) === true;
}

function attempt_admin_login(string $username, string $password): bool
{
    start_admin_session();

    $config = get_admin_config();
    if (!admin_credentials_configured()) {
        return false;
    }

    if (!hash_equals($config['username'], trim($username))) {
        return false;
    }

    if (!password_verify($password, $config['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_username'] = $config['username'];
    $_SESSION['admin_logged_in_at'] = time();

    return true;
}

function logout_admin(): void
{
    start_admin_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function set_admin_flash(string $type, string $message): void
{
    start_admin_session();
    $_SESSION['admin_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function consume_admin_flash(): ?array
{
    start_admin_session();

    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);

    return is_array($flash) ? $flash : null;
}

function escape_html(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return 'N/A';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y g:i A');
    } catch (Throwable) {
        return $value;
    }
}

function normalize_admin_filters(array $input): array
{
    $filters = [
        'q' => trim((string) ($input['q'] ?? '')),
        'entry_term' => trim((string) ($input['entry_term'] ?? '')),
        'program' => trim((string) ($input['program'] ?? '')),
    ];

    if (!in_array($filters['entry_term'], get_valid_terms(), true)) {
        $filters['entry_term'] = '';
    }

    if (!in_array($filters['program'], get_valid_programs(), true)) {
        $filters['program'] = '';
    }

    return $filters;
}

function build_application_filter_parts(array $filters): array
{
    $where = [];
    $params = [];

    if (($filters['q'] ?? '') !== '') {
        $where[] = '(first_name LIKE :search OR last_name LIKE :search OR CONCAT(first_name, " ", last_name) LIKE :search OR email LIKE :search OR phone LIKE :search)';
        $params[':search'] = '%' . $filters['q'] . '%';
    }

    if (($filters['entry_term'] ?? '') !== '') {
        $where[] = 'entry_term = :entry_term';
        $params[':entry_term'] = $filters['entry_term'];
    }

    if (($filters['program'] ?? '') !== '') {
        $where[] = 'program = :program';
        $params[':program'] = $filters['program'];
    }

    return [$where, $params];
}

function fetch_applications(PDO $pdo, array $filters, int $limit = 200): array
{
    [$where, $params] = build_application_filter_parts($filters);

    $sql = 'SELECT id, first_name, last_name, email, phone, program, entry_term, created_at
        FROM applications';

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY id DESC LIMIT ' . max(1, min($limit, 500));

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function count_applications(PDO $pdo, array $filters): int
{
    [$where, $params] = build_application_filter_parts($filters);

    $sql = 'SELECT COUNT(*) FROM applications';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

function fetch_application_by_id(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT * FROM applications WHERE id = :id LIMIT 1');
    $statement->execute([':id' => $id]);
    $application = $statement->fetch();

    return is_array($application) ? $application : null;
}

function delete_application(PDO $pdo, int $id): bool
{
    $statement = $pdo->prepare('DELETE FROM applications WHERE id = :id LIMIT 1');
    $statement->execute([':id' => $id]);

    return $statement->rowCount() === 1;
}

function stream_applications_csv(PDO $pdo, array $filters): never
{
    [$where, $params] = build_application_filter_parts($filters);

    $sql = 'SELECT id, first_name, last_name, email, phone, birth_month, birth_day, birth_year, gender, citizenship,
        entry_term, program, school_name, personal_statement, portfolio_url, additional_notes, ip_address, user_agent,
        origin_url, created_at
        FROM applications';

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY id DESC';

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="wcu-applications-' . date('Ymd-His') . '.csv"');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
        http_response_code(500);
        exit('Unable to generate CSV export.');
    }

    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'birth_month',
        'birth_day',
        'birth_year',
        'gender',
        'citizenship',
        'entry_term',
        'program',
        'school_name',
        'personal_statement',
        'portfolio_url',
        'additional_notes',
        'ip_address',
        'user_agent',
        'origin_url',
        'created_at',
    ]);

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}
