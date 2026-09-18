<?php
// Single PDO connection for the entire application.
// Every page does require_once on this file — never open a second connection.

define('DB_HOST', 'localhost');
define('DB_NAME', 'parking_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Timezone configuration — ensure PHP and MySQL match local campus time
date_default_timezone_set('Asia/Dhaka');

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
    $pdo->exec("SET time_zone = '+06:00'");

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
                type VARCHAR(50) NOT NULL,
                points INT NOT NULL,
                package_name VARCHAR(50) DEFAULT NULL,
                description VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");

            // Ensure type column supports custom transaction types
            $colType = $pdo->query("SHOW COLUMNS FROM point_transactions LIKE 'type'");
            if ($colType) {
                $row = $colType->fetch(PDO::FETCH_ASSOC);
                if ($row && str_starts_with(strtolower($row['Type']), 'enum')) {
                    $pdo->exec("ALTER TABLE point_transactions MODIFY COLUMN type VARCHAR(50) NOT NULL");
                }
            }

            // Check if bookings has start_time / end_time (time-block booking update)
            $colCheck3 = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'start_time'");
            if ($colCheck3 && !$colCheck3->fetch()) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN start_time TIME DEFAULT NULL AFTER points_cost");
                $pdo->exec("ALTER TABLE bookings ADD COLUMN end_time   TIME DEFAULT NULL AFTER start_time");
            }

            // Check if bookings has is_late_checkin column
            $colCheck4 = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'is_late_checkin'");
            if ($colCheck4 && !$colCheck4->fetch()) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN is_late_checkin TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
            }

            // Check if users has late_checkin_count column (3 late check-ins freeze system)
            $colCheck5 = $pdo->query("SHOW COLUMNS FROM users LIKE 'late_checkin_count'");
            if ($colCheck5 && !$colCheck5->fetch()) {
                $pdo->exec("ALTER TABLE users ADD COLUMN late_checkin_count INT NOT NULL DEFAULT 0 AFTER late_departure_count");
            }

            // Check if bookings has penalty_points_deducted column
            $colCheck6 = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'penalty_points_deducted'");
            if ($colCheck6 && !$colCheck6->fetch()) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN penalty_points_deducted INT NOT NULL DEFAULT 0 AFTER is_late_checkin");
            }

            // Create complaints table (slot-occupied reporting system)
            $pdo->exec("CREATE TABLE IF NOT EXISTS complaints (
                id INT AUTO_INCREMENT PRIMARY KEY,
                blocked_booking_id   INT NOT NULL,
                occupying_booking_id INT NOT NULL,
                complainant_id       INT NOT NULL,
                penalty_deducted     INT NOT NULL DEFAULT 5,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_blocked (blocked_booking_id),
                FOREIGN KEY (blocked_booking_id)   REFERENCES bookings(id) ON DELETE CASCADE,
                FOREIGN KEY (occupying_booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
                FOREIGN KEY (complainant_id)        REFERENCES users(id)   ON DELETE CASCADE
            )");

            // Check if complaints has penalty_deducted column if table already existed
            $colCheck7 = $pdo->query("SHOW COLUMNS FROM complaints LIKE 'penalty_deducted'");
            if ($colCheck7 && !$colCheck7->fetch()) {
                $pdo->exec("ALTER TABLE complaints ADD COLUMN penalty_deducted DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER complainant_id");
            }

            // Ensure points columns support decimals for 30s penalty increments
            try {
                $colType = $pdo->query("SHOW COLUMNS FROM users LIKE 'reward_points'");
                $cRow = $colType ? $colType->fetch(PDO::FETCH_ASSOC) : null;
                if ($cRow && !str_starts_with(strtolower($cRow['Type']), 'decimal')) {
                    $pdo->exec("ALTER TABLE users MODIFY COLUMN reward_points DECIMAL(10,2) NOT NULL DEFAULT 100.00");
                }
            } catch (Throwable $e) {}

            try {
                $colType = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'penalty_points_deducted'");
                $cRow = $colType ? $colType->fetch(PDO::FETCH_ASSOC) : null;
                if ($cRow && !str_starts_with(strtolower($cRow['Type']), 'decimal')) {
                    $pdo->exec("ALTER TABLE bookings MODIFY COLUMN penalty_points_deducted DECIMAL(10,2) NOT NULL DEFAULT 0.00");
                }
            } catch (Throwable $e) {}

            try {
                $colType = $pdo->query("SHOW COLUMNS FROM complaints LIKE 'penalty_deducted'");
                $cRow = $colType ? $colType->fetch(PDO::FETCH_ASSOC) : null;
                if ($cRow && !str_starts_with(strtolower($cRow['Type']), 'decimal')) {
                    $pdo->exec("ALTER TABLE complaints MODIFY COLUMN penalty_deducted DECIMAL(10,2) NOT NULL DEFAULT 0.00");
                }
            } catch (Throwable $e) {}

            try {
                $colType = $pdo->query("SHOW COLUMNS FROM point_transactions LIKE 'points'");
                $cRow = $colType ? $colType->fetch(PDO::FETCH_ASSOC) : null;
                if ($cRow && !str_starts_with(strtolower($cRow['Type']), 'decimal')) {
                    $pdo->exec("ALTER TABLE point_transactions MODIFY COLUMN points DECIMAL(10,2) NOT NULL");
                }
            } catch (Throwable $e) {}
        } catch (Throwable $ignore) {
            // Silently ignore if schema setup is already handled or tables not created yet
        }
    }
} catch (PDOException $e) {
    // Surface a safe message; never expose the raw exception to the browser.
    http_response_code(500);
    exit('Database connection failed. Please try again later.');
}

