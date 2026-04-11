<?php
/**
 * WCU Application API Handler
 * 
 * - Direct GET access -> redirect to index.html
 * - POST requests -> process application and return JSON
 */

session_start();

// ========== Configuration ==========
define('DB_HOST', 'localhost');
define('DB_NAME', 'wcu_applications');
define('DB_USER', 'chichi');
define('DB_PASS', 'williamchichi');
define('DB_CHARSET', 'utf8mb4');

// Email settings
define('ADMIN_EMAIL', 'admissions@wcu.edu');
define('FROM_EMAIL', 'noreply@wcu.edu');
define('FROM_NAME', 'WCU Admissions Office');

// Valid options
$valid_terms = ['Fall 2026', 'Spring 2027', 'Fall 2027'];
$valid_programs = [
    'School of Mathematics and Computer Science',
    'School of Engineering and Natural Science',
    'School of Business and Management',
    'School of Art and Literature',
    'School of Humanities and Social Science',
    'School of Interdisciplinary Studies',
    'School of Business and Management'  // 新增匹配前端选项
];

// ========== Helper Functions ==========
function safe($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    // if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    // }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

function sendConfirmationEmail($data) {
    $to = $data['email'];
    $subject = "WCU Application Received – " . $data['first_name'] . " " . $data['last_name'];
    $fullName = $data['first_name'] . " " . $data['last_name'];
    $program = $data['program'];
    $term = $data['entry_term'];
    
    $message = "Dear {$fullName},\n\n";
    $message .= "Thank you for applying to William Chichi University. We have successfully received your application for the {$program} program, {$term} entry.\n\n";
    $message .= "Our admissions committee will review your materials. You will receive an admission decision within 4–6 weeks.\n\n";
    $message .= "If you have any questions, please contact us at admissions@wcu.edu.\n\n";
    $message .= "Best regards,\nWCU Admissions Team\n\n";
    $message .= "Ultra examina. Way beyond exams.";
    
    $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($to, $subject, $message, $headers);
}

function insertApplication($data, $pdo) {
    $sql = "INSERT INTO applications (
                first_name, last_name, email, phone, citizenship, 
                entry_term, program, school_name, personal_statement, 
                portfolio_url, additional_notes, ip_address, user_agent, created_at
            ) VALUES (
                :first_name, :last_name, :email, :phone, :citizenship,
                :entry_term, :program, :school_name, :personal_statement,
                :portfolio_url, :additional_notes, :ip_address, :user_agent, NOW()
            )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':first_name' => $data['first_name'],
        ':last_name' => $data['last_name'],
        ':email' => $data['email'],
        ':phone' => $data['phone'],
        ':citizenship' => $data['citizenship'],
        ':entry_term' => $data['entry_term'],
        ':program' => $data['program'],
        ':school_name' => $data['school_name'],
        ':personal_statement' => $data['personal_statement'],
        ':portfolio_url' => $data['portfolio_url'],
        ':additional_notes' => $data['additional_notes'] ?? null,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    return $pdo->lastInsertId();
}

// ========== Handle Request ==========
header('Content-Type: application/json');

// 1. GET request -> redirect to home
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  // 处理 CSRF token 获取请求（GET with ?csrf=1）
  if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['csrf']) && $_GET['csrf'] == '1') {
      header('Content-Type: application/json');
      $csrf_token = generateCSRFToken();  
      echo json_encode(['csrf_token' => $csrf_token]);
      exit;
  }
  else{
    header('Location: ../index.html');
    exit;
  }
}


// 2. Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

//debug
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf_token)) {
    // 调试日志：记录 session 中的 token 和接收到的 token
    error_log("CSRF mismatch: POST token = " . var_export($csrf_token, true));
    error_log("CSRF mismatch: SESSION token = " . var_export($_SESSION['csrf_token'] ?? 'NOT SET', true));
    error_log("Session ID = " . session_id());

    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['Security validation failed. Please refresh the page and try again.']]);
    exit;
}

