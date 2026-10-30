<?php
/**
 * Practical 7: PHP Form Processing with Server-Side Validation & CSV/JSON File Storage
 * File: php/previous/process_registration_file.php
 * Description: Processes student registration, sanitizes inputs, validates server-side,
 *              and safely stores records concurrently in both JSON and CSV files.
 * Location: Archived in php/previous/ for safe preservation.
 */

// Enable defensive error reporting during development
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Paths to storage files in data/
$jsonFilePath = __DIR__ . '/../../data/registrations.json';
$csvFilePath  = __DIR__ . '/../../data/registrations.csv';

$errors = [];
$success = false;
$submittedData = [];

// Enforce POST request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../page/register.html');
    exit;
}

// -------------------------------------------------------------
// 1. Sanitize Incoming POST Parameters
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

// -------------------------------------------------------------
// 2. Server-Side Validation using Regular Expressions
// -------------------------------------------------------------

// Name Validation: 3-50 letters and spaces only
if (empty($fullName)) {
    $errors['fullname'] = 'Full name is required.';
} elseif (!preg_match('/^[a-zA-Z\s]{3,50}$/', $fullName)) {
    $errors['fullname'] = 'Full name must be 3-50 characters containing only letters and spaces.';
}

// Academic Email Validation: valid email format
if (empty($email)) {
    $errors['email'] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid university email address.';
}

// Phone Number Validation: 10 digits starting with 6-9
if (empty($phone)) {
    $errors['phone'] = 'Mobile number is required.';
} elseif (!preg_match('/^[6-9]\d{9}$/', $phone)) {
    $errors['phone'] = 'Phone must be a valid 10-digit number starting with 6-9.';
}

// Department Validation
$validDepts = [
    'Computer Engineering',
    'Information Technology',
    'Artificial Intelligence & Data Science',
    'Electronics & Communication',
    'Mechanical Engineering',
    'Civil Engineering'
];
if (empty($department) || !in_array($department, $validDepts, true)) {
    $errors['department'] = 'Please select a valid academic department from the list.';
}

// Academic Year Validation
$validYears = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
if (empty($year) || !in_array($year, $validYears, true)) {
    $errors['year'] = 'Please select a valid academic year.';
}

// Gender Validation
$validGenders = ['Male', 'Female', 'Other'];
if (empty($gender) || !in_array($gender, $validGenders, true)) {
    $errors['gender'] = 'Please select your gender.';
}

// Password Validation
$passwordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
if (empty($password)) {
    $errors['password'] = 'Password is required.';
} elseif (!preg_match($passwordRegex, $password)) {
    $errors['password'] = 'Password must be at least 8 chars with 1 uppercase, 1 lowercase, 1 number, and 1 special character (@$!%*?&).';
}

// Confirm Password Validation
if ($password !== $confirmPw) {
    $errors['confirm_password'] = 'Confirm password does not match password.';
}

// Terms Agreement Validation
if (!$terms) {
    $errors['terms'] = 'You must accept the terms of use and university code of conduct.';
}

