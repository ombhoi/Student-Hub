<?php
// Forward to Practical 8 database registration endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/process_registration_db.php';
} else {
    header('Location: ../page/register.html?mode=db');
    exit;
}
