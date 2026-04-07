<?php
/**
 * Application Handler - William Chichi University
 * 
 * This script handles the undergraduate application form submission:
 * - Validates all required fields
 * - Stores application data into MySQL database
 * - Sends confirmation email to the applicant
 * - Prevents CSRF and spam using tokens + honeypot
 * - Implements PRG (Post/Redirect/Get) pattern to avoid duplicate submissions
 */

session_start();

// ========== Configuration ==========
// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'wcu_applications');
define('DB_USER', 'chichi');      // Change to your database username
define('DB_PASS', 'williamchichi');  // Change to your database password
define('DB_CHARSET', 'utf8mb4');

// Email settings (adjust according to your server)
define('ADMIN_EMAIL', 'admissions@wcu.edu');
define('FROM_EMAIL', 'noreply@wcu.edu');
define('FROM_NAME', 'WCU Admissions Office');

// Application year/term options (must match frontend select)
$valid_terms = ['Fall 2026', 'Spring 2027'];
$valid_programs = [
    'School of Mathematics and Computer Science',
    'School of Engineering and Natural Science',
    'School of Business and Management',
    'School of Art and Literature',
    'School of Humanities and Social Science',
    'School of Interdisciplinary Studies'
];

// ========== Helper Functions ==========
function safe($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
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

// Send confirmation email to applicant
function sendConfirmationEmail($data) {
    $to = $data['email'];
    $subject = "WCU Application Received - " . $data['first_name'] . " " . $data['last_name'];
    $fullName = $data['first_name'] . " " . $data['last_name'];
    $program = $data['program'];
    $term = $data['entry_term'];
    
    $message = "Dear {$fullName},\n\n";
    $message .= "Thank you for applying to William Chichi University. We have successfully received your application for the {$program} program, {$term} entry.\n\n";
    $message .= "Our admissions committee will review your materials. You will receive an admission decision within 4-6 weeks.\n\n";
    $message .= "If you have any questions, please contact us at admissions@wcu.edu.\n\n";
    $message .= "Best regards,\nWCU Admissions Team\n\n";
    $message .= "Ultra examina. Way beyond exams.";
    
    $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($to, $subject, $message, $headers);
}

// Insert application into database
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

// ========== Form Processing ==========
$errors = [];
$old_input = [];
$form_success = false;

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. CSRF Protection
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $errors[] = "Security validation failed. Please refresh the page and try again.";
    }
    
    // 2. Honeypot trap (hidden field that should remain empty)
    if (!empty($_POST['website'])) {
        $errors[] = "Spam detection triggered. Submission rejected.";
    }
    
    // 3. Required fields validation
    $required_fields = [
        'first-name', 'last-name', 'email', 'phone', 'citizenship',
        'entry-term', 'program', 'school-name', 'statement', 'portfolio',
        'application-confirmation'
    ];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Please complete all required fields.";
            break;
        }
    }
    
    // Collect and sanitize input (if no major missing error)
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
        
        // 4. Field-specific validation
        // Email format
        if (!filter_var($old_input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please provide a valid email address.";
        }
        
        // Phone: basic check (non-empty, at least 5 chars)
        if (strlen($old_input['phone']) < 5) {
            $errors[] = "Phone number must be at least 5 characters.";
        }
        
        // Citizenship non-empty
        if (strlen($old_input['citizenship']) < 2) {
            $errors[] = "Please enter a valid citizenship country/region.";
        }
        
        // Entry term must be in predefined list
        if (!in_array($old_input['entry_term'], $valid_terms)) {
            $errors[] = "Invalid entry term selected.";
        }
        
        // Program must be valid
        if (!in_array($old_input['program'], $valid_programs)) {
            $errors[] = "Invalid program selection.";
        }
        
        // School name: min 2 chars
        if (strlen($old_input['school_name']) < 2) {
            $errors[] = "Please enter your current or most recent school name.";
        }
        
        // Personal statement: min 30 chars, max 5000
        $stmt_len = strlen($old_input['personal_statement']);
        if ($stmt_len < 30) {
            $errors[] = "Personal statement must be at least 30 characters. Tell us more about your goals.";
        } elseif ($stmt_len > 5000) {
            $errors[] = "Personal statement cannot exceed 5000 characters.";
        }
        
        // Portfolio URL must be a valid URL (http/https)
        if (!filter_var($old_input['portfolio_url'], FILTER_VALIDATE_URL) || 
            !preg_match('/^https?:\/\//', $old_input['portfolio_url'])) {
            $errors[] = "Portfolio or sample link must be a valid URL starting with http:// or https://";
        }
        
        // Confirmation checkbox must be checked
        if (!$old_input['confirmation']) {
            $errors[] = "You must confirm that the information provided is accurate.";
        }
        
        // Additional notes (optional) max length 2000
        if (strlen($old_input['additional_notes']) > 2000) {
            $errors[] = "Additional context cannot exceed 2000 characters.";
        }
    }
    
    // 5. If no errors, store in database and send email
    if (empty($errors)) {
        $pdo = getDBConnection();
        
        if ($pdo === null) {
            $errors[] = "System error: unable to connect to database. Please try again later or contact admissions.";
        } else {
            try {
                $application_id = insertApplication($old_input, $pdo);
                
                // Send confirmation email (non-blocking: even if mail fails, application is saved)
                $email_sent = sendConfirmationEmail($old_input);
                if (!$email_sent) {
                    error_log("Failed to send confirmation email for application ID: " . $application_id);
                }
                
                // Regenerate CSRF token to prevent reusing old token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                // Redirect to success page (PRG pattern)
                if (!empty($_POST['split-flow'])) {
                    header('Location: application-success.html');
                } else {
                    header('Location: apply.php?success=1');
                }
                exit;
                
            } catch (PDOException $e) {
                error_log("Database insert error: " . $e->getMessage());
                $errors[] = "Unable to save your application due to a technical issue. Please contact admissions@wcu.edu.";
            }
        }
    }
    
    // If we reach here, there were errors. Keep old input for repopulating the form.
    // Keep errors in session to display them after redirect? We'll just render the same page with errors.
    // But to avoid form resubmission on refresh, we do NOT redirect; we re-display the form with error messages.
    // That is acceptable: the user will see errors and can correct them. Refresh will attempt to resubmit but
    // will show errors again (no duplicate insert because validation fails).
}

