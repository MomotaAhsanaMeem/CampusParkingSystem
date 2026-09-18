<?php
// All session logic lives here. Pages must never touch $_SESSION directly.
// auth.php is always require_once'd before any output so session_start() runs first.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamically compute base web URL to work in any folder (e.g. /parking-system or /Web Programming/CampusParkingSystem)
if (!defined('BASE_URL')) {
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // Strip the /public or /includes leaf so BASE_URL always points to the project root.
    if (basename($script_dir) === 'public' || basename($script_dir) === 'includes') {
        $script_dir = str_replace('\\', '/', dirname($script_dir));
    }
    if ($script_dir === '/' || $script_dir === '\\' || $script_dir === '.') {
        $script_dir = '';
    }
    define('BASE_URL', rtrim($script_dir, '/'));
}

// Redirect to login if the visitor has no active session.
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/public/login.php');
        exit;
    }
}

// Redirect non-admins away from admin-only pages.
function require_admin(): void {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    }
}

// Returns a small array of the current user's session identity values.
function current_user(): array {
    return [
        'id'                 => $_SESSION['user_id']       ?? null,
        'role'               => $_SESSION['role']          ?? null,
        'name'               => $_SESSION['name']          ?? null,
        'reward_points'      => (float) ($_SESSION['reward_points'] ?? 0),
        'package_tier'       => $_SESSION['package_tier']  ?? 'Starter',
        'late_checkin_count' => (int) ($_SESSION['late_checkin_count'] ?? 0),
        'late_count'         => (int) ($_SESSION['late_count'] ?? 0),
    ];
}

// Returns true when the user is currently serving a booking lock.
// The lock is lifted automatically once booking_locked_until passes the current timestamp.
function is_booking_locked(): bool {
    if (empty($_SESSION['booking_locked_until'])) {
        return false;
    }
    $locked_ts = strtotime($_SESSION['booking_locked_until']);
    if ($locked_ts === false) {
        return false;
    }
    return $locked_ts > time();
}

// Returns remaining seconds on an active booking freeze (0 if not locked)
function booking_lock_remaining_seconds(): int {
    if (empty($_SESSION['booking_locked_until'])) {
        return 0;
    }
    $locked_ts = strtotime($_SESSION['booking_locked_until']);
    if ($locked_ts === false) {
        return 0;
    }
    return max(0, $locked_ts - time());
}

// Persist user identity into the session after a successful login or signup.
function login_user(array $user): void {
    session_regenerate_id(true); // guard against session fixation
    $_SESSION['user_id']              = $user['id'];
    $_SESSION['role']                 = $user['role'];
    $_SESSION['name']                 = $user['full_name'];
    $_SESSION['booking_locked_until'] = $user['booking_locked_until'];
    $_SESSION['late_count']           = (int) ($user['late_departure_count'] ?? 0);
    $_SESSION['late_checkin_count']   = (int) ($user['late_checkin_count'] ?? 0);
    $_SESSION['reward_points']        = (float) ($user['reward_points'] ?? 100);
    $_SESSION['package_tier']         = $user['package_tier'] ?? 'Starter';
}

// Refresh user points, package tier, late counts, and lock status from DB and update session.
function refresh_user_points(PDO $pdo, int $user_id): float {
    $stmt = $pdo->prepare('SELECT reward_points, package_tier, late_checkin_count, late_departure_count, booking_locked_until FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    if ($row) {
        $_SESSION['reward_points']        = (float) ($row['reward_points'] ?? 0);
        $_SESSION['late_checkin_count']   = (int) ($row['late_checkin_count'] ?? 0);
        $_SESSION['late_count']           = (int) ($row['late_departure_count'] ?? 0);
        $_SESSION['booking_locked_until'] = $row['booking_locked_until'] ?? null;
        if (!empty($row['package_tier'])) {
            $_SESSION['package_tier'] = $row['package_tier'];
        }
        return $_SESSION['reward_points'];
    }
    return 0.0;
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
