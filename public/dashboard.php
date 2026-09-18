<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user        = current_user();
$is_locked   = is_booking_locked();
$user_points = refresh_user_points($pdo, $user['id']);
$package_tier = $_SESSION['package_tier'] ?? 'Starter';

// Fetch latest 10 bookings including duration, points cost, check-in/out times, and time range
$stmt = $pdo->prepare(
    'SELECT b.id, b.slot_id, b.booking_date, b.duration_hours, b.points_cost, b.status,
            b.is_late_checkin, b.check_in_time, b.check_out_time, b.start_time, b.end_time,
            s.slot_code, s.zone
       FROM bookings b
       JOIN parking_slots s ON s.id = b.slot_id
      WHERE b.user_id = ?
      ORDER BY b.created_at DESC
      LIMIT 10'
);
$stmt->execute([$user['id']]);
$bookings = $stmt->fetchAll();

// Active count
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status IN ('booked','checked_in')");
$stmt2->execute([$user['id']]);
$active_count = (int) $stmt2->fetchColumn();

// Completed count
$stmt3 = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'completed'");
$stmt3->execute([$user['id']]);
$completed_count = (int) $stmt3->fetchColumn();

$flash           = $_SESSION['flash']           ?? '';
$flash_error     = $_SESSION['flash_error']     ?? '';
$blocked_checkin = $_SESSION['blocked_checkin'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_error'], $_SESSION['blocked_checkin']);

$today = date('Y-m-d');

// Sweep: silently cancel any 'booked' bookings from past dates (ghost rows where date passed).
$pastStmt = $pdo->prepare(
    "UPDATE bookings SET status = 'cancelled'
      WHERE user_id = ? AND status = 'booked' AND booking_date < CURDATE()"
);
$pastStmt->execute([$user['id']]);
$past_cancelled = $pastStmt->rowCount();

// Re-fetch the bookings list if past-date sweep made changes
if ($past_cancelled > 0) {
    $stmt = $pdo->prepare(
        'SELECT b.id, b.slot_id, b.booking_date, b.duration_hours, b.points_cost, b.status,
                b.is_late_checkin, b.check_in_time, b.check_out_time, b.start_time, b.end_time,
                s.slot_code, s.zone
           FROM bookings b
           JOIN parking_slots s ON s.id = b.slot_id
          WHERE b.user_id = ?
          ORDER BY b.created_at DESC
          LIMIT 10'
    );
    $stmt->execute([$user['id']]);
    $bookings = $stmt->fetchAll();
}

// Fetch currently occupied slots (where any booking is status = 'checked_in')
$occupiedStmt = $pdo->query(
    "SELECT slot_id, id, user_id, start_time, end_time, booking_date, check_in_time, duration_hours
       FROM bookings WHERE status = 'checked_in'"
);
$occupied_slots = [];
$occupied_by_bid = [];
while ($row = $occupiedStmt->fetch()) {
    $occupied_slots[(int)$row['slot_id']] = $row;
    $occupied_by_bid[(int)$row['id']]     = $row;
}

// Check if current user has an active checked-in booking for today
$myActiveStmt = $pdo->prepare(
    "SELECT b.id, b.slot_id, b.booking_date, b.start_time, b.end_time, b.check_in_time, b.penalty_points_deducted,
            s.slot_code, s.zone
       FROM bookings b
       JOIN parking_slots s ON s.id = b.slot_id
      WHERE b.user_id = ? AND b.status = 'checked_in' AND b.booking_date = CURDATE()
      LIMIT 1"
);
$myActiveStmt->execute([$user['id']]);
$my_active = $myActiveStmt->fetch();

$can_extend_next_slot = false;
$next_slot_time_label = '';
$my_sec_over          = 0;
$my_is_overstay       = false;

if ($my_active && !empty($my_active['end_time'])) {
    $my_end_ts = strtotime($my_active['booking_date'] . ' ' . $my_active['end_time']);
    $my_sec_over = time() - $my_end_ts;
    if ($my_sec_over > 0) {
        $my_is_overstay = true;
    }

    // Check if next contiguous slot (end_time to end_time + 1h) is empty without booking
    $my_next_start = $my_active['end_time'];
    $my_next_end   = date('H:i:s', strtotime($my_next_start) + 3600);
    if ($my_next_start < '17:00:00') {
        $chkOverlap = $pdo->prepare(
            "SELECT id FROM bookings
              WHERE slot_id = ? AND booking_date = CURDATE() AND id != ?
                AND status IN ('booked', 'checked_in')
                AND start_time < ? AND end_time > ?"
        );
        $chkOverlap->execute([$my_active['slot_id'], $my_active['id'], $my_next_end, $my_next_start]);
        if (!$chkOverlap->fetch()) {
            $can_extend_next_slot = true;
            $next_slot_time_label = date('g:i A', strtotime($my_next_start)) . ' – ' . date('g:i A', strtotime($my_next_end));
        }
    }
}

function booking_badge_class(string $status, int $is_late_checkin = 0): string {
    if ($is_late_checkin && $status === 'checked_in') {
        return 'badge-late-checkin';
    }
    return match($status) {
        'booked'       => 'badge-booked',
        'checked_in'   => 'badge-checked-in',
        'late_checkin' => 'badge-late-checkin',
        'completed'    => 'badge-completed',
        'cancelled'    => 'badge-cancelled',
        default        => 'badge-cancelled',
    };
}

$page_title = 'Dashboard';
$body_page  = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- pt-24 clears the fixed navbar -->
<div class="pt-24 pb-16 px-margin-mobile md:px-margin-desktop w-full max-w-7xl mx-auto">

    <!-- Booking-lock banner -->
    <?php if ($is_locked): ?>
    <div class="alert alert-warning mb-md" role="alert">
        <span class="alert-icon material-symbols-outlined" aria-hidden="true">lock</span>
        <div>
            <strong>Booking privileges suspended</strong> — you have reached 3 late check-ins on record.
            Unlocks on <strong><?= htmlspecialchars($_SESSION['booking_locked_until'] ?? '—') ?></strong>.
        </div>
    </div>
    <?php endif; ?>

    <!-- Active session extension banner (overstayer can book empty next slot to avoid penalty) -->
    <?php if ($my_active && $can_extend_next_slot): ?>
    <div class="alert mb-md" role="alert" style="background:rgba(8,145,178,0.10); border:1.5px solid var(--clr-primary); border-radius:var(--r-lg); padding:14px 18px; display:flex; align-items:center; justify-content:space-between; flex-wrap:gap; gap:14px;">
        <div class="flex items-center gap-sm">
            <span class="material-symbols-outlined" style="font-size:26px; color:var(--clr-primary);">more_time</span>
            <div>
                <strong style="color:var(--clr-primary); font-size:14px; display:block;">Next slot (<?= htmlspecialchars($next_slot_time_label) ?>) is empty!</strong>
                <p style="font-size:12px; margin:2px 0 0 0; color:var(--clr-text-muted);">
                    The bay is not booked after your session. Book the next slot for <strong>10 points</strong> to extend your stay and avoid overstay penalties.
                </p>
            </div>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/includes/checkin.php" style="display:inline;">
            <input type="hidden" name="action"     value="extend_slot">
            <input type="hidden" name="booking_id" value="<?= (int)$my_active['id'] ?>">
            <button type="submit" class="btn btn-primary" style="padding:7px 16px; font-size:13px; white-space:nowrap;">
                <span class="material-symbols-outlined" style="font-size:16px;">add_circle</span>
                Book Next Slot (10 pts)
            </button>
        </form>
    </div>
    <?php elseif ($my_active && $my_is_overstay && !$can_extend_next_slot): ?>
    <?php
        $my_units_30s = max(1, (int) ceil($my_sec_over / 30));
        $my_est_penalty = round($my_units_30s * (20.0 / 120.0), 2);
        $my_time_str = ($my_sec_over < 60) ? "{$my_sec_over} seconds" : ceil($my_sec_over / 60) . " minutes";
    ?>
    <div class="alert alert-error mb-md" role="alert" style="border-radius:var(--r-lg); padding:14px 18px;">
        <span class="alert-icon material-symbols-outlined" aria-hidden="true">warning</span>
        <div>
            <strong>You are overstaying Slot <?= htmlspecialchars($my_active['slot_code']) ?> by <?= $my_time_str ?>!</strong>
            The next time slot is booked by another driver. Overstay penalty (-20 pts/hr, per 30s) is accruing (~<?= $my_est_penalty ?> pts). Please check out immediately.
        </div>
    </div>
    <?php endif; ?>

    <!-- Low / exhausted points banner -->
    <?php if ($user_points < 10): ?>
    <div class="alert alert-warning mb-md" role="alert" style="border-left:4px solid var(--clr-amber);">
        <span class="alert-icon material-symbols-outlined" aria-hidden="true">warning</span>
        <div class="flex items-center justify-between w-full flex-wrap gap-sm">
            <div>
                <strong>Low or exhausted points balance!</strong>
                You have <strong><?= $user_points ?> points</strong> left. Reserving a parking slot requires 10 points/hour.
            </div>
            <a href="<?= BASE_URL ?>/public/payment.php" class="btn btn-primary" style="padding:6px 14px; font-size:13px;">
                Recharge Points
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Flash error (with optional Report button for blocked check-ins) -->
    <?php if ($flash_error !== ''): ?>
    <div class="alert alert-error mb-md" role="alert">
        <span class="alert-icon material-symbols-outlined" aria-hidden="true">error</span>
        <div style="flex:1;">
            <?= htmlspecialchars($flash_error) ?>
            <?php if ($blocked_checkin): ?>
            <?php
                $occ_row = $occupied_by_bid[(int)$blocked_checkin['occupying_id']] ?? null;
                $occ_can_report = false;
                $occ_sec_over = 0;
                $occ_penalty = 0.0;
                $occ_time_str = '';
                if ($occ_row && !empty($occ_row['end_time'])) {
                    $occ_end_ts   = strtotime($occ_row['booking_date'] . ' ' . $occ_row['end_time']);
                    $occ_sec_over = time() - $occ_end_ts;
                    $occ_can_report = ($occ_sec_over > 0);
                    if ($occ_can_report) {
                        $occ_units    = max(1, (int) ceil($occ_sec_over / 30));
                        $occ_penalty  = round($occ_units * (20.0 / 120.0), 2);
                        $occ_time_str = ($occ_sec_over < 60) ? "{$occ_sec_over} seconds" : ceil($occ_sec_over / 60) . " minutes";
                    }
                }
            ?>
            <div class="alert" role="alert" style="margin-top:10px; padding:12px 16px; border-radius:var(--r-lg); background:rgba(245,158,11,0.10); border:1.5px solid #F59E0B; display:flex; align-items:flex-start; gap:10px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:#D97706; margin-top:1px; flex-shrink:0;">warning_amber</span>
                <div style="flex:1; min-width:0;">
                    <strong style="font-size:13px; color:#92400E; display:block; margin-bottom:4px;">Slot is blocked by an occupying vehicle</strong>
                    <?php if ($occ_can_report): ?>
                    <p style="font-size:12px; color:#78350F; margin:0 0 8px 0; line-height:1.5;">
                        The previous reservation has overstayed by <strong><?= $occ_time_str ?></strong> (accrued penalty: <strong><?= $occ_penalty ?> pts</strong> @ 20 pts/hr).
                        You can <strong>report this</strong> now to receive a <strong>+<?= $occ_penalty ?> points reward</strong> (deducted from the occupant).
                    </p>
                    <form method="POST" action="<?= BASE_URL ?>/includes/checkin.php" style="display:inline;">
                        <input type="hidden" name="action"               value="report">
                        <input type="hidden" name="blocked_booking_id"   value="<?= (int)$blocked_checkin['blocked_id'] ?>">
                        <input type="hidden" name="occupying_booking_id" value="<?= (int)$blocked_checkin['occupying_id'] ?>">
                        <button type="submit" class="btn btn-report"
                                aria-label="Report the overstaying vehicle and earn +<?= $occ_penalty ?> reward points">
                            <span class="material-symbols-outlined" style="font-size:15px;">report</span>
                            Report &amp; Earn +<?= $occ_penalty ?> pts
                        </button>
                    </form>
                    <?php else: ?>
                    <p style="font-size:12px; color:#78350F; margin:0; line-height:1.5;">
                        The previous vehicle's reservation is active until <strong><?= !empty($occ_end_ts) ? date('g:i:s A', $occ_end_ts) : 'scheduled end' ?></strong>.
                        Reporting unlocks as soon as its reservation time expires.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Flash success -->
    <?php if ($flash !== ''): ?>
    <div class="alert alert-success mb-md" role="status">
        <span class="alert-icon material-symbols-outlined" aria-hidden="true">check_circle</span>
        <?= htmlspecialchars($flash) ?>
    </div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="flex items-center justify-between flex-wrap gap-md mb-lg">
        <div class="page-header" style="margin-bottom:0;">
            <h1 class="page-title">
                Welcome back, <?= htmlspecialchars($user['name'] ?? 'Driver') ?> 👋
            </h1>
            <p class="page-subtitle">Here's an overview of your campus parking activity & reward wallet.</p>
        </div>
        <div class="flex items-center gap-sm flex-wrap">
            <a href="<?= BASE_URL ?>/public/payment.php" class="btn btn-outline flex items-center gap-xs">
                <span class="material-symbols-outlined" style="font-size:18px;">add_card</span>
                Recharge Points
            </a>
            <a href="<?= BASE_URL ?>/public/book-slot.php" class="btn btn-primary flex items-center gap-xs">
                <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span>
                Reserve a Slot
            </a>
        </div>
    </div>

    <!-- Stats strip (4-metric grid) -->
    <div class="dashboard-stats" aria-label="Your parking stats">
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <span class="stat-card-label">Reward Points</span>
                <a href="<?= BASE_URL ?>/public/payment.php" style="font-size:11px; font-weight:700; color:var(--clr-secondary);">+ Top Up</a>
            </div>
            <span class="stat-card-value stat-card-value--cyan" style="color:var(--clr-secondary);"><?= ($user_points == (int)$user_points) ? (int)$user_points : number_format((float)$user_points, 2) ?> <span style="font-size:16px; font-weight:500;">pts</span></span>
            <span style="font-size:12px; color:var(--clr-text-muted); margin-top:2px;">
                Tier: <strong><?= htmlspecialchars($package_tier) ?></strong> (<?= floor($user_points / 10) ?> hrs available)
            </span>
        </div>
        <div class="stat-card">
            <span class="stat-card-label">Active Bookings</span>
            <span class="stat-card-value stat-card-value--violet"><?= $active_count ?></span>
            <span style="font-size:12px; color:var(--clr-text-muted); margin-top:2px;">Currently scheduled</span>
        </div>
        <div class="stat-card">
            <span class="stat-card-label">Completed Trips</span>
            <span class="stat-card-value stat-card-value--success"><?= $completed_count ?></span>
            <span style="font-size:12px; color:var(--clr-text-muted); margin-top:2px;">All-time parking bays</span>
        </div>
        <div class="stat-card">
            <span class="stat-card-label">Late Check-ins</span>
            <span class="stat-card-value <?= $is_locked ? 'stat-card-value--terra' : '' ?>">
                <?= (int) ($_SESSION['late_checkin_count'] ?? 0) ?> / 3
            </span>
            <span style="font-size:12px; color:var(--clr-text-muted); margin-top:2px;">3 late check-ins = 24h freeze</span>
        </div>
    </div>

    <!-- Booking history -->
    <section aria-labelledby="historyTitle">

        <h2 class="page-title mb-md" style="font-size:22px;" id="historyTitle">Recent Bookings</h2>

        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <span class="empty-state-icon material-symbols-outlined" aria-hidden="true" style="font-size:56px;">local_parking</span>
                <p class="empty-state-title">No bookings yet</p>
                <p class="empty-state-desc">Reserve your first campus parking spot to get started with your 100 reward points.</p>
                <a href="<?= BASE_URL ?>/public/book-slot.php" class="btn btn-primary">
                    <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span>
                    Book a Slot
                </a>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table" aria-label="Your recent bookings">
                    <thead>
                        <tr>
                            <th scope="col">Booking ID</th>
                            <th scope="col">Slot</th>
                            <th scope="col">Zone</th>
                            <th scope="col">Date</th>
                            <th scope="col">Duration & Cost</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b):
                            $duration = (int) ($b['duration_hours'] ?? 1);
                            $pts      = (int) ($b['points_cost'] ?? ($duration * 10));
                            // Determine which action button to show, if any.
                            $is_today_booking = ($b['booking_date'] === $today);
                            $show_checkin     = ($b['status'] === 'booked'      && $is_today_booking);
                            $show_checkout    = ($b['status'] === 'checked_in');

                            $slot_id          = (int) ($b['slot_id'] ?? 0);
                            $now_time         = date('H:i:s');
                            $is_before_start  = (!empty($b['start_time']) && $now_time < $b['start_time']);

                            // Build a time-range label if start_time/end_time are set
                            $time_range = '';
                            $dur_text   = $duration . ' hr' . ($duration > 1 ? 's' : '');
                            if (!empty($b['start_time']) && !empty($b['end_time'])) {
                                $s_ts = strtotime('2000-01-01 ' . $b['start_time']);
                                $e_ts = strtotime('2000-01-01 ' . $b['end_time']);
                                $s_fmt = (date('s', $s_ts) !== '00') ? 'g:i:s A' : 'g:i A';
                                $e_fmt = (date('s', $e_ts) !== '00') ? 'g:i:s A' : 'g:i A';
                                $time_range = date($s_fmt, $s_ts) . ' – ' . date($e_fmt, $e_ts);

                                $diff_sec = $e_ts - $s_ts;
                                if ($diff_sec > 0) {
                                    if ($diff_sec < 60) {
                                        $dur_text = "{$diff_sec}s";
                                    } elseif ($diff_sec < 3600) {
                                        $m = floor($diff_sec / 60);
                                        $s = $diff_sec % 60;
                                        $dur_text = $s > 0 ? "{$m}m {$s}s" : "{$m}m";
                                    }
                                }
                            }
                            // Slot is occupied only if it is a booking for TODAY and another driver's vehicle is currently checked in
                            $row_occ          = $occupied_slots[$slot_id] ?? null;
                            $is_slot_occupied = ($is_today_booking && $row_occ && ((int)$row_occ['user_id'] !== (int)$user['id']));
                            $row_can_report   = false;
                            $row_penalty      = 0.0;
                            $row_time_str     = '';
                            if ($is_slot_occupied && !$is_before_start && !empty($row_occ['end_time'])) {
                                $row_end_ts     = strtotime($row_occ['booking_date'] . ' ' . $row_occ['end_time']);
                                $row_sec_over   = time() - $row_end_ts;
                                $row_can_report = ($row_sec_over > 0);
                                if ($row_can_report) {
                                    $row_units    = max(1, (int) ceil($row_sec_over / 30));
                                    $row_penalty  = round($row_units * (20.0 / 120.0), 2);
                                    $row_time_str = ($row_sec_over < 60) ? "{$row_sec_over}s" : ceil($row_sec_over / 60) . "m";
                                }
                            }
                        ?>
                        <tr>
                            <td class="font-semi text-muted">
                                #CP-<?= str_pad((string)$b['id'], 4, '0', STR_PAD_LEFT) ?>
                            </td>
                            <td class="font-bold"><?= htmlspecialchars($b['slot_code']) ?></td>
                            <td><?= htmlspecialchars($b['zone']) ?></td>
                            <td><?= htmlspecialchars(date('M j, Y', strtotime($b['booking_date']))) ?></td>
                            <td>
                                <?php if ($time_range): ?>
                                <span style="font-size:11px; color:var(--clr-secondary); font-weight:600; display:block;"><?= htmlspecialchars($time_range) ?></span>
                                <?php endif; ?>
                                <strong><?= $dur_text ?></strong>
                                <span class="text-muted" style="font-size:12px;">(<?= $pts ?> pts)</span>
                            </td>
                            <td>
                                <?php
                                    $is_late = (int) ($b['is_late_checkin'] ?? 0);
                                    if ($is_late && $b['status'] === 'checked_in') {
                                        $status_label = 'Late Check-in';
                                    } elseif ($is_late && $b['status'] === 'completed') {
                                        $status_label = 'Completed (Late)';
                                    } else {
                                        $status_label = ucfirst(str_replace('_', ' ', $b['status']));
                                    }
                                ?>
                                <span class="badge <?= booking_badge_class($b['status'], $is_late) ?>">
                                    <?= htmlspecialchars($status_label) ?>
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center gap-xs flex-wrap">
                                    <?php if ($show_checkin): ?>
                                    <form method="POST" action="<?= BASE_URL ?>/includes/checkin.php" style="display:inline;">
                                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                        <?php if ($is_slot_occupied): ?>
                                        <button type="submit" class="btn btn-checkin" style="background:#EF4444; border-color:#EF4444;"
                                                title="Slot occupied by previous vehicle. Click to check slot status."
                                                aria-label="Slot occupied. Click to attempt check-in for booking #CP-<?= str_pad((string)$b['id'], 4, '0', STR_PAD_LEFT) ?>">
                                            <span class="material-symbols-outlined" style="font-size:15px;">warning</span>
                                            Check In (Occupied)
                                        </button>
                                        <?php elseif ($is_before_start): ?>
                                        <?php
                                            $s_start_ts = strtotime('2000-01-01 ' . $b['start_time']);
                                            $s_start_lbl = (date('s', $s_start_ts) !== '00') ? date('g:i:s A', $s_start_ts) : date('g:i A', $s_start_ts);
                                        ?>
                                        <button type="submit" class="btn btn-checkin" style="opacity:0.85;"
                                                title="Check-in opens at <?= $s_start_lbl ?>."
                                                aria-label="Check in for booking #CP-<?= str_pad((string)$b['id'], 4, '0', STR_PAD_LEFT) ?>">
                                            <span class="material-symbols-outlined" style="font-size:15px;">schedule</span>
                                            Check In (Opens <?= $s_start_lbl ?>)
                                        </button>
                                        <?php else: ?>
                                        <button type="submit" class="btn btn-checkin"
                                                aria-label="Check in for booking #CP-<?= str_pad((string)$b['id'], 4, '0', STR_PAD_LEFT) ?>">
                                            <span class="material-symbols-outlined" style="font-size:15px;">login</span>
                                            Check In
                                        </button>
                                        <?php endif; ?>
                                    </form>
                                    <?php endif; ?>

                                    <?php if ($b['status'] === 'booked' && $is_today_booking && $is_slot_occupied && !$is_before_start): ?>
                                    <?php $occ_bid = (int)($row_occ['id'] ?? 0); ?>
                                    <?php if ($occ_bid > 0 && $row_can_report): ?>
                                    <form method="POST" action="<?= BASE_URL ?>/includes/checkin.php" style="display:inline;">
                                        <input type="hidden" name="action"               value="report">
                                        <input type="hidden" name="blocked_booking_id"   value="<?= (int)$b['id'] ?>">
                                        <input type="hidden" name="occupying_booking_id" value="<?= $occ_bid ?>">
                                        <button type="submit" class="btn btn-report"
                                                title="Report vehicle overstaying by <?= $row_time_str ?> to earn +<?= $row_penalty ?> reward points"
                                                aria-label="Report overstaying vehicle in slot <?= htmlspecialchars($b['slot_code']) ?> and earn +<?= $row_penalty ?> pts">
                                            <span class="material-symbols-outlined" style="font-size:15px;">report</span>
                                            Report (+<?= $row_penalty ?> pts)
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($b['status'] === 'booked'): ?>
                                    <button type="button" class="btn btn-cancel-action"
                                            data-booking-id="<?= (int)$b['id'] ?>"
                                            data-booking-code="#CP-<?= str_pad((string)$b['id'], 4, '0', STR_PAD_LEFT) ?>"
                                            data-slot-code="<?= htmlspecialchars($b['slot_code']) ?>"
                                            data-zone="<?= htmlspecialchars($b['zone']) ?>"
                                            data-date="<?= htmlspecialchars(date('M j, Y', strtotime($b['booking_date']))) ?>"
                                            data-time="<?= htmlspecialchars($time_range) ?>"
                                            data-points="<?= $pts ?>"
                                            aria-label="Cancel booking #CP-<?= str_pad((string)$b['id'], 4, '0', STR_PAD_LEFT) ?>">
                                        <span class="material-symbols-outlined" style="font-size:15px;">cancel</span>
                                        Cancel
                                    </button>
                                    <?php endif; ?>

                                    <?php if ($show_checkout): ?>
                                    <?php if ($can_extend_next_slot && (int)$b['id'] === (int)($my_active['id'] ?? 0)): ?>
                                    <form method="POST" action="<?= BASE_URL ?>/includes/checkin.php" style="display:inline;">
                                        <input type="hidden" name="action"     value="extend_slot">
                                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                        <button type="submit" class="btn btn-outline" style="color:var(--clr-primary); border-color:var(--clr-primary); font-size:12px; padding:5px 10px;"
                                                title="Next slot is empty! Book it for 10 pts to avoid penalty.">
                                            <span class="material-symbols-outlined" style="font-size:15px;">more_time</span>
                                            Book Next (10 pts)
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <form method="POST" action="<?= BASE_URL ?>/includes/checkout.php" style="display:inline;">
                                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                        <button type="submit" class="btn btn-checkout"
                                                aria-label="Check out for booking #CP-<?= str_pad((string)$b['id'], 4, '0', STR_PAD_LEFT) ?>">
                                            <span class="material-symbols-outlined" style="font-size:15px;">logout</span>
                                            Check Out
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <?php if (!$show_checkin && !$show_checkout && $b['status'] !== 'booked'): ?>
                                    <span class="text-muted" style="font-size:12px;">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </section>

