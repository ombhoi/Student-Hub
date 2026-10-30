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
$firstName = trim($nameParts[0] ?? '');
$lastName  = trim($nameParts[1] ?? '');

// Allowed valid academic departments
$validDepartments = [
    'Computer Engineering',
    'Information Technology',
    'AI & Data Science',
    'Electronics & Communication',
    'Mechanical Engineering',
    'Civil Engineering',
    'Electrical Engineering'
];

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
if (empty($fullName) || !preg_match('/^[a-zA-Z\s\.\']{3,60}$/', $fullName)) {
    $errors['fullname'] = 'Full name must be 3-60 characters containing letters and spaces only.';
} elseif (empty($firstName) || empty($lastName)) {
    $errors['fullname'] = 'Please enter both your first name and last name (e.g., Alex Johnson).';
} elseif (strcasecmp($lastName, 'Student') === 0) {
    $errors['fullname'] = 'Please enter your actual last name, not "Student".';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please provide a valid academic email address.';
}

if (empty($phone) || !preg_match('/^[1-9]\d{9}$/', $phone)) {
    $errors['phone'] = 'Mobile number must be a valid 10-digit number starting with 1-9.';
}

if (empty($department) || $department === '0' || !in_array($department, $validDepartments, true)) {
    $errors['department'] = 'Please select a valid academic department from the list.';
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

        // Normalize capitalization for authentic presentation
        $firstName = ucwords(strtolower($firstName));
        $lastName  = ucwords(strtolower($lastName));
        $fullName  = $firstName . ' ' . $lastName;

        // A. Check for Duplicate Email in MySQL database using Prepared Statement
        $checkStmt = $pdo->prepare("SELECT student_id FROM students WHERE email = :email LIMIT 1");
        $checkStmt->execute([':email' => $email]);
        if ($checkStmt->fetch()) {
            $errors['email'] = 'A student account with this email address is already registered in the MySQL database.';
        } else {
            // A. Generate Unique Sequential Student Code (e.g., STU-001, STU-002, ...)
            $maxIdStmt = $pdo->query("SELECT MAX(student_id) AS max_id FROM students");
            $nextNumericId = (int)($maxIdStmt->fetchColumn() ?: 0) + 1;
            $studentCode = sprintf('STU-%03d', $nextNumericId);

            // Generate Academic Roll Number (e.g. 26CE089)
            $deptPrefix = 'CE';
            if (strpos($department, 'Information') !== false) $deptPrefix = 'IT';
            elseif (strpos($department, 'Data') !== false || strpos($department, 'AI') !== false) $deptPrefix = 'AI';
            elseif (strpos($department, 'Electronics') !== false) $deptPrefix = 'EC';
            elseif (strpos($department, 'Mechanical') !== false) $deptPrefix = 'ME';
            elseif (strpos($department, 'Civil') !== false) $deptPrefix = 'CL';
            elseif (strpos($department, 'Electrical') !== false) $deptPrefix = 'EE';

            $rollNumber = '26' . $deptPrefix . str_pad((string)mt_rand(1, 999), 3, '0', STR_PAD_LEFT);

            // B. Hash Password Securely with BCRYPT (Practical 9 Core Requirement)
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // C. Insert into Main `students` Table via Prepared Statement (Practical 9)
            $insertSql = "INSERT INTO students 
                          (student_code, first_name, last_name, email, password, roll_number, department, semester, phone, bio, role) 
                          VALUES (:student_code, :first_name, :last_name, :email, :password, :roll_number, :department, :semester, :phone, :bio, 'student')";
            
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                ':student_code' => $studentCode,
                ':first_name'   => $firstName,
                ':last_name'    => $lastName,
                ':email'        => $email,
                ':password'     => $hashedPassword,
                ':roll_number'  => $rollNumber,
                ':department'   => $department,
                ':semester'     => $semester,
                ':phone'        => $phone,
                ':bio'          => 'Registered student account created via StudentHub portal.'
            ]);

            $dbStudentId = (int)$pdo->lastInsertId();
            $success = true;

            $submittedData = [
                'student_id'   => $dbStudentId,
                'student_code' => $studentCode,
                'fullname'     => $fullName,
                'email'        => $email,
                'phone'        => $phone,
                'roll_number'  => $rollNumber,
                'department'   => $department,
                'semester'     => $semester,
                'year'         => $year,
                'registered_at'=> date('Y-m-d H:i:s')
            ];

            // If AJAX request, return JSON response
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success'      => true,
                    'message'      => 'Registration successful! Your account is created.',
                    'student_code' => $studentCode,
                    'redirect'     => '../page/login.html?registered=1&code=' . urlencode($studentCode) . '&email=' . urlencode($email),
                    'data'         => $submittedData
                ]);
                exit;
            }
        }
    } catch (PDOException $e) {
        $errors['db'] = 'Database Connection or Query Error: ' . $e->getMessage() . '. Ensure MySQL is running in XAMPP and studenthub_db is created.';
    }
}

// Return JSON error for AJAX requests
if (!empty($errors) && ((!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false))) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'errors' => $errors, 'message' => reset($errors)]);
    exit;
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
                    <span class="material-symbols-outlined" style="font-size: 16px;">verified_user</span>
                    Practical 9: Secure Registration (Bcrypt Hashed)
                </span>
                <h1 class="result-title">Student Account Created Successfully!</h1>
                <p style="color: var(--text-secondary); font-size: 14.5px;">
                    Your account has been securely stored in the <strong>students</strong> table using a PHP PDO prepared statement with parameterized binding and bcrypt password hashing. You can now log into your student dashboard.
                </p>

                <div class="db-info-box">
                    <div style="font-size: 12px; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">
                        Official Unique Student ID | Roll No: <?= $submittedData['roll_number'] ?>
                    </div>
                    <div class="ticket-code"><?= $submittedData['student_code'] ?></div>
                    <span style="font-size: 12.5px; color: var(--text-secondary);">
                        Use this <strong><?= $submittedData['student_code'] ?></strong> or your email to log in and unlock protected portal pages.
                    </span>
                </div>

                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Student ID</div>
                        <div class="summary-value" style="font-weight: 700; color: #2563eb;"><?= htmlspecialchars($submittedData['student_code'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
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
                </div>

                <div class="action-buttons-group">
                    <a href="../page/login.html?registered=1&code=<?= urlencode($submittedData['student_code']) ?>&email=<?= urlencode($submittedData['email']) ?>" class="btn-db-action">
                        <span class="material-symbols-outlined">login</span>
                        <span>Proceed to Login (Authenticate)</span>
                    </a>
                    <a href="../page/register.html" class="btn-secondary-action">
                        <span class="material-symbols-outlined">person_add</span>
                        <span>Register Another Student</span>
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
