<?php
/**
 * Practical 14: Institutional Security & Audit Logging System
 * File: php/audit_logger.php
 * Description: Centralized audit logging utility recording administrative operations,
 *              authentication events, entity mutations, IP addresses, and timestamps.
 *              Guarantees high-integrity audit trails with prepared statements and
 *              non-blocking error handling.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

/**
 * Resolves the client's public or local IP address safely.
 *
 * @return string
 */
function getClientIpAddress(): string {
    $ip = '127.0.0.1';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return substr($ip, 0, 45);
}

/**
 * Records an entry into the audit_logs table.
 *
 * @param string $action AUTH_LOGIN, AUTH_LOGOUT, CREATE_EVENT, UPDATE_EVENT, DELETE_EVENT, STATUS_TOGGLE, CREATE_STUDENT, UPDATE_STUDENT, DELETE_STUDENT
 * @param string $entityType auth, event, student, registration, system
 * @param int|null $entityId The primary key ID of the affected entity
 * @param string|null $details Descriptive summary of the operation
 * @param int|null $userId Override user ID (defaults to current session user)
 * @param string|null $userName Override user name (defaults to current session user)
 * @param string|null $role Override user role (defaults to current session role)
 * @return bool True if logged successfully, false otherwise (never throws)
 */
function recordAuditLog(
    string $action,
    string $entityType = 'system',
    ?int $entityId = null,
    ?string $details = null,
    ?int $userId = null,
    ?string $userName = null,
    ?string $role = null
): bool {
    try {
        if ($userId === null) {
            if (function_exists('isLoggedIn') && isLoggedIn()) {
                $cur = getCurrentUser();
                $userId = (int)($cur['user_id'] ?? 0);
                $userName = $userName ?: ($cur['name'] ?? 'Admin User');
                $role = $role ?: ($cur['role'] ?? 'admin');
            } else {
                $userId = 0;
                $userName = $userName ?: 'System';
                $role = $role ?: 'system';
            }
        }

        $ip = getClientIpAddress();
        $pdo = getDBConnection(true);

        $stmt = $pdo->prepare("INSERT INTO `audit_logs` 
            (`user_id`, `user_name`, `role`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) 
            VALUES (:uid, :uname, :role, :action, :entity_type, :entity_id, :details, :ip, NOW())");

        return $stmt->execute([
            ':uid'         => $userId,
            ':uname'       => substr($userName ?? 'System', 0, 100),
            ':role'        => substr($role ?? 'admin', 0, 30),
            ':action'      => substr($action, 0, 50),
            ':entity_type' => substr($entityType, 0, 50),
            ':entity_id'   => $entityId,
            ':details'     => $details,
            ':ip'          => $ip
        ]);
    } catch (Throwable $e) {
        // Silently fail so audit logging never crashes core user or administrative operations
        error_log("StudentHub Audit Log Error: " . $e->getMessage());
        return false;
    }
}