// Check if this is a successful redirect (GET with success=1)
$just_submitted = isset($_GET['success']) && $_GET['success'] == 1;

// Generate new CSRF token for each form display
$csrf_token = generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Apply | William Chichi University</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    /* Additional inline styles for error/success messages and form states */
    .error-list {
      background: #fee2e2;
      border-left: 4px solid #dc2626;
      padding: 1rem 1.5rem;
      margin-bottom: 2rem;
      border-radius: 8px;
    }
    .error-list ul {
      margin: 0.5rem 0 0 1.2rem;
      color: #991b1b;
    }
    .error-list p {
      font-weight: 600;
      margin-bottom: 0.25rem;
      color: #b91c1c;
    }
    .success-card {
      background: #e0f2fe;
      border-left: 4px solid #0f3b5c;
      padding: 2rem;
      border-radius: 16px;
      text-align: center;
      margin: 2rem 0;
    }
    .success-card h2 {
      color: #0c4a6e;
      margin-bottom: 0.5rem;
    }
    .new-application-btn {
      display: inline-block;
      margin-top: 1.5rem;
      background: #0f3b5c;
      color: white;
      padding: 0.75rem 1.75rem;
      border-radius: 40px;
      text-decoration: none;
      font-weight: 500;
      transition: background 0.2s;
    }
    .new-application-btn:hover {
      background: #1e4a76;
    }
    .form-hidden {
      display: none;
    }
    .field-error {
      border-color: #dc2626 !important;
      background-color: #fff5f5;
    }
    .form-note.error-note {
      color: #dc2626;
      font-weight: 500;
    }
  </style>
