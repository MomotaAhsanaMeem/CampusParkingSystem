<?php
// admin/logout.php — Clean logout endpoint for administrators
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

logout_user();
header('Location: ' . BASE_URL . '/public/login.php?logged_out=admin');
exit;
