<?php
// admin/dashboard.php — Administrator Live Operations Dashboard (Step 1)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$user = current_user();
$today = date('Y-m-d');
$now_time = date('H:i:s');

// ═══════════════════════════════════════════════════════════════════════════
// 1. STATISTICAL QUERIES — Authoritative real data from existing tables
// ═══════════════════════════════════════════════════════════════════════════

// A. Users Metrics
$total_users = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$total_accounts = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$locked_users_count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE booking_locked_until IS NOT NULL AND booking_locked_until > NOW()")->fetchColumn();

// B. Parking Slots Metrics
$total_slots = (int) $pdo->query("SELECT COUNT(*) FROM parking_slots")->fetchColumn();
$active_slots = (int) $pdo->query("SELECT COUNT(*) FROM parking_slots WHERE is_active = 1")->fetchColumn();
$inactive_slots = max(0, $total_slots - $active_slots);

// C. Occupied / Checked-In Slots (slots with a vehicle currently checked in)
$occupiedStmt = $pdo->query("SELECT COUNT(DISTINCT slot_id) FROM bookings WHERE status = 'checked_in'");
$occupied_slots = (int) $occupiedStmt->fetchColumn();

// D. Reserved / Booked Slots (slots with upcoming or today's active reservations)
$todayReservedStmt = $pdo->query("SELECT COUNT(DISTINCT slot_id) FROM bookings WHERE status = 'booked' AND booking_date = CURDATE()");
$reserved_slots_today = (int) $todayReservedStmt->fetchColumn();

$totalReservedStmt = $pdo->query("SELECT COUNT(DISTINCT slot_id) FROM bookings WHERE status = 'booked' AND booking_date >= CURDATE()");
$reserved_slots_upcoming = (int) $totalReservedStmt->fetchColumn();

// E. Available Slots (real-time calculation: active slots minus occupied minus reserved for today)
$available_slots = max(0, $active_slots - $occupied_slots - $reserved_slots_today);

// F. Active Bookings (all bookings currently in progress: booked or checked_in)
$activeBookingsStmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('booked', 'checked_in')");
$active_bookings = (int) $activeBookingsStmt->fetchColumn();

// G. Today's Activity
$todayBookingsStmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_date = CURDATE() OR DATE(created_at) = CURDATE()");
$today_activity_total = (int) $todayBookingsStmt->fetchColumn();

$todayCheckinsStmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE (booking_date = CURDATE() AND check_in_time IS NOT NULL) OR DATE(check_in_time) = CURDATE()");
$today_checkins = (int) $todayCheckinsStmt->fetchColumn();

$todayCheckoutsStmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE (booking_date = CURDATE() AND status = 'completed') OR DATE(check_out_time) = CURDATE()");
$today_checkouts = (int) $todayCheckoutsStmt->fetchColumn();

$todayCancelledStmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled' AND (booking_date = CURDATE() OR DATE(created_at) = CURDATE())");
$today_cancelled = (int) $todayCancelledStmt->fetchColumn();

// H. Late / Overdue Activity
// 1. Ongoing active overstays (vehicles currently parked past their scheduled end_time)
$overstayStmt = $pdo->prepare(
    "SELECT b.id, b.slot_id, b.user_id, b.booking_date, b.start_time, b.end_time, b.check_in_time,
            b.penalty_points_deducted, u.full_name, u.email, s.slot_code, s.zone
       FROM bookings b
       JOIN users u ON u.id = b.user_id
       JOIN parking_slots s ON s.id = b.slot_id
      WHERE b.status = 'checked_in'
        AND (b.booking_date < CURDATE() OR (b.booking_date = CURDATE() AND b.end_time IS NOT NULL AND b.end_time < CURTIME()))
      ORDER BY b.booking_date ASC, b.end_time ASC"
);
$overstayStmt->execute();
$active_overstays = $overstayStmt->fetchAll();
$active_overstay_count = count($active_overstays);

// 2. Late check-ins recorded
$lateCheckinCount = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE is_late_checkin = 1")->fetchColumn();

// 3. Completed late checkouts
$lateCheckoutCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM bookings 
      WHERE status = 'completed' 
        AND check_out_time IS NOT NULL 
        AND end_time IS NOT NULL 
        AND check_out_time > CONCAT(booking_date, ' ', end_time)"
)->fetchColumn();

