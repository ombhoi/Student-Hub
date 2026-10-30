<?php
/**
 * Practical 8: Contact Inquiry Processing via MySQL Database (PDO Prepared Statements)
 * File: php/process_contact_db.php
 * Description: Validates user inquiry input server-side and stores records
 *              into MySQL `contact_inquiries` table using PDO prepared statements.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/db.php';

// Helper function to check if request expects JSON / AJAX
function isJsonRequest() {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return (strpos($accept, 'application/json') !== false) ||
           (strtolower($requestedWith) === 'xmlhttprequest') ||
           (isset($_POST['ajax']) && $_POST['ajax'] === '1');
}

// Enforce POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../page/contact.html');
    exit;
}

// 1. Sanitize Incoming POST Parameters
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

$errors = [];

// 2. Validate Inputs
if (empty($name)) {
    $errors['name'] = 'Your name is required.';
} elseif (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
    $errors['name'] = 'Name must be between 2 and 60 characters.';
}

if (empty($email)) {
    $errors['email'] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

if (empty($subject)) {
    $errors['subject'] = 'Subject is required.';
} elseif (mb_strlen($subject) < 3 || mb_strlen($subject) > 120) {
    $errors['subject'] = 'Subject must be between 3 and 120 characters.';
}

if (empty($message)) {
    $errors['message'] = 'Message content is required.';
} elseif (mb_strlen($message) < 5) {
    $errors['message'] = 'Message must be at least 5 characters long.';
}

if (!empty($errors)) {
    sendResponse([
        'success' => false,
        'message' => 'Please correct the validation errors in the contact form.',
        'errors'  => $errors,
        'inquiry' => null
    ]);
}

try {
    $pdo = getDBConnection(true);

    // Ensure contact_inquiries table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `contact_inquiries` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `inquiry_id` VARCHAR(50) NOT NULL UNIQUE,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `subject` VARCHAR(150) NOT NULL,
            `message` TEXT NOT NULL,
            `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `status` ENUM('New', 'In Review', 'Resolved') DEFAULT 'New'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $inquiryId = 'INQ-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        INSERT INTO `contact_inquiries` (`inquiry_id`, `name`, `email`, `subject`, `message`, `submitted_at`, `status`)
        VALUES (:inquiry_id, :name, :email, :subject, :message, :submitted_at, 'New')
    ");

    $stmt->execute([
        ':inquiry_id'   => $inquiryId,
        ':name'         => $name,
        ':email'        => strtolower($email),
        ':subject'      => $subject,
        ':message'      => $message,
        ':submitted_at' => $now
    ]);

    $lastInsertId = $pdo->lastInsertId();

    $newInquiry = [
        'id'           => $lastInsertId,
        'inquiry_id'   => $inquiryId,
        'name'         => html_entity_decode($name, ENT_QUOTES, 'UTF-8'),
        'email'        => strtolower($email),
        'subject'      => html_entity_decode($subject, ENT_QUOTES, 'UTF-8'),
        'message'      => html_entity_decode($message, ENT_QUOTES, 'UTF-8'),
        'submitted_at' => $now,
        'status'       => 'New',
        'source'       => 'MySQL Database (Practical 8)'
    ];

    sendResponse([
        'success' => true,
        'message' => 'Thank you, ' . html_entity_decode($name, ENT_QUOTES, 'UTF-8') . '. Your message has been safely saved to MySQL database.',
        'inquiry' => $newInquiry
    ]);

} catch (PDOException $e) {
    sendResponse([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage(),
        'errors'  => ['database' => $e->getMessage()],
        'inquiry' => null
    ]);
}

/**
 * Handle JSON response or HTML confirmation view
 */
function sendResponse($resp) {
    if (isJsonRequest()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resp);
        exit;
    }

    $success = $resp['success'];
    $msg     = $resp['message'];
    $data    = $resp['inquiry'] ?? null;
    $errors  = $resp['errors'] ?? [];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>StudentHub - Inquiry Result (MySQL Practical 8)</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
        <link rel="stylesheet" href="../css/contact.css">
        <style>
            .result-card {
                max-width: 600px;
                margin: 60px auto;
                background: #ffffff;
                border: 1px solid #ede4dc;
                border-radius: 24px;
                padding: 40px;
                text-align: center;
                box-shadow: 0 16px 40px -8px rgba(120, 80, 50, 0.1);
            }
            .badge-code {
                font-family: monospace;
                font-size: 20px;
                font-weight: 700;
                color: #2563eb;
                background: #eff6ff;
                padding: 8px 18px;
                border-radius: 12px;
                display: inline-block;
                margin: 14px 0;
            }
            .btn-action {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                background: #2563eb;
                color: #ffffff;
                border-radius: 12px;
                text-decoration: none;
                font-weight: 600;
                margin: 8px 4px;
                transition: background 0.2s;
            }
            .btn-action:hover {
                background: #1d4ed8;
            }
        </style>
    </head>
    <body style="background: #faf8f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Plus Jakarta Sans', sans-serif;">
        <main class="container" style="padding: 20px; width: 100%;">
            <div class="result-card">
                <?php if ($success): ?>
                    <span class="material-symbols-outlined" style="font-size: 56px; color: #15803d;">task_alt</span>
                    <h1 style="font-size: 24px; color: #1f1d1b; margin-top: 10px;">
                        Practical 8: Contact Saved in MySQL Database!
                    </h1>
                    <div class="badge-code"><?= htmlspecialchars($data['inquiry_id']) ?></div>
                    <div style="text-align: left; background: #fdfbf9; border: 1px solid #ede4dc; padding: 18px; border-radius: 14px; margin: 20px 0; font-size: 13.5px; line-height: 1.6;">
                        <div><strong>Name:</strong> <?= htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div><strong>Email:</strong> <?= htmlspecialchars($data['email'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div><strong>Subject:</strong> <?= htmlspecialchars($data['subject'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div style="margin-top: 6px; padding-top: 6px; border-top: 1px dashed #e2d9d0;">
                            <strong>Message:</strong><br>
                            <span style="color: #4b4540;"><?= nl2br(htmlspecialchars($data['message'], ENT_QUOTES, 'UTF-8')) ?></span>
                        </div>
                        <div style="margin-top: 8px; font-size: 12px; color: #8c837b;">
                            <strong>Stored in Table:</strong> <code>contact_inquiries</code> &bull; <span style="color: #2563eb; font-weight: 600;">MySQL PDO</span>
                        </div>
                    </div>
                <?php else: ?>
                    <span class="material-symbols-outlined" style="font-size: 56px; color: #b91c1c;">error</span>
                    <h1 style="font-size: 26px; color: #1f1d1b; margin-top: 10px;">Submission Error</h1>
                    <p style="color: #645e58; margin-top: 6px; font-size: 14px;"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <div style="margin-top: 24px;">
                    <a href="../page/contact.html" class="btn-action">Back to Contact Us</a>
                    <a href="test_connection.php" class="btn-action" style="background: transparent; color: #2563eb; border: 1px solid #2563eb;">MySQL Diagnostics</a>
                    <a href="../index.html" class="btn-action" style="background: transparent; color: #852d1b; border: 1px solid #852d1b;">Home</a>
                </div>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}
