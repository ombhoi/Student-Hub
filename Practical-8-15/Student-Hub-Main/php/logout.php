<?php
/**
 * Practical 10: Secure Logout Handler
 * File: php/logout.php
 * Description: Terminates user session securely, destroys session cookie,
 *              clears remember-me token in DB, and redirects to login with confirmation.
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/audit_logger.php';

startSecureSession();

if (isLoggedIn()) {
    $user = getCurrentUser();
    recordAuditLog(
        'AUTH_LOGOUT',
        'auth',
        (int)($user['user_id'] ?? 0),
        "User logged out successfully.",
        (int)($user['user_id'] ?? 0),
        $user['name'] ?? 'User',
        $user['role'] ?? 'student'
    );
}

destroyUserSession();

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'  => true,
        'message'  => 'Logged out successfully.',
        'redirect' => '../page/login.html?msg=logged_out'
    ]);
    exit;
}

header('Location: ../page/login.html?msg=logged_out');
exit;
