<?php
/**
 * Practical 10: Secure User Login with Sessions, password_verify(), RBAC, and Remember-Me
 * File: php/login.php
 * Description: Validates credentials, verifies bcrypt hash, regenerates session ID,
 *              updates last_login timestamp, handles remember-me cookies, and routes
 *              users by role (student -> dashboard, admin -> admin panel).
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/audit_logger.php';

// Accept both POST and GET (GET checks if already logged in)
startSecureSession();

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// If already logged in, redirect to destination
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'student';
    $target = ($role === 'admin') ? '../admin/admin.html' : '../page/student-dashboard.html';
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'redirect' => $target, 'user' => getCurrentUser()]);
        exit;
    }
    header('Location: ' . $target);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../page/login.html');
    exit;
}

// -------------------------------------------------------------
// 1. Retrieve & Sanitize Credentials
// -------------------------------------------------------------
$loginId  = trim($_POST['login_id'] ?? $_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);
$redirect = trim($_POST['redirect'] ?? '');

$errors = [];

if (empty($loginId)) {
    $errors[] = 'Please enter your academic Email ID or Student Code.';
}

if (empty($password)) {
    $errors[] = 'Please enter your account password.';
}

if (!empty($errors)) {
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }
    $errMsg = urlencode(implode(' ', $errors));
    header("Location: ../page/login.html?error={$errMsg}&login_id=" . urlencode($loginId));
    exit;
}

// -------------------------------------------------------------
// 2. Query Database using Prepared Statement
// -------------------------------------------------------------
try {
    $pdo = getDBConnection(true);

    // Look up by email OR student_code (e.g. STU-001 or alex.ce23@charusat.edu.in)
    $stmt = $pdo->prepare("SELECT * FROM students WHERE email = :email OR student_code = :code LIMIT 1");
    $stmt->execute([
        ':email' => $loginId,
        ':code'  => $loginId
    ]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $errorMsg = 'Account not found. Please verify your credentials or register for a new account.';
        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        }
        header("Location: ../page/login.html?error=" . urlencode($errorMsg) . "&login_id=" . urlencode($loginId));
        exit;
    }

    // ---------------------------------------------------------
    // 3. Verify Password using password_verify()
    // ---------------------------------------------------------
    if (!password_verify($password, $user['password'])) {
        $errorMsg = 'Incorrect password entered. Please try again.';
        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        }
        header("Location: ../page/login.html?error=" . urlencode($errorMsg) . "&login_id=" . urlencode($loginId));
        exit;
    }

    // ---------------------------------------------------------
    // 4. Authentication Succeeded - Regenerate Session ID
    // ---------------------------------------------------------
    session_regenerate_id(true);

    // Store essential user credentials into $_SESSION
    $_SESSION['user_id']       = (int)$user['student_id'];
    $_SESSION['student_code']  = $user['student_code'];
    $_SESSION['user_name']     = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['first_name']    = $user['first_name'];
    $_SESSION['last_name']     = $user['last_name'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = $user['role'] ?? 'student';
    $_SESSION['department']    = $user['department'] ?? '';
    $_SESSION['semester']      = (int)($user['semester'] ?? 1);
    $_SESSION['phone']         = $user['phone'] ?? '';
    $_SESSION['roll_number']   = $user['roll_number'] ?? '';
    $_SESSION['bio']           = $user['bio'] ?? '';
    $_SESSION['LAST_ACTIVITY'] = time();
    unset($_SESSION['timeout_expired']);

    // ---------------------------------------------------------
    // 5. Update last_login Timestamp in MySQL
    // ---------------------------------------------------------
    $updateStmt = $pdo->prepare("UPDATE students SET last_login = NOW() WHERE student_id = :id");
    $updateStmt->execute([':id' => $user['student_id']]);

    // Practical 14: Institutional Security Audit Log
    $userRole = strtolower($user['role'] ?? 'student');
    recordAuditLog(
        'AUTH_LOGIN',
        'auth',
        (int)$user['student_id'],
        "User authenticated successfully with role '{$userRole}'.",
        (int)$user['student_id'],
        $user['first_name'] . ' ' . $user['last_name'],
        $userRole
    );

    // ---------------------------------------------------------
    // 6. Handle Remember-Me Token (Optional Extension in Practical 10)
    // ---------------------------------------------------------
    if ($remember) {
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $tokenStmt = $pdo->prepare("UPDATE students SET remember_token = :token WHERE student_id = :id");
        $tokenStmt->execute([
            ':token' => $hashedToken,
            ':id'    => $user['student_id']
        ]);

        // Cookie expires in 30 days
        $cookieExpire = time() + (86400 * 30);
        $cookiePath = '/';
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

        if (PHP_VERSION_ID >= 70300) {
            setcookie(REMEMBER_COOKIE_NAME, $rawToken, [
                'expires'  => $cookieExpire,
                'path'     => $cookiePath,
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } else {
            setcookie(REMEMBER_COOKIE_NAME, $rawToken, $cookieExpire, $cookiePath, '', $secure, true);
        }
    }

    // ---------------------------------------------------------
    // 7. Role-Based Redirection (RBAC)
    // ---------------------------------------------------------
    $userRole = strtolower($user['role'] ?? 'student');
    $destination = '../page/student-dashboard.html';

    if ($userRole === 'admin') {
        $destination = '../admin/admin.html';
    } elseif (!empty($redirect) && strpos($redirect, 'login.html') === false && strpos($redirect, 'register.html') === false) {
        if (!preg_match('#^(\.\./|/|https?://)#', $redirect)) {
            $destination = '../page/' . ltrim($redirect, '/');
        } else {
            $destination = $redirect;
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'   => true,
            'message'   => 'Login successful! Welcome back, ' . htmlspecialchars($user['first_name']) . '.',
            'redirect'  => $destination,
            'user'      => getCurrentUser()
        ]);
        exit;
    }

    header('Location: ' . $destination);
    exit;

} catch (Throwable $e) {
    $errorMsg = 'Database connection error: ' . $e->getMessage();
    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }
    header("Location: ../page/login.html?error=" . urlencode($errorMsg));
    exit;
}
