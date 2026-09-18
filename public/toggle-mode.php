<?php
// toggle-mode.php — POST-only handler that flips the app_mode session key
// between 'real' and 'test' and redirects back to the referrer.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

toggle_mode();   // flip 'real' <-> 'test' and persist to $_SESSION

// Redirect back to where the user clicked the toggle.
$ref  = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($ref && parse_url($ref, PHP_URL_HOST) === $host) {
    header('Location: ' . $ref);
} else {
    header('Location: ' . BASE_URL . '/public/dashboard.php');
}
exit;
