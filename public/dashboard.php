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
    'SELECT b.id, b.booking_date, b.duration_hours, b.points_cost, b.status,
            b.check_in_time, b.check_out_time, b.start_time, b.end_time,
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

// Sweep 1: silently cancel any 'booked' bookings from past dates.
// These are ghost rows — the date passed without a check-in. No late-strike
// is applied here because the penalty for today's no-shows is handled below.
$pastStmt = $pdo->prepare(
    "UPDATE bookings SET status = 'cancelled'
      WHERE user_id = ? AND status = 'booked' AND booking_date < CURDATE()"
);
$pastStmt->execute([$user['id']]);
$past_cancelled = $pastStmt->rowCount();

// Sweep 2: cancel today's 'booked' slots whose 15-minute check-in window has expired,
// and apply the late-departure penalty for each no-show.
// Uses start_time for time-block bookings; falls back to created_at for legacy rows.
$expStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM bookings
      WHERE user_id = ? AND status = 'booked' AND booking_date = CURDATE()
        AND (
              (start_time IS NOT NULL AND CONCAT(booking_date, ' ', start_time) <= NOW() - INTERVAL 15 MINUTE)
           OR (start_time IS NULL     AND created_at <= NOW() - INTERVAL 15 MINUTE)
            )"
);
$expStmt->execute([$user['id']]);
$expired_count = (int) $expStmt->fetchColumn();

if ($expired_count > 0) {
    // Cancel the expired bookings.
    $cancelStmt = $pdo->prepare(
        "UPDATE bookings SET status = 'cancelled'
          WHERE user_id = ? AND status = 'booked' AND booking_date = CURDATE()
            AND (
                  (start_time IS NOT NULL AND CONCAT(booking_date, ' ', start_time) <= NOW() - INTERVAL 15 MINUTE)
               OR (start_time IS NULL     AND created_at <= NOW() - INTERVAL 15 MINUTE)
                )"
    );
    $cancelStmt->execute([$user['id']]);

    // Penalise: one late-strike per expired booking.
    $incStmt = $pdo->prepare(
        'UPDATE users SET late_departure_count = late_departure_count + ? WHERE id = ?'
    );
    $incStmt->execute([$expired_count, $user['id']]);

    // Re-fetch authoritative late count after update.
    $cntStmt = $pdo->prepare('SELECT late_departure_count FROM users WHERE id = ?');
    $cntStmt->execute([$user['id']]);
    $new_late = (int) $cntStmt->fetchColumn();
    $_SESSION['late_count'] = $new_late;

    // Apply booking lock if the new count is a multiple of 3.
    if ($new_late % 3 === 0) {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $lockStmt = $pdo->prepare('UPDATE users SET booking_locked_until = ? WHERE id = ?');
        $lockStmt->execute([$tomorrow, $user['id']]);
        $_SESSION['booking_locked_until'] = $tomorrow;
        $is_locked = true;
    }

    // Surface a warning banner (only if there's no other flash message already queued).
    if ($flash_error === '') {
        $noun = $expired_count === 1 ? 'booking was' : 'bookings were';
        $flash_error = "{$expired_count} {$noun} auto-cancelled — the 15-minute check-in window expired without a check-in.";
    }
}

// Re-fetch the bookings list whenever either sweep made changes,
// so the table reflects the correct badge state on this very load.
if ($past_cancelled > 0 || $expired_count > 0) {
    $stmt = $pdo->prepare(
        'SELECT b.id, b.booking_date, b.duration_hours, b.points_cost, b.status,
                b.check_in_time, b.check_out_time, b.start_time, b.end_time,
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

function booking_badge_class(string $status): string {
    return match($status) {
        'booked'     => 'badge-booked',
        'checked_in' => 'badge-checked-in',
        'completed'  => 'badge-completed',
        'cancelled'  => 'badge-cancelled',
        default      => 'badge-cancelled',
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
    <div class="alert alert-warning" role="alert">
        <span class="alert-icon material-symbols-outlined" aria-hidden="true">lock</span>
        <div>
            <strong>Booking privileges suspended</strong> — you have 3 late departures on record.
            Unlocks on <strong><?= htmlspecialchars($_SESSION['booking_locked_until'] ?? '—') ?></strong>.
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
    <div class="alert alert-error" role="alert">
        <span class="alert-icon material-symbols-outlined" aria-hidden="true">error</span>
        <div style="flex:1;">
            <?= htmlspecialchars($flash_error) ?>
            <?php if ($blocked_checkin): ?>
            <form method="POST" action="<?= BASE_URL ?>/includes/checkin.php" style="margin-top:10px;">
                <input type="hidden" name="action"               value="report">
                <input type="hidden" name="blocked_booking_id"   value="<?= (int)$blocked_checkin['blocked_id'] ?>">
                <input type="hidden" name="occupying_booking_id" value="<?= (int)$blocked_checkin['occupying_id'] ?>">
                <button type="submit" class="btn btn-secondary" style="padding:6px 16px; font-size:12px; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:15px;">report</span>
                    Report this (+15 pts)
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Flash success -->
    <?php if ($flash !== ''): ?>
    <div class="alert alert-success" role="status">
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
            <span class="stat-card-value stat-card-value--cyan" style="color:var(--clr-secondary);"><?= $user_points ?> <span style="font-size:16px; font-weight:500;">pts</span></span>
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
            <span class="stat-card-label">Late Departures</span>
            <span class="stat-card-value <?= $is_locked ? 'stat-card-value--terra' : '' ?>">
                <?= (int) ($_SESSION['late_count'] ?? 0) ?> / 3
            </span>
            <span style="font-size:12px; color:var(--clr-text-muted); margin-top:2px;">3 warnings = 24h freeze</span>
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
                            $show_checkin  = ($b['status'] === 'booked'      && $b['booking_date'] === $today);
                            $show_checkout = ($b['status'] === 'checked_in');
                            // Build a time-range label if start_time/end_time are set
                            $time_range = '';
                            if (!empty($b['start_time']) && !empty($b['end_time'])) {
                                $time_range = date('g A', strtotime($b['start_time']))
                                            . ' – '
                                            . date('g A', strtotime($b['end_time']));
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
                                <strong><?= $duration ?> hr<?= $duration > 1 ? 's' : '' ?></strong>
                                <span class="text-muted" style="font-size:12px;">(<?= $pts ?> pts)</span>
                            </td>
                            <td>
                                <span class="badge <?= booking_badge_class($b['status']) ?>">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $b['status']))) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($show_checkin): ?>
                                <form method="POST" action="<?= BASE_URL ?>/includes/checkin.php">
                                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                    <button type="submit" class="btn btn-checkin"
                                            aria-label="Check in for booking #CP-<?= str_pad((string)$b['id'], 4, '0', STR_PAD_LEFT) ?>">
                                        <span class="material-symbols-outlined" style="font-size:15px;">login</span>
                                        Check In
                                    </button>
                                </form>
                                <?php elseif ($show_checkout): ?>
                                <form method="POST" action="<?= BASE_URL ?>/includes/checkout.php">
                                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                    <button type="submit" class="btn btn-checkout"
                                            aria-label="Check out for booking #CP-<?= str_pad((string)$b['id'], 4, '0', STR_PAD_LEFT) ?>">
                                        <span class="material-symbols-outlined" style="font-size:15px;">logout</span>
                                        Check Out
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-muted" style="font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </section>

</div><!-- /max-w-7xl -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
