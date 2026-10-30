<?php
/**
 * Practical 12: Event Management CRUD with Poster Upload and File Validation
 * File: php/event_crud.php
 * Description: Robust backend CRUD API for StudentHub Campus Event Moderation.
 *              Strictly uses MySQLi prepared statements as mandated in syllabus.
 *              Handles SELECT, INSERT, UPDATE, DELETE, File Uploads with strict MIME/size
 *              validation, sanitized image renaming, and directory security.
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_logger.php';

startSecureSession();

// -----------------------------------------------------------------------------
// 1. RBAC: Verify administrator privileges
// -----------------------------------------------------------------------------
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
        'message' => 'Access Denied: Administrative authorization required to moderate events.'
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// 2. MySQLi Connection (Practical 12 syllabus requirement)
// -----------------------------------------------------------------------------
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

// -----------------------------------------------------------------------------
// 3. Helper: File Upload & Validation (MIME type, size, image integrity, renaming)
// -----------------------------------------------------------------------------
/**
 * Validates and stores uploaded event poster image.
 *
 * @param array $file $_FILES['poster'] entry
 * @param string|null $existingImage Path to previous image if replacing
 * @return array ['success' => bool, 'path' => string, 'error' => string|null]
 */
function handlePosterUpload(array $file, ?string $existingImage = null): array {
    // If no file was selected or empty upload
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => $existingImage ?: 'image/Full-Stack.jpg', 'error' => null];
    }

    // Check PHP upload error codes
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'Uploaded file exceeds PHP upload_max_filesize directive.',
            UPLOAD_ERR_FORM_SIZE  => 'Uploaded file exceeds HTML MAX_FILE_SIZE limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload directory on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write uploaded file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension halted the file upload.'
        ];
        return ['success' => false, 'path' => null, 'error' => $errorMessages[$file['error']] ?? 'Unknown upload error.'];
    }

    // File size validation (Strict Max 2MB = 2,097,152 bytes)
    $maxFileSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxFileSize) {
        return ['success' => false, 'path' => null, 'error' => 'Poster image exceeds maximum allowed size of 2MB (' . round($file['size'] / 1024 / 1024, 2) . 'MB uploaded).'];
    }

    if ($file['size'] === 0) {
        return ['success' => false, 'path' => null, 'error' => 'Uploaded file is empty.'];
    }

    // Validate Extension
    $originalName = basename($file['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($extension, $allowedExtensions, true)) {
        return ['success' => false, 'path' => null, 'error' => "Invalid file extension '.{$extension}'. Allowed formats: JPG, JPEG, PNG, WEBP, GIF."];
    }

    // Validate Real MIME Type using finfo
    $tmpPath = $file['tmp_name'];
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];

    $detectedMime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $detectedMime = mime_content_type($tmpPath);
    }

    if ($detectedMime === null || !array_key_exists($detectedMime, $allowedMimeTypes)) {
        return ['success' => false, 'path' => null, 'error' => "Security Validation Failed: MIME type '{$detectedMime}' is not an authorized image format."];
    }

    // Verify it is a valid renderable image via getimagesize()
    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        return ['success' => false, 'path' => null, 'error' => 'Corrupt or invalid image file. Server could not parse image dimensions.'];
    }

    // Advanced Syllabus Feature: Image Renaming & Upload Directory Protection
    $targetDir = realpath(__DIR__ . '/../uploads/events');
    if (!$targetDir) {
        $makeDir = __DIR__ . '/../uploads/events';
        if (!is_dir($makeDir)) {
            @mkdir($makeDir, 0755, true);
        }
        $targetDir = realpath($makeDir);
    }

    if (!$targetDir || !is_writable($targetDir)) {
        return ['success' => false, 'path' => null, 'error' => 'Uploads folder is not writable on server. Please check directory permissions.'];
    }

    // Sanitize and generate unique collision-free filename
    $uniqueSuffix = date('Ymd_His') . '_' . bin2hex(random_bytes(6));
    $sanitizedFilename = 'event_poster_' . $uniqueSuffix . '.' . $extension;
    $destination = $targetDir . DIRECTORY_SEPARATOR . $sanitizedFilename;

    if (!move_uploaded_file($tmpPath, $destination)) {
        return ['success' => false, 'path' => null, 'error' => 'Failed to save uploaded poster to target directory.'];
    }

    $relativeWebPath = 'uploads/events/' . $sanitizedFilename;

    // Clean up old custom uploaded poster if it was in uploads/events/
    if ($existingImage && strpos($existingImage, 'uploads/events/') !== false) {
        $oldFilePath = realpath(__DIR__ . '/../' . $existingImage);
        if ($oldFilePath && file_exists($oldFilePath) && strpos($oldFilePath, $targetDir) === 0) {
            @unlink($oldFilePath);
        }
    }

    return ['success' => true, 'path' => $relativeWebPath, 'error' => null];
}