</head>
<body>
  <div class="loading-screen" id="loadingScreen" aria-hidden="true">
    <svg class="loading-mark" viewBox="0 0 120 120" role="img" aria-label="WCU loading mark">
      <path d="M18 20 L36 98 L60 42 L84 98 L102 20" />
    </svg>
  </div>

  <header class="site-header">
    <a class="brand" href="../index.html"><span class="brand-mark">W</span><span class="brand-text">William Chichi University</span></a>
    <button class="menu-toggle" id="menuToggle" aria-expanded="false" aria-controls="navMenu">Menu</button>
    <nav id="navMenu" class="site-nav" aria-label="Primary">
      <a href="../index.html">Home</a>
      <a href="about.html">About</a>
      <a href="academics.html">Academics</a>
      <a href="campus.html">Campus</a>
      <a class="active" href="admissions.html">Admissions</a>
      <a href="research.html">Research</a>
      <a href="news.html">News</a>
    </nav>
    <a class="btn btn-outline" href="apply.php">Apply Now</a>
  </header>

  <section class="page-hero">
    <div class="inner reveal">
      <p class="eyebrow">Application Form</p>
      <h1>Undergraduate application for WCU.</h1>
      <p class="page-intro">Complete the form below to begin your application. This page is designed as the primary intake form for academic background, program interest, and supporting information.</p>
    </div>
  </section>

  <main class="application-main">
    <section class="section reveal application-layout">
      
      <?php if ($just_submitted): ?>
        <div class="success-card">
          <h2>Application Submitted Successfully!</h2>
          <p>Thank you for applying to William Chichi University. A confirmation email has been sent to your inbox.</p>
          <p>You will be redirected to the homepage in <span id="countdown">5</span> seconds.</p>
          <a href="../index.html" class="new-application-btn" style="background:#64748b;">Go to Homepage Now</a>
        </div>
        <script>
          let seconds = 5;
          const countdownSpan = document.getElementById('countdown');
          const timer = setInterval(() => {
            seconds--;
            if (countdownSpan) countdownSpan.textContent = seconds;
            if (seconds <= 0) {
              clearInterval(timer);
              window.location.href = '../index.html';
            }
          }, 1000);
        </script>
      <?php else: ?>
      
        <!-- APPLICATION FORM (display errors if any) -->
        <?php if (!empty($errors)): ?>
        <div class="error-list">
          <p>Please correct the following issues:</p>
          <ul>
            <?php foreach (array_unique($errors) as $err): ?>
              <li><?php echo safe($err); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      
        <aside class="application-panel">
          <p class="eyebrow">Before You Start</p>
          <h3>Prepare these materials</h3>
          <ul>
            <li>We do NOT need academic transcript; standardized test scores are optional!</li>
            <li>Personal statement describing your goals and interests.</li>
            <li>One project, portfolio, or writing sample (URL).</li>
            <li>Passport or legal name details for enrollment records.</li>
          </ul>
        </aside>

        <form class="application-form" id="applicationForm" method="POST" action="apply.php">
          <!-- CSRF Token & Honeypot -->
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          <div style="display:none;">
            <label for="website">Leave this field empty</label>
            <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
          </div>
          
          <div class="section-header">
            <p class="eyebrow">Applicant Information</p>
            <h2>Start your application.</h2>
          </div>

          <div class="application-form-grid">
            <div class="form-field">
              <label for="first-name">First Name <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="first-name" name="first-name" type="text" placeholder="Given name" value="<?php echo safe($old_input['first_name'] ?? ''); ?>" required />
            </div>
            <div class="form-field">
              <label for="last-name">Last Name <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="last-name" name="last-name" type="text" placeholder="Family name" value="<?php echo safe($old_input['last_name'] ?? ''); ?>" required />
            </div>
            <div class="form-field">
              <label for="email">Email <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="email" name="email" type="email" placeholder="name@example.com" value="<?php echo safe($old_input['email'] ?? ''); ?>" required />
            </div>
            <div class="form-field">
              <label for="phone">Phone <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="phone" name="phone" type="tel" placeholder="+86 191 9810 7813" value="<?php echo safe($old_input['phone'] ?? ''); ?>" required />
            </div>
            <div class="form-field">
              <label for="citizenship">Citizenship <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="citizenship" name="citizenship" type="text" placeholder="Country or region" value="<?php echo safe($old_input['citizenship'] ?? ''); ?>" required />
            </div>
            <div class="form-field">
              <label for="entry-term">Entry Term <span class="required-mark" aria-hidden="true">*</span></label>
              <select id="entry-term" name="entry-term" required>
                <?php foreach ($valid_terms as $term): ?>
                  <option value="<?php echo $term; ?>" <?php echo (($old_input['entry_term'] ?? '') == $term) ? 'selected' : ''; ?>><?php echo $term; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-field">
              <label for="program">Intended Program <span class="required-mark" aria-hidden="true">*</span></label>
              <select id="program" name="program" required>
                <?php foreach ($valid_programs as $prog): ?>
                  <option value="<?php echo $prog; ?>" <?php echo (($old_input['program'] ?? '') == $prog) ? 'selected' : ''; ?>><?php echo $prog; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-field">
              <label for="school-name">Current or Most Recent School <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="school-name" name="school-name" type="text" placeholder="School name" value="<?php echo safe($old_input['school_name'] ?? ''); ?>" required />
            </div>
            <div class="form-field form-field-full">
              <label for="statement">Personal Statement <span class="required-mark" aria-hidden="true">*</span></label>
              <textarea id="statement" name="statement" placeholder="Tell us what you hope to study, build, or understand at WCU." required><?php echo safe($old_input['personal_statement'] ?? ''); ?></textarea>
            </div>
            <div class="form-field form-field-full">
              <label for="portfolio">Portfolio or Sample Link <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="portfolio" name="portfolio" type="url" placeholder="https://your-portfolio.example" value="<?php echo safe($old_input['portfolio_url'] ?? ''); ?>" required />
            </div>
            <div class="form-field form-field-full">
              <label for="notes">Additional Context</label>
              <textarea id="notes" name="notes" placeholder="Optional context about achievements, circumstances, or interests."><?php echo safe($old_input['additional_notes'] ?? ''); ?></textarea>
            </div>
            <div class="form-field form-field-full">
              <label for="application-confirmation">Required Confirmation <span class="required-mark" aria-hidden="true">*</span></label>
              <div class="form-checkbox">
                <input id="application-confirmation" name="application-confirmation" type="checkbox" <?php echo (isset($old_input['confirmation']) && $old_input['confirmation']) ? 'checked' : ''; ?> required />
                <label for="application-confirmation">I have read and understood the University's application policies, and I certify that all information provided above is true and accurate.</label>
              </div>
            </div>
          </div>

          <div class="application-actions">
            <div class="application-status">
              <p class="form-note">Fields marked as required must be completed before submission. Additional Context is optional.</p>
              <p class="form-note" id="applicationMessage" aria-live="polite"></p>
            </div>
            <button class="btn btn-solid" type="submit">Submit Application</button>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </main>

  <footer class="site-footer">
    <div>
      <p class="footer-title">William Chichi University</p>
      <p>Ultra examina. Way beyond exams.</p>
    </div>
    <div>
      <p>admissions@wcu.edu</p>
      <p>Island Academic District, WCU Bay</p>
    </div>
  </footer>

  <script src="../assets/js/script.js"></script>
  <script>
    // Optional: smooth loading screen hide
    window.addEventListener('load', function() {
      const loader = document.getElementById('loadingScreen');
      if (loader) loader.style.opacity = '0';
      setTimeout(() => { if(loader) loader.style.display = 'none'; }, 300);
    });
  </script>
</body>
</html>
