<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user        = current_user();
$is_locked   = is_booking_locked();
$user_points = refresh_user_points($pdo, $user['id']);

// Redirect to payment if points too low for even 1 hour
if ($user_points < 10) {
    header('Location: ' . BASE_URL . '/public/payment.php?reason=insufficient_points&return_to=book-slot');
    exit;
}

$form_error = '';
$today      = date('Y-m-d');

// ---------- POST — process booking ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {

    $slot_id      = (int) ($_POST['slot_id']       ?? 0);
    $booking_date = trim($_POST['booking_date']     ?? '');
    $start_time   = trim($_POST['start_time']       ?? '');
    $end_time     = trim($_POST['end_time']         ?? '');

    // Validate slot
    $valid_slot = false;
    if ($slot_id > 0) {
        $stmt = $pdo->prepare('SELECT id, slot_code, zone FROM parking_slots WHERE id = ? AND is_active = 1');
        $stmt->execute([$slot_id]);
        $valid_slot = $stmt->fetch();
    }
    if (!$valid_slot) {
        $form_error = 'Selected slot is not available. Please choose another.';
    }

    // Validate date
    $today = date('Y-m-d');
    if ($form_error === '' && ($booking_date < $today || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_date))) {
        $form_error = 'Please select today or a future date.';
    }

    // Validate time selection (supports HH:MM or HH:MM:SS)
    if ($form_error === '') {
        $time_regex = '/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/';
        if (!preg_match($time_regex, $start_time) || !preg_match($time_regex, $end_time)) {
            $form_error = 'Please enter valid start and end times in HH:MM:SS format.';
        } else {
            // Normalize to HH:MM:SS
            if (strlen($start_time) === 5) $start_time .= ':00';
            if (strlen($end_time) === 5)   $end_time   .= ':00';

            if ($start_time >= $end_time) {
                $form_error = 'End time must be strictly after start time.';
            }
        }
    }

    // Reject time ranges where end time is already in the past if booking for today
    if ($form_error === '' && $booking_date === $today) {
        $now_time = date('H:i:s');
        if ($end_time <= $now_time) {
            $form_error = 'You cannot book a time slot that has already ended.';
        }
    }

    // Compute duration in seconds and hours
    $dur_seconds    = 0;
    $duration_hours = 1;
    $points_cost    = 10;
    if ($form_error === '') {
        $dur_seconds    = strtotime('2000-01-01 ' . $end_time) - strtotime('2000-01-01 ' . $start_time);
        if ($dur_seconds <= 0) {
            $form_error = 'End time must be after start time.';
        } else {
            $duration_hours = max(1, (int) ceil($dur_seconds / 3600));
            $points_cost    = $duration_hours * 10;

            if ($user_points < $points_cost) {
                header('Location: ' . BASE_URL . '/public/payment.php?reason=insufficient_points&return_to=book-slot');
                exit;
            }
        }
    }

    // Slot time-overlap check: strictly prevent booking overlapping time on the same slot
    if ($form_error === '') {
        $stmt = $pdo->prepare(
            "SELECT id, start_time, end_time FROM bookings
              WHERE slot_id = ? AND booking_date = ? AND status IN ('booked','checked_in')
                AND start_time < ? AND end_time > ?"
        );
        $stmt->execute([$slot_id, $booking_date, $end_time, $start_time]);
        $overlap = $stmt->fetch();
        if ($overlap) {
            $overlap_start = date('g:i:s A', strtotime('2000-01-01 ' . $overlap['start_time']));
            $overlap_end   = date('g:i:s A', strtotime('2000-01-01 ' . $overlap['end_time']));
            $form_error = "This slot is already booked from {$overlap_start} to {$overlap_end}. No bookings can be done for the same time.";
        }
    }

    // One-active-booking-per-user-per-day rule:
    // Exception: If overstayer is checked-in on this exact slot and booking the contiguous next slot to extend and avoid penalty.
    $is_extension   = false;
    $ext_booking_id = 0;
    if ($form_error === '') {
        $stmt = $pdo->prepare(
            "SELECT id, slot_id, end_time, status, duration_hours, points_cost FROM bookings WHERE user_id = ? AND booking_date = ? AND status IN ('booked','checked_in')"
        );
        $stmt->execute([$user['id'], $booking_date]);
        $existing = $stmt->fetch();
        if ($existing) {
            if ($existing['status'] === 'checked_in' && (int)$existing['slot_id'] === $slot_id && $existing['end_time'] === $start_time) {
                $is_extension   = true;
                $ext_booking_id = (int) $existing['id'];
            } else {
                $form_error = 'You already have an active booking on that date.';
            }
        }
    }

    if ($form_error === '') {
        // Atomic points deduction
        $stmtDeduct = $pdo->prepare(
            'UPDATE users SET reward_points = reward_points - ? WHERE id = ? AND reward_points >= ?'
        );
        $stmtDeduct->execute([$points_cost, $user['id'], $points_cost]);

        if ($stmtDeduct->rowCount() === 0) {
            header('Location: ' . BASE_URL . '/public/payment.php?reason=insufficient_points&return_to=book-slot');
            exit;
        }

        if ($is_extension) {
            $stmtExt = $pdo->prepare(
                'UPDATE bookings SET end_time = ?, duration_hours = duration_hours + ?, points_cost = points_cost + ? WHERE id = ?'
            );
            $stmtExt->execute([$end_time, $duration_hours, $points_cost, $ext_booking_id]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO bookings (user_id, slot_id, booking_date, duration_hours, points_cost, start_time, end_time, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$user['id'], $slot_id, $booking_date, $duration_hours, $points_cost, $start_time, $end_time, 'booked']);
        }

        // Duration text for notifications
        $dur_text = '';
        if ($dur_seconds < 60) {
            $dur_text = "{$dur_seconds}s";
        } elseif ($dur_seconds < 3600) {
            $mins = floor($dur_seconds / 60);
            $secs = $dur_seconds % 60;
            $dur_text = $secs > 0 ? "{$mins}m {$secs}s" : "{$mins}m";
        } else {
            $dur_text = "{$duration_hours} hr" . ($duration_hours > 1 ? 's' : '');
        }

        $start_label = date('g:i:s A', strtotime('2000-01-01 ' . $start_time));
        $end_label   = date('g:i:s A', strtotime('2000-01-01 ' . $end_time));

        // Record point transaction
        try {
            $stmtTx = $pdo->prepare(
                "INSERT INTO point_transactions (user_id, type, points, description) VALUES (?, 'booking_deduction', ?, ?)"
            );
            $desc_suffix = $is_extension ? ' (extended stay, penalty avoided)' : '';
            $stmtTx->execute([
                $user['id'],
                -$points_cost,
                "Slot {$valid_slot['slot_code']} ({$start_label}–{$end_label}, {$dur_text}){$desc_suffix}",
            ]);
        } catch (Throwable $ignore) {}

        refresh_user_points($pdo, $user['id']);

        if ($is_extension) {
            $_SESSION['flash'] = "Slot {$valid_slot['slot_code']} extended to {$end_label} (+{$dur_text} · {$points_cost} pts)! Overstay penalty avoided.";
        } else {
            $_SESSION['flash'] = "Slot {$valid_slot['slot_code']} booked for "
                . date('M j, Y', strtotime($booking_date))
                . " ({$start_label} – {$end_label} · {$dur_text} · {$points_cost} pts)!";
        }

        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    }
}

