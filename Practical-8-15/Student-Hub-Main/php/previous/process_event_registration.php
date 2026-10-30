<?php
/**
 * Practical 7: Event Registration Processing using File Storage
 * File: php/previous/process_event_registration.php
 * Description: Reads registered student credentials from data/registrations.json.
 *              Validates existence of student, checks duplicate event bookings,
 *              and stores event registrations into data/event_registrations.json and data/event_registrations.csv.
 * Location: Archived in php/previous/ for safe preservation.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

$registrationsJsonPath = __DIR__ . '/../../data/registrations.json';
$registrationsCsvPath  = __DIR__ . '/../../data/registrations.csv';
$eventRegJsonPath      = __DIR__ . '/../../data/event_registrations.json';
$eventRegCsvPath       = __DIR__ . '/../../data/event_registrations.csv';
$eventsJsonPath        = __DIR__ . '/../../data/events.json';

// Helper function to check if request expects JSON
function isJsonRequest() {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return (strpos($accept, 'application/json') !== false) ||
           (strtolower($requestedWith) === 'xmlhttprequest') ||
           (isset($_POST['ajax']) && $_POST['ajax'] === '1');
}

// --------------------------------------------------------------------------
// 1. GET Request: Provide API for Registered Students (for modal helper)
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_GET['action'] === 'get_students') {
        $students = [];
        if (file_exists($registrationsJsonPath)) {
            $data = json_decode(file_get_contents($registrationsJsonPath), true);
            if (is_array($data)) {
                foreach ($data as $s) {
                    $stuId = $s['student_id'] ?? ('STU-' . (1000 + (int)($s['id'] ?? 0)));
                    $students[] = [
                        'id'         => $s['id'] ?? 0,
                        'student_id' => $stuId,
                        'fullname'   => $s['fullname'] ?? '',
                        'email'      => $s['email'] ?? '',
                        'department' => $s['department'] ?? ''
                    ];
                }
            }
        }
        echo json_encode(['success' => true, 'students' => $students]);
        exit;
    }

    if ($_GET['action'] === 'get_registrations') {
        $regs = [];
        if (file_exists($eventRegJsonPath)) {
            $data = json_decode(file_get_contents($eventRegJsonPath), true);
            if (is_array($data)) {
                $regs = $data;
            }
        }
        echo json_encode(['success' => true, 'registrations' => $regs]);
        exit;
    }
}

// --------------------------------------------------------------------------
// 2. POST Request: Process Event Registration
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../page/events.html');
    exit;
}

$eventId           = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$submittedTitle    = trim($_POST['event_title'] ?? '');
$studentIdentifier = trim($_POST['student_id'] ?? ($_POST['student_identifier'] ?? ($_POST['email'] ?? '')));

$response = [
    'success' => false,
    'message' => '',
    'registration' => null
];

// Basic Input Validation
if ($eventId <= 0) {
    $response['message'] = 'Invalid event selected. Please select an event to register.';
    sendResponse($response);
}

if (empty($studentIdentifier)) {
    $response['message'] = 'Please enter your registered Student Unique ID (e.g., STU-1001).';
    sendResponse($response);
}

// Read events list to fetch event details
$eventsList = [];
if (file_exists($eventsJsonPath)) {
    $rawEvents = file_get_contents($eventsJsonPath);
    if ($rawEvents !== false) {
        $decoded = json_decode($rawEvents, true);
        if (is_array($decoded)) {
            $eventsList = $decoded;
        }
    }
}

$eventDetails = null;
foreach ($eventsList as $evt) {
    if ((int)($evt['id'] ?? 0) === $eventId) {
        $eventDetails = $evt;
        break;
    }
}

$eventTitle = $eventDetails ? $eventDetails['title'] : ($submittedTitle ?: 'Campus Event #' . $eventId);

// Read student registrations from data/registrations.json
$registeredStudents = [];
if (file_exists($registrationsJsonPath)) {
    $rawStudents = file_get_contents($registrationsJsonPath);
    if ($rawStudents !== false) {
        $decoded = json_decode($rawStudents, true);
        if (is_array($decoded)) {
            $registeredStudents = $decoded;
        }
    }
}

// Fallback/read from data/registrations.csv if available
if (file_exists($registrationsCsvPath)) {
    $fpCsvRead = fopen($registrationsCsvPath, 'r');
    if ($fpCsvRead) {
        $headers = fgetcsv($fpCsvRead);
        if ($headers !== false) {
            $colMap = array_flip($headers);
            while (($row = fgetcsv($fpCsvRead)) !== false) {
                if (empty($row) || !isset($colMap['student_id'])) continue;
                $rowStuId = trim($row[$colMap['student_id']] ?? '');
                if (empty($rowStuId)) continue;
                $alreadyLoaded = false;
                foreach ($registeredStudents as $s) {
                    if (strcasecmp($s['student_id'] ?? '', $rowStuId) === 0) {
                        $alreadyLoaded = true;
                        break;
                    }
                }
                if (!$alreadyLoaded) {
                    $registeredStudents[] = [
                        'id'         => isset($colMap['id']) ? (int)$row[$colMap['id']] : (count($registeredStudents) + 1),
                        'student_id' => $rowStuId,
                        'fullname'   => isset($colMap['fullname']) ? $row[$colMap['fullname']] : '',
                        'email'      => isset($colMap['email']) ? $row[$colMap['email']] : '',
                        'department' => isset($colMap['department']) ? $row[$colMap['department']] : ''
                    ];
                }
            }
        }
        fclose($fpCsvRead);
    }
}

// Lookup student in registered students
$studentFound = null;
$cleanInput = strtolower(trim($studentIdentifier));

// If student typed e.g. "1001", normalize to "stu-1001"
if (preg_match('/^100\d$/', $cleanInput)) {
    $cleanInput = 'stu-' . $cleanInput;
}

foreach ($registeredStudents as $student) {
    $stuId    = strtolower(trim($student['student_id'] ?? ('stu-' . (1000 + (int)($student['id'] ?? 0)))));
    $stuEmail = strtolower(trim($student['email'] ?? ''));
    
    if ($cleanInput === $stuId || $cleanInput === $stuEmail) {
        $studentFound = $student;
        if (!isset($studentFound['student_id'])) {
            $studentFound['student_id'] = 'STU-' . (1000 + (int)($student['id'] ?? 0));
        }
        break;
    }
}

// Check if student exists in registrations.json / registrations.csv
if ($studentFound === null) {
    $response['success'] = false;
    $response['not_found'] = true;
    $response['not_registered'] = true;
    $response['message'] = "ID doesn't found";
    $response['action'] = "register_now";
    sendResponse($response);
}

// Read existing event registrations from data/event_registrations.json
$eventRegistrations = [];
if (file_exists($eventRegJsonPath)) {
    $rawEventReg = file_get_contents($eventRegJsonPath);
    if ($rawEventReg !== false) {
        $decoded = json_decode($rawEventReg, true);
        if (is_array($decoded)) {
            $eventRegistrations = $decoded;
        }
    }
}

// Check if student is already registered for this event
foreach ($eventRegistrations as $reg) {
    if ((int)($reg['event_id'] ?? 0) === $eventId && 
        (strtolower($reg['student_id'] ?? '') === strtolower($studentFound['student_id']) || 
         strtolower($reg['email'] ?? '') === strtolower($studentFound['email']))) {
        
        $response['success'] = false;
        $response['already_registered'] = true;
        $response['message'] = 'Student ' . htmlspecialchars($studentFound['fullname'], ENT_QUOTES, 'UTF-8') . ' (' . $studentFound['student_id'] . ') is already registered for "' . htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') . '"!';
        $response['registration'] = $reg;
        sendResponse($response);
    }
}

// Create new Event Registration Record
$nextId = count($eventRegistrations) + 1;
$regId  = 'EVT-REG-' . (1000 + $nextId);
$now    = date('Y-m-d H:i:s');

$newEventRecord = [
    'id'                => $nextId,
    'registration_id'   => $regId,
    'event_id'          => $eventId,
    'event_title'       => html_entity_decode($eventTitle, ENT_QUOTES, 'UTF-8'),
    'student_id'        => $studentFound['student_id'],
    'fullname'          => html_entity_decode($studentFound['fullname'], ENT_QUOTES, 'UTF-8'),
    'email'             => $studentFound['email'],
    'department'        => html_entity_decode($studentFound['department'], ENT_QUOTES, 'UTF-8'),
    'registration_date' => $now,
    'status'            => 'Confirmed'
];

$eventRegistrations[] = $newEventRecord;

// Ensure data folder exists
$dataDir = dirname($eventRegJsonPath);
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// 1. Atomic write to data/event_registrations.json
$fpJson = fopen($eventRegJsonPath, 'c+');
if ($fpJson) {
    if (flock($fpJson, LOCK_EX)) {
        ftruncate($fpJson, 0);
        rewind($fpJson);
        fwrite($fpJson, json_encode($eventRegistrations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fpJson);
        flock($fpJson, LOCK_UN);
    }
    fclose($fpJson);
}

// 2. Atomic write to data/event_registrations.csv
$csvExists = file_exists($eventRegCsvPath) && filesize($eventRegCsvPath) > 0;
$fpCsv = fopen($eventRegCsvPath, 'a');
if ($fpCsv) {
    if (flock($fpCsv, LOCK_EX)) {
        if (!$csvExists) {
            fputcsv($fpCsv, ['id', 'registration_id', 'event_id', 'event_title', 'student_id', 'fullname', 'email', 'department', 'registration_date', 'status']);
        }
        fputcsv($fpCsv, [
            $newEventRecord['id'],
            $newEventRecord['registration_id'],
            $newEventRecord['event_id'],
            $newEventRecord['event_title'],
            $newEventRecord['student_id'],
            $newEventRecord['fullname'],
            $newEventRecord['email'],
            $newEventRecord['department'],
            $newEventRecord['registration_date'],
            $newEventRecord['status']
        ]);
        fflush($fpCsv);
        flock($fpCsv, LOCK_UN);
    }
    fclose($fpCsv);
}

$response['success'] = true;
$response['message'] = 'Event Registration Confirmed for ' . htmlspecialchars($studentFound['fullname'], ENT_QUOTES, 'UTF-8') . '!';
$response['registration'] = $newEventRecord;

sendResponse($response);

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
        <title>StudentHub - Event Registration Result</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
        <link rel="stylesheet" href="../../css/register.css">
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
                color: #852d1b;
                background: #faece5;
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
                background: #852d1b;
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
                    <span class="material-symbols-outlined" style="font-size: 54px; color: #15803d;">check_circle</span>
                    <h1 style="font-family: var(--font-serif); margin-top: 10px; font-size: 28px;">Event Registration Confirmed!</h1>
                    <p style="color: var(--text-secondary); margin-top: 6px;"><?= $msg ?></p>
                    <div class="badge-code"><?= htmlspecialchars($data['registration_id']) ?></div>
                    <div style="text-align: left; background: var(--bg-color); padding: 18px; border-radius: 14px; margin: 20px 0; font-size: 14px; line-height: 1.6;">
                        <div><strong>Event:</strong> <?= htmlspecialchars($data['event_title']) ?> (ID: <?= $data['event_id'] ?>)</div>
                        <div><strong>Student ID:</strong> <?= htmlspecialchars($data['student_id']) ?></div>
                        <div><strong>Student Name:</strong> <?= htmlspecialchars($data['fullname']) ?></div>
                        <div><strong>Department:</strong> <?= htmlspecialchars($data['department']) ?></div>
                        <div><strong>Date:</strong> <?= htmlspecialchars($data['registration_date']) ?></div>
                    </div>
                <?php else: ?>
                    <span class="material-symbols-outlined" style="font-size: 54px; color: #b91c1c;">error</span>
                    <h1 style="font-family: var(--font-serif); margin-top: 10px; font-size: 28px;">
                        <?= (isset($resp['not_found']) || isset($resp['not_registered'])) ? "ID doesn't found" : "Registration Notice" ?>
                    </h1>
                    <p style="color: var(--text-secondary); margin-top: 6px;">
                        <?= (isset($resp['not_found']) || isset($resp['not_registered'])) ? "Student Unique ID was not found in registrations. Please register yourself first before registering for events." : $msg ?>
                    </p>
                <?php endif; ?>
                <div style="margin-top: 24px;">
                    <?php if (isset($resp['not_found']) || isset($resp['not_registered'])): ?>
                        <a href="../../page/register.html" class="btn-action">Register Now</a>
                    <?php endif; ?>
                    <a href="../../page/events.html" class="btn-action" style="<?= (isset($resp['not_found']) || isset($resp['not_registered'])) ? 'background: transparent; color: #852d1b; border: 1px solid #852d1b;' : '' ?>">Back to Events</a>
                    <a href="view_file_records.php" class="btn-action" style="background: transparent; color: #852d1b; border: 1px solid #852d1b;">View File Records</a>
                </div>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}
