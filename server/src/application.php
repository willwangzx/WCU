<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function first_present_value(array $payload, array $keys, mixed $default = ''): mixed
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $payload)) {
            return $payload[$key];
        }
    }

    return $default;
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function get_valid_terms(): array
{
    return [
        'Fall 2026',
        'Spring 2027',
        'Fall 2027',
    ];
}

function get_valid_programs(): array
{
    return [
        'School of Mathematics and Computer Science',
        'School of Engineering and Natural Science',
        'School of Business and Management',
        'School of Art and Literature',
        'School of Humanities and Social Science',
        'School of Interdisciplinary Studies',
    ];
}

function get_valid_genders(): array
{
    return [
        'Female',
        'Male',
        'Non-binary',
        'Prefer to self-describe',
        'Prefer not to say',
    ];
}

function get_valid_birth_months(): array
{
    return [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ];
}

function normalize_application_payload(array $payload): array
{
    return [
        'first_name' => trim((string) first_present_value($payload, ['first_name', 'first-name', 'firstName'])),
        'last_name' => trim((string) first_present_value($payload, ['last_name', 'last-name', 'lastName'])),
        'email' => trim((string) first_present_value($payload, ['email'])),
        'phone' => trim((string) first_present_value($payload, ['phone'])),
        'birth_month' => trim((string) first_present_value($payload, ['birth_month', 'birth-month', 'birthMonth'])),
        'birth_day' => (int) first_present_value($payload, ['birth_day', 'birth-day', 'birthDay'], 0),
        'birth_year' => (int) first_present_value($payload, ['birth_year', 'birth-year', 'birthYear'], 0),
        'gender' => trim((string) first_present_value($payload, ['gender'])),
        'citizenship' => trim((string) first_present_value($payload, ['citizenship', 'Nationality', 'nationality'])),
        'entry_term' => trim((string) first_present_value($payload, ['entry_term', 'entry-term', 'entryTerm'])),
        'program' => trim((string) first_present_value($payload, ['program'])),
        'school_name' => trim((string) first_present_value($payload, ['school_name', 'school-name', 'schoolName'])),
        'personal_statement' => trim((string) first_present_value($payload, ['personal_statement', 'personal-statement', 'statement'])),
        'portfolio_url' => trim((string) first_present_value($payload, ['portfolio_url', 'portfolio-url', 'portfolio'])),
        'additional_notes' => trim((string) first_present_value($payload, ['additional_notes', 'additional-notes', 'notes'])),
        'application_confirmation' => filter_var(
            first_present_value($payload, ['application_confirmation', 'application-confirmation'], false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'honeypot' => trim((string) first_present_value($payload, ['website'], '')),
    ];
}

function validate_application_payload(array $application): array
{
    $errors = [];

    if (text_length($application['first_name']) < 2) {
        $errors[] = 'First name must be at least 2 characters.';
    }

    if (text_length($application['last_name']) < 2) {
        $errors[] = 'Last name must be at least 2 characters.';
    }

    if (!filter_var($application['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (text_length($application['phone']) < 5) {
        $errors[] = 'Phone number must be at least 5 characters.';
    }

    if (!in_array($application['birth_month'], get_valid_birth_months(), true)) {
        $errors[] = 'Please select a valid birth month.';
    }

    if ($application['birth_day'] < 1 || $application['birth_day'] > 31) {
        $errors[] = 'Birth day must be between 1 and 31.';
    }

    $currentYear = (int) date('Y');
    if ($application['birth_year'] < 1900 || $application['birth_year'] > $currentYear) {
        $errors[] = 'Birth year must be a valid year.';
    }

    if (!in_array($application['gender'], get_valid_genders(), true)) {
        $errors[] = 'Please select a valid gender.';
    }

    if (text_length($application['citizenship']) < 2) {
        $errors[] = 'Please enter a valid citizenship country or region.';
    }

    if (!in_array($application['entry_term'], get_valid_terms(), true)) {
        $errors[] = 'Invalid entry term selected.';
    }

    if (!in_array($application['program'], get_valid_programs(), true)) {
        $errors[] = 'Invalid program selection.';
    }

    if (text_length($application['school_name']) < 2) {
        $errors[] = 'Please enter your current or most recent school name.';
    }

    $statementLength = text_length($application['personal_statement']);
    if ($statementLength < 30) {
        $errors[] = 'Personal statement must be at least 30 characters.';
    } elseif ($statementLength > 5000) {
        $errors[] = 'Personal statement cannot exceed 5000 characters.';
    }

    if (
        !filter_var($application['portfolio_url'], FILTER_VALIDATE_URL) ||
        !preg_match('/^https?:\/\//i', $application['portfolio_url'])
    ) {
        $errors[] = 'Portfolio or sample link must start with http:// or https://';
    }

    if (text_length($application['additional_notes']) > 2000) {
        $errors[] = 'Additional context cannot exceed 2000 characters.';
    }

    if ($application['application_confirmation'] !== true) {
        $errors[] = 'You must confirm that the information provided is accurate.';
    }

    return $errors;
}

function insert_application(PDO $pdo, array $application): int
{
    $statement = $pdo->prepare(
        'INSERT INTO applications (
            first_name,
            last_name,
            email,
            phone,
            birth_month,
            birth_day,
            birth_year,
            gender,
            citizenship,
            entry_term,
            program,
            school_name,
            personal_statement,
            portfolio_url,
            additional_notes,
            ip_address,
            user_agent,
            origin_url,
            created_at
        ) VALUES (
            :first_name,
            :last_name,
            :email,
            :phone,
            :birth_month,
            :birth_day,
            :birth_year,
            :gender,
            :citizenship,
            :entry_term,
            :program,
            :school_name,
            :personal_statement,
            :portfolio_url,
            :additional_notes,
            :ip_address,
            :user_agent,
            :origin_url,
            :created_at
        )'
    );

    $statement->execute([
        ':first_name' => $application['first_name'],
        ':last_name' => $application['last_name'],
        ':email' => $application['email'],
        ':phone' => $application['phone'],
        ':birth_month' => $application['birth_month'],
        ':birth_day' => $application['birth_day'],
        ':birth_year' => $application['birth_year'],
        ':gender' => $application['gender'],
        ':citizenship' => $application['citizenship'],
        ':entry_term' => $application['entry_term'],
        ':program' => $application['program'],
        ':school_name' => $application['school_name'],
        ':personal_statement' => $application['personal_statement'],
        ':portfolio_url' => $application['portfolio_url'],
        ':additional_notes' => $application['additional_notes'] !== '' ? $application['additional_notes'] : null,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ':origin_url' => $_SERVER['HTTP_ORIGIN'] ?? null,
        ':created_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
    ]);

    return (int) $pdo->lastInsertId();
}

function maybe_send_confirmation_email(array $application): bool
{
    $email = load_config()['email'] ?? [];

    if (($email['enabled'] ?? false) !== true) {
        return false;
    }

    $to = $application['email'];
    $subject = 'WCU Application Received - ' . $application['first_name'] . ' ' . $application['last_name'];
    $message = "Dear {$application['first_name']} {$application['last_name']},\n\n";
    $message .= "Thank you for applying to William Chichi University.\n";
    $message .= "We have received your application for {$application['program']} ({$application['entry_term']}).\n\n";
    $message .= "Our admissions team will review your materials and contact you with next steps.\n\n";
    $message .= "Best regards,\n";
    $message .= ($email['from_name'] ?? 'WCU Admissions Office');

    $headers = [
        'From: ' . ($email['from_name'] ?? 'WCU Admissions Office') . ' <' . ($email['from_address'] ?? '') . '>',
        'Reply-To: ' . ($email['admin'] ?? ''),
        'X-Mailer: PHP/' . phpversion(),
    ];

    return mail($to, $subject, $message, implode("\r\n", $headers));
}