// -----------------------------------------------------------------------------
// 4. Request Action Routing
// -----------------------------------------------------------------------------
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {

    // =========================================================================
    // 1. LIST / SEARCH / FILTER EVENTS (READ)
    // =========================================================================
    case 'list':
        header('Content-Type: application/json; charset=utf-8');

        $search   = trim($_GET['search'] ?? '');
        $category = trim($_GET['category'] ?? '');
        $status   = trim($_GET['status'] ?? '');
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $limit    = max(1, min(100, (int)($_GET['limit'] ?? 6)));
        $offset   = ($page - 1) * $limit;

        $whereClauses = ["1=1"];
        $params = [];
        $types = "";

        if ($search !== '') {
            $whereClauses[] = "(title LIKE ? OR description LIKE ? OR location LIKE ? OR badge LIKE ?)";
            $searchWildcard = "%{$search}%";
            $params[] = $searchWildcard;
            $params[] = $searchWildcard;
            $params[] = $searchWildcard;
            $params[] = $searchWildcard;
            $types .= "ssss";
        }

        if ($category !== '' && strtolower($category) !== 'all') {
            $whereClauses[] = "category = ?";
            $params[] = $category;
            $types .= "s";
        }

        if ($status !== '' && strtolower($status) !== 'all') {
            $whereClauses[] = "status = ?";
            $params[] = $status;
            $types .= "s";
        }

        $whereSql = "WHERE " . implode(" AND ", $whereClauses);

        // Count total records
        $countSql = "SELECT COUNT(*) AS total FROM events {$whereSql}";
        $countStmt = $mysqli->prepare($countSql);
        if ($types !== "") {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        // Fetch paginated events
        $dataSql = "SELECT event_id, title, description, category, badge, location,
                           DATE_FORMAT(event_date, '%Y-%m-%d %H:%i:%s') AS event_date,
                           DATE_FORMAT(event_date, '%b %d, %Y') AS formatted_date,
                           DATE_FORMAT(event_date, '%h:%i %p') AS formatted_time,
                           DATE_FORMAT(event_date, '%b %d, %Y • %h:%i %p') AS date_time_full,
                           image, max_capacity, status, created_at, updated_at
                    FROM events {$whereSql}
                    ORDER BY event_date ASC, event_id DESC
                    LIMIT ? OFFSET ?";

        $dataParams = $params;
        $dataParams[] = $limit;
        $dataParams[] = $offset;
        $dataTypes = $types . "ii";

        $dataStmt = $mysqli->prepare($dataSql);
        $dataStmt->bind_param($dataTypes, ...$dataParams);
        $dataStmt->execute();
        $res = $dataStmt->get_result();

        $events = [];
        while ($row = $res->fetch_assoc()) {
            $events[] = $row;
        }
        $dataStmt->close();

        // Fetch distinct categories for dynamic filter
        $catRes = $mysqli->query("SELECT DISTINCT category FROM events WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
        $categories = [];
        while ($c = $catRes->fetch_assoc()) {
            $categories[] = $c['category'];
        }

        // Summary metrics for dashboard
        $metricsRes = $mysqli->query("SELECT 
            COUNT(*) AS total_events,
            SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) AS upcoming_events,
            SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing_events,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_events,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_events,
            COALESCE(SUM(max_capacity), 0) AS total_capacity
            FROM events");
        $metrics = $metricsRes->fetch_assoc();

        echo json_encode([
            'success'     => true,
            'events'      => $events,
            'total'       => $totalRows,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => ceil($totalRows / $limit),
            'categories'  => $categories,
            'metrics'     => [
                'total_events'     => (int)$metrics['total_events'],
                'upcoming_events'  => (int)$metrics['upcoming_events'],
                'ongoing_events'   => (int)$metrics['ongoing_events'],
                'completed_events' => (int)$metrics['completed_events'],
                'cancelled_events' => (int)$metrics['cancelled_events'],
                'total_capacity'   => (int)$metrics['total_capacity']
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        break;

    // =========================================================================
    // 2. GET SINGLE EVENT RECORD
    // =========================================================================
    case 'get':
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_GET['event_id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid event identifier.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT event_id, title, description, category, badge, location,
                                         DATE_FORMAT(event_date, '%Y-%m-%dT%H:%i') AS event_date_input,
                                         DATE_FORMAT(event_date, '%b %d, %Y • %h:%i %p') AS formatted_datetime,
                                         image, max_capacity, status, created_at, updated_at
                                  FROM events WHERE event_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$event) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Event record not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'event' => $event], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        break;

    // =========================================================================
    // 3. CREATE NEW EVENT (WITH POSTER UPLOAD & VALIDATION)
    // =========================================================================
    case 'create':
        header('Content-Type: application/json; charset=utf-8');

        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $badge       = trim($_POST['badge'] ?? 'Event');
        $location    = trim($_POST['location'] ?? '');
        $eventDate   = trim($_POST['event_date'] ?? '');
        $maxCapacity = max(1, (int)($_POST['max_capacity'] ?? 100));
        $status      = trim($_POST['status'] ?? 'upcoming');

        // Validation
        $errors = [];
        if ($title === '') $errors[] = 'Event title is required.';
        if ($category === '') $errors[] = 'Event category must be selected.';
        if ($location === '') $errors[] = 'Event venue / location is required.';
        if ($eventDate === '') {
            $errors[] = 'Event date and time must be provided.';
        } elseif (!strtotime($eventDate)) {
            $errors[] = 'Invalid event date format.';
        }
        $validStatuses = ['upcoming', 'ongoing', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses, true)) {
            $status = 'upcoming';
        }

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors]);
            exit;
        }

        // Format date for MySQL DATETIME
        $mysqlDate = date('Y-m-d H:i:s', strtotime($eventDate));

        // Poster Upload & Validation
        $imagePath = 'image/Full-Stack.jpg';
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = handlePosterUpload($_FILES['poster']);
            if (!$uploadResult['success']) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => $uploadResult['error']]);
                exit;
            }
            $imagePath = $uploadResult['path'];
        }

        // MySQLi Prepared Statement Insert
        $insertSql = "INSERT INTO events (title, description, category, badge, location, event_date, image, max_capacity, status)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($insertSql);
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Query preparation failure: ' . $mysqli->error]);
            exit;
        }

        $stmt->bind_param("sssssssis", $title, $description, $category, $badge, $location, $mysqlDate, $imagePath, $maxCapacity, $status);
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();

            // Practical 14: Audit Logging
            recordAuditLog('CREATE_EVENT', 'event', $newId, "Published new event #{$newId}: {$title} ({$category}).");

            http_response_code(201);
            echo json_encode([
                'success'  => true,
                'message'  => "Event '{$title}' successfully published and registered in campus registry.",
                'event_id' => $newId,
                'image'    => $imagePath
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            $error = $stmt->error;
            $stmt->close();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database execution failed: ' . $error]);
        }
        break;

    // =========================================================================
    // 4. UPDATE EXISTING EVENT (OPTIONAL POSTER REPLACEMENT)
    // =========================================================================
    case 'update':
        header('Content-Type: application/json; charset=utf-8');

        $eventId     = (int)($_POST['event_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $badge       = trim($_POST['badge'] ?? 'Event');
        $location    = trim($_POST['location'] ?? '');
        $eventDate   = trim($_POST['event_date'] ?? '');
        $maxCapacity = max(1, (int)($_POST['max_capacity'] ?? 100));
        $status      = trim($_POST['status'] ?? 'upcoming');

        if ($eventId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid event identifier required for update.']);
            exit;
        }

        // Verify event exists and fetch existing image
        $checkStmt = $mysqli->prepare("SELECT event_id, image, title FROM events WHERE event_id = ?");
        $checkStmt->bind_param("i", $eventId);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Event record not found.']);
            exit;
        }

        // Validation
        $errors = [];
        if ($title === '') $errors[] = 'Event title cannot be empty.';
        if ($category === '') $errors[] = 'Event category must be selected.';
        if ($location === '') $errors[] = 'Venue location cannot be empty.';
        if ($eventDate === '') {
            $errors[] = 'Event date and time must be provided.';
        } elseif (!strtotime($eventDate)) {
            $errors[] = 'Invalid event date format.';
        }
        $validStatuses = ['upcoming', 'ongoing', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses, true)) {
            $status = 'upcoming';
        }

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors]);
            exit;
        }

        $mysqlDate = date('Y-m-d H:i:s', strtotime($eventDate));
        $imagePath = $existing['image'];

        // Handle new poster upload if provided
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = handlePosterUpload($_FILES['poster'], $existing['image']);
            if (!$uploadResult['success']) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => $uploadResult['error']]);
                exit;
            }
            $imagePath = $uploadResult['path'];
        }

        // MySQLi Prepared Statement Update
        $updateSql = "UPDATE events SET title=?, description=?, category=?, badge=?, location=?, event_date=?, image=?, max_capacity=?, status=? WHERE event_id=?";
        $stmt = $mysqli->prepare($updateSql);
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Query preparation failure: ' . $mysqli->error]);
            exit;
        }

        $stmt->bind_param("sssssssisi", $title, $description, $category, $badge, $location, $mysqlDate, $imagePath, $maxCapacity, $status, $eventId);
        if ($stmt->execute()) {
            $stmt->close();

            // Practical 14: Audit Logging
            recordAuditLog('UPDATE_EVENT', 'event', $eventId, "Updated event #{$eventId}: {$title} (Status: {$status}, Capacity: {$maxCapacity}).");

            echo json_encode([
                'success' => true,
                'message' => "Event '{$title}' updated successfully.",
                'image'   => $imagePath
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            $error = $stmt->error;
            $stmt->close();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database execution failed: ' . $error]);
        }
        break;

    // =========================================================================
    // 5. DELETE EVENT (CONFIRMED DELETION & OPTIONAL IMAGE CLEANUP)
    // =========================================================================
    case 'delete':
        header('Content-Type: application/json; charset=utf-8');

        $eventId = (int)($_POST['event_id'] ?? $_GET['event_id'] ?? 0);
        if ($eventId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid event identifier for deletion.']);
            exit;
        }

        // Check if event exists
        $fetchStmt = $mysqli->prepare("SELECT event_id, title, image FROM events WHERE event_id = ?");
        $fetchStmt->bind_param("i", $eventId);
        $fetchStmt->execute();
        $event = $fetchStmt->get_result()->fetch_assoc();
        $fetchStmt->close();

        if (!$event) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Event record not found or already deleted.']);
            exit;
        }

        // Delete associated registrations first to maintain relational integrity
        $delRegStmt = $mysqli->prepare("DELETE FROM registrations WHERE event_id = ?");
        if ($delRegStmt) {
            $delRegStmt->bind_param("i", $eventId);
            $delRegStmt->execute();
            $delRegStmt->close();
        }

        // Delete event using MySQLi prepared statement
        $delStmt = $mysqli->prepare("DELETE FROM events WHERE event_id = ?");
        $delStmt->bind_param("i", $eventId);

        if ($delStmt->execute()) {
            $delStmt->close();

            // Practical 14: Audit Logging
            recordAuditLog('DELETE_EVENT', 'event', $eventId, "Permanently deleted event #{$eventId}: {$event['title']}.");

            // Clean up custom uploaded poster file if stored in uploads/events/
            if (!empty($event['image']) && strpos($event['image'], 'uploads/events/') !== false) {
                $targetFile = realpath(__DIR__ . '/../' . $event['image']);
                $uploadDir = realpath(__DIR__ . '/../uploads/events');
                if ($targetFile && file_exists($targetFile) && strpos($targetFile, $uploadDir) === 0) {
                    @unlink($targetFile);
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Event '{$event['title']}' permanently removed from campus registry."
            ], JSON_PRETTY_PRINT);
        } else {
            $error = $delStmt->error;
            $delStmt->close();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete event: ' . $error]);
        }
        break;

    // =========================================================================
    // 6. QUICK STATUS TOGGLE
    // =========================================================================
    case 'status_toggle':
        header('Content-Type: application/json; charset=utf-8');

        $eventId   = (int)($_POST['event_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $validStatuses = ['upcoming', 'ongoing', 'completed', 'cancelled'];

        if ($eventId <= 0 || !in_array($newStatus, $validStatuses, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid event identifier or status value.']);
            exit;
        }

        $stmt = $mysqli->prepare("UPDATE events SET status = ? WHERE event_id = ?");
        $stmt->bind_param("si", $newStatus, $eventId);
        if ($stmt->execute()) {
            $stmt->close();

            // Practical 14: Audit Logging
            recordAuditLog('STATUS_TOGGLE', 'event', $eventId, "Toggled status of event #{$eventId} to '{$newStatus}'.");

            echo json_encode([
                'success' => true,
                'message' => "Event status updated to '{$newStatus}'."
            ]);
        } else {
            $error = $stmt->error;
            $stmt->close();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Status update failed: ' . $error]);
        }
        break;

    // =========================================================================
    // 7. GET LIVE METRICS FOR HERO / DASHBOARD
    // =========================================================================
    case 'metrics':
        header('Content-Type: application/json; charset=utf-8');

        $metricsRes = $mysqli->query("SELECT 
            COUNT(*) AS total_events,
            SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) AS upcoming_events,
            SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing_events,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_events,
            COALESCE(SUM(max_capacity), 0) AS total_capacity
            FROM events");
        $metrics = $metricsRes->fetch_assoc();

        echo json_encode([
            'success' => true,
            'metrics' => [
                'total_events'     => (int)$metrics['total_events'],
                'upcoming_events'  => (int)$metrics['upcoming_events'],
                'ongoing_events'   => (int)$metrics['ongoing_events'],
                'completed_events' => (int)$metrics['completed_events'],
                'total_capacity'   => (int)$metrics['total_capacity']
            ]
        ]);
        break;

    // =========================================================================
    // 8. PRACTICAL 13: LIVE API PING & CONTRACT VERIFICATION
    // =========================================================================
    case 'ping':
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);
        echo json_encode([
            'success'     => true,
            'status_code' => 200,
            'api'         => 'StudentHub Campus Events RESTful JSON API',
            'protocol'    => 'JavaScript Fetch API (Asynchronous CRUD without Page Reload)',
            'practical'   => 'Practical 13: PHP JSON API and Fetch-based CRUD',
            'timestamp'   => date('Y-m-d H:i:s'),
            'database'    => 'MySQL (InnoDB, MySQLi Prepared Statements)',
            'features'    => [
                'Zero Full-Page Reload (SPA Architecture)',
                'Debounced Live Search (300ms delay)',
                'Multipart File Upload via FormData',
                'HTTP Status Code Protocol (200, 201, 400, 401, 403, 404, 422, 500)',
                'Centralized JSON Error Handling'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        break;

    default:
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => "Unsupported action '{$action}'. Supported actions: list, get, create, update, delete, status_toggle, metrics, ping."
        ]);
        break;
}

$mysqli->close();
