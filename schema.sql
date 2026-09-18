-- Parking Reservation System — Update 1 schema
-- Scope: users (auth) + slots + bookings only.
-- Admin table, notifications, requests/transactions come in later updates.

CREATE DATABASE IF NOT EXISTS parking_system;
USE parking_system;

-- 1. Users (also doubles as the "admin" table via a role flag —
--    simpler than a separate admin table for a group project)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    late_departure_count INT NOT NULL DEFAULT 0,
    late_checkin_count INT NOT NULL DEFAULT 0,
    booking_locked_until DATE DEFAULT NULL, -- set when late_checkin_count hits a multiple of 3 (24h freeze)
    reward_points DECIMAL(10,2) NOT NULL DEFAULT 100.00,
    package_tier VARCHAR(50) DEFAULT 'Starter',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Slot management
CREATE TABLE parking_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_code VARCHAR(10) NOT NULL UNIQUE,   -- e.g. 'A1', 'B12'
    zone VARCHAR(50) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Bookings (the "book slot" feature — Update 1's main feature)
CREATE TABLE bookings (
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
CREATE TABLE complaints (
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

-- 4. Point transactions and demo payment log
CREATE TABLE point_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('signup_bonus', 'booking_deduction', 'package_purchase') NOT NULL,
    points DECIMAL(10,2) NOT NULL,
    package_name VARCHAR(50) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed a few test slots so Update 1 has something to book
INSERT INTO parking_slots (slot_code, zone) VALUES
('A1', 'North Campus'),
('A2', 'North Campus'),
('B1', 'South Campus'),
('B2', 'South Campus');