</div><!-- /max-w-7xl -->

<!-- Cancellation Confirmation Modal -->
<div id="cancelBookingModal" class="modal-overlay"
     role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle" aria-hidden="true">

    <div class="modal-panel w-full max-w-lg mx-auto p-md md:p-gutter rounded-2xl">

        <button id="cancelModalClose" class="modal-close" type="button" aria-label="Close modal">✕</button>

        <h2 class="modal-title mb-md flex items-center gap-xs" id="cancelModalTitle">
            <span class="material-symbols-outlined" style="font-size:24px; color:var(--clr-error);">warning</span>
            Cancel Reservation
        </h2>

        <!-- Reservation details summary -->
        <ul class="modal-detail-list mb-md p-md gap-sm" aria-label="Reservation details">
            <li class="modal-detail-item">
                <span class="modal-detail-label">Booking ID</span>
                <span class="modal-detail-value font-mono" id="cancelModalBookingCode">—</span>
            </li>
            <li class="modal-detail-item">
                <span class="modal-detail-label">Parking Spot</span>
                <span class="modal-detail-value font-bold" id="cancelModalSlotZone">—</span>
            </li>
            <li class="modal-detail-item">
                <span class="modal-detail-label">Date & Time</span>
                <span class="modal-detail-value" id="cancelModalDateTime">—</span>
            </li>
            <li class="modal-detail-item">
                <span class="modal-detail-label">Points Charged</span>
                <span class="modal-detail-value font-bold text-base" id="cancelModalPoints" style="color:var(--clr-secondary);">—</span>
            </li>
        </ul>

        <!-- Points Forfeiture Warning Notice -->
        <div class="alert alert-error mb-md flex items-start gap-sm" role="alert" style="border-left: 4px solid var(--clr-error); background: #FEF2F2;">
            <span class="alert-icon material-symbols-outlined text-error shrink-0" aria-hidden="true" style="font-size:22px; color:var(--clr-error);">error</span>
            <div class="flex-1 min-w-0" style="color:#991B1B;">
                <strong class="block text-sm mb-1 font-bold">Warning: Points Will NOT Be Returned!</strong>
                <p class="text-xs m-0 leading-normal">
                    Under campus parking policy, reward points used to reserve a parking spot are <strong>strictly non-refundable</strong>. 
                    If you confirm this cancellation, your reservation will be cancelled and the slot released, but your <span id="cancelModalPointsNotice" class="font-bold">points</span> will <strong>not</strong> be returned or refunded to your account.
                </p>
            </div>
        </div>

        <!-- Cancellation Form -->
        <form method="POST" action="<?= BASE_URL ?>/includes/cancel-booking.php" id="cancelBookingForm" class="w-full">
            <input type="hidden" id="cancelModalBookingId" name="booking_id" value="">

            <div class="modal-actions flex flex-col sm:flex-row gap-sm w-full">
                <button type="button" class="btn btn-outline w-full sm:flex-1 justify-center" id="cancelModalDismissBtn">
                    Keep Reservation
                </button>
                <button type="submit" class="btn btn-danger w-full sm:flex-1 justify-center" id="cancelModalConfirmBtn">
                    <span class="material-symbols-outlined" style="font-size:18px;">cancel</span>
                    Yes, Cancel Booking
                </button>
            </div>
        </form>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
