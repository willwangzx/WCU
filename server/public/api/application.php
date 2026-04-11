<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/application.php';

handle_preflight();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!origin_is_allowed(get_request_origin())) {
    respond_json(403, [
        'ok' => false,
        'errors' => ['This origin is not allowed to submit applications.'],
    ]);
}

if ($method === 'GET') {
    respond_json(200, [
        'ok' => true,
        'service' => 'wcu-applications-api',
    ]);
}

if ($method !== 'POST') {
    respond_json(405, [
        'ok' => false,
        'errors' => ['Method not allowed.'],
    ]);
}

$application = normalize_application_payload(read_request_payload());
$errors = validate_application_payload($application);

if ($errors !== []) {
    respond_json(422, [
        'ok' => false,
        'errors' => $errors,
    ]);
}

try {
    $applicationId = insert_application(get_database_connection(), $application);
    $emailSent = maybe_send_confirmation_email($application);

    respond_json(201, [
        'ok' => true,
        'application_id' => $applicationId,
        'email_sent' => $emailSent,
    ]);
} catch (Throwable $throwable) {
    error_log('WCU application API failure: ' . $throwable->getMessage());

    respond_json(500, [
        'ok' => false,
        'errors' => ['The admissions system is temporarily unavailable. Please try again later.'],
    ]);
}
