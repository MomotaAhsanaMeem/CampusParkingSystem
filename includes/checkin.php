<?php
// checkin.php — POST-only handler for check-in, report overstayer, and extend slot actions:
//   action=checkin     — enforces start_time access and checks if slot is occupied.
//                        Tracks late check-ins (>15 min late) for record-keeping.
//   action=report      — files complaint if occupying vehicle is overstaying.
//                        Awards complainant reward points, deducts points from overstayer.
//   action=extend_slot — if next slot on the bay is empty without booking, driver can book it (10 pts)
//                        to extend stay and avoid penalty.
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
// ACTION: extend_slot — book next empty slot on same bay to avoid penalty
// ═══════════════════════════════════════════════════════════════════════════
if ($action === 'extend_slot') {
    $booking_id = (int) ($_POST['booking_id'] ?? 0);

    if ($booking_id <= 0) {
        $_SESSION['flash_error'] = 'Invalid booking for extension.';
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Fetch current active checked-in booking
        $stmt = $pdo->prepare(
            "SELECT b.id, b.slot_id, b.booking_date, b.start_time, b.end_time, b.duration_hours, b.points_cost,
                    s.slot_code, s.zone
               FROM bookings b
               JOIN parking_slots s ON s.id = b.slot_id
              WHERE b.id = ? AND b.user_id = ? AND b.status = 'checked_in' AND b.booking_date = CURDATE()
              FOR UPDATE"
        );
        $stmt->execute([$booking_id, $user_id]);
        $booking = $stmt->fetch();

        if (!$booking || empty($booking['end_time'])) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = 'Active session could not be found for extension.';
            header('Location: ' . BASE_URL . '/public/dashboard.php');
            exit;
        }

        $next_start = $booking['end_time'];
        $next_end   = date('H:i:s', strtotime($booking['end_time']) + 3600);

        // Verify operating hours (max 17:00:00)
        if ($next_start >= '17:00:00') {
            $pdo->rollBack();
            $_SESSION['flash_error'] = 'Cannot extend: campus parking operating hours end at 5:00 PM.';
            header('Location: ' . BASE_URL . '/public/dashboard.php');
            exit;
        }

        // Check if the next slot was empty without booking
        $overlapStmt = $pdo->prepare(
            "SELECT id FROM bookings
              WHERE slot_id = ? AND booking_date = ? AND id != ?
                AND status IN ('booked', 'checked_in')
                AND start_time < ? AND end_time > ?
              FOR UPDATE"
        );
        $overlapStmt->execute([$booking['slot_id'], $booking['booking_date'], $booking['id'], $next_end, $next_start]);
        if ($overlapStmt->fetch()) {
            $pdo->rollBack();
            $label_start = date('g:i A', strtotime($next_start));
            $label_end   = date('g:i A', strtotime($next_end));
            $_SESSION['flash_error'] = "The next time slot ({$label_start} – {$label_end}) is already booked by another user. Extension unavailable.";
            header('Location: ' . BASE_URL . '/public/dashboard.php');
            exit;
        }

        // Cost is 10 points for 1-hour extension
        $extension_cost = 10;
        $user_pts = refresh_user_points($pdo, $user_id);
        if ($user_pts < $extension_cost) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Insufficient points! Extending the slot requires {$extension_cost} points.";
            header('Location: ' . BASE_URL . '/public/payment.php?reason=insufficient_points&return_to=dashboard');
            exit;
        }

        // Deduct 10 points atomically
        $deductStmt = $pdo->prepare('UPDATE users SET reward_points = reward_points - ? WHERE id = ? AND reward_points >= ?');
        $deductStmt->execute([$extension_cost, $user_id, $extension_cost]);
        if ($deductStmt->rowCount() === 0) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not deduct points for extension.';
            header('Location: ' . BASE_URL . '/public/dashboard.php');
            exit;
        }

        // Extend booking: new end_time, +1 hour duration, +10 points cost
        $extStmt = $pdo->prepare(
            'UPDATE bookings
                SET end_time = ?,
                    duration_hours = duration_hours + 1,
                    points_cost = points_cost + ?
              WHERE id = ?'
        );
        $extStmt->execute([$next_end, $extension_cost, $booking_id]);

        // Log transaction
        try {
            $tx = $pdo->prepare(
                "INSERT INTO point_transactions (user_id, type, points, description)
                 VALUES (?, 'booking_deduction', ?, ?)"
            );
            $tx->execute([
                $user_id,
                -$extension_cost,
                "Extended Slot {$booking['slot_code']} to " . date('g:i A', strtotime($next_end)) . " (avoided overstay penalty)"
            ]);
        } catch (Throwable $ignore) {}

        refresh_user_points($pdo, $user_id);
        $pdo->commit();

        $label_new_end = date('g:i A', strtotime($next_end));
        $_SESSION['flash'] = "Slot {$booking['slot_code']} successfully extended to {$label_new_end}! Overstay penalty avoided.";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash_error'] = 'An error occurred while extending your slot. Please try again.';
    }

    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// ACTION: report — file complaint about a slot-occupying overstayer (>15 mins)
