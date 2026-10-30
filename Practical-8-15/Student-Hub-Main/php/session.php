<?php
/**
 * Practical 10: Secure Session Management & Role-Based Access Control (RBAC)
 * File: php/session.php
 * Description: Centralized session management supporting:
 *              - Secure session initialization with HTTP-only and SameSite flags
 *              - Session fixation prevention via session_regenerate_id()
 *              - Inactivity timeout enforcement (30 minutes)
 *              - Role-Based Access Control (Student vs Admin)
 *              - Automatic Remember-Me token validation
 */

if (!defined('SESSION_TIMEOUT_SECONDS')) {
    define('SESSION_TIMEOUT_SECONDS', 1800); // 30 minutes idle timeout
}

if (!defined('REMEMBER_COOKIE_NAME')) {
    define('REMEMBER_COOKIE_NAME', 'studenthub_remember');
}

/**
 * Initializes PHP session with secure configuration flags.
 *
 * @return void
 */
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $lifetime = 0; // Session cookie dies when browser closes
        $path = '/';
        $domain = '';
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $httponly = true; // Prevent JavaScript access to session cookie

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => $httponly,
                'samesite' => 'Lax'
            ]);
        } else {
            session_set_cookie_params($lifetime, $path . '; samesite=Lax', $domain, $secure, $httponly);
        }

        session_name('STUDENTHUB_SESSID');
        session_start();
    }

    // Check for inactivity timeout
    checkSessionTimeout();

    // If not authenticated via active session, check for Remember-Me token
    if (!isLoggedIn() && isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
        attemptRememberMeLogin();
    }
}

/**
 * Enforces session inactivity timeout.
 * Destroys session if last activity was more than SESSION_TIMEOUT_SECONDS ago.
 *
 * @return void
 */
function checkSessionTimeout(): void {
    if (isset($_SESSION['user_id']) && isset($_SESSION['LAST_ACTIVITY'])) {
        $inactiveSeconds = time() - (int)$_SESSION['LAST_ACTIVITY'];
        if ($inactiveSeconds > SESSION_TIMEOUT_SECONDS) {
            // Session has expired
            destroyUserSession();
            $_SESSION['timeout_expired'] = true;
            return;
        }
    }
    // Update last activity timestamp on current request
    if (isset($_SESSION['user_id'])) {
        $_SESSION['LAST_ACTIVITY'] = time();
    }
}

/**
 * Checks if a user is currently logged into an active session.
 *
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) && empty($_SESSION['timeout_expired']);
}

/**
 * Retrieves the currently logged-in user session array.
 *
 * @return array|null
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'user_id'      => $_SESSION['user_id'],
        'student_code' => $_SESSION['student_code'] ?? '',
        'name'         => $_SESSION['user_name'] ?? 'Student',
        'first_name'   => $_SESSION['first_name'] ?? '',
        'last_name'    => $_SESSION['last_name'] ?? '',
        'email'        => $_SESSION['email'] ?? '',
        'role'         => $_SESSION['role'] ?? 'student',
        'department'   => $_SESSION['department'] ?? '',
        'semester'     => $_SESSION['semester'] ?? 1,
        'phone'        => $_SESSION['phone'] ?? '',
        'roll_number'  => $_SESSION['roll_number'] ?? '',
        'bio'          => $_SESSION['bio'] ?? ''
    ];
}

/**
 * Checks if current user has a specific role ('student' or 'admin').
 *
 * @param string $requiredRole
 * @return bool
 */
function hasRole(string $requiredRole): bool {
    if (!isLoggedIn()) return false;
    $role = $_SESSION['role'] ?? 'student';
    return strtolower($role) === strtolower($requiredRole);
}

/**
 * Requires the user to be authenticated.
 * If not authenticated, redirects to login page or terminates with JSON 401.
 *
 * @param string|null $requiredRole Optional required role ('student' or 'admin')
 * @param bool $jsonResponse Whether to respond with JSON instead of redirecting
 * @return void
 */
function requireAuth(?string $requiredRole = null, bool $jsonResponse = false): void {
    startSecureSession();

    if (!isLoggedIn()) {
        if ($jsonResponse) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'authenticated' => false,
                'message' => 'Authentication required. Please log in.',
                'redirect' => '../page/login.html?error=auth_required'
            ]);
            exit;
        }

        $redirectUrl = '../page/login.html?error=auth_required';
        if (isset($_SERVER['REQUEST_URI'])) {
            $redirectUrl .= '&redirect=' . urlencode($_SERVER['REQUEST_URI']);
        }
        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($requiredRole !== null && !hasRole($requiredRole)) {
        if ($jsonResponse) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'authenticated' => true,
                'message' => "Access denied. Required role: {$requiredRole}.",
                'redirect' => '../page/student-dashboard.html?error=forbidden'
            ]);
            exit;
        }

        header('Location: ../page/student-dashboard.html?error=forbidden');
        exit;
    }
}

/**
 * Restores user session from valid Remember-Me cookie token.
 *
 * @return bool
 */
function attemptRememberMeLogin(): bool {
    $cookieToken = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';
    if (empty($cookieToken)) return false;

    try {
        require_once __DIR__ . '/db.php';
        $pdo = getDBConnection(true);

        $hashedToken = hash('sha256', $cookieToken);
        $stmt = $pdo->prepare("SELECT * FROM students WHERE remember_token = :token LIMIT 1");
        $stmt->execute([':token' => $hashedToken]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user_id']      = (int)$user['student_id'];
            $_SESSION['student_code'] = $user['student_code'];
            $_SESSION['user_name']    = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['first_name']   = $user['first_name'];
            $_SESSION['last_name']    = $user['last_name'];
            $_SESSION['email']        = $user['email'];
            $_SESSION['role']         = $user['role'] ?? 'student';
            $_SESSION['department']   = $user['department'] ?? '';
            $_SESSION['semester']     = $user['semester'] ?? 1;
            $_SESSION['phone']        = $user['phone'] ?? '';
            $_SESSION['roll_number']  = $user['roll_number'] ?? '';
            $_SESSION['bio']          = $user['bio'] ?? '';
            $_SESSION['LAST_ACTIVITY']= time();

            // Update last login
            $update = $pdo->prepare("UPDATE students SET last_login = NOW() WHERE student_id = :id");
            $update->execute([':id' => $user['student_id']]);

            return true;
        }
    } catch (Throwable $e) {
        // Fall through
    }

    return false;
}

/**
 * Completely terminates and clears the user's session and remember-me tokens.
 *
 * @return void
 */
function destroyUserSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Clear remember_token in DB if user logged in
    if (isset($_SESSION['user_id'])) {
        try {
            require_once __DIR__ . '/db.php';
            $pdo = getDBConnection(true);
            $stmt = $pdo->prepare("UPDATE students SET remember_token = NULL WHERE student_id = :id");
            $stmt->execute([':id' => $_SESSION['user_id']]);
        } catch (Throwable $e) {
            // Ignore DB error during logout
        }
    }

    // Unset all session variables
    $_SESSION = [];

    // Clear session cookie in browser
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Clear Remember-Me cookie
    if (isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
        setcookie(REMEMBER_COOKIE_NAME, '', time() - 3600, '/', '', false, true);
        unset($_COOKIE[REMEMBER_COOKIE_NAME]);
    }

    // Finally destroy the session
    @session_destroy();
}