// 4. Overstay penalty points total
$penaltiesSum = (float) $pdo->query("SELECT COALESCE(SUM(penalty_points_deducted), 0) FROM bookings WHERE penalty_points_deducted > 0")->fetchColumn();

// 5. Total late/overdue events combined
$total_late_incidents = $active_overstay_count + $lateCheckinCount + $lateCheckoutCount;

// 6. Currently locked user accounts
$lockedUsersStmt = $pdo->query("SELECT id, full_name, email, late_departure_count, late_checkin_count, booking_locked_until FROM users WHERE booking_locked_until IS NOT NULL AND booking_locked_until > NOW() ORDER BY booking_locked_until ASC");
$locked_users = $lockedUsersStmt->fetchAll();

// 7. Recent overstay complaints
$complaintsStmt = $pdo->query(
    "SELECT c.id, c.created_at, c.penalty_deducted,
            u1.full_name AS complainant_name,
            u2.full_name AS overstayer_name,
            s.slot_code, s.zone
       FROM complaints c
       JOIN bookings b1 ON b1.id = c.blocked_booking_id
       JOIN parking_slots s ON s.id = b1.slot_id
       JOIN users u1 ON u1.id = c.complainant_id
       JOIN bookings b2 ON b2.id = c.occupying_booking_id
       JOIN users u2 ON u2.id = b2.user_id
      ORDER BY c.created_at DESC
      LIMIT 5"
);
$recent_complaints = $complaintsStmt->fetchAll();

// ═══════════════════════════════════════════════════════════════════════════
// 2. ZONE BREAKDOWN METRICS — North, South, Central Campus
// ═══════════════════════════════════════════════════════════════════════════
$zones = ['North Campus', 'South Campus', 'Central Campus'];
$zone_stats = [];