// ---------- GET — load slots with per-slot time-block availability ----------
$selected_date = $_GET['date'] ?? ($_POST['booking_date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date) || $selected_date < date('Y-m-d')) {
    $selected_date = date('Y-m-d');
}

// Fetch all active bookings for the selected date across all slots
$stmtBookings = $pdo->prepare(
    "SELECT slot_id, start_time, end_time, user_id
       FROM bookings
      WHERE booking_date = ? AND status IN ('booked','checked_in')
        AND start_time IS NOT NULL AND end_time IS NOT NULL
      ORDER BY start_time ASC"
);
$stmtBookings->execute([$selected_date]);
$slot_blocked         = [];   // slot_id → [['start','end','mine'], ...]
$user_has_active_today = false;
foreach ($stmtBookings->fetchAll() as $row) {
    $start = substr($row['start_time'], 0, 8);
    $end   = substr($row['end_time'],   0, 8);
    $mine  = ((int)$row['user_id'] === (int)$user['id']);
    $slot_blocked[$row['slot_id']][] = ['start' => $start, 'end' => $end, 'mine' => $mine];
    if ($mine) $user_has_active_today = true;
}

// Fetch all active slots
$stmtSlots = $pdo->prepare(
    'SELECT id, slot_code, zone FROM parking_slots WHERE is_active = 1 ORDER BY zone, slot_code'
);
$stmtSlots->execute();
$slots = $stmtSlots->fetchAll();

$slots_by_zone = [];
foreach ($slots as $slot) {
    $slots_by_zone[$slot['zone']][] = $slot;
}

$page_title = 'Book a Slot';
$body_page  = 'book-slot';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- pt-24 clears the fixed navbar -->
<div class="pt-24 pb-16 px-margin-mobile md:px-margin-desktop w-full max-w-7xl mx-auto">

    <!-- Booking-lock banner -->
    <?php if ($is_locked): ?>
    <div class="alert alert-warning mb-gutter w-full" role="alert">
        <span class="alert-icon material-symbols-outlined shrink-0" aria-hidden="true">lock</span>
        <div>
            <strong>Booking suspended.</strong> You have 3 late departures.
            Reservations disabled until <strong><?= htmlspecialchars($_SESSION['booking_locked_until'] ?? '—') ?></strong>.
        </div>
    </div>
    <?php endif; ?>

    <!-- Multiple-booking warning banner -->
    <?php if ($user_has_active_today && !$is_locked): ?>
    <div id="activeBookingWarning" class="alert alert-error bg-error-container text-on-error-container border border-error/30 rounded-lg p-md mb-gutter flex items-start gap-sm w-full" role="alert" tabindex="-1" aria-live="polite">
        <span class="alert-icon material-symbols-outlined text-error shrink-0" aria-hidden="true">error</span>
        <div class="flex-1 min-w-0">
            <strong class="block text-sm mb-1 font-bold text-on-error-container">Active Booking Notice</strong>
            <p class="text-sm m-0 text-on-error-container leading-normal">
                You already have an active booking today and must cancel or check out first before booking again.
            </p>
            <div class="mt-2 flex items-center gap-sm flex-wrap">
                <a href="<?= BASE_URL ?>/public/dashboard.php" class="btn btn-outline inline-flex items-center gap-xs py-1 px-3" style="font-size:12px; font-weight:600;">
                    <span class="material-symbols-outlined" style="font-size:16px;">dashboard</span>
                    Go to Dashboard to Check Out
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Error banner -->
    <?php if ($form_error !== ''): ?>
    <div class="alert alert-error bg-error-container text-on-error-container border border-error/30 rounded-lg p-md mb-gutter flex items-start gap-sm w-full" role="alert">
        <span class="alert-icon material-symbols-outlined text-error shrink-0" aria-hidden="true">error</span>
        <div class="flex-1 min-w-0">
            <?= htmlspecialchars($form_error) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="flex items-center justify-between flex-wrap gap-md mb-lg w-full">
        <div class="page-header mb-0">
            <h1 class="page-title">Reserve a Parking Slot</h1>
            <p class="page-subtitle">Pick a slot to view existing booked times, then select your start &amp; end time (minutes &amp; seconds supported).</p>
        </div>
        <a href="<?= BASE_URL ?>/public/dashboard.php" class="btn btn-outline flex items-center gap-sm shrink-0">
            <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span>
            Dashboard
        </a>
    </div>

    <!-- Points & Hourly Rate Strip -->
    <div class="book-slot-rate-strip mb-lg w-full">
        <div class="flex items-center justify-between flex-wrap gap-md w-full">
            <div class="flex items-center gap-sm min-w-0">
                <span class="material-symbols-outlined shrink-0" style="color:var(--clr-secondary); font-size:28px;">toll</span>
                <div class="min-w-0">
                    <span class="block text-xs text-on-surface-variant uppercase font-bold tracking-wider">Your Points Balance</span>
                    <div class="text-xl font-extrabold text-on-surface">
                        <?= ($user_points == (int)$user_points) ? (int)$user_points : number_format((float)$user_points, 2) ?> <span class="text-sm font-semibold text-on-surface-variant">Points available</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-md flex-wrap">
                <div class="badge shrink-0" style="background:rgba(8,145,178,0.12); color:var(--clr-secondary); font-weight:700; font-size:13px; padding:6px 14px; border:1px solid var(--clr-border-violet); border-radius:9999px;">
                    🅿️ Rate: 10 pts / hr · Flexible minutes &amp; seconds
                </div>
                <a href="<?= BASE_URL ?>/public/payment.php?return_to=book-slot" class="btn btn-outline flex items-center gap-xs shrink-0" style="padding:8px 14px; font-size:13px; font-weight:600;">
                    <span class="material-symbols-outlined" style="font-size:16px;">add_card</span>
                    Recharge Points
                </a>
            </div>
        </div>
    </div>

    <!-- Date filter controls -->
    <form id="dateFilterForm" method="GET" action="" class="w-full mb-lg">
        <div class="book-slot-controls w-full flex flex-col sm:flex-row sm:items-end gap-md">
            <div class="form-group flex-1 w-full min-w-0">
                <label for="bookingDate" class="form-label mb-xs block">Select Date</label>
                <input type="date" id="bookingDate" name="date"
                       class="form-input w-full"
                       value="<?= htmlspecialchars($selected_date) ?>"
                       min="<?= date('Y-m-d') ?>"
                       aria-label="Select booking date">
            </div>
            <button type="submit" class="btn btn-outline flex items-center justify-center gap-sm w-full sm:w-auto shrink-0">
                <span class="material-symbols-outlined" style="font-size:18px;">search</span>
                Check Availability
            </button>
        </div>
    </form>

    <!-- Slot grid -->
    <?php if (empty($slots)): ?>
        <div class="empty-state w-full mb-lg">
            <span class="empty-state-icon material-symbols-outlined" aria-hidden="true" style="font-size:56px;">local_parking</span>
            <p class="empty-state-title">No active slots found</p>
            <p class="empty-state-desc">All slots may be under maintenance. Check back later.</p>
        </div>
    <?php else: ?>

        <div class="flex items-center justify-between flex-wrap gap-sm mb-md w-full">
            <p class="text-lg font-bold text-on-surface m-0">
                Slots for <?= htmlspecialchars(date('l, F j, Y', strtotime($selected_date))) ?>
            </p>
            <!-- Legend -->
            <div class="flex gap-sm flex-wrap items-center" aria-label="Slot status legend">
                <span class="badge badge-available">● Available</span>
                <span class="badge" style="background:rgba(245,158,11,0.15);color:#D97706;border:1px solid rgba(245,158,11,0.3);">● Partially Booked</span>
                <span class="badge badge-booked">● Your Booking</span>
            </div>
        </div>

        <?php foreach ($slots_by_zone as $zone => $zone_slots): ?>
        <div class="zone-group mb-lg w-full">
            <p class="zone-label mb-md"><?= htmlspecialchars($zone) ?></p>

            <div class="slot-grid-container w-full">
                <div class="slot-grid grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-gutter w-full" role="list" aria-label="Slots in <?= htmlspecialchars($zone) ?>">
                    <?php foreach ($zone_slots as $slot):
                        $ranges     = $slot_blocked[$slot['id']] ?? [];
                        $is_my_slot = !empty(array_filter($ranges, fn($r) => $r['mine']));
                        $book_count = count($ranges);

                        // JSON of booked ranges for modal display and overlap checking
                        $blocked_json = htmlspecialchars(json_encode(
                            array_map(fn($r) => ['start' => $r['start'], 'end' => $r['end'], 'mine' => $r['mine']], $ranges)
                        ), ENT_QUOTES);

                        if ($is_my_slot) {
                            $card_class   = 'slot-card--booked';
                            $status_badge = '<span class="badge badge-booked">Your Booking</span>';
                            $aria_label   = "Slot {$slot['slot_code']} — already booked by you";
                        } elseif ($book_count > 0) {
                            $card_class   = 'slot-card--available';
                            $status_badge = '<span class="badge" style="background:rgba(245,158,11,0.15);color:#D97706;border:1px solid rgba(245,158,11,0.3);font-size:11px;font-weight:700;">' . $book_count . ' Booking' . ($book_count > 1 ? 's' : '') . '</span>';
                            $aria_label   = "Slot {$slot['slot_code']} — {$book_count} active booking(s), click to view booked slots and choose time";
                        } else {
                            $card_class   = 'slot-card--available';
                            $status_badge = '<span class="badge badge-available">Open (All Day)</span>';
                            $aria_label   = "Slot {$slot['slot_code']} — available, click to book";
                        }

                        $is_available = !$is_my_slot && !$user_has_active_today && !$is_locked;
                    ?>
                    <div
                        class="slot-card <?= $card_class ?> w-full min-w-0"
                        role="listitem"
                        aria-label="<?= htmlspecialchars($aria_label) ?>"
                        <?php if ($is_available): ?>
                            data-slot-id="<?= (int)$slot['id'] ?>"
                            data-slot-code="<?= htmlspecialchars($slot['slot_code']) ?>"
                            data-zone="<?= htmlspecialchars($slot['zone']) ?>"
                            data-blocked="<?= $blocked_json ?>"
                        <?php elseif ($user_has_active_today && !$is_locked && !$is_my_slot): ?>
                            data-has-active-booking="true"
                            tabindex="0"
                            role="button"
                        <?php endif; ?>
                    >
                        <div class="flex items-center justify-between gap-xs min-w-0">
                            <span class="slot-card-code truncate"><?= htmlspecialchars($slot['slot_code']) ?></span>
                            <?php if ($is_available): ?>
                            <span class="material-symbols-outlined shrink-0" style="font-size:20px;color:var(--clr-success);">add_circle</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center justify-between gap-xs min-w-0">
                            <span class="slot-card-zone truncate"><?= htmlspecialchars($slot['zone']) ?></span>
                            <span class="slot-card-time text-[10px] text-on-surface-variant font-medium shrink-0">Custom Time</span>
                        </div>
                        <div class="slot-card-status mt-auto pt-xs min-w-0"><?= $status_badge ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div><!-- /max-w-7xl -->

<!-- Booking confirmation modal -->
<div id="bookingModal" class="modal-overlay"
     data-user-points="<?= $user_points ?>"
     data-server-today="<?= date('Y-m-d') ?>"
     data-server-time="<?= date('H:i:s') ?>"
     role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-hidden="true">

    <div class="modal-panel w-full max-w-lg mx-auto max-h-[90vh] overflow-y-auto p-md md:p-gutter rounded-2xl">

        <button id="modalClose" class="modal-close" type="button" aria-label="Close">✕</button>

        <h2 class="modal-title mb-md" id="modalTitle">
            <span class="material-symbols-outlined" style="font-size:22px;vertical-align:middle;margin-right:6px;color:var(--clr-primary);">local_parking</span>
            Select Booking Time
        </h2>

        <ul class="modal-detail-list mb-md p-md gap-sm" aria-label="Booking details">
            <li class="modal-detail-item">
                <span class="modal-detail-label">Slot</span>
                <span class="modal-detail-value font-bold" id="modalSlotCode">—</span>
            </li>
            <li class="modal-detail-item">
                <span class="modal-detail-label">Zone</span>
                <span class="modal-detail-value" id="modalZone">—</span>
            </li>
            <li class="modal-detail-item">
                <span class="modal-detail-label">Date</span>
                <span class="modal-detail-value" id="modalDate">—</span>
            </li>

            <!-- Existing Bookings on This Slot -->
            <li class="modal-detail-item flex flex-col items-start gap-xs w-full">
                <div class="flex items-center justify-between w-full">
                    <span class="modal-detail-label font-bold text-on-surface">Booked Times on this Slot</span>
                    <span id="existingBookingsCount" class="text-xs text-on-surface-variant font-medium"></span>
                </div>
                <div id="existingBookingsContainer" class="existing-bookings-box w-full my-xs">
                    <!-- Populated by JS -->
                </div>
            </li>

            <!-- Time Selection (Start & End with minutes & seconds) -->
            <li class="modal-detail-item flex flex-col items-start gap-xs w-full">
                <span class="modal-detail-label font-bold text-on-surface">
                    Set Start &amp; End Time <span class="font-normal text-on-surface-variant text-xs">(HH:MM:SS format)</span>
                </span>

                <div class="time-pickers-grid w-full grid grid-cols-1 sm:grid-cols-2 gap-sm my-xs">
                    <div class="form-group mb-0">
                        <label for="pickerStartTime" class="text-xs font-semibold text-on-surface-variant mb-1 block">
                            <span class="material-symbols-outlined" style="font-size:14px;vertical-align:text-bottom;">play_circle</span>
                            Start Time
                        </label>
                        <input type="time" step="1" id="pickerStartTime" class="form-input w-full font-mono text-sm" placeholder="HH:MM:SS" required>
                    </div>
                    <div class="form-group mb-0">
                        <label for="pickerEndTime" class="text-xs font-semibold text-on-surface-variant mb-1 block">
                            <span class="material-symbols-outlined" style="font-size:14px;vertical-align:text-bottom;">stop_circle</span>
                            End Time
                        </label>
                        <input type="time" step="1" id="pickerEndTime" class="form-input w-full font-mono text-sm" placeholder="HH:MM:SS" required>
                    </div>
                </div>

                <!-- Quick presets for testing seconds/minutes -->
                <div class="quick-preset-bar w-full flex items-center gap-xs flex-wrap mt-1">
                    <span class="text-xs text-on-surface-variant font-medium mr-1">Quick Test:</span>
                    <button type="button" class="btn-preset" data-preset="now" title="Set start time to now">Now</button>
                    <button type="button" class="btn-preset" data-preset="30s" title="Add 30 seconds">+30s</button>
                    <button type="button" class="btn-preset" data-preset="1m" title="Add 1 minute">+1 min</button>
                    <button type="button" class="btn-preset" data-preset="5m" title="Add 5 minutes">+5 min</button>
                    <button type="button" class="btn-preset" data-preset="1h" title="Add 1 hour">+1 hr</button>
                </div>

                <!-- Conflict warning alert -->
                <div id="modalConflictAlert" class="alert alert-error bg-error-container text-on-error-container border border-error/30 rounded-lg p-xs px-sm mt-xs w-full flex items-center gap-xs" style="display:none;" role="alert">
                    <span class="material-symbols-outlined text-error shrink-0" style="font-size:18px;">error</span>
                    <span id="modalConflictMsg" class="text-xs font-semibold leading-tight"></span>
                </div>

                <p id="timeBlockSummary" class="text-xs text-on-surface-variant min-h-[18px] mt-xs mb-0"></p>
            </li>

            <!-- Duration and Points Cost Breakdown -->
            <li class="modal-detail-item">
                <span class="modal-detail-label">Duration</span>
                <span class="modal-detail-value font-bold" id="modalDurationText">—</span>
            </li>
            <li class="modal-detail-item">
                <span class="modal-detail-label">Total Points Cost</span>
                <span class="modal-detail-value font-extrabold text-base" id="modalPointCost" style="color:var(--clr-secondary);">
                    —
                </span>
            </li>
            <li class="modal-detail-item">
                <span class="modal-detail-label">Your Points Balance</span>
                <span class="modal-detail-value font-semibold" id="modalUserBalance">
                    <?= $user_points ?> points
                </span>
            </li>
            <li class="modal-detail-item">
                <span class="modal-detail-label">Remaining Balance</span>
                <span class="modal-detail-value font-bold" id="modalRemainingBalance" style="color:var(--clr-success);">
                    — points
                </span>
            </li>

            <div class="modal-divider my-sm"></div>
            <li class="modal-detail-item">
                <span class="modal-detail-label">Policy</span>
                <span class="modal-detail-value text-muted text-xs">
                    10 pts / hr (minimum 10 pts) · Check-in opens at start time
                </span>
            </li>
        </ul>

        <!-- Points Insufficient Warning in Modal -->
        <div id="modalPointsWarning" class="alert alert-error bg-error-container text-on-error-container border border-error/30 rounded-lg p-md mb-md flex items-start gap-sm" style="display:none;" role="alert">
            <span class="alert-icon material-symbols-outlined text-error shrink-0" aria-hidden="true">warning</span>
            <div class="flex-1 min-w-0">
                <strong>Insufficient points for this duration!</strong>
                <p class="text-xs mt-1 mb-0">
                    You need <span id="modalWarningCost">10</span> points, but you have <?= $user_points ?> points.
                </p>
                <a href="<?= BASE_URL ?>/public/payment.php?reason=insufficient_points&return_to=book-slot" class="btn btn-primary mt-2 inline-flex items-center gap-xs py-1 px-3 text-xs font-semibold">
                    Top Up Points
                </a>
            </div>
        </div>

        <form method="POST" action="" id="bookingForm" class="w-full">
            <input type="hidden" id="inputSlotId"        name="slot_id"        value="">
            <input type="hidden" id="inputDurationHours" name="duration_hours"  value="1">
            <input type="hidden" id="inputStartTime"     name="start_time"      value="">
            <input type="hidden" id="inputEndTime"       name="end_time"        value="">
            <input type="hidden"                         name="booking_date"    value="<?= htmlspecialchars($selected_date) ?>">

            <div class="modal-actions mt-gutter flex flex-col sm:flex-row gap-sm w-full">
                <button type="button" class="btn btn-outline w-full sm:flex-1 justify-center" id="modalCancelBtn"
                        onclick="document.getElementById('bookingModal').classList.remove('is-open');document.getElementById('bookingModal').setAttribute('aria-hidden','true');">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary w-full sm:flex-1 justify-center" id="modalSubmitBtn" disabled style="opacity:0.5;cursor:not-allowed;">
                    Confirm Booking
                    <span class="material-symbols-outlined" style="font-size:18px;">check_circle</span>
                </button>
            </div>
        </form>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