// 3. CSRF Protection
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['Security validation failed. Please refresh the page and try again.']]);
    exit;
}

// 4. Honeypot (should be empty)
if (!empty($_POST['website'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Spam detected.']]);
    exit;
}

// 5. Validate required fields
$errors = [];
$required_fields = [
    'first-name', 'last-name', 'email', 'phone', 'citizenship',
    'entry-term', 'program', 'school-name', 'statement', 'portfolio',
    'application-confirmation'
];

foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $errors[] = "All required fields must be filled.";
        break;
    }
}

// Collect and sanitize input
$old_input = [];
if (empty($errors)) {
    $old_input = [
        'first_name' => trim($_POST['first-name']),
        'last_name' => trim($_POST['last-name']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone']),
        'citizenship' => trim($_POST['citizenship']),
        'entry_term' => $_POST['entry-term'],
        'program' => $_POST['program'],
        'school_name' => trim($_POST['school-name']),
        'personal_statement' => trim($_POST['statement']),
        'portfolio_url' => trim($_POST['portfolio']),
        'additional_notes' => trim($_POST['notes'] ?? ''),
        'confirmation' => isset($_POST['application-confirmation']) ? 1 : 0
    ];
    
    // Email format
    if (!filter_var($old_input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please provide a valid email address.";
    }
    
    // Phone basic check
    if (strlen($old_input['phone']) < 5) {
        $errors[] = "Phone number must be at least 5 characters.";
    }
    
    // Citizenship
    if (strlen($old_input['citizenship']) < 2) {
        $errors[] = "Please enter a valid citizenship country/region.";
    }
    
    // Entry term
    if (!in_array($old_input['entry_term'], $valid_terms)) {
        $errors[] = "Invalid entry term selected.";
    }
    
    // Program
    if (!in_array($old_input['program'], $valid_programs)) {
        $errors[] = "Invalid program selection.";
    }
    
    // School name
    if (strlen($old_input['school_name']) < 2) {
        $errors[] = "Please enter your current or most recent school name.";
    }
    
    // Personal statement length
    $stmt_len = strlen($old_input['personal_statement']);
    if ($stmt_len < 30) {
        $errors[] = "Personal statement must be at least 30 characters.";
    } elseif ($stmt_len > 5000) {
        $errors[] = "Personal statement cannot exceed 5000 characters.";
    }
    
    // Portfolio URL
    if (!filter_var($old_input['portfolio_url'], FILTER_VALIDATE_URL) || 
        !preg_match('/^https?:\/\//', $old_input['portfolio_url'])) {
        $errors[] = "Portfolio or sample link must be a valid URL starting with http:// or https://";
    }
    
    // Confirmation checkbox
    if (!$old_input['confirmation']) {
        $errors[] = "You must confirm that the information provided is accurate.";
    }
    
    // Additional notes max length
    if (strlen($old_input['additional_notes']) > 2000) {
        $errors[] = "Additional context cannot exceed 2000 characters.";
    }
}

// 6. If validation fails, return errors
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => array_unique($errors)]);
    exit;
}

// 7. Store in database
$pdo = getDBConnection();
if ($pdo === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['System error: unable to connect to database. Please try again later or contact admissions.']]);
    exit;
}

try {
    $application_id = insertApplication($old_input, $pdo);
    
    // Send confirmation email (non-blocking)
    $email_sent = sendConfirmationEmail($old_input);
    if (!$email_sent) {
        error_log("Failed to send confirmation email for application ID: " . $application_id);
    }
    
    // Regenerate CSRF token for next submission (optional)
    // $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    // Return success JSON
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully! A confirmation email has been sent.',
        'application_id' => $application_id
    ]);
    exit;
    
} catch (PDOException $e) {
    error_log("Database insert error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Unable to save your application due to a technical issue. Please contact admissions@wcu.edu.']]);
    exit;
}