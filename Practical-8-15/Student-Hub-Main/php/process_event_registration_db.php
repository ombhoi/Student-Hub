<?php
/**
 * Practical 8: Event Registration Processing via MySQL (PDO Prepared Statements)
 * File: php/process_event_registration_db.php
 * Description: Validates student identity against `students` table in MySQL,
 *              checks duplicate booking against `registrations` table,
 *              and inserts event registration using PDO prepared statements.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/audit_logger.php';

startSecureSession();

// Helper function to check if request expects JSON
function isJsonRequest() {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return (strpos($accept, 'application/json') !== false) ||
           (strtolower($requestedWith) === 'xmlhttprequest') ||
           (isset($_POST['ajax']) && $_POST['ajax'] === '1');
}

// Support GET requests for student lookup modal & registered events
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $pdo = getDBConnection(true);
        if ($_GET['action'] === 'my_registrations') {
            if (!isLoggedIn() || empty($_SESSION['user_id'])) {
                echo json_encode(['success' => true, 'authenticated' => false, 'registrations' => []]);
                exit;
            }
            $stmt = $pdo->prepare("
                SELECT r.registration_id, r.event_id, r.ticket_code, r.status, r.registered_at,
                       e.title AS event_title, e.event_date,
                       s.first_name, s.last_name, s.student_code, s.department
                FROM registrations r
                JOIN events e ON r.event_id = e.event_id
                JOIN students s ON r.student_id = s.student_id
                WHERE r.student_id = :sid
                ORDER BY r.registration_id DESC
            ");
            $stmt->execute([':sid' => (int)$_SESSION['user_id']]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $mapped = [];
            foreach ($rows as $r) {
                $mapped[$r['event_id']] = [
                    'id'                => $r['registration_id'],
                    'registration_id'   => $r['ticket_code'],
                    'event_id'          => $r['event_id'],
                    'event_title'       => $r['event_title'],
                    'event_date'        => $r['event_date'],
                    'student_id'        => $r['student_code'] ?: ('STU-' . $r['registration_id']),
                    'fullname'          => trim($r['first_name'] . ' ' . $r['last_name']),
                    'department'        => $r['department'] ?: 'StudentHub Scholar',
                    'registration_date' => $r['registered_at'],
                    'status'            => $r['status']
                ];
            }
            echo json_encode(['success' => true, 'authenticated' => true, 'registrations' => $mapped]);
            exit;
        }
        if ($_GET['action'] === 'get_students') {
            $stmt = $pdo->query("SELECT student_id, CONCAT(first_name, ' ', last_name) AS fullname, email, department, roll_number FROM students ORDER BY student_id ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $students = [];
            foreach ($rows as $r) {
                $students[] = [
                    'id'         => (int)$r['student_id'],
                    'student_id' => $r['roll_number'] ?: ('STU-' . (1000 + (int)$r['student_id'])),
                    'fullname'   => $r['fullname'],
                    'email'      => $r['email'],
                    'department' => $r['department']
                ];
            }
            echo json_encode(['success' => true, 'students' => $students, 'source' => 'MySQL']);
            exit;
        }
        if ($_GET['action'] === 'get_registrations') {
            $stmt = $pdo->query("SELECT * FROM vw_student_registrations ORDER BY registration_id DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'registrations' => $rows, 'source' => 'MySQL vw_student_registrations']);
            exit;
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Enforce POST request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../page/events.html');
    exit;
}

if (!isLoggedIn()) {
    if (isJsonRequest()) {
        http_response_code(401);
        echo json_encode([
            'success'       => false,
            'authenticated' => false,
            'message'       => 'Authentication required. Please log in with your StudentHub account to register for events.',
            'redirect'      => '../page/login.html?error=auth_required&redirect=events.html'
        ]);
        exit;
    } else {
        header('Location: ../page/login.html?error=auth_required&redirect=events.html');
        exit;
    }
}

$eventId           = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$submittedTitle    = trim($_POST['event_title'] ?? '');
$studentIdentifier = trim($_POST['student_id'] ?? ($_POST['student_identifier'] ?? ($_POST['email'] ?? '')));

// Fallback to logged-in session student code if empty
if (empty($studentIdentifier) && isLoggedIn()) {
    $studentIdentifier = $_SESSION['student_code'] ?? ($_SESSION['email'] ?? '');
}

$response = [
    'success' => false,
    'message' => '',
    'registration' => null
];

// Basic Input Validation
if ($eventId <= 0) {
    $response['message'] = 'Invalid event selected. Please choose a valid campus event.';
    sendResponse($response);
}

try {
    $pdo = getDBConnection(true);

    // 1. Verify Event in `events` table (retrieve title and event_date)
    $stmtEvt = $pdo->prepare("SELECT event_id, title, event_date FROM events WHERE event_id = :eid");
    $stmtEvt->execute([':eid' => $eventId]);
    $eventRecord = $stmtEvt->fetch(PDO::FETCH_ASSOC);

    if (!$eventRecord) {
        $eventTitle = $submittedTitle ?: ('Campus Event #' . $eventId);
        $eventDate  = date('Y-m-d 14:00:00', strtotime('+7 days'));
        // Ensure event exists in `events` table so foreign key constraint never fails
        $stmtCreateEvt = $pdo->prepare("
            INSERT INTO events (event_id, title, description, category, badge, location, event_date, status) 
            VALUES (:eid, :title, 'Official university campus event.', 'Tech & Innovation', 'Campus', 'Main Campus Auditorium', :edate, 'upcoming') 
            ON DUPLICATE KEY UPDATE title = VALUES(title)
        ");
        $stmtCreateEvt->execute([
            ':eid'   => $eventId,
            ':title' => $eventTitle,
            ':edate' => $eventDate
        ]);
    } else {
        $eventTitle = $eventRecord['title'];
        $eventDate  = $eventRecord['event_date'];
    }

    // 2. Identify Student: Prioritize currently authenticated session user
    $student = null;
    if (isLoggedIn() && !empty($_SESSION['user_id'])) {
        $stmtSession = $pdo->prepare("SELECT student_id, student_code, first_name, last_name, email, department, roll_number FROM students WHERE student_id = :sid LIMIT 1");
        $stmtSession->execute([':sid' => (int)$_SESSION['user_id']]);
        $student = $stmtSession->fetch(PDO::FETCH_ASSOC);
    }

    // If not found in session, or if an explicit identifier was passed
    if (!$student && !empty($studentIdentifier)) {
        $cleanId = trim($studentIdentifier);
        $numericId = ctype_digit($cleanId) ? (int)$cleanId : 0;
        $stmtStu = $pdo->prepare("
            SELECT student_id, student_code, first_name, last_name, email, department, roll_number 
            FROM students 
            WHERE LOWER(student_code) = :code
               OR LOWER(email) = :email 
               OR LOWER(roll_number) = :roll
               OR student_id = :numId
            LIMIT 1
        ");
        $stmtStu->execute([
            ':code'  => strtolower($cleanId),
            ':email' => strtolower($cleanId),
            ':roll'  => strtolower($cleanId),
            ':numId' => $numericId
        ]);
        $student = $stmtStu->fetch(PDO::FETCH_ASSOC);
    }

    if (!$student) {
        $response['success'] = false;
        $response['not_found'] = true;
        $response['not_registered'] = true;
        $response['message'] = "Student account not found in database. Please log in or register your student account first.";
        $response['action'] = "register_now";
        sendResponse($response);
    }

    $dbStudentId = (int)$student['student_id'];
    $fullName = $student['first_name'] . ' ' . $student['last_name'];
    $stuDisplayId = $student['student_code'] ?: ($student['roll_number'] ?: ('STU-' . sprintf('%03d', $dbStudentId)));

    // 3. Check for Duplicate Registration in `registrations`
    $stmtDup = $pdo->prepare("SELECT registration_id, ticket_code, status, registered_at FROM registrations WHERE student_id = :sid AND event_id = :eid");
    $stmtDup->execute([':sid' => $dbStudentId, ':eid' => $eventId]);
    $existingReg = $stmtDup->fetch(PDO::FETCH_ASSOC);

    if ($existingReg) {
        $response['success'] = true;
        $response['already_registered'] = true;
        $response['message'] = 'You are already registered for "' . htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') . '"!';
        $response['registration'] = [
            'id'                => $existingReg['registration_id'],
            'registration_id'   => $existingReg['ticket_code'],
            'event_id'          => $eventId,
            'event_title'       => $eventTitle,
            'event_date'        => $eventDate,
            'student_id'        => $stuDisplayId,
            'fullname'          => $fullName,
            'email'             => $student['email'],
            'department'        => $student['department'] ?: 'Engineering',
            'registration_date' => $existingReg['registered_at'],
            'status'            => $existingReg['status']
        ];
        sendResponse($response);
    }

    // 4. Insert into `registrations` Table with event_id, event_date, ticket_code, and 'confirm' flag
    $ticketCode = 'SH-2026-EVT-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    $now = date('Y-m-d H:i:s');

    $stmtIns = $pdo->prepare("
        INSERT INTO registrations (student_id, event_id, event_date, ticket_code, status, registered_at)
        VALUES (:sid, :eid, :evt_date, :ticket, 'confirm', :now)
    ");
    $stmtIns->execute([
        ':sid'      => $dbStudentId,
        ':eid'      => $eventId,
        ':evt_date' => $eventDate,
        ':ticket'   => $ticketCode,
        ':now'      => $now
    ]);

    $lastRegId = (int)$pdo->lastInsertId();

    // Practical 14: Institutional Audit Trail
    if (function_exists('recordAuditLog')) {
        recordAuditLog(
            'EVENT_REGISTRATION',
            'registration',
            $lastRegId,
            "Student {$fullName} ({$stuDisplayId}) registered for event '{$eventTitle}' with ticket {$ticketCode}",
            $dbStudentId,
            $fullName,
            'student'
        );
    }

    $newRegistration = [
        'id'                => $lastRegId,
        'registration_id'   => $ticketCode,
        'event_id'          => $eventId,
        'event_title'       => $eventTitle,
        'event_date'        => $eventDate,
        'student_id'        => $stuDisplayId,
        'fullname'          => $fullName,
        'email'             => $student['email'],
        'department'        => $student['department'] ?: 'Engineering',
        'registration_date' => $now,
        'status'            => 'confirm',
        'source'            => 'MySQL Database (Practical 8)'
    ];

    $response['success'] = true;
    $response['message'] = 'Event Registration Confirmed in MySQL for ' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . '!';
    $response['registration'] = $newRegistration;

    sendResponse($response);

} catch (PDOException $e) {
    $response['success'] = false;
    $response['message'] = 'Database error: ' . $e->getMessage();
    sendResponse($response);
}

/**
 * Output JSON or render HTML confirmation
 */
