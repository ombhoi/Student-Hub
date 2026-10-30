<?php
/**
 * StudentHub: Contact Inquiry Form Processing
 * File: php/process_contact.php
 * Description: Processes user inquiries from contact.html, validates server-side,
 *              and safely stores submissions into data/contact.json and data/contact.csv
 *              using atomic file operations (flock). Keeps contact inquiries separate.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

$contactJsonPath = __DIR__ . '/../data/contact.json';
$contactCsvPath  = __DIR__ . '/../data/contact.csv';

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
        'message' => 'Please correct the errors in the contact form.',
        'errors'  => $errors,
        'inquiry' => null
    ]);
}

// 3. Read existing contact inquiries from data/contact.json
$contactList = [];
if (file_exists($contactJsonPath)) {
    $rawContent = file_get_contents($contactJsonPath);
    if ($rawContent !== false && !empty(trim($rawContent))) {
        $decoded = json_decode($rawContent, true);
        if (is_array($decoded)) {
            $contactList = $decoded;
        }
    }
}

// 4. Generate Unique Inquiry ID & Record
$nextId    = count($contactList) + 1;
$inquiryId = 'INQ-' . (1000 + $nextId);
$now       = date('Y-m-d H:i:s');

$newInquiry = [
    'id'           => $nextId,
    'inquiry_id'   => $inquiryId,
    'name'         => html_entity_decode($name, ENT_QUOTES, 'UTF-8'),
    'email'        => strtolower($email),
    'subject'      => html_entity_decode($subject, ENT_QUOTES, 'UTF-8'),
    'message'      => html_entity_decode($message, ENT_QUOTES, 'UTF-8'),
    'submitted_at' => $now,
    'status'       => 'New'
];

$contactList[] = $newInquiry;

// 5. Atomic write to data/contact.json
$fpJson = fopen($contactJsonPath, 'c+');
if ($fpJson) {
    if (flock($fpJson, LOCK_EX)) {
        ftruncate($fpJson, 0);
        rewind($fpJson);
        fwrite($fpJson, json_encode($contactList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($fpJson);
        flock($fpJson, LOCK_UN);
    }
    fclose($fpJson);
}

// 6. Atomic write to data/contact.csv
$csvExists = file_exists($contactCsvPath) && filesize($contactCsvPath) > 0;
$fpCsv = fopen($contactCsvPath, 'a');
if ($fpCsv) {
    if (flock($fpCsv, LOCK_EX)) {
        if (!$csvExists) {
            fputcsv($fpCsv, ['id', 'inquiry_id', 'name', 'email', 'subject', 'message', 'submitted_at', 'status']);
        }
        fputcsv($fpCsv, [
            $newInquiry['id'],
            $newInquiry['inquiry_id'],
            $newInquiry['name'],
            $newInquiry['email'],
            $newInquiry['subject'],
            str_replace(["\r", "\n"], ' ', $newInquiry['message']),
            $newInquiry['submitted_at'],
            $newInquiry['status']
        ]);
        fflush($fpCsv);
        flock($fpCsv, LOCK_UN);
    }
    fclose($fpCsv);
}

sendResponse([
    'success' => true,
    'message' => 'Thank you, ' . html_entity_decode($name, ENT_QUOTES, 'UTF-8') . '. Your message has been received.',
    'inquiry' => $newInquiry
]);

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
        <title>StudentHub - Inquiry Submission Result</title>
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
                color: #852d1b;
                background: #faece5;
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
                background: #852d1b;
                color: #ffffff;
                border-radius: 12px;
                text-decoration: none;
                font-weight: 600;
                margin: 8px 4px;
                transition: background 0.2s;
            }
            .btn-action:hover {
                background: #6a2415;
            }
        </style>
    </head>
    <body style="background: #faf8f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Plus Jakarta Sans', sans-serif;">
        <main class="container" style="padding: 20px; width: 100%;">
            <div class="result-card">
                <?php if ($success): ?>
                    <span class="material-symbols-outlined" style="font-size: 56px; color: #15803d;">check_circle</span>
                    <h1 style="font-size: 24px; color: #1f1d1b; margin-top: 10px;">
                        Thank you, <?= htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8') ?>. Your message has been received.
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
                            <strong>Submitted:</strong> <?= htmlspecialchars($data['submitted_at']) ?> &bull; <span style="color: #15803d; font-weight: 600;">Status: New</span>
                        </div>
                    </div>
                <?php else: ?>
                    <span class="material-symbols-outlined" style="font-size: 56px; color: #b91c1c;">error</span>
                    <h1 style="font-size: 26px; color: #1f1d1b; margin-top: 10px;">Submission Error</h1>
                    <p style="color: #645e58; margin-top: 6px; font-size: 14px;"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($errors)): ?>
                        <ul style="text-align: left; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 14px 24px; border-radius: 12px; margin: 16px 0; font-size: 13px;">
                            <?php foreach ($errors as $field => $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
                <div style="margin-top: 24px;">
                    <a href="../page/contact.html" class="btn-action">Back to Contact Us</a>
                    <a href="../index.html" class="btn-action" style="background: transparent; color: #852d1b; border: 1px solid #852d1b;">Go to Home</a>
                </div>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}
