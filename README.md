# CampusPark — Campus Parking Reservation System

CampusPark is a web-based campus parking management and reservation platform designed for university students, faculty, staff, and facility administrators. It allows drivers to search and reserve parking slots ahead of arrival, manage active parking sessions with check-in/check-out functionality, and enforces compliance through a structured penalty policy.

---

## Features

- **Public Landing Page** — Interactive overview, feature highlights, and zone preview.
- **User Reservation Portal** — Search available slots by date, time, and campus zone.
- **Check-In / Check-Out Interface** — Manage active sessions with live status tracking.
- **Automated Penalty Enforcement** — 3 late departures trigger a temporary 24-hour booking restriction.
- **Admin Management Console** — Live slot oversight and zone management for facility managers.
- **Role-Based Authentication** — Secure login and signup with distinct `user` and `admin` permissions.

---

## Tech Stack

- **Backend:** PHP 8+ (PDO for database abstraction)
- **Database:** MySQL 5.7+ / MariaDB 10.4+
- **Frontend:** HTML5, Vanilla CSS3 (Kinetic Campus UI system with dark/light theme support), JavaScript
- **Local Server Environment:** XAMPP / WAMP / LAMP (Apache + MySQL)

---

## Prerequisites

Before testing locally, make sure you have installed:
- [XAMPP](https://www.apachefriends.org/) (or any PHP + MySQL web server stack)
- PHP **8.0** or higher
- MySQL / MariaDB

---

## Local Setup & Testing Instructions

Follow these steps to run and test the project on your local machine using XAMPP:

### 1. Clone or Move the Repository
Clone the repository or copy the project folder into your XAMPP `htdocs` directory:

```bash
# Windows default path:
C:\xampp\htdocs\parking-system
```

### 2. Start Web & Database Server
Open the **XAMPP Control Panel** and start both:
- **Apache**
- **MySQL**

### 3. Import Database Schema
1. Open your browser and navigate to `http://localhost/phpmyadmin`.
2. Click **New** in the left sidebar and create a database named `parking_system`.
3. Select the newly created `parking_system` database.
4. Click the **Import** tab at the top.
5. Click **Choose File**, select `schema.sql` from the root directory of this project, and click **Go**.

*Note: The schema automatically creates the `users`, `parking_slots`, and `bookings` tables, along with initial test parking slots (e.g., A1, A2, B1, B2).*

### 4. Database Connection Setup
Check `includes/db.php` to ensure the database settings match your local MySQL configuration:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'parking_system');
define('DB_USER', 'root'); // Your MySQL username (default: root)
define('DB_PASS', '');     // Your MySQL password (default: empty)
```

### 5. Access the Application
Open your browser and visit:

```
http://localhost/parking-system/landing.html
```

---

## How to Test Roles & Features

1. **User Account Registration:**
   - Go to `http://localhost/parking-system/public/signup.php` and register a new user account.
   - Log in to access the user dashboard and reserve a parking slot.

2. **Creating an Admin Account:**
   - Register a normal user account first.
   - Open phpMyAdmin (`http://localhost/phpmyadmin`) and select the `parking_system` database.
   - Run the following SQL query in the **SQL** tab:
     ```sql
     UPDATE users SET role = 'admin' WHERE email = 'your_registered_email@example.com';
     ```
   - Log back in with that account to access admin functionalities.

---

## Project Structure

```
parking-system/
├── landing.html          # Public landing page
├── schema.sql            # Database creation & seed data
├── campuspark_prd.md     # Product Requirements Document (PRD)
├── CONVENTIONS.md        # Codebase rules & standards
├── DESIGN.md             # Design system specifications
├── includes/             # Shared backend logic & templates
│   ├── db.php            # Database connection configuration
│   ├── auth.php          # Session & authentication functions
│   ├── header.php        # Site header component
│   └── footer.php        # Site footer component
├── public/               # Publicly accessible routes & views
│   ├── index.php         # User dashboard & spot selection
│   ├── book-slot.php     # Slot reservation processor
│   ├── dashboard.php     # User active sessions & check-in/out
│   ├── login.php         # Login interface
│   ├── signup.php        # Account registration
│   └── logout.php        # Session handler
└── assets/               # Static styles, scripts, and images
```
