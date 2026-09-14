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

    // Auto-migration check: ensure reward points & payment schema columns exist
    static $migrated = false;
    if (!$migrated) {
        $migrated = true;
        try {
            // Check if users table has reward_points
            $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'reward_points'");
            if ($colCheck && !$colCheck->fetch()) {
                $pdo->exec("ALTER TABLE users ADD COLUMN reward_points INT NOT NULL DEFAULT 100 AFTER booking_locked_until");
                $pdo->exec("ALTER TABLE users ADD COLUMN package_tier VARCHAR(50) DEFAULT 'Starter' AFTER reward_points");
            }

            // Check if bookings table has duration_hours
            $colCheck2 = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'duration_hours'");
            if ($colCheck2 && !$colCheck2->fetch()) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN duration_hours INT NOT NULL DEFAULT 1 AFTER booking_date");
                $pdo->exec("ALTER TABLE bookings ADD COLUMN points_cost INT NOT NULL DEFAULT 10 AFTER duration_hours");
            }

            // Check if point_transactions table exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS point_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type ENUM('signup_bonus', 'booking_deduction', 'package_purchase') NOT NULL,
                points INT NOT NULL,
                package_name VARCHAR(50) DEFAULT NULL,
                description VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");

            // Check if bookings has start_time / end_time (time-block booking update)
            $colCheck3 = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'start_time'");
            if ($colCheck3 && !$colCheck3->fetch()) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN start_time TIME DEFAULT NULL AFTER points_cost");
                $pdo->exec("ALTER TABLE bookings ADD COLUMN end_time   TIME DEFAULT NULL AFTER start_time");
            }

            // Create complaints table (slot-occupied reporting system)
            $pdo->exec("CREATE TABLE IF NOT EXISTS complaints (
                id INT AUTO_INCREMENT PRIMARY KEY,
                blocked_booking_id   INT NOT NULL,
                occupying_booking_id INT NOT NULL,
                complainant_id       INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_blocked (blocked_booking_id),
                FOREIGN KEY (blocked_booking_id)   REFERENCES bookings(id) ON DELETE CASCADE,
                FOREIGN KEY (occupying_booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
                FOREIGN KEY (complainant_id)        REFERENCES users(id)   ON DELETE CASCADE
            )");
        } catch (Throwable $ignore) {
            // Silently ignore if schema setup is already handled or tables not created yet
        }
    }
} catch (PDOException $e) {
    // Surface a safe message; never expose the raw exception to the browser.
    http_response_code(500);
    exit('Database connection failed. Please try again later.');
}

