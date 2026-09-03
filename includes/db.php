<?php
// Single PDO connection for the entire application.
// Every page does require_once on this file — never open a second connection.

define('DB_HOST', 'localhost');
define('DB_NAME', 'parking_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
} catch (PDOException $e) {
    // Surface a safe message; never expose the raw exception to the browser.
    http_response_code(500);
    exit('Database connection failed. Please try again later.');
}
