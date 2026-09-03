<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user      = current_user();
$is_locked = is_booking_locked();

$form_error = '';

// ---------- POST — process booking ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {

    $slot_id      = (int) ($_POST['slot_id']   ?? 0);
    $booking_date = trim($_POST['booking_date'] ?? '');

    $valid_slot = false;
    if ($slot_id > 0) {
        $stmt = $pdo->prepare('SELECT id, slot_code, zone FROM parking_slots WHERE id = ? AND is_active = 1');
        $stmt->execute([$slot_id]);
        $valid_slot = $stmt->fetch();
    }

    if (!$valid_slot) {
        $form_error = 'Selected slot is not available. Please choose another.';
    }

    $today = date('Y-m-d');
    if ($form_error === '' && ($booking_date < $today || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_date))) {
        $form_error = 'Please select today or a future date.';
    }

    if ($form_error === '') {
        $stmt = $pdo->prepare(
            "SELECT id FROM bookings WHERE slot_id = ? AND booking_date = ? AND status IN ('booked','checked_in')"
        );
        $stmt->execute([$slot_id, $booking_date]);
        if ($stmt->fetch()) {
            $form_error = 'That slot is already reserved for the selected date. Please pick another.';
        }
    }

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
        $stmt = $pdo->prepare('INSERT INTO bookings (user_id, slot_id, booking_date, status) VALUES (?, ?, ?, ?)');
        $stmt->execute([$user['id'], $slot_id, $booking_date, 'booked']);

        $_SESSION['flash'] = "Slot {$valid_slot['slot_code']} booked for "
            . date('M j, Y', strtotime($booking_date)) . '!';

        header('Location: /parking-system/public/dashboard.php');
        exit;
    }
}

