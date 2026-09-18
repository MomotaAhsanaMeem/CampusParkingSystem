# CampusPark — Campus Parking Reservation System

CampusPark is a modern, responsive web-based campus parking management and reservation platform designed for university students, faculty, and staff. It allows drivers to search and reserve parking slots ahead of arrival, manage active parking sessions with check-in/check-out functionality, extend reservations to avoid overstaying, report unauthorized bay occupants, top up reward points, and enforces compliance through a structured penalty policy.

---

## Key Features

- **Public Landing Page** — Interactive overview, live campus zone preview, and visual feature highlights.
- **Slot Reservation Portal** — Search and reserve available bays by date, time window, and campus zone (North, South, and Central Campus) at 10 points/hour.
- **Check-In Verification** — Check in upon arrival with start-time enforcement and 15-minute grace period tracking.
- **Automated Penalty Enforcement (Late Checkouts)**:
  - Overstaying past your scheduled checkout time incurs an overtime fine (20 points/hour calculated in 30-second intervals).
  - **Every 3 late checkouts trigger an automated temporary 120-second booking restriction** (`booking_locked_until = now + 120s`), complete with a live countdown timer on the dashboard.
- **Slot Extension System** — Drivers with an active session can extend their stay by 1 hour (10 points) directly from the dashboard if no immediate conflicting booking exists, preventing overstay penalties.
- **Occupant Conflict & Complaint System** — If an arriving driver finds their reserved slot blocked by an overstaying vehicle, they can file a 1-click report to claim a **reward in points** deducted directly from the offending occupant.
- **Reward Points & Demo Payment Gateway** — 100 bonus welcome points upon signup, with instant point pack top-ups (Starter, Standard, Pro) and complete transaction audit logs.
- **Non-Refundable Cancellation** — Cancel unneeded reservations with instant slot release and transparent non-refundable policy enforcement.
- **Dark / Light Mode Support** — Built-in theme switcher with client-side persistence and anti-FOUC initialization.

---

## Tech Stack

- **Backend:** PHP 8.0+ (PDO with prepared statements for database operations)
- **Database:** MySQL 5.7+ / MariaDB 10.4+
- **Frontend:** HTML5, Vanilla CSS3 (Kinetic Campus UI design system), Vanilla JavaScript, Leaflet.js (maps)
- **Local Environment:** XAMPP / WAMP / LAMP (Apache + MySQL)

---

## Prerequisites

Before testing locally, ensure you have:
- [XAMPP](https://www.apachefriends.org/) (or any PHP 8.0+ & MySQL/MariaDB server stack)
- Apache and MySQL running in the XAMPP Control Panel

---

## Quick Setup Guide

### 1. Clone or Move the Repository
Clone or copy the project folder into your XAMPP `htdocs` directory:

```bash
# Windows default path:
C:\xampp\htdocs\parking-system
```

### 2. Start Apache and MySQL
Open the **XAMPP Control Panel** and click **Start** for:
- **Apache**
- **MySQL**

### 3. Import the Database Schema
1. Open your browser and navigate to `http://localhost/phpmyadmin`.
2. Click **Import** in the top navigation bar (or create a database named `parking_system` first).
3. Click **Choose File**, select `schema.sql` from the root directory of this project, and click **Go** (or **Import**).

> **Note:** `schema.sql` automatically creates the database, all required tables, parking slots across campus zones, and **pre-seeded demo accounts** ready for testing.

### 4. Database Connection Settings
Verify `includes/db.php` matches your local MySQL configuration (default XAMPP settings):

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'parking_system');
define('DB_USER', 'root');
define('DB_PASS', '');
```

*Note: The application defaults to the `Asia/Dhaka` (`+06:00`) timezone. You can adjust this in `includes/db.php` if desired.*

### 5. Open the Application
Visit either of the following URLs in your web browser:
- Dynamic PHP Entry: `http://localhost/parking-system/public/index.php`
- Static Landing Page: `http://localhost/parking-system/landing.html`

---

## Pre-Seeded Test Credentials

You can log in immediately with either of the pre-seeded accounts or register a brand-new account via the signup page (which automatically awards 100 points):

| Account | Email | Password | Role | Starting Points |
| :--- | :--- | :--- | :--- | :--- |
| **Student Account 1** | `student@campuspark.edu` | `password123` | User | 100.00 pts |
| **Driver Account 2** | `driver2@campuspark.edu` | `password123` | User | 100.00 pts |

---

## Step-by-Step Feature Testing Guide

### 1. Booking a Parking Slot
1. Log in with `student@campuspark.edu` (or create a new user).
2. Click **Book a Slot** in the navigation bar.
3. Select today's date (or a future date), choose a start time and end time, and click a parking slot in any zone (e.g., North Campus `A1`).
4. Review the estimated points cost (10 points per hour).
5. Click **Confirm Booking**. Your points are deducted and the booking appears on your dashboard.

