<?php
// Forward to registration page or processing endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/process_registration_file.php';
} else {
    header('Location: ../page/register.html');
    exit;
}