// ---------- GET — load slots with availability ----------
$selected_date = $_GET['date'] ?? ($_POST['booking_date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date) || $selected_date < date('Y-m-d')) {
    $selected_date = date('Y-m-d');
}

$stmt = $pdo->prepare(
    "SELECT s.id, s.slot_code, s.zone,
            CASE WHEN b.id  IS NOT NULL THEN 1 ELSE 0 END AS is_occupied,
            CASE WHEN ub.id IS NOT NULL THEN 1 ELSE 0 END AS booked_by_me
       FROM parking_slots s
       LEFT JOIN bookings b  ON b.slot_id  = s.id AND b.booking_date  = ? AND b.status  IN ('booked','checked_in')
       LEFT JOIN bookings ub ON ub.slot_id = s.id AND ub.booking_date = ? AND ub.user_id = ? AND ub.status IN ('booked','checked_in')
      WHERE s.is_active = 1
      ORDER BY s.zone, s.slot_code"
);
$stmt->execute([$selected_date, $selected_date, $user['id']]);
$slots = $stmt->fetchAll();

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
    <div class="alert alert-warning" role="alert">
        <span class="alert-icon material-symbols-outlined" aria-hidden="true">lock</span>
        <div>
            <strong>Booking suspended.</strong> You have 3 late departures.
            Reservations disabled until <strong><?= htmlspecialchars($_SESSION['booking_locked_until'] ?? '—') ?></strong>.
        </div>
    </div>
    <?php endif; ?>

    <!-- Error banner -->
    <?php if ($form_error !== ''): ?>
    <div class="alert alert-error" role="alert">
        <span class="alert-icon material-symbols-outlined" aria-hidden="true">error</span>
        <?= htmlspecialchars($form_error) ?>
    </div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="flex items-center justify-between flex-wrap gap-md mb-lg">
        <div class="page-header" style="margin-bottom:0;">
            <h1 class="page-title">Reserve a Parking Slot</h1>
            <p class="page-subtitle">Select a date, then click an available slot to confirm.</p>
        </div>
        <a href="/parking-system/public/dashboard.php" class="btn btn-outline flex items-center gap-sm">
            <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span>
            Dashboard
        </a>
    </div>

    <!-- Date filter controls -->
    <form id="dateFilterForm" method="GET" action="">
        <div class="book-slot-controls">
            <div class="form-group">
                <label for="bookingDate" class="form-label">Select Date</label>
                <input type="date" id="bookingDate" name="date"
                       class="form-input"
                       value="<?= htmlspecialchars($selected_date) ?>"
                       min="<?= date('Y-m-d') ?>"
                       aria-label="Select booking date">
            </div>
            <button type="submit" class="btn btn-outline flex items-center gap-sm">
                <span class="material-symbols-outlined" style="font-size:18px;">search</span>
                Check Availability
            </button>
        </div>
    </form>

    <!-- Slot grid -->
    <?php if (empty($slots)): ?>
        <div class="empty-state">
            <span class="empty-state-icon material-symbols-outlined" aria-hidden="true" style="font-size:56px;">local_parking</span>
            <p class="empty-state-title">No active slots found</p>
            <p class="empty-state-desc">All slots may be under maintenance. Check back later.</p>
        </div>
    <?php else: ?>

        <div class="flex items-center justify-between flex-wrap gap-sm mb-md">
            <p style="font-size:18px; font-weight:700; color:var(--clr-text);">
                Slots for <?= htmlspecialchars(date('l, F j, Y', strtotime($selected_date))) ?>
            </p>
            <!-- Legend -->
            <div class="flex gap-sm flex-wrap" aria-label="Slot status legend">
                <span class="badge badge-available">● Available</span>
                <span class="badge badge-occupied">● Occupied</span>
                <span class="badge badge-booked">● Your Booking</span>
            </div>
        </div>

        <?php foreach ($slots_by_zone as $zone => $zone_slots): ?>
        <div class="zone-group mb-lg">
            <p class="zone-label"><?= htmlspecialchars($zone) ?></p>

            <div class="slot-grid" role="list" aria-label="Slots in <?= htmlspecialchars($zone) ?>">
                <?php foreach ($zone_slots as $slot):
                    if ($slot['booked_by_me']) {
                        $card_class   = 'slot-card--booked';
                        $status_badge = '<span class="badge badge-booked">Your Booking</span>';
                        $aria_label   = "Slot {$slot['slot_code']} — already booked by you";
                    } elseif ($slot['is_occupied']) {
                        $card_class   = 'slot-card--occupied';
                        $status_badge = '<span class="badge badge-occupied">Occupied</span>';
                        $aria_label   = "Slot {$slot['slot_code']} — occupied";
                    } else {
                        $card_class   = 'slot-card--available';
                        $status_badge = '<span class="badge badge-available">Available</span>';
                        $aria_label   = "Slot {$slot['slot_code']} — available, click to book";
                    }
                    $is_available = !$slot['is_occupied'] && !$slot['booked_by_me'] && !$is_locked;
                ?>
                <div
                    class="slot-card <?= $card_class ?>"
                    role="listitem"
                    aria-label="<?= htmlspecialchars($aria_label) ?>"
                    <?php if ($is_available): ?>
                        data-slot-id="<?= (int)$slot['id'] ?>"
                        data-slot-code="<?= htmlspecialchars($slot['slot_code']) ?>"
                        data-zone="<?= htmlspecialchars($slot['zone']) ?>"
                    <?php endif; ?>
                >
                    <div class="flex items-center justify-between">
                        <span class="slot-card-code"><?= htmlspecialchars($slot['slot_code']) ?></span>
                        <?php if ($is_available): ?>
                        <span class="material-symbols-outlined" style="font-size:20px;color:var(--clr-success);">add_circle</span>
                        <?php endif; ?>
                    </div>
                    <span class="slot-card-zone"><?= htmlspecialchars($slot['zone']) ?></span>
                    <div class="slot-card-status"><?= $status_badge ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div><!-- /max-w-7xl -->

<!-- Booking confirmation modal -->
<div id="bookingModal" class="modal-overlay"
     role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-hidden="true">

    <div class="modal-panel">

        <button id="modalClose" class="modal-close" type="button" aria-label="Close">✕</button>

        <h2 class="modal-title" id="modalTitle">
            <span class="material-symbols-outlined" style="font-size:22px;vertical-align:middle;margin-right:6px;color:var(--clr-primary);">local_parking</span>
            Confirm Booking
        </h2>

        <ul class="modal-detail-list" aria-label="Booking details">
            <li class="modal-detail-item">
                <span class="modal-detail-label">Slot</span>
                <span class="modal-detail-value" id="modalSlotCode">—</span>
            </li>
            <li class="modal-detail-item">
                <span class="modal-detail-label">Zone</span>
                <span class="modal-detail-value" id="modalZone">—</span>
            </li>
            <li class="modal-detail-item">
                <span class="modal-detail-label">Date</span>
                <span class="modal-detail-value" id="modalDate">—</span>
            </li>
            <div class="modal-divider"></div>
            <li class="modal-detail-item">
                <span class="modal-detail-label">Policy</span>
                <span class="modal-detail-value text-muted" style="font-size:12px;">
                    3 late departures = 24-hr booking freeze
                </span>
            </li>
        </ul>

        <div class="alert alert-warning" style="margin-bottom:var(--sp-md);">
            <span class="alert-icon material-symbols-outlined" aria-hidden="true">info</span>
            <span style="font-size:13px;">Check in within 15 minutes of your reservation time.</span>
        </div>

        <form method="POST" action="">
            <input type="hidden" id="inputSlotId"   name="slot_id"      value="">
            <input type="hidden" id="inputSlotCode" name="slot_code_ref" value="">
            <input type="hidden" name="booking_date" value="<?= htmlspecialchars($selected_date) ?>">

            <div class="modal-actions">
                <button type="button" class="btn btn-outline"
                        onclick="document.getElementById('bookingModal').classList.remove('is-open');document.getElementById('bookingModal').setAttribute('aria-hidden','true');">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    Confirm Booking
                    <span class="material-symbols-outlined" style="font-size:18px;">check_circle</span>
                </button>
            </div>
        </form>

    </div>
</div>

<?php
// TODO (later update): check-in / check-out actions from this page.
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
