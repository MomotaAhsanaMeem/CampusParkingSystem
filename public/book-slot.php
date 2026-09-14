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

// Fixed operating hours: 9 contiguous 1-hour blocks, 08:00–17:00
$HOUR_BLOCKS = [
    ['start' => '08:00', 'end' => '09:00', 'label' => '8–9 AM'],
    ['start' => '09:00', 'end' => '10:00', 'label' => '9–10 AM'],
    ['start' => '10:00', 'end' => '11:00', 'label' => '10–11 AM'],
    ['start' => '11:00', 'end' => '12:00', 'label' => '11 AM–12 PM'],
    ['start' => '12:00', 'end' => '13:00', 'label' => '12–1 PM'],
    ['start' => '13:00', 'end' => '14:00', 'label' => '1–2 PM'],
    ['start' => '14:00', 'end' => '15:00', 'label' => '2–3 PM'],
    ['start' => '15:00', 'end' => '16:00', 'label' => '3–4 PM'],
    ['start' => '16:00', 'end' => '17:00', 'label' => '4–5 PM'],
];
$VALID_HOUR_MARKS = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00'];

$form_error = '';

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

    // Validate time-block selection
    if ($form_error === '') {
        if (!in_array($start_time, $VALID_HOUR_MARKS, true) ||
            !in_array($end_time,   $VALID_HOUR_MARKS, true) ||
            $start_time >= $end_time) {
            $form_error = 'Invalid time selection. Please pick at least one hour block within operating hours (8 AM – 5 PM).';
        }
    }

    // Compute duration and cost
    $duration_hours = 0;
    $points_cost    = 0;
    if ($form_error === '') {
        $duration_hours = (int) round(
            (strtotime('2000-01-01 ' . $end_time) - strtotime('2000-01-01 ' . $start_time)) / 3600
        );
        $points_cost = $duration_hours * 10;

        if ($user_points < $points_cost) {
            header('Location: ' . BASE_URL . '/public/payment.php?reason=insufficient_points&return_to=book-slot');
            exit;
        }
    }

    // Slot time-overlap check (replaces old all-day occupancy check)
    if ($form_error === '') {
        $stmt = $pdo->prepare(
            "SELECT id FROM bookings
              WHERE slot_id = ? AND booking_date = ? AND status IN ('booked','checked_in')
                AND start_time < ? AND end_time > ?"
        );
        $stmt->execute([$slot_id, $booking_date, $end_time, $start_time]);
        if ($stmt->fetch()) {
            $form_error = 'That time range overlaps with an existing booking on this slot. Please choose different hours.';
        }
    }

    // One-active-booking-per-user-per-day rule (unchanged)
    if ($form_error === '') {
        $stmt = $pdo->prepare(
            "SELECT id FROM bookings WHERE user_id = ? AND booking_date = ? AND status IN ('booked','checked_in')"
        );
        $stmt->execute([$user['id'], $booking_date]);
        if ($stmt->fetch()) {
            $form_error = 'You already have an active booking on that date.';
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

        $stmt = $pdo->prepare(
            'INSERT INTO bookings (user_id, slot_id, booking_date, duration_hours, points_cost, start_time, end_time, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$user['id'], $slot_id, $booking_date, $duration_hours, $points_cost, $start_time, $end_time, 'booked']);

        // Record point transaction
        try {
            $stmtTx = $pdo->prepare(
                "INSERT INTO point_transactions (user_id, type, points, description) VALUES (?, 'booking_deduction', ?, ?)"
            );
            $start_label = date('g A', strtotime('2000-01-01 ' . $start_time));
            $end_label   = date('g A', strtotime('2000-01-01 ' . $end_time));
            $stmtTx->execute([
                $user['id'],
                -$points_cost,
                "Slot {$valid_slot['slot_code']} ({$start_label}–{$end_label}, {$duration_hours} hr" . ($duration_hours > 1 ? 's' : '') . ')',
            ]);
        } catch (Throwable $ignore) {}

        refresh_user_points($pdo, $user['id']);

        $start_label = date('g A', strtotime('2000-01-01 ' . $start_time));
        $end_label   = date('g A', strtotime('2000-01-01 ' . $end_time));
        $_SESSION['flash'] = "Slot {$valid_slot['slot_code']} booked for "
            . date('M j, Y', strtotime($booking_date))
            . " ({$start_label} – {$end_label} · {$duration_hours} hr" . ($duration_hours > 1 ? 's' : '') . " · {$points_cost} pts)!";

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
        AND start_time IS NOT NULL AND end_time IS NOT NULL"
);
$stmtBookings->execute([$selected_date]);
$slot_blocked         = [];   // slot_id → [['start','end','mine'], ...]
$user_has_active_today = false;
foreach ($stmtBookings->fetchAll() as $row) {
    $start = substr($row['start_time'], 0, 5);
    $end   = substr($row['end_time'],   0, 5);
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
            <p class="page-subtitle">Pick a date and slot, then choose your hour blocks.</p>
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
                        <?= $user_points ?> <span class="text-sm font-semibold text-on-surface-variant">Points available</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-md flex-wrap">
                <div class="badge shrink-0" style="background:rgba(8,145,178,0.12); color:var(--clr-secondary); font-weight:700; font-size:13px; padding:6px 14px; border:1px solid var(--clr-border-violet); border-radius:9999px;">
                    🅿️ Rate: 10 pts / hour · 8 AM – 5 PM
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
                <span class="badge badge-occupied">● Fully Booked</span>
                <span class="badge badge-booked">● Your Booking</span>
            </div>
        </div>

        <?php foreach ($slots_by_zone as $zone => $zone_slots): ?>
        <div class="zone-group mb-lg w-full">
            <p class="zone-label mb-md"><?= htmlspecialchars($zone) ?></p>

            <div class="slot-grid-container w-full">
                <div class="slot-grid grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-gutter w-full" role="list" aria-label="Slots in <?= htmlspecialchars($zone) ?>">
                    <?php foreach ($zone_slots as $slot):
                        $ranges   = $slot_blocked[$slot['id']] ?? [];
                        $is_my_slot = !empty(array_filter($ranges, fn($r) => $r['mine']));

                        // Count how many of the 9 standard blocks are occupied by any booking
                        $occ_count = 0;
                        foreach ($HOUR_BLOCKS as $blk) {
                            foreach ($ranges as $r) {
                                if ($r['start'] < $blk['end'] && $r['end'] > $blk['start']) {
                                    $occ_count++;
                                    break;
                                }
                            }
                        }
                        $free_count     = 9 - $occ_count;
                        $fully_occupied = ($free_count === 0);

                        // JSON of booked ranges for the JS time-block picker
                        $blocked_json = htmlspecialchars(json_encode(
                            array_map(fn($r) => ['start' => $r['start'], 'end' => $r['end']], $ranges)
                        ), ENT_QUOTES);

                        if ($is_my_slot) {
                            $card_class   = 'slot-card--booked';
                            $status_badge = '<span class="badge badge-booked">Your Booking</span>';
                            $aria_label   = "Slot {$slot['slot_code']} — already booked by you";
                        } elseif ($fully_occupied) {
                            $card_class   = 'slot-card--occupied';
                            $status_badge = '<span class="badge badge-occupied">Fully Booked</span>';
                            $aria_label   = "Slot {$slot['slot_code']} — fully booked for this date";
                        } else {
                            $card_class   = 'slot-card--available';
                            $status_badge = '<span class="badge badge-available">' . $free_count . '/9 Free</span>';
                            $aria_label   = "Slot {$slot['slot_code']} — {$free_count} hour blocks available, click to book";
                        }

                        $is_available = !$fully_occupied && !$is_my_slot && !$user_has_active_today && !$is_locked;
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
                            <span class="slot-card-time text-[10px] text-on-surface-variant font-medium shrink-0">8 AM–5 PM</span>
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
     role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-hidden="true">

    <div class="modal-panel w-full max-w-lg mx-auto max-h-[90vh] overflow-y-auto p-md md:p-gutter rounded-2xl">

        <button id="modalClose" class="modal-close" type="button" aria-label="Close">✕</button>

        <h2 class="modal-title mb-md" id="modalTitle">
            <span class="material-symbols-outlined" style="font-size:22px;vertical-align:middle;margin-right:6px;color:var(--clr-primary);">local_parking</span>
            Confirm Booking
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

            <!-- Time-Block Selector -->
            <li class="modal-detail-item flex flex-col items-start gap-xs w-full">
                <span class="modal-detail-label font-bold text-on-surface">
                    Select Hours <span class="font-normal text-on-surface-variant text-xs">(10 pts / hr · pick contiguous blocks)</span>
                </span>
                <div id="timeBlockGrid" class="time-block-grid w-full my-xs" role="group" aria-label="Select hour blocks"></div>
                <p id="timeBlockSummary" class="text-xs text-on-surface-variant min-h-[18px] m-0"></p>
            </li>

            <!-- Points Cost Breakdown -->
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
                    Check in within 15 min of start · 3 late departures = 24-hr freeze
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
            <input type="hidden" id="inputDurationHours" name="duration_hours"  value="0">
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
