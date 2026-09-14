<?php
// checkout.php — POST-only handler for check-out action.
// Sets check_out_time = NOW() and status = 'completed', then compares
// check_out_time against the booking's own end_time + 15 min grace period.
// Late departures incur a point fine (5 pts per started 15-min block),
// increment late_departure_count, and optionally freeze booking access.
// Never outputs HTML; always redirects back to dashboard.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

// Reject anything that isn't a plain POST from our own form.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

$user       = current_user();
$user_id    = (int) $user['id'];
$booking_id = (int) ($_POST['booking_id'] ?? 0);

if ($booking_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid booking.';
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// Fetch the booking — must belong to this user and be status='checked_in'.
// Also pull booking_date + end_time so we can compute the deadline accurately.
$stmt = $pdo->prepare(
    "SELECT id, check_in_time, booking_date, end_time
       FROM bookings WHERE id = ? AND user_id = ? AND status = 'checked_in'"
);
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();

if (!$booking) {
    $_SESSION['flash_error'] = 'Check-out not available for this booking.';
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// Record check-out time.
$upd = $pdo->prepare(
    "UPDATE bookings SET check_out_time = NOW(), status = 'completed' WHERE id = ?"
);
$upd->execute([$booking_id]);

// Reload the just-saved check_out_time from DB for an authoritative comparison.
$row = $pdo->prepare('SELECT check_out_time FROM bookings WHERE id = ?');
$row->execute([$booking_id]);
$checkout_time = $row->fetchColumn();

// Deadline = booking's own end_time + 15-minute grace period.
// Falls back to check_in_time + 4h15m for legacy bookings without an end_time.
if (!empty($booking['end_time'])) {
    $deadline = strtotime($booking['booking_date'] . ' ' . $booking['end_time']) + (15 * 60);
} else {
    $deadline = strtotime($booking['check_in_time']) + (4 * 3600) + (15 * 60);
}

$checked_out  = strtotime($checkout_time);
$seconds_over = $checked_out - $deadline;
$is_late      = $seconds_over > 0;

if (!$is_late) {
    // On-time checkout — no points adjustment.
    $_SESSION['flash'] = 'Checked out on time!';
} else {
    // --- Fine calculation ---
    // 5 points per started 15-minute block past the deadline (minimum 5 pts).
    $blocks_over = (int) ceil($seconds_over / (15 * 60));
    $fine_pts    = $blocks_over * 5;

    // Deduct the fine — points may go negative (signals debt to the user).
    $fineStmt = $pdo->prepare(
        'UPDATE users SET reward_points = reward_points - ? WHERE id = ?'
    );
    $fineStmt->execute([$fine_pts, $user_id]);
    refresh_user_points($pdo, $user_id);

    // --- Late-departure counter ---
    $inc = $pdo->prepare(
        'UPDATE users SET late_departure_count = late_departure_count + 1 WHERE id = ?'
    );
    $inc->execute([$user_id]);

    // Fetch updated count to decide whether to lock bookings.
    $cntStmt = $pdo->prepare('SELECT late_departure_count FROM users WHERE id = ?');
    $cntStmt->execute([$user_id]);
    $new_count = (int) $cntStmt->fetchColumn();
    $_SESSION['late_count'] = $new_count;

    // Human-readable overrun duration for the flash message.
    $mins_over  = (int) ceil($seconds_over / 60);
    $fine_label = "-{$fine_pts} pts fine ({$mins_over} min late)";

    if ($new_count % 3 === 0) {
        // Lock bookings until tomorrow.
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $lock = $pdo->prepare(
            'UPDATE users SET booking_locked_until = ? WHERE id = ?'
        );
        $lock->execute([$tomorrow, $user_id]);
        $_SESSION['booking_locked_until'] = $tomorrow;

        $_SESSION['flash'] = "Late check-out — {$fine_label}. You've reached {$new_count} late departures; booking access is suspended until {$tomorrow}.";
    } else {
        $remaining = 3 - ($new_count % 3);
        $_SESSION['flash'] = "Late check-out — {$fine_label}. Warning {$new_count}/3 — {$remaining} more will suspend your booking access.";
    }
}

header('Location: ' . BASE_URL . '/public/dashboard.php');
exit;
