<?php
/**
 * Practical 8: Student Registration Form Processing via MySQL & PDO Prepared Statements
 * File: php/process_registration_db.php
 * Description: Processes registration using PHP PDO, checks duplicate email, hashes password,
 *              and inserts records into the normalized `students` and `registrations` tables.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/db.php';

$errors = [];
$success = false;
$submittedData = [];
$dbStudentId = null;
$ticketCode = null;

// Enforce POST request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../page/register.html?mode=db');
    exit;
}

// -------------------------------------------------------------
// 1. Sanitize & Retrieve Form Inputs
// -------------------------------------------------------------
$fullName   = trim($_POST['fullname'] ?? '');
$email      = trim($_POST['email'] ?? '');
$phone      = trim($_POST['phone'] ?? '');
$department = trim($_POST['department'] ?? '');
$year       = trim($_POST['year'] ?? '');
$gender     = trim($_POST['gender'] ?? '');
$password   = $_POST['password'] ?? '';
$confirmPw  = $_POST['confirm_password'] ?? '';
$terms      = isset($_POST['terms']);

// Extract first and last names
$nameParts = preg_split('/\s+/', $fullName, 2);
$firstName = $nameParts[0] ?? '';
$lastName  = $nameParts[1] ?? 'Student';

// Map academic year to semester
$semesterMap = [
    '1st Year' => 1,
    '2nd Year' => 3,
    '3rd Year' => 5,
    '4th Year' => 7
];
$semester = $semesterMap[$year] ?? 1;

// -------------------------------------------------------------
// 2. Server-Side Validation
// -------------------------------------------------------------
if (empty($fullName) || !preg_match('/^[a-zA-Z\s]{3,50}$/', $fullName)) {
    $errors['fullname'] = 'Full name must be 3-50 characters with letters and spaces only.';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please provide a valid academic email address.';
}

if (empty($phone) || !preg_match('/^[1-9]\d{9}$/', $phone)) {
    $errors['phone'] = 'Mobile number must be a valid 10-digit number starting with 1-9.';
}

if (empty($department)) {
    $errors['department'] = 'Please select a valid academic department.';
}

if (empty($password) || strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters long.';
} elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[@$!%*?&#]/', $password)) {
    $errors['password'] = 'Password must include uppercase, lowercase, number, and special character.';
}

if ($password !== $confirmPw) {
    $errors['confirm_password'] = 'Passwords do not match.';
}

if (!$terms) {
    $errors['terms'] = 'You must accept the terms and campus regulations to proceed.';
}

// -------------------------------------------------------------
// 3. MySQL Database Operations via PDO & Prepared Statements
// -------------------------------------------------------------
if (empty($errors)) {
    try {
        $pdo = getDBConnection(true);

        // A. Check for Duplicate Email in MySQL database using Prepared Statement
        $checkStmt = $pdo->prepare("SELECT student_id FROM students WHERE email = :email LIMIT 1");
        $checkStmt->execute([':email' => $email]);
        if ($checkStmt->fetch()) {
            $errors['email'] = 'A student account with this email address is already registered in the MySQL database.';
        } else {
            // Generate Roll Number (e.g. 26CE089)
            $deptPrefix = 'CE';
            if (strpos($department, 'Information') !== false) $deptPrefix = 'IT';
            elseif (strpos($department, 'Data') !== false || strpos($department, 'AI') !== false) $deptPrefix = 'AI';
            elseif (strpos($department, 'Electronics') !== false) $deptPrefix = 'EC';
            elseif (strpos($department, 'Mechanical') !== false) $deptPrefix = 'ME';
            elseif (strpos($department, 'Civil') !== false) $deptPrefix = 'CL';

            $rollNumber = '26' . $deptPrefix . str_pad((string)mt_rand(1, 999), 3, '0', STR_PAD_LEFT);

            // B. Hash Password Securely with BCRYPT
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // C. Insert into `students` table via Prepared Statement
            $insertSql = "INSERT INTO students 
                          (first_name, last_name, email, password, roll_number, department, semester, phone, bio) 
                          VALUES (:first_name, :last_name, :email, :password, :roll_number, :department, :semester, :phone, :bio)";
            
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                ':first_name'  => $firstName,
                ':last_name'   => $lastName,
                ':email'       => $email,
                ':password'    => $hashedPassword,
                ':roll_number' => $rollNumber,
                ':department'  => $department,
                ':semester'    => $semester,
                ':phone'       => $phone,
                ':bio'         => 'Registered through StudentHub portal during Practical 8 database evaluation.'
            ]);

            $dbStudentId = (int)$pdo->lastInsertId();

            // D. Generate Ticket & Register for Welcome Workshop (Event ID 1 or first available event)
            $ticketCode = 'SH-2026-DB-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));

            $eventCheck = $pdo->query("SELECT event_id FROM events ORDER BY event_id ASC LIMIT 1")->fetch();
            $eventId = $eventCheck ? (int)$eventCheck['event_id'] : 1;

            $regSql = "INSERT INTO registrations (student_id, event_id, ticket_code, status) 
                       VALUES (:student_id, :event_id, :ticket_code, 'confirmed')";
            $regStmt = $pdo->prepare($regSql);
            $regStmt->execute([
                ':student_id'  => $dbStudentId,
                ':event_id'    => $eventId,
                ':ticket_code' => $ticketCode
            ]);

            $success = true;
            $submittedData = [
                'student_id'  => $dbStudentId,
                'fullname'    => $fullName,
                'email'       => $email,
                'phone'       => $phone,
                'roll_number' => $rollNumber,
                'department'  => $department,
                'semester'    => $semester,
                'year'        => $year,
                'ticket_code' => $ticketCode,
                'registered_at' => date('Y-m-d H:i:s')
            ];
        }
    } catch (PDOException $e) {
        $errors['db'] = 'Database Connection or Query Error: ' . $e->getMessage() . '. Ensure MySQL is running in XAMPP and studenthub_db is created.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MySQL DB Registration Result | StudentHub Practical 8</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400..700;1,6..72,400..700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="../css/register.css">
    <style>
        .result-container {
            max-width: 680px;
            margin: 40px auto 80px;
            padding: 0 20px;
        }
        .result-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 36px 32px;
            box-shadow: var(--card-hover-shadow);
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-badge.success {
            background-color: #dbeafe;
            color: #1d4ed8;
        }
        body[data-theme="dark"] .status-badge.success {
            background-color: #172554;
            color: #93c5fd;
        }
        .status-badge.error {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        body[data-theme="dark"] .status-badge.error {
            background-color: #450a0a;
            color: #fca5a5;
        }
        .result-title {
            font-family: var(--font-serif);
            font-size: 32px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .db-info-box {
            background-color: var(--notice-bg);
            border: 1px dashed var(--notice-border);
            border-radius: 14px;
            padding: 20px;
            margin: 24px 0;
            text-align: center;
        }
        .ticket-code {
            font-family: monospace;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 2px;
            color: var(--primary);
            margin: 8px 0;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin: 20px 0;
        }
        @media (max-width: 540px) {
            .summary-grid { grid-template-columns: 1fr; }
        }
        .summary-item {
            background-color: var(--bg-color);
            border: 1px solid var(--card-border);
            padding: 12px 14px;
            border-radius: 10px;
        }
        .summary-label {
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        .summary-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }
        .action-buttons-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 24px;
        }
        .btn-db-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px 20px;
            background-color: var(--primary);
            color: #ffffff;
            font-weight: 600;
            border-radius: 12px;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .btn-db-action:hover { background-color: var(--primary-hover); }
        .btn-secondary-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            background-color: var(--bg-color);
            border: 1px solid var(--card-border);
            color: var(--text-primary);
            font-weight: 600;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-secondary-action:hover { border-color: var(--primary); color: var(--primary); }
        .error-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 20px 0;
        }
        .error-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background-color: #fef2f2;
            border-left: 4px solid #b91c1c;
            border-radius: 6px;
            color: #991b1b;
            font-size: 13.5px;
        }
        body[data-theme="dark"] .error-item {
            background-color: #2b1212;
            color: #fca5a5;
        }
    </style>
</head>
<body>

    <!-- Standard StudentHub Navbar -->
    <header class="navbar">
        <div class="navbar-container">
            <a href="../index.html" class="brand-link">
                <img src="../image/studenthub-logo.webp" alt="StudentHub Logo" class="brand-logo-img">
                <div class="brand-text">
                    <span class="brand-title">StudentHub</span>
                    <span class="brand-subtitle">University Portal</span>
                </div>
            </a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="../index.html" class="nav-link">Home</a></li>
                    <li><a href="../page/events.html" class="nav-link">Events</a></li>
                    <li><a href="../page/about-us.html" class="nav-link">About</a></li>
                    <li><a href="../page/FAQ.html" class="nav-link">FAQ</a></li>
                    <li><a href="../page/contact.html" class="nav-link">Contact</a></li>
                </ul>
            </nav>
            <div class="nav-actions">
                <button id="theme-toggle" class="icon-btn" aria-label="Toggle theme">
                    <span class="material-symbols-outlined theme-icon">dark_mode</span>
                </button>
                <span class="nav-divider"></span>
                <a href="../page/login.html" class="login-link">Login</a>
                <a href="../page/register.html" class="btn-register">Register</a>
            </div>
        </div>
    </header>

    <main class="result-container">
        <div class="result-card">
            <?php if ($success): ?>
                <span class="status-badge success">
                    <span class="material-symbols-outlined" style="font-size: 16px;">database</span>
                    Practical 8: MySQL Insert Success
                </span>
                <h1 class="result-title">Record Inserted into Database!</h1>
                <p style="color: var(--text-secondary); font-size: 14.5px;">
                    Your account has been inserted into the <strong>students</strong> table using a PHP PDO prepared statement with parameterized binding and bcrypt password hashing.
                </p>

                <div class="db-info-box">
                    <div style="font-size: 12px; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">
                        MySQL Primary Key (student_id) #<?= $submittedData['student_id'] ?> | Roll No: <?= $submittedData['roll_number'] ?>
                    </div>
                    <div class="ticket-code"><?= $submittedData['ticket_code'] ?></div>
                    <span style="font-size: 12.5px; color: var(--text-secondary);">
                        Linked Registration Table Entry Inserted Successfully
                    </span>
                </div>

                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Full Name</div>
                        <div class="summary-value"><?= htmlspecialchars($submittedData['fullname'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Academic Email</div>
                        <div class="summary-value"><?= htmlspecialchars($submittedData['email'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Department / Sem</div>
                        <div class="summary-value"><?= htmlspecialchars($submittedData['department'], ENT_QUOTES, 'UTF-8') ?> (Sem <?= $submittedData['semester'] ?>)</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Database Connection</div>
                        <div class="summary-value">MySQL 8.x via PDO (InnoDB)</div>
                    </div>
                </div>

                <div class="action-buttons-group">
                    <a href="test_connection.php" class="btn-db-action">
                        <span class="material-symbols-outlined">network_check</span>
                        <span>Open Database Schema &amp; Connection Diagnostic</span>
                    </a>
                    <a href="../page/events.html" class="btn-secondary-action">
                        <span class="material-symbols-outlined">event</span>
                        <span>View Campus Events Catalog</span>
                    </a>
                    <a href="view_file_records.php" class="btn-secondary-action">
                        <span class="material-symbols-outlined">folder</span>
                        <span>Compare with Practical 7 File Records</span>
                    </a>
                </div>

            <?php else: ?>
                <span class="status-badge error">
                    <span class="material-symbols-outlined" style="font-size: 16px;">error</span>
                    Database Operation Failed
                </span>
                <h1 class="result-title">Unable to Complete Registration</h1>
                <p style="color: var(--text-secondary); font-size: 14.5px;">
                    An issue occurred while validating or inserting your data into MySQL:
                </p>

                <ul class="error-list">
                    <?php foreach ($errors as $field => $msg): ?>
                        <li class="error-item">
                            <span class="material-symbols-outlined" style="font-size: 18px;">warning</span>
                            <span><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="action-buttons-group">
                    <a href="../page/register.html?mode=db" class="btn-db-action">
                        <span class="material-symbols-outlined">arrow_back</span>
                        <span>Return to Registration Form</span>
                    </a>
                    <a href="test_connection.php" class="btn-secondary-action">
                        <span class="material-symbols-outlined">construction</span>
                        <span>Check MySQL Server Status</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="../js/script.js"></script>
</body>
</html>
