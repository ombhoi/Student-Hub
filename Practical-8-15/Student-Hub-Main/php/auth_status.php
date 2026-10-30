<?php
/**
 * Practical 10: Auth Status & Session State API
 * File: php/auth_status.php
 * Description: Returns current session authentication state, logged-in user profile,
 *              and remaining session seconds in JSON format for client-side routing.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/session.php';

startSecureSession();

$wasTimeout = !empty($_SESSION['timeout_expired']);
if ($wasTimeout) {
    unset($_SESSION['timeout_expired']);
    echo json_encode([
        'authenticated' => false,
        'timeout'       => true,
        'message'       => 'Your session has timed out due to inactivity. Please log in again.'
    ]);
    exit;
}

if (isLoggedIn()) {
    $user = getCurrentUser();
    
    // Calculate initials (e.g., Alex Johnson -> AJ)
    $firstInitial = !empty($user['first_name']) ? mb_substr($user['first_name'], 0, 1) : '';
    $lastInitial  = !empty($user['last_name']) ? mb_substr($user['last_name'], 0, 1) : '';
    $initials     = strtoupper($firstInitial . $lastInitial);
    if (empty($initials)) $initials = 'ST';

    $user['initials'] = $initials;

    $lastActivity = $_SESSION['LAST_ACTIVITY'] ?? time();
    $remainingSeconds = max(0, SESSION_TIMEOUT_SECONDS - (time() - $lastActivity));

    echo json_encode([
        'authenticated'     => true,
        'timeout'           => false,
        'session_remaining' => $remainingSeconds,
        'user'              => $user
    ]);
    exit;
}

echo json_encode([
    'authenticated'     => false,
    'timeout'           => false,
    'session_remaining' => 0,
    'user'              => null
]);
exit;
