<?php
// checkin.php — POST-only handler for two actions:
//   action=checkin (default) — enforces 15-min check-in window from start_time,
//                              then blocks if the physical slot is still occupied
//                              by an overstaying booking.
//   action=report            — files a complaint about a slot-occupying booking
//                              and awards the complainant +15 reward points.
// Never outputs HTML; always redirects back to dashboard.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

$user    = current_user();
$user_id = (int) $user['id'];
$action  = trim($_POST['action'] ?? 'checkin');

// ═══════════════════════════════════════════════════════════════════════════
// ACTION: report — file a complaint about a slot-occupying booking
// ═══════════════════════════════════════════════════════════════════════════
if ($action === 'report') {
    $blocked_id   = (int) ($_POST['blocked_booking_id']   ?? 0);
    $occupying_id = (int) ($_POST['occupying_booking_id'] ?? 0);

    if ($blocked_id <= 0 || $occupying_id <= 0) {
        $_SESSION['flash_error'] = 'Invalid complaint data.';
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    }

    // Verify the blocked booking belongs to the current user and is still 'booked'.
    $verify = $pdo->prepare(
        "SELECT id FROM bookings WHERE id = ? AND user_id = ? AND status = 'booked'"
    );
    $verify->execute([$blocked_id, $user_id]);
    if (!$verify->fetch()) {
        $_SESSION['flash_error'] = 'Invalid complaint request.';
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    }

    try {
        $ins = $pdo->prepare(
            'INSERT INTO complaints (blocked_booking_id, occupying_booking_id, complainant_id)
             VALUES (?, ?, ?)'
        );
        $ins->execute([$blocked_id, $occupying_id, $user_id]);

        // Award 15 reward points for filing the complaint.
        $pts = $pdo->prepare('UPDATE users SET reward_points = reward_points + 15 WHERE id = ?');
        $pts->execute([$user_id]);
        refresh_user_points($pdo, $user_id);

        $_SESSION['flash'] = 'Complaint filed — +15 reward points added to your wallet. Our team will investigate the overstaying vehicle.';
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            // UNIQUE constraint on blocked_booking_id — already reported.
            $_SESSION['flash_error'] = 'You have already reported this slot occupancy. Our team is looking into it.';
        } else {
            $_SESSION['flash_error'] = 'Could not file complaint. Please try again.';
        }
    }

    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// ACTION: checkin (default)
// ═══════════════════════════════════════════════════════════════════════════
$booking_id = (int) ($_POST['booking_id'] ?? 0);

if ($booking_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid booking.';
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// Fetch the booking — must belong to this user, be status='booked', and for today.
// Pull start_time for the no-show window; created_at as fallback for legacy rows.
$stmt = $pdo->prepare(
    "SELECT id, slot_id, booking_date, start_time, created_at
       FROM bookings
      WHERE id = ? AND user_id = ? AND status = 'booked' AND booking_date = CURDATE()"
);
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();

if (!$booking) {
    $_SESSION['flash_error'] = 'Check-in not available for this booking.';
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// Enforce the 15-minute check-in window.
// Use start_time for time-block bookings; fall back to created_at for legacy rows.
if (!empty($booking['start_time'])) {
    $window_ref = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
} else {
    $window_ref = strtotime($booking['created_at']);
}
$window_expired = ($window_ref + (15 * 60)) < time();

if ($window_expired) {
    // Auto-cancel the expired booking and apply the late-departure penalty.
    $cancel = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
    $cancel->execute([$booking_id]);

    $inc = $pdo->prepare('UPDATE users SET late_departure_count = late_departure_count + 1 WHERE id = ?');
    $inc->execute([$user_id]);

    $cntStmt = $pdo->prepare('SELECT late_departure_count FROM users WHERE id = ?');
    $cntStmt->execute([$user_id]);
    $new_count = (int) $cntStmt->fetchColumn();
    $_SESSION['late_count'] = $new_count;

    if ($new_count % 3 === 0) {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $lock = $pdo->prepare('UPDATE users SET booking_locked_until = ? WHERE id = ?');
        $lock->execute([$tomorrow, $user_id]);
        $_SESSION['booking_locked_until'] = $tomorrow;
        $_SESSION['flash_error'] = "No-show — 15-minute check-in window expired. You've reached {$new_count} late strikes; booking access is suspended until {$tomorrow}.";
    } else {
        $remaining = 3 - ($new_count % 3);
        $_SESSION['flash_error'] = "No-show — 15-minute check-in window expired. Warning {$new_count}/3 — {$remaining} more will suspend your booking access.";
    }

    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// Check if the physical slot is still occupied by a DIFFERENT booking that is
// checked_in and whose end_time has already passed (the previous vehicle overstayed).
$occupyStmt = $pdo->prepare(
    "SELECT id FROM bookings
      WHERE slot_id = ?
        AND id != ?
        AND status = 'checked_in'
        AND end_time IS NOT NULL
        AND CONCAT(booking_date, ' ', end_time) < NOW()"
);
$occupyStmt->execute([$booking['slot_id'], $booking_id]);
$occupying = $occupyStmt->fetch();

if ($occupying) {
    // Slot is still physically occupied by an overstaying vehicle — block check-in.
    $_SESSION['flash_error'] = 'The slot is still occupied by a vehicle that should have left by now. You can report this to earn +15 reward points.';
    $_SESSION['blocked_checkin'] = [
        'blocked_id'   => $booking_id,
        'occupying_id' => (int) $occupying['id'],
    ];
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// Window is still open and slot is clear — record check-in.
$upd = $pdo->prepare(
    "UPDATE bookings SET check_in_time = NOW(), status = 'checked_in' WHERE id = ?"
);
$upd->execute([$booking_id]);

$_SESSION['flash'] = 'Checked in successfully! Your reserved slot time has started.';
header('Location: ' . BASE_URL . '/public/dashboard.php');
exit;
