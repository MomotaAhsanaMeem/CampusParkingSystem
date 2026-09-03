<?php
// All session logic lives here. Pages must never touch $_SESSION directly.
// auth.php is always require_once'd before any output so session_start() runs first.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if the visitor has no active session.
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /parking-system/public/login.php');
        exit;
    }
}

// Redirect non-admins away from admin-only pages.
function require_admin(): void {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: /parking-system/public/dashboard.php');
        exit;
    }
}

// Returns a small array of the current user's session identity values.
function current_user(): array {
    return [
        'id'   => $_SESSION['user_id'] ?? null,
        'role' => $_SESSION['role']    ?? null,
        'name' => $_SESSION['name']    ?? null,
    ];
}

// Returns true when the user is currently serving a booking lock.
// The lock is lifted automatically once booking_locked_until passes today.
function is_booking_locked(): bool {
    if (empty($_SESSION['booking_locked_until'])) {
        return false;
    }
    return $_SESSION['booking_locked_until'] >= date('Y-m-d');
}

// Persist user identity into the session after a successful login or signup.
function login_user(array $user): void {
    session_regenerate_id(true); // guard against session fixation
    $_SESSION['user_id']              = $user['id'];
    $_SESSION['role']                 = $user['role'];
    $_SESSION['name']                 = $user['full_name'];
    $_SESSION['booking_locked_until'] = $user['booking_locked_until'];
    $_SESSION['late_count']           = (int) ($user['late_departure_count'] ?? 0);
}

// Destroy the session cleanly on logout.
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}
