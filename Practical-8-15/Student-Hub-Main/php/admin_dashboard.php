<?php
/**
 * Practical 14: Admin Dashboard with RBAC, Dynamic Menu, and Audit Log
 * File: php/admin_dashboard.php
 * Description: Aggregated administrative analytics API endpoint.
 *              Strictly enforces RBAC (admin only). Provides live aggregated
 *              metrics (students, events, registrations, contact inquiries),
 *              live security audit logs, and recent registration activity feed.
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_logger.php';

startSecureSession();

// -----------------------------------------------------------------------------
// 1. Role-Based Access Control (RBAC): Admin Authorization Guard
// -----------------------------------------------------------------------------
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required. Please sign in to the administrative portal.',
        'redirect' => '../page/login.html?error=auth_required'
    ]);
    exit;
}

$currentUser = getCurrentUser();
if (($currentUser['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access Denied: Administrative privileges required to access this console.',
        'redirect' => '../page/student-dashboard.html?error=forbidden'
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// 2. Database Connection
// -----------------------------------------------------------------------------
try {
    $mysqli = getMySQLiConnection(true);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

$action = trim($_GET['action'] ?? 'overview');

switch ($action) {

    // =========================================================================
    // 1. AGGREGATED OVERVIEW & METRICS
    // =========================================================================
    case 'overview':
        try {
            // Count Students
            $resStudents = $mysqli->query("SELECT COUNT(*) AS total FROM students WHERE role != 'admin' OR role IS NULL");
            $totalStudents = (int)($resStudents ? $resStudents->fetch_assoc()['total'] : 0);

            // Count Events & Status Breakdown
            $resEvents = $mysqli->query("SELECT 
                COUNT(*) AS total_events,
                SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) AS upcoming_events,
                SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing_events,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_events,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_events,
                COALESCE(SUM(max_capacity), 0) AS total_capacity
                FROM events");
            $eventStats = $resEvents ? $resEvents->fetch_assoc() : [
                'total_events' => 0, 'upcoming_events' => 0, 'ongoing_events' => 0,
                'completed_events' => 0, 'cancelled_events' => 0, 'total_capacity' => 0
            ];

            // Count Total Registrations
            $resReg = $mysqli->query("SELECT COUNT(*) AS total FROM registrations");
            $totalRegistrations = (int)($resReg ? $resReg->fetch_assoc()['total'] : 0);

            // Count Inquiries
            $resInq = $mysqli->query("SELECT COUNT(*) AS total FROM contact_inquiries");
            $totalInquiries = (int)($resInq ? $resInq->fetch_assoc()['total'] : 0);

            // Category Distribution for Visual Analytics
            $resCats = $mysqli->query("SELECT category, COUNT(*) AS count FROM events WHERE category IS NOT NULL AND category != '' GROUP BY category ORDER BY count DESC LIMIT 8");
            $categoryDistribution = [];
            while ($row = $resCats->fetch_assoc()) {
                $categoryDistribution[] = [
                    'category' => $row['category'],
                    'count'    => (int)$row['count']
                ];
            }

            // Audit Logs Count
            $resLogs = $mysqli->query("SELECT COUNT(*) AS total FROM audit_logs");
            $totalAuditLogs = (int)($resLogs ? $resLogs->fetch_assoc()['total'] : 0);

            echo json_encode([
                'success' => true,
                'metrics' => [
                    'students'             => $totalStudents,
                    'events'               => (int)($eventStats['total_events'] ?? 0),
                    'upcoming_events'      => (int)($eventStats['upcoming_events'] ?? 0),
                    'ongoing_events'       => (int)($eventStats['ongoing_events'] ?? 0),
                    'completed_events'     => (int)($eventStats['completed_events'] ?? 0),
                    'cancelled_events'     => (int)($eventStats['cancelled_events'] ?? 0),
                    'registrations'        => $totalRegistrations,
                    'inquiries'            => $totalInquiries,
                    'audit_logs_count'     => $totalAuditLogs,
                    'category_distribution'=> $categoryDistribution
                ],
                'admin_user' => [
                    'user_id' => $currentUser['user_id'],
                    'name'    => $currentUser['name'],
                    'email'   => $currentUser['email'],
                    'role'    => $currentUser['role']
                ]
            ], JSON_PRETTY_PRINT);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Overview query error: ' . $e->getMessage()]);
        }
        break;

    // =========================================================================
    // 2. AUDIT LOGS LIST WITH FILTERING
    // =========================================================================
    case 'audit_logs':
        try {
            $filterAction = trim($_GET['action_filter'] ?? 'all');
            $limit = max(1, min(100, (int)($_GET['limit'] ?? 25)));

            $whereSql = "1=1";
            $params = [];
            $types = "";

            if ($filterAction !== '' && $filterAction !== 'all') {
                if ($filterAction === 'auth') {
                    $whereSql .= " AND action LIKE 'AUTH_%'";
                } elseif ($filterAction === 'event') {
                    $whereSql .= " AND (action LIKE '%_EVENT' OR action = 'STATUS_TOGGLE')";
                } elseif ($filterAction === 'student') {
                    $whereSql .= " AND action LIKE '%_STUDENT'";
                } else {
                    $whereSql .= " AND action = ?";
                    $params[] = $filterAction;
                    $types .= "s";
                }
            }

            $sql = "SELECT log_id, user_id, user_name, role, action, entity_type, entity_id, details, ip_address,
                           DATE_FORMAT(created_at, '%b %d, %Y • %h:%i:%s %p') AS formatted_time,
                           created_at
                    FROM audit_logs
                    WHERE {$whereSql}
                    ORDER BY log_id DESC
                    LIMIT ?";

            $params[] = $limit;
            $types .= "i";

            $stmt = $mysqli->prepare($sql);
            if ($types !== "") {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();

            $logs = [];
            while ($row = $res->fetch_assoc()) {
                $logs[] = $row;
            }
            $stmt->close();

            echo json_encode([
                'success' => true,
                'count'   => count($logs),
                'logs'    => $logs
            ], JSON_PRETTY_PRINT);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Audit logs query failed: ' . $e->getMessage()]);
        }
        break;

    // =========================================================================
    // 3. RECENT EVENT REGISTRATIONS FEED
    // =========================================================================
    case 'recent_registrations':
        try {
            $limit = max(1, min(50, (int)($_GET['limit'] ?? 6)));

            $sql = "SELECT 
                        r.registration_id, 
                        r.ticket_code, 
                        r.status, 
                        DATE_FORMAT(r.registered_at, '%b %d, %Y • %h:%i %p') AS formatted_date,
                        CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                        s.student_code,
                        s.department,
                        e.event_id,
                        e.title AS event_title,
                        e.category AS event_category,
                        e.badge AS event_badge
                    FROM registrations r
                    JOIN students s ON r.student_id = s.student_id
                    JOIN events e ON r.event_id = e.event_id
                    ORDER BY r.registration_id DESC
                    LIMIT ?";

            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $res = $stmt->get_result();

            $registrations = [];
            while ($row = $res->fetch_assoc()) {
                $registrations[] = $row;
            }
            $stmt->close();

            echo json_encode([
                'success'       => true,
                'count'         => count($registrations),
                'registrations' => $registrations
            ], JSON_PRETTY_PRINT);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Recent registrations query failed: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Unsupported action '{$action}' requested."]);
        break;
}
