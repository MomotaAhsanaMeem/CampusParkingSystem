<?php
// checkout.php — POST-only handler for check-out action.
// Sets check_out_time = NOW() and status = 'completed'.
// Compares check_out_time against booking's scheduled end_time.
// If overstay occurs:
// - Calculates overtime penalty at 20 points/hour (in 30-second decimal units).
// - Deducts remaining penalty points (offsetting any points already deducted via occupant reports).
// - Increments user's late_departure_count.
// - Every 3 late checkouts triggers a temporary 120-second booking restriction (booking_locked_until = now + 120s).
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
$stmt = $pdo->prepare(
    "SELECT id, check_in_time, booking_date, start_time, end_time, penalty_points_deducted
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

// Scheduled end timestamp
if (!empty($booking['end_time'])) {
    $scheduled_end_ts = strtotime($booking['booking_date'] . ' ' . $booking['end_time']);
} else {
    $scheduled_end_ts = strtotime($booking['check_in_time']) + (4 * 3600);
}

$checked_out  = strtotime($checkout_time);
$seconds_over = $checked_out - $scheduled_end_ts;

if ($seconds_over <= 0) {
    $_SESSION['flash'] = 'Checked out on time! Have a great day.';
} else {
    // Late departure penalty enforcement:
    // 1. Calculate & deduct overtime points (20 points / hour in 30-second increments)
    $units_30s     = max(1, (int) ceil($seconds_over / 30));
    $total_penalty = round($units_30s * (20.0 / 120.0), 2);

    // Deduct remaining points (offsetting points already deducted from reports)
    $already_deducted = (float) ($booking['penalty_points_deducted'] ?? 0);
    $to_deduct        = max(0.0, round($total_penalty - $already_deducted, 2));

    if ($to_deduct > 0) {
        $fineStmt = $pdo->prepare('UPDATE users SET reward_points = reward_points - ? WHERE id = ?');
        $fineStmt->execute([$to_deduct, $user_id]);

        $trackStmt = $pdo->prepare('UPDATE bookings SET penalty_points_deducted = penalty_points_deducted + ? WHERE id = ?');
        $trackStmt->execute([$to_deduct, $booking_id]);

        refresh_user_points($pdo, $user_id);

        try {
            $tx = $pdo->prepare(
                "INSERT INTO point_transactions (user_id, type, points, description)
                 VALUES (?, 'late_fine', ?, ?)"
            );
            $tx->execute([
                $user_id,
                -$to_deduct,
                "Late check-out penalty: -{$to_deduct} pts ({$seconds_over}s overstay, {$units_30s}x30s @ 20 pts/hr = {$total_penalty} pts total)"
            ]);
        } catch (Throwable $ignoreTx) {}
    }

    // 2. Increment user's late_departure_count
    $incDep = $pdo->prepare('UPDATE users SET late_departure_count = late_departure_count + 1 WHERE id = ?');
    $incDep->execute([$user_id]);

    $cntStmt = $pdo->prepare('SELECT late_departure_count FROM users WHERE id = ?');
    $cntStmt->execute([$user_id]);
    $new_late_departure = (int) $cntStmt->fetchColumn();
    $_SESSION['late_count'] = $new_late_departure;

    // 3. Automated Penalty: Every 3 late checkouts trigger a temporary 120-second booking restriction
    $freeze_msg = '';
    if ($new_late_departure % 3 === 0) {
        $lock_until = date('Y-m-d H:i:s', time() + 120);
        $lockStmt = $pdo->prepare('UPDATE users SET booking_locked_until = ? WHERE id = ?');
        $lockStmt->execute([$lock_until, $user_id]);
        $_SESSION['booking_locked_until'] = $lock_until;
        $freeze_msg = " Warning ({$new_late_departure}/3): System frozen! Your booking privileges are suspended for 120 seconds due to 3 late checkouts.";
    } else {
        $rem = 3 - ($new_late_departure % 3);
        $freeze_msg = " Warning ({$new_late_departure}/3): {$rem} more late checkout(s) will freeze your booking privileges for 120 seconds.";
    }

    $time_over_str = ($seconds_over < 60) ? "{$seconds_over}s" : ((int)ceil($seconds_over / 60) . " min");
    if ($to_deduct > 0) {
        $_SESSION['flash'] = "Checked out. Overstay: {$time_over_str}. Penalty: {$units_30s} x 30s = {$total_penalty} points (-20 pts/hr). {$to_deduct} points deducted.{$freeze_msg}";
    } else {
        $_SESSION['flash'] = "Checked out. Overstay: {$time_over_str}. Total penalty: {$total_penalty} points (already deducted via report).{$freeze_msg}";
    }
}

header('Location: ' . BASE_URL . '/public/dashboard.php');
exit;

