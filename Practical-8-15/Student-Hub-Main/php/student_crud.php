<?php
/**
 * Practical 11: Student Management CRUD with Search and Filter
 * File: php/student_crud.php
 * Description: Complete backend CRUD API for StudentHub Administrative Console.
 *              Strictly uses MySQLi prepared statements as mandated in syllabus.
 *              Handles SELECT, INSERT, UPDATE, DELETE, Search, Filter, and CSV Export.
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_logger.php';

startSecureSession();

// RBAC: Verify administrator privileges
if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required. Please sign in to the administrative portal.'
    ]);
    exit;
}

$currentUser = getCurrentUser();
if (($currentUser['role'] ?? '') !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Access Denied: Administrative authorization required to manage student records.'
    ]);
    exit;
}

// Connect via MySQLi as required by Practical 11 specification
try {
    $mysqli = getMySQLiConnection(true);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failure: ' . $e->getMessage()
    ]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {

    // =========================================================================
    // 1. LIST / SEARCH / FILTER STUDENTS (READ)
    // =========================================================================
    case 'list':
        header('Content-Type: application/json; charset=utf-8');

        $search     = trim($_GET['search'] ?? '');
        $department = trim($_GET['department'] ?? '');
        $semester   = trim($_GET['semester'] ?? '');
        $page       = max(1, (int)($_GET['page'] ?? 1));
        $limit      = max(1, min(100, (int)($_GET['limit'] ?? 10)));
        $offset     = ($page - 1) * $limit;

        $whereClauses = ["role != 'admin'"]; // Focus CRUD on student records
        $params = [];
        $types = "";

        if ($search !== '') {
            $whereClauses[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR student_code LIKE ? OR roll_number LIKE ?)";
            $searchWildcard = "%{$search}%";
            $params[] = $searchWildcard;
            $params[] = $searchWildcard;
            $params[] = $searchWildcard;
            $params[] = $searchWildcard;
            $params[] = $searchWildcard;
            $types .= "sssss";
        }

        if ($department !== '' && strtolower($department) !== 'all') {
            $whereClauses[] = "department = ?";
            $params[] = $department;
            $types .= "s";
        }

        if ($semester !== '' && strtolower($semester) !== 'all') {
            $whereClauses[] = "semester = ?";
            $params[] = (int)$semester;
            $types .= "i";
        }

        $whereSql = "WHERE " . implode(" AND ", $whereClauses);

        // Count total matching records
        $countSql = "SELECT COUNT(*) AS total FROM students {$whereSql}";
        $countStmt = $mysqli->prepare($countSql);
        if ($types !== "") {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        // Fetch paginated data
        $dataSql = "SELECT student_id, student_code, first_name, last_name, email, roll_number, 
                           department, semester, phone, bio, role, created_at, last_login 
                    FROM students {$whereSql} 
                    ORDER BY student_id DESC 
                    LIMIT ? OFFSET ?";
        
        $dataParams = $params;
        $dataParams[] = $limit;
        $dataParams[] = $offset;
        $dataTypes = $types . "ii";

        $dataStmt = $mysqli->prepare($dataSql);
        $dataStmt->bind_param($dataTypes, ...$dataParams);
        $dataStmt->execute();
        $res = $dataStmt->get_result();

        $students = [];
        while ($row = $res->fetch_assoc()) {
            $students[] = $row;
        }
        $dataStmt->close();

        // Fetch distinct departments for the filter dropdown
        $deptRes = $mysqli->query("SELECT DISTINCT department FROM students WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
        $departments = [];
        while ($d = $deptRes->fetch_assoc()) {
            $departments[] = $d['department'];
        }

        // Summary metrics
        $metricsRes = $mysqli->query("SELECT COUNT(*) AS total_students, 
                                             COUNT(DISTINCT department) AS total_depts 
                                      FROM students WHERE role = 'student'");
        $metrics = $metricsRes->fetch_assoc();

        echo json_encode([
            'success'      => true,
            'students'     => $students,
            'total'        => $totalRows,
            'page'         => $page,
            'limit'        => $limit,
            'total_pages'  => ceil($totalRows / $limit),
            'departments'  => $departments,
            'metrics'      => [
                'total_students' => (int)$metrics['total_students'],
                'total_departments' => (int)$metrics['total_depts']
            ]
        ], JSON_PRETTY_PRINT);
        break;

    // =========================================================================
    // 2. GET SINGLE STUDENT RECORD
    // =========================================================================
    case 'get':
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_GET['student_id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid student identifier.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT student_id, student_code, first_name, last_name, email, roll_number, department, semester, phone, bio, role, created_at, last_login FROM students WHERE student_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$student) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Student record not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'student' => $student], JSON_PRETTY_PRINT);
        break;

    // =========================================================================
    // 3. CREATE / INSERT NEW STUDENT
    // =========================================================================
    case 'create':
        header('Content-Type: application/json; charset=utf-8');

        $firstName  = trim($_POST['first_name'] ?? '');
        $lastName   = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $rollNumber = strtoupper(trim($_POST['roll_number'] ?? ''));
        $department = trim($_POST['department'] ?? '');
        $semester   = max(1, min(8, (int)($_POST['semester'] ?? 1)));
        $phone      = trim($_POST['phone'] ?? '');
        $phoneDigits = preg_replace('/\D/', '', $phone);
        if ($phoneDigits !== '') {
            if (strlen($phoneDigits) > 10) {
                $phoneDigits = substr($phoneDigits, -10);
            }
            if (!preg_match('/^[1-9]\d{9}$/', $phoneDigits)) {
                $errors[] = 'Phone number must be a valid 10-digit mobile number starting with 1-9.';
            } else {
                $phone = $phoneDigits;
            }
        } else {
            $phone = '';
        }

        $bio        = trim($_POST['bio'] ?? '');
        $password   = trim($_POST['password'] ?? '');
        if ($password === '') {
            $password = 'password123';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Student account password must be at least 6 characters long.';
        }

        // Validation
        $errors = [];
        if ($firstName === '') $errors[] = 'First name is required.';
        if ($lastName === '')  $errors[] = 'Last name is required.';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid university email address is required.';
        }
        if ($rollNumber === '') $errors[] = 'Roll number / Student registration ID is required.';
        if ($department === '') $errors[] = 'Academic department must be selected.';

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors]);
            exit;
        }

        // Duplicate checks using MySQLi prepared statements
        $dupEmailStmt = $mysqli->prepare("SELECT student_id FROM students WHERE email = ? LIMIT 1");
        $dupEmailStmt->bind_param("s", $email);
        $dupEmailStmt->execute();
        if ($dupEmailStmt->get_result()->num_rows > 0) {
            $dupEmailStmt->close();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "Duplicate Error: Email '{$email}' is already registered with another student record."]);
            exit;
        }
        $dupEmailStmt->close();

        $dupRollStmt = $mysqli->prepare("SELECT student_id FROM students WHERE roll_number = ? LIMIT 1");
        $dupRollStmt->bind_param("s", $rollNumber);
        $dupRollStmt->execute();
        if ($dupRollStmt->get_result()->num_rows > 0) {
            $dupRollStmt->close();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "Duplicate Error: Roll number '{$rollNumber}' is already assigned to an existing record."]);
            exit;
        }
        $dupRollStmt->close();

        // Auto-generate formatted student_code e.g. STU-023
        $maxStmt = $mysqli->query("SELECT MAX(student_id) AS max_id FROM students");
        $nextId = ((int)($maxStmt->fetch_assoc()['max_id'] ?? 0)) + 1;
        $studentCode = sprintf('STU-%03d', $nextId);

        // Hash default/given password
        $hashedPass = password_hash($password, PASSWORD_BCRYPT);
        $role = 'student';

        // Execute MySQLi INSERT
        $insertStmt = $mysqli->prepare("INSERT INTO students (student_code, first_name, last_name, email, password, roll_number, department, semester, phone, bio, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $insertStmt->bind_param(
            "sssssssisss",
            $studentCode, $firstName, $lastName, $email, $hashedPass, $rollNumber, $department, $semester, $phone, $bio, $role
        );

        if ($insertStmt->execute()) {
            $newId = $insertStmt->insert_id;
            $insertStmt->close();

            // Practical 14: Audit Logging
            recordAuditLog('CREATE_STUDENT', 'student', $newId, "Enrolled new student #{$studentCode} ({$firstName} {$lastName}, {$department}).");

            echo json_encode([
                'success'      => true,
                'message'      => "Student account #{$studentCode} ({$firstName} {$lastName}) created successfully. Initial password: '{$password}'.",
                'student_id'   => $newId,
                'student_code' => $studentCode,
                'password'     => $password
            ]);
        } else {
            $err = $insertStmt->error;
            $insertStmt->close();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save student record: ' . $err]);
        }
        break;

    // =========================================================================
    // 4. UPDATE STUDENT RECORD
    // =========================================================================
    case 'update':
        header('Content-Type: application/json; charset=utf-8');

        $studentId  = (int)($_POST['student_id'] ?? 0);
        $firstName  = trim($_POST['first_name'] ?? '');
        $lastName   = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $rollNumber = strtoupper(trim($_POST['roll_number'] ?? ''));
        $department = trim($_POST['department'] ?? '');
        $semester   = max(1, min(8, (int)($_POST['semester'] ?? 1)));
        $phone      = trim($_POST['phone'] ?? '');
        $phoneDigits = preg_replace('/\D/', '', $phone);
        if ($phoneDigits !== '') {
            if (strlen($phoneDigits) > 10) {
                $phoneDigits = substr($phoneDigits, -10);
            }
            if (!preg_match('/^[1-9]\d{9}$/', $phoneDigits)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Phone number must be a valid 10-digit mobile number starting with 1-9.']);
                exit;
            }
            $phone = $phoneDigits;
        } else {
            $phone = '';
        }
        $bio        = trim($_POST['bio'] ?? '');

        if ($studentId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid student record identifier.']);
            exit;
        }

        // Validate required fields
        if ($firstName === '' || $lastName === '' || $email === '' || $rollNumber === '' || $department === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'First name, last name, email, roll number, and department are mandatory.']);
            exit;
        }

        // Check student existence
        $checkExist = $mysqli->prepare("SELECT student_id, student_code FROM students WHERE student_id = ?");
        $checkExist->bind_param("i", $studentId);
        $checkExist->execute();
        $existing = $checkExist->get_result()->fetch_assoc();
        $checkExist->close();

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Target student record does not exist.']);
            exit;
        }

        // Check duplicate email for OTHER students
        $dupEmailStmt = $mysqli->prepare("SELECT student_id FROM students WHERE email = ? AND student_id != ? LIMIT 1");
        $dupEmailStmt->bind_param("si", $email, $studentId);
        $dupEmailStmt->execute();
        if ($dupEmailStmt->get_result()->num_rows > 0) {
            $dupEmailStmt->close();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "Conflict: Email '{$email}' is already taken by another student."]);
            exit;
        }
        $dupEmailStmt->close();

        // Check duplicate roll number for OTHER students
        $dupRollStmt = $mysqli->prepare("SELECT student_id FROM students WHERE roll_number = ? AND student_id != ? LIMIT 1");
        $dupRollStmt->bind_param("si", $rollNumber, $studentId);
        $dupRollStmt->execute();
        if ($dupRollStmt->get_result()->num_rows > 0) {
            $dupRollStmt->close();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "Conflict: Roll number '{$rollNumber}' is already assigned to another student."]);
            exit;
        }
        $dupRollStmt->close();

        $password = trim($_POST['password'] ?? '');
        if ($password !== '') {
            if (strlen($password) < 6) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long.']);
                exit;
            }
            $hashedPass = password_hash($password, PASSWORD_BCRYPT);
            $updateStmt = $mysqli->prepare("UPDATE students SET first_name = ?, last_name = ?, email = ?, password = ?, roll_number = ?, department = ?, semester = ?, phone = ?, bio = ?, updated_at = NOW() WHERE student_id = ?");
            $updateStmt->bind_param(
                "ssssssissi",
                $firstName, $lastName, $email, $hashedPass, $rollNumber, $department, $semester, $phone, $bio, $studentId
            );
        } else {
            $updateStmt = $mysqli->prepare("UPDATE students SET first_name = ?, last_name = ?, email = ?, roll_number = ?, department = ?, semester = ?, phone = ?, bio = ?, updated_at = NOW() WHERE student_id = ?");
            $updateStmt->bind_param(
                "sssssissi",
                $firstName, $lastName, $email, $rollNumber, $department, $semester, $phone, $bio, $studentId
            );
        }

        if ($updateStmt->execute()) {
            $updateStmt->close();

            // Practical 14: Audit Logging
            recordAuditLog('UPDATE_STUDENT', 'student', $studentId, "Updated student profile #{$existing['student_code']} ({$firstName} {$lastName}).");

            echo json_encode([
                'success' => true,
                'message' => "Student record #{$existing['student_code']} ({$firstName} {$lastName}) updated successfully."
            ]);
        } else {
            $err = $updateStmt->error;
            $updateStmt->close();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database update error: ' . $err]);
        }
        break;

    // =========================================================================
    // 5. DELETE STUDENT RECORD
    // =========================================================================
    case 'delete':
        header('Content-Type: application/json; charset=utf-8');

        $studentId = (int)($_POST['student_id'] ?? 0);

        if ($studentId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid student identifier.']);
            exit;
        }

        // Prevent self-deletion of the logged-in administrator
        if ($studentId === (int)($currentUser['user_id'] ?? 0)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Protection Error: Cannot delete your own active administrator account.']);
            exit;
        }

        // Fetch student details before deletion for clear feedback message
        $fetchStmt = $mysqli->prepare("SELECT student_id, student_code, first_name, last_name, role FROM students WHERE student_id = ?");
        $fetchStmt->bind_param("i", $studentId);
        $fetchStmt->execute();
        $target = $fetchStmt->get_result()->fetch_assoc();
        $fetchStmt->close();

        if (!$target) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Student record not found or already deleted.']);
            exit;
        }

        if ($target['role'] === 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Protection Error: System administrator accounts cannot be deleted from student CRUD.']);
            exit;
        }

        // Execute MySQLi DELETE
        $delStmt = $mysqli->prepare("DELETE FROM students WHERE student_id = ?");
        $delStmt->bind_param("i", $studentId);

        if ($delStmt->execute()) {
            $delStmt->close();

            // Practical 14: Audit Logging
            recordAuditLog('DELETE_STUDENT', 'student', $studentId, "Permanently deleted student #{$target['student_code']} ({$target['first_name']} {$target['last_name']}).");

            echo json_encode([
                'success' => true,
                'message' => "Student record #{$target['student_code']} ({$target['first_name']} {$target['last_name']}) permanently removed from registry."
            ]);
        } else {
            $err = $delStmt->error;
            $delStmt->close();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database delete error: ' . $err]);
        }
        break;

    // =========================================================================
    // 6. EXPORT TO CSV (Practical 11 Intermediate Extension)
    // =========================================================================
    case 'export_csv':
        $search     = trim($_GET['search'] ?? '');
        $department = trim($_GET['department'] ?? '');

        $whereClauses = ["role != 'admin'"];
        $params = [];
        $types = "";

        if ($search !== '') {
            $whereClauses[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR student_code LIKE ? OR roll_number LIKE ?)";
            $wild = "%{$search}%";
            $params[] = $wild; $params[] = $wild; $params[] = $wild; $params[] = $wild; $params[] = $wild;
            $types .= "sssss";
        }

        if ($department !== '' && strtolower($department) !== 'all') {
            $whereClauses[] = "department = ?";
            $params[] = $department;
            $types .= "s";
        }

        $whereSql = "WHERE " . implode(" AND ", $whereClauses);
        $sql = "SELECT student_code, first_name, last_name, email, roll_number, department, semester, phone, created_at 
                FROM students {$whereSql} ORDER BY student_id ASC";

        $stmt = $mysqli->prepare($sql);
        if ($types !== "") {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        $filename = "studenthub_students_" . date('Y-m-d_His') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Student Code', 'First Name', 'Last Name', 'Email', 'Roll Number', 'Department', 'Semester', 'Phone', 'Registration Date']);

        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $row['student_code'],
                $row['first_name'],
                $row['last_name'],
                $row['email'],
                $row['roll_number'],
                $row['department'],
                $row['semester'],
                $row['phone'],
                $row['created_at']
            ]);
        }

        fclose($out);
        $stmt->close();
        exit;

    default:
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unknown action request.']);
        break;
}
