-- CampusPark — Database Schema & Initial Seed Data
-- Comprehensive schema: users, parking_slots, bookings, complaints, point_transactions

CREATE DATABASE IF NOT EXISTS parking_system;
USE parking_system;

-- 1. Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    late_departure_count INT NOT NULL DEFAULT 0,
    late_checkin_count INT NOT NULL DEFAULT 0,
    booking_locked_until DATETIME DEFAULT NULL, -- set when late_departure_count hits a multiple of 3 (120s freeze)
    reward_points DECIMAL(10,2) NOT NULL DEFAULT 100.00,
    package_tier VARCHAR(50) DEFAULT 'Starter',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Parking slots
CREATE TABLE IF NOT EXISTS parking_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_code VARCHAR(10) NOT NULL UNIQUE,   -- e.g. 'A1', 'B12'
    zone VARCHAR(50) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Bookings
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    slot_id INT NOT NULL,
    booking_date DATE NOT NULL,
    duration_hours INT NOT NULL DEFAULT 1,
    points_cost INT NOT NULL DEFAULT 10,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    check_in_time DATETIME DEFAULT NULL,
    check_out_time DATETIME DEFAULT NULL,
    status ENUM('booked', 'checked_in', 'completed', 'cancelled') NOT NULL DEFAULT 'booked',
    is_late_checkin TINYINT(1) NOT NULL DEFAULT 0,
    penalty_points_deducted DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES parking_slots(id) ON DELETE CASCADE
);

-- 4. Complaints table (for slot-occupying overstayer reports)
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blocked_booking_id   INT NOT NULL,
    occupying_booking_id INT NOT NULL,
    complainant_id       INT NOT NULL,
    penalty_deducted     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_blocked (blocked_booking_id),
    FOREIGN KEY (blocked_booking_id)   REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (occupying_booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (complainant_id)        REFERENCES users(id)   ON DELETE CASCADE
);

-- 5. Point transactions and demo payment log
CREATE TABLE IF NOT EXISTS point_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL, -- 'signup_bonus', 'booking_deduction', 'package_purchase', 'late_fine', 'report_reward'
    points DECIMAL(10,2) NOT NULL,
    package_name VARCHAR(50) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. Seed Parking Slots across campus zones
INSERT INTO parking_slots (slot_code, zone) VALUES
('A1', 'North Campus'),
('A2', 'North Campus'),
('A3', 'North Campus'),
('A4', 'North Campus'),
('B1', 'South Campus'),
('B2', 'South Campus'),
('B3', 'South Campus'),
('B4', 'South Campus'),
('C1', 'Central Campus'),
('C2', 'Central Campus')
ON DUPLICATE KEY UPDATE zone = VALUES(zone);

-- 7. Seed Demo Accounts for Instant Local Testing (Password for both: password123)
INSERT INTO users (id, full_name, email, password_hash, role, reward_points, package_tier) VALUES
(1, 'Demo Student', 'student@campuspark.edu', '$2y$10$uQKdAa7PhWnSeMgF1a/ti.SOzN/E9DMiMrS2d5XbXFKifPLnl8kfW', 'user', 100.00, 'Starter'),
(2, 'Test Driver 2', 'driver2@campuspark.edu', '$2y$10$uQKdAa7PhWnSeMgF1a/ti.SOzN/E9DMiMrS2d5XbXFKifPLnl8kfW', 'user', 100.00, 'Starter')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

INSERT INTO point_transactions (user_id, type, points, description) VALUES
(1, 'signup_bonus', 100.00, 'Welcome bonus reward points'),
(2, 'signup_bonus', 100.00, 'Welcome bonus reward points')
ON DUPLICATE KEY UPDATE description = VALUES(description);