// ═══════════════════════════════════════════════════════════════════════════
if ($action === 'report') {
    $blocked_id   = (int) ($_POST['blocked_booking_id']   ?? 0);
    $occupying_id = (int) ($_POST['occupying_booking_id'] ?? 0);

    if ($blocked_id <= 0 || $occupying_id <= 0) {
        $_SESSION['flash_error'] = 'Invalid complaint data.';
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    }

    // Verify the blocked booking belongs to the current user, is scheduled for today, and is still 'booked'.
    $verify = $pdo->prepare(
        "SELECT id, slot_id, booking_date, start_time FROM bookings WHERE id = ? AND user_id = ? AND status = 'booked' AND booking_date = CURDATE()"
    );
    $verify->execute([$blocked_id, $user_id]);
    $blocked_booking = $verify->fetch();
    if (!$blocked_booking) {
        $_SESSION['flash_error'] = 'Invalid complaint request. Reporting is only permitted for reservations scheduled for today.';
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    }

    // Ensure the blocked booking's scheduled start time has arrived
    $now_time = date('H:i:s');
    if (!empty($blocked_booking['start_time']) && $now_time < $blocked_booking['start_time']) {
        $_SESSION['flash_error'] = 'You cannot report an occupant before your scheduled reservation start time.';
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    }

    // Fetch the occupying booking details to verify overstay threshold
    $occStmt = $pdo->prepare(
        "SELECT b.id, b.user_id, b.slot_id, b.booking_date, b.start_time, b.end_time, b.check_in_time, b.duration_hours,
                b.penalty_points_deducted, s.slot_code, u.full_name as occupant_name
           FROM bookings b
           JOIN parking_slots s ON s.id = b.slot_id
           JOIN users u ON u.id = b.user_id
          WHERE b.id = ? AND b.status = 'checked_in'"
    );
    $occStmt->execute([$occupying_id]);
    $occupying_booking = $occStmt->fetch();

    if (!$occupying_booking) {
        $_SESSION['flash_error'] = 'The occupying vehicle has already checked out or is no longer occupying this slot.';
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    }

    if ((int)$occupying_booking['user_id'] === $user_id) {
        $_SESSION['flash_error'] = 'You cannot report your own vehicle.';
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    }

    // Calculate overstay against scheduled end_time
    $now = time();
    $scheduled_end_ts = 0;
    if (!empty($occupying_booking['end_time'])) {
        $scheduled_end_ts = strtotime($occupying_booking['booking_date'] . ' ' . $occupying_booking['end_time']);
    } elseif (!empty($occupying_booking['check_in_time'])) {
        $scheduled_end_ts = strtotime($occupying_booking['check_in_time']) + (int)($occupying_booking['duration_hours'] ?? 1) * 3600;
    } else {
        $scheduled_end_ts = $now;
    }

    $seconds_overstay = $now - $scheduled_end_ts;

    // Grace time removed: can report as soon as scheduled reservation expires
    if ($seconds_overstay <= 0) {
        $end_lbl = date('g:i:s A', $scheduled_end_ts);
        $_SESSION['flash_error'] = "The occupying vehicle has not overstayed yet (scheduled until {$end_lbl}). You can report once the reservation ends.";
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    }

    // Penalty calculation: every 30 seconds in decimals, so in total for 1 hour 20 points will be deducted.
    // 1 hour = 3600 seconds = 120 x 30s intervals. Rate = 20 / 120 = 1/6 = ~0.1667 pts per 30 seconds.
    $units_30s     = max(1, (int) ceil($seconds_overstay / 30));
    $fine_points   = round($units_30s * (20.0 / 120.0), 2);
    $reward_points = $fine_points;

    // Format overstay time string
    if ($seconds_overstay < 60) {
        $time_over_str = "{$seconds_overstay}s";
    } elseif ($seconds_overstay < 3600) {
        $m = floor($seconds_overstay / 60);
        $s = $seconds_overstay % 60;
        $time_over_str = $s > 0 ? "{$m}m {$s}s" : "{$m}m";
    } else {
        $h = floor($seconds_overstay / 3600);
        $rem_m = floor(($seconds_overstay % 3600) / 60);
        $time_over_str = $rem_m > 0 ? "{$h}h {$rem_m}m" : "{$h}h";
    }

    try {
        $pdo->beginTransaction();

        $ins = $pdo->prepare(
            'INSERT INTO complaints (blocked_booking_id, occupying_booking_id, complainant_id, penalty_deducted)
             VALUES (?, ?, ?, ?)'
        );
        $ins->execute([$blocked_id, $occupying_id, $user_id, $fine_points]);

        $occupant_user_id = (int) $occupying_booking['user_id'];

        // 1. Deduct decimal penalty from the overstaying driver (no freeze for overstayers)
        $deduct = $pdo->prepare('UPDATE users SET reward_points = reward_points - ? WHERE id = ?');
        $deduct->execute([$fine_points, $occupant_user_id]);
        refresh_user_points($pdo, $occupant_user_id);

        // Track points deducted on the occupying booking row
        $trackStmt = $pdo->prepare('UPDATE bookings SET penalty_points_deducted = penalty_points_deducted + ? WHERE id = ?');
        $trackStmt->execute([$fine_points, $occupying_id]);

        // 2. Award decimal reward to complainant
        $award = $pdo->prepare('UPDATE users SET reward_points = reward_points + ? WHERE id = ?');
        $award->execute([$reward_points, $user_id]);
        refresh_user_points($pdo, $user_id);

        // 3. Log transactions with decimal points
        try {
            $tx1 = $pdo->prepare(
                "INSERT INTO point_transactions (user_id, type, points, description)
                 VALUES (?, 'report_reward', ?, ?)"
            );
            $tx1->execute([
                $user_id,
                $reward_points,
                "Reward (+{$reward_points} pts) for reporting overstaying vehicle in Slot {$occupying_booking['slot_code']} ({$time_over_str} overdue, {$units_30s}x30s @ 20 pts/hr)"
            ]);

            $tx2 = $pdo->prepare(
                "INSERT INTO point_transactions (user_id, type, points, description)
                 VALUES (?, 'late_fine', ?, ?)"
            );
            $tx2->execute([
                $occupant_user_id,
                -$fine_points,
                "Overstay penalty: -{$fine_points} pts deducted for occupying Slot {$occupying_booking['slot_code']} ({$time_over_str} overdue, {$units_30s}x30s @ 20 pts/hr)"
            ]);
        } catch (Throwable $txEx) {
            // Non-critical logging failure
        }

        $pdo->commit();

        $_SESSION['flash'] = "Complaint filed successfully! Slot {$occupying_booking['slot_code']} has been overstayed by {$time_over_str} ({$units_30s} x 30s intervals). +{$reward_points} reward points were added to your wallet, and {$fine_points} points were deducted from the overstaying driver.";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            $_SESSION['flash_error'] = 'You have already reported this slot occupancy. Our campus patrol has been notified.';
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

try {
    $pdo->beginTransaction();

    // Fetch the booking — must belong to this user, be status='booked', and for today.
    // Lock row FOR UPDATE to prevent race conditions during check-in.
    $stmt = $pdo->prepare(
        "SELECT b.id, b.slot_id, b.booking_date, b.start_time, b.end_time, b.created_at, s.slot_code
           FROM bookings b
           JOIN parking_slots s ON s.id = b.slot_id
          WHERE b.id = ? AND b.user_id = ? AND b.status = 'booked' AND b.booking_date = CURDATE()
          FOR UPDATE"
    );
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Check-in not available for this booking.';
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    }

    // 1. Enforce time slot access: booking cannot be accessed before its reserved start_time
    $now_time = date('H:i:s');
    if (!empty($booking['start_time']) && $now_time < $booking['start_time']) {
        $pdo->rollBack();
        $s_ts = strtotime('2000-01-01 ' . $booking['start_time']);
        $start_formatted = (date('s', $s_ts) !== '00') ? date('g:i:s A', $s_ts) : date('g:i A', $s_ts);
        $_SESSION['flash_error'] = "You cannot check in before your reserved time slot. Check-in opens at {$start_formatted}.";
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    }

    // 2. Enforce only one check-in per slot at a time:
    // Check if the physical slot is occupied by ANY other booking with status='checked_in'
    $occupyStmt = $pdo->prepare(
        "SELECT b.id, b.user_id, b.booking_date, b.start_time, b.end_time
           FROM bookings b
          WHERE b.slot_id = ?
            AND b.id != ?
            AND b.status = 'checked_in'
          FOR UPDATE"
    );
    $occupyStmt->execute([$booking['slot_id'], $booking_id]);
    $occupying = $occupyStmt->fetch();

    if ($occupying) {
        $pdo->rollBack();

        if ((int)$occupying['user_id'] === $user_id) {
            $_SESSION['flash_error'] = "Slot {$booking['slot_code']} is already checked in under your account. Please check out your active session first.";
            header('Location: ' . BASE_URL . '/public/dashboard.php');
            exit;
        }

        $occ_end_ts = 0;
        if (!empty($occupying['end_time'])) {
            $occ_end_ts = strtotime($occupying['booking_date'] . ' ' . $occupying['end_time']);
        }
        $sec_over = $occ_end_ts > 0 ? (time() - $occ_end_ts) : 0;
        $is_overstay = ($sec_over > 0);

        if ($is_overstay) {
            $units_30s = max(1, (int) ceil($sec_over / 30));
            $penalty_pts = round($units_30s * (20.0 / 120.0), 2);
            $time_over_str = ($sec_over < 60) ? "{$sec_over}s" : ((int)ceil($sec_over / 60) . " min");
            $_SESSION['flash_error'] = "Slot {$booking['slot_code']} is currently occupied. The previous vehicle has overstayed by {$time_over_str}. You can report below to receive a +{$penalty_pts} pts reward.";
        } else {
            $end_label = $occ_end_ts > 0 ? date('g:i:s A', $occ_end_ts) : 'scheduled end';
            $_SESSION['flash_error'] = "Slot {$booking['slot_code']} is currently occupied. The previous reservation is active until {$end_label}.";
        }

        $_SESSION['blocked_checkin'] = [
            'blocked_id'   => $booking_id,
            'occupying_id' => (int) $occupying['id'],
            'is_overstay'  => $is_overstay,
            'sec_over'     => $sec_over,
        ];
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    }

    // 3. Enforce 15-minute check-in window:
    // If check-in occurs > 15 minutes after start_time, it is a late check-in.
    if (!empty($booking['start_time'])) {
        $window_ref = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
    } else {
        $window_ref = strtotime($booking['created_at']);
    }
    $is_late = ($window_ref + (15 * 60)) < time();

    if ($is_late) {
        // Record late check-in
        $upd = $pdo->prepare(
            "UPDATE bookings SET check_in_time = NOW(), status = 'checked_in', is_late_checkin = 1 WHERE id = ?"
        );
        $upd->execute([$booking_id]);

        // Increment user's late_checkin_count
        $inc = $pdo->prepare('UPDATE users SET late_checkin_count = late_checkin_count + 1 WHERE id = ?');
        $inc->execute([$user_id]);

        // Fetch updated late check-in count
        $cntStmt = $pdo->prepare('SELECT late_checkin_count FROM users WHERE id = ?');
        $cntStmt->execute([$user_id]);
        $new_late_count = (int) $cntStmt->fetchColumn();
        $_SESSION['late_checkin_count'] = $new_late_count;

        // Late check-ins are recorded on account, but booking restriction is enforced on late departures
        $_SESSION['flash'] = "Checked in late (arrived more than 15 minutes past start time). Your slot time is now active.";
    } else {
        // On-time check-in
        $upd = $pdo->prepare(
            "UPDATE bookings SET check_in_time = NOW(), status = 'checked_in', is_late_checkin = 0 WHERE id = ?"
        );
        $upd->execute([$booking_id]);
        $_SESSION['flash'] = 'Checked in successfully! Your reserved slot time has started.';
    }

    $pdo->commit();
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = 'An error occurred during check-in. Please try again.';
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}