### 2. Testing Check-In
1. On your **Dashboard**, find the booking under **Active Parking Sessions**.
2. **On-Time Check-In:** If the scheduled start time has arrived and is within 15 minutes, click **Check In Now**. The session turns active with live countdown tracking.
3. **Late Check-In:** If check-in occurs >15 minutes after start time, the system records it as a late check-in on the booking badge without freezing the account.

### 3. Testing Overstay & 24-Hour Late Departure Freeze
1. While a booking is in `checked_in` status, wait until the scheduled end time passes (or book a short test slot that ends in 1–2 minutes).
2. The dashboard will show the live overstay timer in red.
3. Click **Check Out**.
4. The system calculates an overtime penalty (20 points/hour in 30s increments), deducts points from the account, and increments the **Late Checkouts** counter immediately.
5. **Triggering the 120-Second Freeze:** When a user records **3 late checkouts**, the system automatically freezes booking privileges for **120 seconds** (`booking_locked_until = now + 120s`). A warning banner with a live countdown timer appears on the dashboard and booking page. Once the timer reaches 0, the page refreshes and privileges automatically unlock.

### 4. Testing Slot Extension
1. While in an active `checked_in` session, if the bay is not reserved by someone else for the next hour, an **"Extend Slot (+1 hr)"** banner appears on the dashboard.
2. Click **Extend 1 Hour (10 pts)** to add an hour to your reservation and avoid overstay penalties.

### 5. Testing Overstaying Occupant Complaints (Multi-User Test)
1. **User 1 (`student@campuspark.edu`)**: Checks into slot `A1` and allows the reservation to overstay past scheduled end time.
2. **User 2 (`driver2@campuspark.edu`)**: Books slot `A1` for a time slot starting after User 1's scheduled end time.
3. When User 2 attempts to check in, the system detects that User 1 is overstaying in slot `A1`.
4. User 2 clicks **Report Occupying Vehicle**.
5. User 2 receives a **reward in points** credited to their account, which is deducted directly from User 1's balance.

### 6. Points Recharging & Transaction History
1. Click the **Points Badge** (`... pts`) in the navigation bar or visit `http://localhost/parking-system/public/payment.php`.
2. Choose a package (**Starter 100 pts**, **Standard 300 pts**, or **Pro 600 pts**).
3. Test the demo payment simulation (Instant, Card, or Mobile Banking) and submit.
4. Points are credited immediately and an audit entry is logged in the **Transaction History** table.

### 7. Booking Cancellation
1. From the dashboard, locate any upcoming booking in `booked` status.
2. Click **Cancel Booking** and confirm.
3. The slot is freed up immediately for other drivers (as per system policy, points are non-refundable).

### 8. Dark / Light Theme Switching
- Click the moon/sun icon in the navbar to toggle between Light Mode and Dark Mode. The selection is saved in `localStorage`.

---

## Project Structure

```
parking-system/
├── landing.html              # Public static landing page
├── schema.sql                # Database schema with seeded slots & demo accounts
├── campuspark_prd.md         # Product Requirements Document (PRD)
├── CONVENTIONS.md            # Codebase conventions & architectural guidelines
├── DESIGN.md                 # Design tokens and visual styling specs
├── includes/                 # Shared backend modules
│   ├── auth.php              # Session management, role guards & lock checks
│   ├── db.php                # PDO connection & schema checks
│   ├── config.php            # Environment configuration
│   ├── checkin.php           # Check-in, extension, and complaint processor
│   ├── checkout.php          # Check-out, overstay fine & 24h freeze handler
│   ├── cancel-booking.php    # Non-refundable booking cancellation
│   ├── header.php            # Universal navbar & asset loader
│   └── footer.php            # Shared site footer component
├── public/                   # Application endpoints
│   ├── index.php             # Dynamic landing page & quick reservation view
│   ├── book-slot.php         # Spot reservation flow with slot conflict checks
│   ├── dashboard.php         # User dashboard, active sessions & timers
│   ├── payment.php           # Points top-up packages & transaction log
│   ├── login.php             # User login form
│   ├── signup.php            # User registration (100 welcome bonus pts)
│   ├── logout.php            # Session termination handler
│   ├── terms-of-service.php  # Campus parking terms of service
│   ├── privacy-policy.php    # Privacy policy & data usage notice
│   └── toggle-mode.php       # Theme persistence handler
└── assets/                   # Static assets (CSS, JS, images)
    ├── css/
    │   └── style.css         # Complete Kinetic Campus stylesheet
    ├── js/
    │   └── main.js           # Client-side validation & UI interactions
    └── images/               # Campus visuals and UI graphics
```