// -------------------------------------------------------------
// 3. Process & Store Submissions if Validation Passes
// -------------------------------------------------------------
if (empty($errors)) {

    // Ensure data directory exists
    $dataDir = dirname($jsonFilePath);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    // 1. Read existing records from JSON for duplicate email check & ID generation
    $existingRegistrations = [];
    if (file_exists($jsonFilePath)) {
        $rawJson = file_get_contents($jsonFilePath);
        if ($rawJson !== false && !empty(trim($rawJson))) {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded)) {
                $existingRegistrations = $decoded;
            }
        }
    }

    // Check duplicate email
    $isDuplicate = false;
    foreach ($existingRegistrations as $reg) {
        if (isset($reg['email']) && strcasecmp($reg['email'], $email) === 0) {
            $isDuplicate = true;
            break;
        }
    }

    if ($isDuplicate) {
        $errors['email'] = 'A student with this academic email address has already been registered.';
    } else {
        // Calculate next ID
        $nextId = count($existingRegistrations) + 1;
        $studentUniqueId = 'STU-' . (1000 + $nextId);
        $registrationTime = date('Y-m-d H:i:s');

        // Prepare sanitized record
        $record = [
            'id'            => $nextId,
            'student_id'    => $studentUniqueId,
            'fullname'      => html_entity_decode($fullName, ENT_QUOTES, 'UTF-8'),
            'email'         => strtolower($email),
            'phone'         => $phone,
            'department'    => $department,
            'year'          => $year,
            'gender'        => $gender,
            'registered_at' => $registrationTime
        ];

        // A. Atomic write to JSON with flock
        $existingRegistrations[] = $record;
        $fpJson = fopen($jsonFilePath, 'c+');
        if ($fpJson) {
            if (flock($fpJson, LOCK_EX)) {
                ftruncate($fpJson, 0);
                rewind($fpJson);
                fwrite($fpJson, json_encode($existingRegistrations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                fflush($fpJson);
                flock($fpJson, LOCK_UN);
            }
            fclose($fpJson);
        }

        // B. Atomic write to CSV with flock
        $csvFileExists = file_exists($csvFilePath) && filesize($csvFilePath) > 0;
        $fpCsv = fopen($csvFilePath, 'a');
        if ($fpCsv) {
            if (flock($fpCsv, LOCK_EX)) {
                // If CSV file is newly created, write header row
                if (!$csvFileExists) {
                    fputcsv($fpCsv, ['id', 'student_id', 'fullname', 'email', 'phone', 'department', 'year', 'gender', 'registered_at']);
                }
                fputcsv($fpCsv, [
                    $record['id'],
                    $record['student_id'],
                    $record['fullname'],
                    $record['email'],
                    $record['phone'],
                    $record['department'],
                    $record['year'],
                    $record['gender'],
                    $record['registered_at']
                ]);
                fflush($fpCsv);
                flock($fpCsv, LOCK_UN);
            }
            fclose($fpCsv);
        }

        $success = true;
        $submittedData = $record;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Result | StudentHub Practical 7</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400..700;1,6..72,400..700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="../../css/register.css">
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
            background-color: #dcfce7;
            color: #15803d;
        }
        body[data-theme="dark"] .status-badge.success {
            background-color: #052e16;
            color: #86efac;
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
        .ticket-box {
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
        .btn-view-records {
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
        .btn-view-records:hover { background-color: var(--primary-hover); }
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
            <a href="../../index.html" class="brand-link">
                <img src="../../image/studenthub-logo.webp" alt="StudentHub Logo" class="brand-logo-img">
                <div class="brand-text">
                    <span class="brand-title">StudentHub</span>
                    <span class="brand-subtitle">University Portal</span>
                </div>
            </a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="../../index.html" class="nav-link">Home</a></li>
                    <li><a href="../../page/events.html" class="nav-link">Events</a></li>
                    <li><a href="../../page/about-us.html" class="nav-link">About</a></li>
                    <li><a href="../../page/FAQ.html" class="nav-link">FAQ</a></li>
                    <li><a href="../../page/contact.html" class="nav-link">Contact</a></li>
                </ul>
            </nav>
            <div class="nav-actions">
                <button id="theme-toggle" class="icon-btn" aria-label="Toggle theme">
                    <span class="material-symbols-outlined theme-icon">dark_mode</span>
                </button>
                <span class="nav-divider"></span>
                <a href="../../page/login.html" class="login-link">Login</a>
                <a href="../../page/register.html" class="btn-register">Register</a>
            </div>
        </div>
    </header>

    <main class="result-container">
        <div class="result-card">
            <?php if ($success): ?>
                <span class="status-badge success">
                    <span class="material-symbols-outlined" style="font-size: 16px;">verified</span>
                    Practical 7: File Storage Success
                </span>
                <h1 class="result-title">Registration Record Saved!</h1>
                <p style="color: var(--text-secondary); font-size: 14.5px;">
                    Your account has been processed and saved concurrently in <strong>data/registrations.json</strong> and <strong>data/registrations.csv</strong> using safe atomic file operations (flock).
                </p>

                <div class="ticket-box">
                    <span style="font-size: 12px; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Student Unique ID</span>
                    <div class="ticket-code"><?= $submittedData['student_id'] ?></div>
                    <span style="font-size: 12.5px; color: var(--text-secondary);">Registered: <?= $submittedData['registered_at'] ?></span>
                </div>

                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Student Name</div>
                        <div class="summary-value"><?= $submittedData['fullname'] ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Academic Email</div>
                        <div class="summary-value"><?= $submittedData['email'] ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Contact Number</div>
                        <div class="summary-value"><?= $submittedData['phone'] ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Department</div>
                        <div class="summary-value"><?= $submittedData['department'] ?></div>
                    </div>
                    <div class="summary-item" style="grid-column: 1 / -1;">
                        <div class="summary-label">Academic Year / Gender</div>
                        <div class="summary-value"><?= $submittedData['year'] ?> • <?= $submittedData['gender'] ?></div>
                    </div>
                </div>

                <div class="action-buttons-group">
                    <a href="view_file_records.php" class="btn-view-records">
                        <span class="material-symbols-outlined">table_view</span>
                        <span>View Stored JSON/CSV Records</span>
                    </a>
                    <a href="../../page/events.html" class="btn-secondary-action">
                        <span class="material-symbols-outlined">event</span>
                        <span>Browse Events with Student ID</span>
                    </a>
                    <a href="../../page/register.html" class="btn-secondary-action">
                        <span class="material-symbols-outlined">person_add</span>
                        <span>Register Another Student</span>
                    </a>
                </div>

            <?php else: ?>
                <span class="status-badge error">
                    <span class="material-symbols-outlined" style="font-size: 16px;">error</span>
                    Validation Errors Encountered
                </span>
                <h1 class="result-title">Registration Could Not Be Completed</h1>
                <p style="color: var(--text-secondary); font-size: 14.5px;">
                    Please correct the following server-side validation issues and re-submit:
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
                    <a href="../../page/register.html" class="btn-view-records">
                        <span class="material-symbols-outlined">arrow_back</span>
                        <span>Back to Registration Form</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="../../js/script.js"></script>
</body>
</html>