function sendResponse($resp) {
    if (isJsonRequest()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resp);
        exit;
    }
    
    // HTML Fallback Page
    $success = $resp['success'];
    $msg     = $resp['message'];
    $data    = $resp['registration'] ?? null;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>StudentHub - Event Registration Result (MySQL Practical 8)</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
        <link rel="stylesheet" href="../css/register.css">
        <style>
            .result-card {
                max-width: 600px;
                margin: 60px auto;
                background: var(--card-bg, #fff);
                border: 1px solid var(--card-border, #ede4dc);
                border-radius: 24px;
                padding: 40px;
                text-align: center;
                box-shadow: 0 16px 40px -8px rgba(120, 80, 50, 0.1);
            }
            .badge-code {
                font-family: monospace;
                font-size: 22px;
                font-weight: 700;
                color: #2563eb;
                background: #eff6ff;
                padding: 10px 20px;
                border-radius: 12px;
                display: inline-block;
                margin: 16px 0;
            }
            .btn-action {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                background: #2563eb;
                color: #fff;
                border-radius: 12px;
                text-decoration: none;
                font-weight: 600;
                margin: 8px 4px;
            }
        </style>
    </head>
    <body>
        <main class="result-container" style="padding: 20px;">
            <div class="result-card">
                <?php if ($success): ?>
                    <span class="material-symbols-outlined" style="font-size: 54px; color: #15803d;">task_alt</span>
                    <h1 style="font-family: var(--font-serif); margin-top: 10px; font-size: 28px;">Event Registration Recorded in MySQL!</h1>
                    <p style="color: var(--text-secondary); margin-top: 6px;"><?= $msg ?></p>
                    <div class="badge-code"><?= htmlspecialchars($data['registration_id']) ?></div>
                    <div style="text-align: left; background: var(--bg-color); padding: 18px; border-radius: 14px; margin: 20px 0; font-size: 14px; line-height: 1.6;">
                        <div><strong>Event:</strong> <?= htmlspecialchars($data['event_title']) ?> (ID: <?= $data['event_id'] ?>)</div>
                        <div><strong>Student ID:</strong> <?= htmlspecialchars($data['student_id']) ?></div>
                        <div><strong>Student Name:</strong> <?= htmlspecialchars($data['fullname']) ?></div>
                        <div><strong>Database:</strong> Inserted into <code>registrations</code> table (FK to <code>students</code>)</div>
                        <div><strong>Date:</strong> <?= htmlspecialchars($data['registration_date']) ?></div>
                    </div>
                <?php else: ?>
                    <span class="material-symbols-outlined" style="font-size: 54px; color: #b91c1c;">error</span>
                    <h1 style="font-family: var(--font-serif); margin-top: 10px; font-size: 28px;">
                        <?= (isset($resp['not_found']) || isset($resp['not_registered'])) ? "Student Not Found in Database" : "Registration Notice" ?>
                    </h1>
                    <p style="color: var(--text-secondary); margin-top: 6px;"><?= $msg ?></p>
                <?php endif; ?>
                <div style="margin-top: 24px;">
                    <?php if (isset($resp['not_found']) || isset($resp['not_registered'])): ?>
                        <a href="../page/register.html?mode=db" class="btn-action">Register Account</a>
                    <?php endif; ?>
                    <a href="../page/events.html" class="btn-action" style="<?= (isset($resp['not_found']) || isset($resp['not_registered'])) ? 'background: transparent; color: #2563eb; border: 1px solid #2563eb;' : '' ?>">Back to Events</a>
                    <a href="test_connection.php" class="btn-action" style="background: transparent; color: #2563eb; border: 1px solid #2563eb;">DB Diagnostics</a>
                </div>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}