foreach ($zones as $zone) {
    // Total slots in zone
    $zSlotsStmt = $pdo->prepare("SELECT COUNT(*) FROM parking_slots WHERE zone = ? AND is_active = 1");
    $zSlotsStmt->execute([$zone]);
    $z_total = (int) $zSlotsStmt->fetchColumn();

    // Occupied in zone
    $zOccStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT b.slot_id) FROM bookings b
           JOIN parking_slots s ON s.id = b.slot_id
          WHERE s.zone = ? AND b.status = 'checked_in'"
    );
    $zOccStmt->execute([$zone]);
    $z_occ = (int) $zOccStmt->fetchColumn();

    // Reserved today in zone
    $zResStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT b.slot_id) FROM bookings b
           JOIN parking_slots s ON s.id = b.slot_id
          WHERE s.zone = ? AND b.status = 'booked' AND b.booking_date = CURDATE()"
    );
    $zResStmt->execute([$zone]);
    $z_res = (int) $zResStmt->fetchColumn();

    $z_avail = max(0, $z_total - $z_occ - $z_res);
    $pct_used = $z_total > 0 ? round((($z_occ + $z_res) / $z_total) * 100) : 0;

    $zone_stats[$zone] = [
        'total'     => $z_total,
        'occupied'  => $z_occ,
        'reserved'  => $z_res,
        'available' => $z_avail,
        'pct'       => $pct_used
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// 3. ACTIVITY FEED FILTERING
// ═══════════════════════════════════════════════════════════════════════════
$filter = trim($_GET['filter'] ?? 'all');
$valid_filters = ['all', 'today', 'checked_in', 'booked', 'completed', 'late'];
if (!in_array($filter, $valid_filters)) {
    $filter = 'all';
}

$feed_sql = "SELECT b.id, b.slot_id, b.user_id, b.booking_date, b.duration_hours, b.points_cost,
                    b.start_time, b.end_time, b.check_in_time, b.check_out_time, b.status,
                    b.is_late_checkin, b.penalty_points_deducted, b.created_at,
                    u.full_name, u.email, s.slot_code, s.zone
               FROM bookings b
               JOIN users u ON u.id = b.user_id
               JOIN parking_slots s ON s.id = b.slot_id";

$where_clauses = [];
$params = [];

if ($filter === 'today') {
    $where_clauses[] = "(b.booking_date = CURDATE() OR DATE(b.created_at) = CURDATE())";
} elseif ($filter === 'checked_in') {
    $where_clauses[] = "b.status = 'checked_in'";
} elseif ($filter === 'booked') {
    $where_clauses[] = "b.status = 'booked'";
} elseif ($filter === 'completed') {
    $where_clauses[] = "b.status = 'completed'";
} elseif ($filter === 'late') {
    $where_clauses[] = "(b.is_late_checkin = 1 OR b.penalty_points_deducted > 0 OR (b.status = 'checked_in' AND (b.booking_date < CURDATE() OR (b.booking_date = CURDATE() AND b.end_time < CURTIME()))))";
}

if (!empty($where_clauses)) {
    $feed_sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$feed_sql .= " ORDER BY b.created_at DESC LIMIT 20";

$feedStmt = $pdo->prepare($feed_sql);
$feedStmt->execute($params);
$recent_bookings = $feedStmt->fetchAll();

$admin_title      = 'Operations Dashboard';
$admin_active_nav = 'dashboard';
require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- Dashboard Page Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Operations & Telemetry Dashboard</h1>
        <p class="admin-page-subtitle">Live campus parking slot status, driver check-in telemetry, and compliance auditing.</p>
    </div>
    <div style="display:flex; align-items:center; gap:8px;">
        <a href="<?= BASE_URL ?>/admin/export-pdf.php?type=audit" target="_blank" class="btn btn-outline" style="font-size:13px; padding:8px 14px; display:inline-flex; align-items:center; gap:6px; border-color:var(--clr-secondary); color:var(--clr-secondary); font-weight:600;">
            <span class="material-symbols-outlined" style="font-size:16px;">picture_as_pdf</span>
            <span>Export Audit PDF</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline" style="font-size:13px; padding:8px 14px; display:inline-flex; align-items:center; gap:6px;">
            <span class="material-symbols-outlined" style="font-size:16px;">refresh</span>
            <span>Refresh Telemetry</span>
        </a>
    </div>
</div>

<!-- =========================================================================
     1. PRIMARY KPI STAT CARDS (8 Core Metrics)
     ========================================================================= -->
<div class="admin-stat-grid">

    <!-- KPI 1: Total Users -->
    <div class="admin-kpi-card">
        <div class="admin-kpi-top">
            <span class="admin-kpi-label">Total Users</span>
            <div class="admin-kpi-icon admin-kpi-icon--cyan">
                <span class="material-symbols-outlined">group</span>
            </div>
        </div>
        <div class="admin-kpi-value"><?= number_format($total_users) ?></div>
        <div class="admin-kpi-sub">
            <span class="material-symbols-outlined" style="font-size:14px; color:var(--clr-secondary);">account_box</span>
            <span><?= number_format($total_accounts) ?> registered accounts</span>
        </div>
    </div>

    <!-- KPI 2: Total Parking Slots -->
    <div class="admin-kpi-card">
        <div class="admin-kpi-top">
            <span class="admin-kpi-label">Total Slots</span>
            <div class="admin-kpi-icon admin-kpi-icon--purple">
                <span class="material-symbols-outlined">local_parking</span>
            </div>
        </div>
        <div class="admin-kpi-value"><?= number_format($total_slots) ?></div>
        <div class="admin-kpi-sub">
            <span class="material-symbols-outlined" style="font-size:14px; color:var(--clr-success);">check</span>
            <span><?= $active_slots ?> active across 3 campus zones</span>
        </div>
    </div>

    <!-- KPI 3: Available Slots -->
    <div class="admin-kpi-card" style="border-top: 3px solid var(--clr-success);">
        <div class="admin-kpi-top">
            <span class="admin-kpi-label">Available Slots</span>
            <div class="admin-kpi-icon admin-kpi-icon--emerald">
                <span class="material-symbols-outlined">check_circle</span>
            </div>
        </div>
        <div class="admin-kpi-value text-emerald"><?= number_format($available_slots) ?></div>
        <div class="admin-kpi-sub">
            <span>Ready for instant reservation</span>
        </div>
    </div>

    <!-- KPI 4: Reserved / Booked Slots -->
    <div class="admin-kpi-card">
        <div class="admin-kpi-top">
            <span class="admin-kpi-label">Reserved Slots</span>
            <div class="admin-kpi-icon admin-kpi-icon--cyan">
                <span class="material-symbols-outlined">schedule</span>
            </div>
        </div>
        <div class="admin-kpi-value text-cyan"><?= number_format($reserved_slots_today) ?></div>
        <div class="admin-kpi-sub">
            <span><?= $reserved_slots_upcoming ?> upcoming booked slots</span>
        </div>
    </div>

    <!-- KPI 5: Occupied / Checked-In Slots -->
    <div class="admin-kpi-card" style="border-top: 3px solid var(--clr-error);">
        <div class="admin-kpi-top">
            <span class="admin-kpi-label">Occupied / Checked-In</span>
            <div class="admin-kpi-icon admin-kpi-icon--error">
                <span class="material-symbols-outlined">directions_car</span>
            </div>
        </div>
        <div class="admin-kpi-value" style="color:var(--clr-error);"><?= number_format($occupied_slots) ?></div>
        <div class="admin-kpi-sub">
            <span>Active vehicles currently on bays</span>
        </div>
    </div>

    <!-- KPI 6: Active Bookings -->
    <div class="admin-kpi-card">
        <div class="admin-kpi-top">
            <span class="admin-kpi-label">Active Bookings</span>
            <div class="admin-kpi-icon admin-kpi-icon--purple">
                <span class="material-symbols-outlined">confirmation_number</span>
            </div>
        </div>
        <div class="admin-kpi-value"><?= number_format($active_bookings) ?></div>
        <div class="admin-kpi-sub">
            <span>Booked or checked in sessions</span>
        </div>
    </div>

    <!-- KPI 7: Today's Activity -->
    <div class="admin-kpi-card">
        <div class="admin-kpi-top">
            <span class="admin-kpi-label">Today's Activity</span>
            <div class="admin-kpi-icon admin-kpi-icon--amber">
                <span class="material-symbols-outlined">today</span>
            </div>
        </div>
        <div class="admin-kpi-value"><?= number_format($today_activity_total) ?></div>
        <div class="admin-kpi-sub">
            <span><?= $today_checkins ?> check-ins &bull; <?= $today_checkouts ?> checkouts</span>
        </div>
    </div>

    <!-- KPI 8: Late / Overdue Incidents -->
    <div class="admin-kpi-card" style="border-top: 3px solid var(--clr-amber);">
        <div class="admin-kpi-top">
            <span class="admin-kpi-label">Late / Overdue</span>
            <div class="admin-kpi-icon admin-kpi-icon--amber">
                <span class="material-symbols-outlined">warning</span>
            </div>
        </div>
        <div class="admin-kpi-value" style="color: <?= $total_late_incidents > 0 ? 'var(--clr-amber)' : 'var(--clr-success)' ?>;">
            <?= number_format($total_late_incidents) ?>
        </div>
        <div class="admin-kpi-sub">
            <span><?= $active_overstay_count ?> live overstays &bull; <?= $locked_users_count ?> locked</span>
        </div>
    </div>

</div>

<!-- =========================================================================
     2. ZONE CAPACITY OVERVIEW
     ========================================================================= -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-card-title">
            <span class="material-symbols-outlined" style="color:var(--clr-secondary);">hub</span>
            <span>Campus Parking Zones Capacity & Live Load</span>
        </div>
        <div style="font-size:12px; color:var(--clr-text-muted);">
            Campus Operating Time: 08:00 AM – 05:00 PM
        </div>
    </div>
    <div class="admin-card-body">
        <div class="admin-zone-grid" style="margin-bottom:0;">
            <?php foreach ($zone_stats as $zName => $zData): 
                $bar_color = 'var(--clr-success)';
                if ($zData['pct'] > 80) {
                    $bar_color = 'var(--clr-error)';
                } elseif ($zData['pct'] > 50) {
                    $bar_color = 'var(--clr-amber)';
                }
            ?>
                <div class="admin-zone-card">
                    <div class="admin-zone-header">
                        <span class="admin-zone-title"><?= htmlspecialchars($zName) ?></span>
                        <span class="badge" style="background:rgba(8,145,178,0.12); color:var(--clr-secondary);">
                            <?= $zData['pct'] ?>% Occupied
                        </span>
                    </div>

                    <div class="admin-zone-progress-wrap">
                        <div class="admin-zone-progress-bar" style="width: <?= min(100, $zData['pct']) ?>%; background: <?= $bar_color ?>;"></div>
                    </div>

                    <div class="admin-zone-metrics">
                        <span><strong><?= $zData['available'] ?></strong> Available</span>
                        <span><strong><?= $zData['reserved'] ?></strong> Reserved</span>
                        <span><strong><?= $zData['occupied'] ?></strong> Parked</span>
                        <span>Total: <?= $zData['total'] ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- =========================================================================
     3. LATE & OVERDUE COMPLIANCE PANEL
     ========================================================================= -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-card-title">
            <span class="material-symbols-outlined" style="color:var(--clr-error);">report</span>
            <span>Late & Overdue Activity Log</span>
            <?php if ($total_late_incidents > 0): ?>
                <span class="badge badge-occupied"><?= $total_late_incidents ?> Recorded</span>
            <?php else: ?>
                <span class="badge badge-available">All Clear</span>
            <?php endif; ?>
        </div>
        <div style="font-size:12px; color:var(--clr-text-muted);">
            Fines Collected: <strong><?= number_format($penaltiesSum, 2) ?> pts</strong>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 16px 20px;">

        <!-- Section A: Active Live Overstays -->
        <?php if (!empty($active_overstays)): ?>
            <div style="margin-bottom:20px;">
                <h3 style="font-size:14px; font-weight:700; color:var(--clr-error); margin-bottom:12px; display:flex; align-items:center; gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:18px;">timer_off</span>
                    <span>Currently Overstaying Vehicles (Immediate Attention Required)</span>
                </h3>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Driver</th>
                                <th>Slot / Zone</th>
                                <th>Scheduled Window</th>
                                <th>Checked In At</th>
                                <th>Overstay Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_overstays as $ov): 
                                $sched_end = strtotime($ov['booking_date'] . ' ' . $ov['end_time']);
                                $diff_sec  = max(0, time() - $sched_end);
                                $diff_str  = ($diff_sec < 60) ? ($diff_sec . 's overdue') : ((int)ceil($diff_sec / 60) . ' min overdue');
                            ?>
                                <tr>
                                    <td><strong>#<?= (int) $ov['id'] ?></strong></td>
                                    <td>
                                        <strong><?= htmlspecialchars($ov['full_name']) ?></strong><br>
                                        <span style="font-size:11px; color:var(--clr-text-muted);"><?= htmlspecialchars($ov['email']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge" style="background:var(--clr-surface-high);"><?= htmlspecialchars($ov['slot_code']) ?></span>
                                        <span style="font-size:12px; color:var(--clr-text-muted);"><?= htmlspecialchars($ov['zone']) ?></span>
                                    </td>
                                    <td>
                                        <?= date('g:i A', strtotime($ov['start_time'])) ?> – <?= date('g:i A', strtotime($ov['end_time'])) ?>
                                    </td>
                                    <td><?= !empty($ov['check_in_time']) ? date('g:i A', strtotime($ov['check_in_time'])) : '—' ?></td>
                                    <td>
                                        <span class="badge badge-occupied">
                                            <span class="material-symbols-outlined" style="font-size:14px;">alarm</span>
                                            <?= $diff_str ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Section B: Account Freeze Status & Complaints Overview -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:16px;">

            <!-- Locked Users Box -->
            <div style="background:var(--clr-surface-low); border:1px solid var(--clr-border); border-radius:10px; padding:14px 16px;">
                <h4 style="font-size:13px; font-weight:700; color:var(--clr-text); margin-bottom:10px; display:flex; align-items:center; justify-content:space-between;">
                    <span style="display:flex; align-items:center; gap:6px;">
                        <span class="material-symbols-outlined" style="font-size:18px; color:var(--clr-amber);">lock_clock</span>
                        <span>Account Freeze Violations</span>
                    </span>
                    <span class="badge" style="background:var(--clr-surface-high);"><?= count($locked_users) ?> Active</span>
                </h4>

                <?php if (empty($locked_users)): ?>
                    <p style="font-size:12px; color:var(--clr-text-muted); margin:0;">
                        No user accounts are currently serving a booking freeze. (Triggered when late departures hit multiples of 3).
                    </p>
                <?php else: ?>
                    <ul style="list-style:none; display:flex; flex-direction:column; gap:8px; margin:0; padding:0; font-size:12px;">
                        <?php foreach ($locked_users as $lu): 
                            $rem_sec = max(0, strtotime($lu['booking_locked_until']) - time());
                        ?>
                            <li style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--clr-border); padding-bottom:6px;">
                                <div>
                                    <strong><?= htmlspecialchars($lu['full_name']) ?></strong>
                                    <div style="font-size:11px; color:var(--clr-text-muted);"><?= (int)$lu['late_departure_count'] ?> late checkouts</div>
                                </div>
                                <span class="badge badge-late-checkin">
                                    Locked (<?= $rem_sec ?>s left)
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Overstay Reports / Complaints Box -->
            <div style="background:var(--clr-surface-low); border:1px solid var(--clr-border); border-radius:10px; padding:14px 16px;">
                <h4 style="font-size:13px; font-weight:700; color:var(--clr-text); margin-bottom:10px; display:flex; align-items:center; justify-content:space-between;">
                    <span style="display:flex; align-items:center; gap:6px;">
                        <span class="material-symbols-outlined" style="font-size:18px; color:var(--clr-secondary);">campaign</span>
                        <span>Bay Obstruction Complaints</span>
                    </span>
                    <span class="badge" style="background:var(--clr-surface-high);"><?= count($recent_complaints) ?> Logged</span>
                </h4>

                <?php if (empty($recent_complaints)): ?>
                    <p style="font-size:12px; color:var(--clr-text-muted); margin:0;">
                        Zero slot blockage complaints reported. System turnover running cleanly.
                    </p>
                <?php else: ?>
                    <ul style="list-style:none; display:flex; flex-direction:column; gap:8px; margin:0; padding:0; font-size:12px;">
                        <?php foreach ($recent_complaints as $cmp): ?>
                            <li style="border-bottom:1px solid var(--clr-border); padding-bottom:6px;">
                                <div style="display:flex; justify-content:space-between;">
                                    <strong>Slot <?= htmlspecialchars($cmp['slot_code']) ?> (<?= htmlspecialchars($cmp['zone']) ?>)</strong>
                                    <span style="color:var(--clr-error); font-weight:700;">-<?= number_format($cmp['penalty_deducted'], 2) ?> pts fine</span>
                                </div>
                                <div style="font-size:11px; color:var(--clr-text-muted); margin-top:2px;">
                                    Reported by <?= htmlspecialchars($cmp['complainant_name']) ?> against overstayer <?= htmlspecialchars($cmp['overstayer_name']) ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

        </div>

        <?php if (empty($active_overstays) && empty($locked_users) && empty($recent_complaints) && $lateCheckinCount === 0 && $lateCheckoutCount === 0): ?>
            <div class="admin-empty-state" style="padding: 24px 16px;">
                <span class="material-symbols-outlined admin-empty-icon text-emerald">verified</span>
                <div class="admin-empty-title">All Parking Sessions in Good Standing</div>
                <div class="admin-empty-desc">No active overstays, late departures, or account restrictions currently recorded.</div>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- =========================================================================
     4. RECENT ACTIVITY FEED & BOOKINGS AUDIT TABLE
     ========================================================================= -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-card-title">
            <span class="material-symbols-outlined" style="color:var(--clr-secondary);">history</span>
            <span>System Activity Feed</span>
        </div>

        <!-- Filter Tab Pills -->
        <div class="admin-filter-tabs">
            <a href="?filter=all" class="admin-filter-pill <?= $filter === 'all' ? 'admin-filter-pill--active' : '' ?>">
                All
            </a>
            <a href="?filter=today" class="admin-filter-pill <?= $filter === 'today' ? 'admin-filter-pill--active' : '' ?>">
                Today's Bookings
            </a>
            <a href="?filter=checked_in" class="admin-filter-pill <?= $filter === 'checked_in' ? 'admin-filter-pill--active' : '' ?>">
                Checked In
            </a>
            <a href="?filter=booked" class="admin-filter-pill <?= $filter === 'booked' ? 'admin-filter-pill--active' : '' ?>">
                Reserved
            </a>
            <a href="?filter=completed" class="admin-filter-pill <?= $filter === 'completed' ? 'admin-filter-pill--active' : '' ?>">
                Completed
            </a>
            <a href="?filter=late" class="admin-filter-pill <?= $filter === 'late' ? 'admin-filter-pill--active' : '' ?>">
                Late / Overdue
            </a>
        </div>
    </div>

    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($recent_bookings)): ?>
            <div class="admin-empty-state">
                <span class="material-symbols-outlined admin-empty-icon">event_busy</span>
                <div class="admin-empty-title">No Bookings Found</div>
                <div class="admin-empty-desc">
                    <?= $filter === 'all' ? 'No reservation activity has been recorded yet.' : 'No records match the current filter selection.' ?>
                </div>
            </div>
        <?php else: ?>
            <div class="table-wrapper" style="border:none; border-radius:0; box-shadow:none;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Driver</th>
                            <th>Slot & Zone</th>
                            <th>Date & Hours</th>
                            <th>Scheduled Time</th>
                            <th>Actual Timestamps</th>
                            <th>Status</th>
                            <th>Points Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_bookings as $b): 
                            $status_class = 'badge-booked';
                            $status_label = 'Booked';

                            if ($b['status'] === 'checked_in') {
                                $status_class = 'badge-checked-in';
                                $status_label = 'Checked In';
                                // Check if overstaying
                                if (!empty($b['end_time']) && ($b['booking_date'] < $today || ($b['booking_date'] === $today && $b['end_time'] < $now_time))) {
                                    $status_class = 'badge-occupied';
                                    $status_label = 'Overdue';
                                }
                            } elseif ($b['status'] === 'completed') {
                                $status_class = 'badge-completed';
                                $status_label = 'Completed';
                            } elseif ($b['status'] === 'cancelled') {
                                $status_class = 'badge-cancelled';
                                $status_label = 'Cancelled';
                            }

                            $time_window = '—';
                            if (!empty($b['start_time']) && !empty($b['end_time'])) {
                                $time_window = date('g:i A', strtotime($b['start_time'])) . ' – ' . date('g:i A', strtotime($b['end_time']));
                            }
                        ?>
                            <tr>
                                <td><strong>#<?= (int) $b['id'] ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($b['full_name']) ?></strong><br>
                                    <span style="font-size:11px; color:var(--clr-text-muted);"><?= htmlspecialchars($b['email']) ?></span>
                                </td>
                                <td>
                                    <span class="badge" style="background:var(--clr-surface-high);"><?= htmlspecialchars($b['slot_code']) ?></span>
                                    <span style="font-size:12px; color:var(--clr-text-muted);"><?= htmlspecialchars($b['zone']) ?></span>
                                </td>
                                <td>
                                    <span style="font-weight:600;"><?= date('M j, Y', strtotime($b['booking_date'])) ?></span><br>
                                    <span style="font-size:11px; color:var(--clr-text-muted);"><?= (int)$b['duration_hours'] ?> hr(s)</span>
                                </td>
                                <td><?= $time_window ?></td>
                                <td style="font-size:12px;">
                                    <?php if (!empty($b['check_in_time'])): ?>
                                        <div><span style="color:var(--clr-text-muted);">In:</span> <?= date('g:i A', strtotime($b['check_in_time'])) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($b['check_out_time'])): ?>
                                        <div><span style="color:var(--clr-text-muted);">Out:</span> <?= date('g:i A', strtotime($b['check_out_time'])) ?></div>
                                    <?php endif; ?>
                                    <?php if (empty($b['check_in_time']) && empty($b['check_out_time'])): ?>
                                        <span style="color:var(--clr-text-muted);">Awaiting arrival</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $status_class ?>">
                                        <?= $status_label ?>
                                    </span>
                                    <?php if ((int)$b['is_late_checkin'] === 1): ?>
                                        <span class="badge badge-late-checkin" title="Late Check-In recorded" style="margin-top:2px;">
                                            Late In
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= (int)$b['points_cost'] ?> pts</strong>
                                    <?php if ((float)$b['penalty_points_deducted'] > 0): ?>
                                        <div style="font-size:11px; color:var(--clr-error); font-weight:700;">
                                            -<?= number_format($b['penalty_points_deducted'], 2) ?> penalty
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="admin-card-footer">
        <span>Showing up to 20 recent records</span>
        <span style="display:flex; align-items:center; gap:4px;">
            <span class="admin-status-indicator"></span>
            <span>Live Database Synchronized</span>
        </span>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
