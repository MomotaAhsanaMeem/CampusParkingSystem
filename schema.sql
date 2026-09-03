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
    booking_locked_until DATE DEFAULT NULL, -- the "unique twist": set when late_departure_count hits 3
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
    check_in_time DATETIME DEFAULT NULL,
    check_out_time DATETIME DEFAULT NULL,
    status ENUM('booked', 'checked_in', 'completed', 'cancelled') NOT NULL DEFAULT 'booked',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES parking_slots(id) ON DELETE CASCADE
);

-- Seed a few test slots so Update 1 has something to book
INSERT INTO parking_slots (slot_code, zone) VALUES
('A1', 'North Campus'),
('A2', 'North Campus'),
('B1', 'South Campus'),
('B2', 'South Campus');
