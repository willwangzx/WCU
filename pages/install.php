<?php
/**
 * Database Installation Script for WCU Applications
 *
 * This script will:
 * 1. Create the database 'wcu_applications' if it doesn't exist.
 * 2. Create the table 'applications' with all required fields.
 * 3. Optionally insert sample data for testing.
 *
 * Usage: Run this script once via browser or command line.
 * After installation, you may delete this file for security.
 */

// Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'wcu_applications');
define('DB_USER', 'chichi');
define('DB_PASS', 'williamchichi');
define('DB_CHARSET', 'utf8mb4');
define('FORCE_RECREATE_TABLE', false);
define('INSERT_SAMPLE_DATA', true);

error_reporting(E_ALL);
ini_set('display_errors', 1);

$output = [];
$error = false;

function addMessage($msg, $type = 'info') {
    global $output;
    $output[] = ['msg' => $msg, 'type' => $type];
}

try {
    addMessage('Connecting to MySQL server...');
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    addMessage('Connected successfully.');

    addMessage('Checking or creating database `' . DB_NAME . '`...');
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET ' . DB_CHARSET . ' COLLATE ' . DB_CHARSET . '_unicode_ci');
    addMessage('Database `' . DB_NAME . '` is ready.');

    $pdo->exec('USE `' . DB_NAME . '`');
    addMessage('Using database `' . DB_NAME . '`.');

    $tableExists = $pdo->query("SHOW TABLES LIKE 'applications'")->rowCount() > 0;

    if ($tableExists && FORCE_RECREATE_TABLE) {
        addMessage('Table `applications` exists. Dropping it because FORCE_RECREATE_TABLE is true...', 'warning');
        $pdo->exec('DROP TABLE `applications`');
        $tableExists = false;
    }

    if (!$tableExists) {
        addMessage('Creating table `applications`...');
        $sql = "CREATE TABLE `applications` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `first_name` VARCHAR(100) NOT NULL,
            `last_name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(50) NOT NULL,
            `citizenship` VARCHAR(100) NOT NULL,
            `entry_term` VARCHAR(20) NOT NULL,
            `program` VARCHAR(100) NOT NULL,
            `school_name` VARCHAR(255) NOT NULL,
            `personal_statement` TEXT NOT NULL,
            `portfolio_url` VARCHAR(500) NOT NULL,
            `additional_notes` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` TEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_email` (`email`),
            KEY `idx_entry_term` (`entry_term`),
            KEY `idx_program` (`program`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=" . DB_CHARSET . " COLLATE=" . DB_CHARSET . "_unicode_ci";

        $pdo->exec($sql);
        addMessage('Table `applications` created successfully.', 'success');
    } else {
        addMessage('Table `applications` already exists. Skipping creation.', 'info');
    }

    if (INSERT_SAMPLE_DATA) {
        $count = $pdo->query('SELECT COUNT(*) FROM `applications`')->fetchColumn();

        if ($count == 0) {
            addMessage('Inserting sample application records...', 'info');
            $sampleData = [
                [
                    'first_name' => 'Jane',
                    'last_name' => 'Doe',
                    'email' => 'jane.doe@example.com',
                    'phone' => '+1 555 123 4567',
                    'citizenship' => 'United States',
                    'entry_term' => 'Fall 2026',
                    'program' => 'School of Mathematics and Computer Science',
                    'school_name' => 'Springfield High School',
                    'personal_statement' => 'I have always been fascinated by algorithms and mathematical proofs. I want to combine both at WCU to work on AI for social good.',
                    'portfolio_url' => 'https://github.com/janedoe/portfolio',
                    'additional_notes' => 'Won state math competition twice.',
                    'ip_address' => '192.168.1.100',
                    'user_agent' => 'Mozilla/5.0 (Sample)',
                    'created_at' => date('Y-m-d H:i:s')
                ],
                [
                    'first_name' => 'Carlos',
                    'last_name' => 'Mendez',
                    'email' => 'carlos.mendez@example.com',
                    'phone' => '+52 55 1234 5678',
                    'citizenship' => 'Mexico',
                    'entry_term' => 'Spring 2027',
                    'program' => 'School of Engineering and Natural Science',
                    'school_name' => 'Prepa Tec',
                    'personal_statement' => "My passion for renewable energy drives me to study sustainable engineering. WCU's lab facilities are unmatched.",
                    'portfolio_url' => 'https://carlosmendez.dev/projects',
                    'additional_notes' => 'Built a solar-powered water purifier.',
                    'ip_address' => '10.0.0.5',
                    'user_agent' => 'Mozilla/5.0 (Sample)',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];

            $insertSql = "INSERT INTO `applications` (
                first_name, last_name, email, phone, citizenship, entry_term, program,
                school_name, personal_statement, portfolio_url, additional_notes,
                ip_address, user_agent, created_at
            ) VALUES (
                :first_name, :last_name, :email, :phone, :citizenship, :entry_term, :program,
                :school_name, :personal_statement, :portfolio_url, :additional_notes,
                :ip_address, :user_agent, :created_at
            )";

            $stmt = $pdo->prepare($insertSql);
            foreach ($sampleData as $row) {
                $stmt->execute($row);
            }
            addMessage('Inserted ' . count($sampleData) . ' sample application(s).', 'success');
        } else {
            addMessage('Table `applications` already contains data. Skipping sample insertion.', 'info');
        }
    }

    addMessage('Database initialization completed successfully!', 'success');
} catch (PDOException $e) {
    $error = true;
    addMessage('Database error: ' . $e->getMessage(), 'danger');
} catch (Exception $e) {
    $error = true;
    addMessage('General error: ' . $e->getMessage(), 'danger');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WCU Database Installer</title>
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f0f4f8;
            margin: 0;
            padding: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 35px -8px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        h1 {
            font-size: 1.8rem;
            margin-top: 0;
            color: #0f3b5c;
        }
        .message-list {
            margin: 1.5rem 0;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .message {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.95rem;
        }
        .message:last-child {
            border-bottom: none;
        }
        .message.info { background: #eef2ff; color: #1e40af; border-left: 4px solid #3b82f6; }
        .message.success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .message.warning { background: #fffbeb; color: #92400e; border-left: 4px solid #f59e0b; }
        .message.danger { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }
        .success-box {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }
        hr {
            margin: 1.5rem 0;
            border: none;
            border-top: 1px solid #e2e8f0;
        }
        .footer {
            font-size: 0.8rem;
            color: #6c757d;
            text-align: center;
            margin-top: 1.5rem;
        }
        .btn {
            display: inline-block;
            background: #0f3b5c;
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 500;
            margin-right: 0.75rem;
        }
        .btn-secondary {
            background: #64748b;
        }
        .btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>WCU Applications Database Setup</h1>
    <p>This script will create the database <strong><?php echo DB_NAME; ?></strong> and the <strong>applications</strong> table.</p>

    <?php if ($error): ?>
        <div class="alert">
            Setup encountered errors. Please check messages below and fix your configuration.
        </div>
    <?php else: ?>
        <div class="success-box">
            Setup completed without fatal errors.
        </div>
    <?php endif; ?>

    <div class="message-list">
        <?php foreach ($output as $msg): ?>
            <div class="message <?php echo $msg['type']; ?>">
                <?php echo htmlspecialchars($msg['msg']); ?>
            </div>
        <?php endforeach; ?>
    </div>

    <hr>
    <div>
        <a href="apply.php" class="btn">Go to Application Form</a>
        <a href="#" class="btn btn-secondary" onclick="window.location.reload();">Run Again</a>
    </div>

    <div class="footer">
        <p>For security, <strong>delete this file (install.php)</strong> after successful installation.</p>
        <p>Database: <?php echo DB_NAME; ?> | Table: applications</p>
    </div>
</div>
</body>
</html>
