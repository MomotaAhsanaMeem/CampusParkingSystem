<?php
// admin/bookings.php — All Bookings Management (Step 2)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$today    = date('Y-m-d');
$now_time = date('H:i:s');

// ─── FILTERS ────────────────────────────────────────────────────────────────
$search        = trim($_GET['search']   ?? '');
$status_filter = trim($_GET['status']   ?? '');
$zone_filter   = trim($_GET['zone']     ?? '');
$date_from     = trim($_GET['date_from'] ?? '');
$date_to       = trim($_GET['date_to']   ?? '');
$page          = max(1, (int) ($_GET['page'] ?? 1));
$per_page      = 25;

// Valid statuses
$valid_statuses = ['booked', 'checked_in', 'completed', 'cancelled'];

// ─── QUERY BUILD ────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(u.full_name LIKE ? OR u.email LIKE ? OR s.slot_code LIKE ? OR b.id = ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = is_numeric($search) ? (int)$search : -1;
}
if (in_array($status_filter, $valid_statuses, true)) {
    $where[]  = 'b.status = ?';
    $params[] = $status_filter;
}
if ($zone_filter !== '') {
    $where[]  = 's.zone = ?';
    $params[] = $zone_filter;
}
if ($date_from !== '') {
    $where[]  = 'b.booking_date >= ?';
    $params[] = $date_from;
}
if ($date_to !== '') {
    $where[]  = 'b.booking_date <= ?';
    $params[] = $date_to;
}

$where_sql = implode(' AND ', $where);

// Count for pagination
$count_stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM bookings b
       JOIN users u ON u.id = b.user_id
       JOIN parking_slots s ON s.id = b.slot_id
      WHERE {$where_sql}"
);
$count_stmt->execute($params);
$total_records = (int) $count_stmt->fetchColumn();
$total_pages   = max(1, (int) ceil($total_records / $per_page));
$page          = min($page, $total_pages);
$offset        = ($page - 1) * $per_page;

// Fetch bookings
$bookings_stmt = $pdo->prepare(
    "SELECT b.id, b.booking_date, b.duration_hours, b.points_cost, b.status,
            b.start_time, b.end_time, b.check_in_time, b.check_out_time,
            b.is_late_checkin, b.penalty_points_deducted, b.created_at,
            u.id AS user_id, u.full_name, u.email,
            s.id AS slot_id, s.slot_code, s.zone
       FROM bookings b
       JOIN users u ON u.id = b.user_id
       JOIN parking_slots s ON s.id = b.slot_id
      WHERE {$where_sql}
      ORDER BY b.created_at DESC
      LIMIT {$per_page} OFFSET {$offset}"
);
$bookings_stmt->execute($params);
$bookings = $bookings_stmt->fetchAll();

// Zones dropdown
$zones_stmt = $pdo->query("SELECT DISTINCT zone FROM parking_slots ORDER BY zone ASC");
$all_zones  = array_column($zones_stmt->fetchAll(), 'zone');

// Summary stats (always from full DB, not filtered)
$stats = [
    'total'      => (int) $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'booked'     => (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='booked'")->fetchColumn(),
    'checked_in' => (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='checked_in'")->fetchColumn(),
    'completed'  => (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetchColumn(),
    'cancelled'  => (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='cancelled'")->fetchColumn(),
    'overdue'    => (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='checked_in' AND (booking_date < CURDATE() OR (booking_date = CURDATE() AND end_time IS NOT NULL AND end_time < CURTIME()))")->fetchColumn(),
];

// ─── Build query string helper for pagination ────────────────────────────────
function booking_query(array $extra = []): string {
    $params = array_merge([
        'search'    => $_GET['search']    ?? '',
        'status'    => $_GET['status']    ?? '',
        'zone'      => $_GET['zone']      ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to'   => $_GET['date_to']   ?? '',
    ], $extra);
    $params = array_filter($params, fn($v) => $v !== '');
    return $params ? '?' . http_build_query($params) : '?';
}

$admin_title      = 'All Bookings';
$admin_active_nav = 'bookings';
require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">All Bookings</h1>
        <p class="admin-page-subtitle">Search, filter and monitor every reservation across all users and slots.</p>
    </div>
</div>

<!-- KPI Cards -->
<div class="admin-stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin-bottom:20px;">
    <div class="admin-kpi-card">
        <div class="admin-kpi-top"><span class="admin-kpi-label">Total</span><div class="admin-kpi-icon admin-kpi-icon--purple"><span class="material-symbols-outlined">receipt_long</span></div></div>
        <div class="admin-kpi-value"><?= number_format($stats['total']) ?></div>
        <div class="admin-kpi-sub"><span>All-time bookings</span></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-top"><span class="admin-kpi-label">Reserved</span><div class="admin-kpi-icon admin-kpi-icon--cyan"><span class="material-symbols-outlined">schedule</span></div></div>
        <div class="admin-kpi-value text-cyan"><?= number_format($stats['booked']) ?></div>
        <div class="admin-kpi-sub"><span>Awaiting check-in</span></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-top"><span class="admin-kpi-label">Checked In</span><div class="admin-kpi-icon admin-kpi-icon--emerald"><span class="material-symbols-outlined">directions_car</span></div></div>
        <div class="admin-kpi-value text-emerald"><?= number_format($stats['checked_in']) ?></div>
        <div class="admin-kpi-sub"><span>Active sessions</span></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-top"><span class="admin-kpi-label">Completed</span><div class="admin-kpi-icon admin-kpi-icon--emerald"><span class="material-symbols-outlined">check_circle</span></div></div>
        <div class="admin-kpi-value"><?= number_format($stats['completed']) ?></div>
        <div class="admin-kpi-sub"><span>Checked out</span></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-top"><span class="admin-kpi-label">Cancelled</span><div class="admin-kpi-icon admin-kpi-icon--error"><span class="material-symbols-outlined">cancel</span></div></div>
        <div class="admin-kpi-value" style="color:var(--clr-error)"><?= number_format($stats['cancelled']) ?></div>
        <div class="admin-kpi-sub"><span>Cancelled sessions</span></div>
    </div>
    <div class="admin-kpi-card" style="border-top:3px solid var(--clr-amber);">
        <div class="admin-kpi-top"><span class="admin-kpi-label">Overdue Now</span><div class="admin-kpi-icon admin-kpi-icon--amber"><span class="material-symbols-outlined">timer_off</span></div></div>
        <div class="admin-kpi-value" style="color:<?= $stats['overdue'] > 0 ? 'var(--clr-amber)' : 'var(--clr-success)' ?>"><?= number_format($stats['overdue']) ?></div>
        <div class="admin-kpi-sub"><span>Active overstays</span></div>
    </div>
</div>

<!-- Search & Filter -->
<div class="admin-card" style="margin-bottom:16px;">
    <div class="admin-card-body" style="padding:16px 20px;">
        <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:1; min-width:200px;">
                <label style="font-size:12px; font-weight:600; color:var(--clr-text-muted); display:block; margin-bottom:4px;">Search</label>
                <input type="text" name="search" class="form-input" placeholder="Name, email, slot, or booking ID…"
                       value="<?= htmlspecialchars($search) ?>" style="font-size:13px; padding:8px 12px;">
            </div>
            <div style="min-width:130px;">
                <label style="font-size:12px; font-weight:600; color:var(--clr-text-muted); display:block; margin-bottom:4px;">Status</label>
                <select name="status" class="form-input" style="font-size:13px; padding:8px 10px;">
                    <option value="">All Status</option>
                    <option value="booked"     <?= $status_filter === 'booked'     ? 'selected' : '' ?>>Reserved</option>
                    <option value="checked_in" <?= $status_filter === 'checked_in' ? 'selected' : '' ?>>Checked In</option>
                    <option value="completed"  <?= $status_filter === 'completed'  ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled"  <?= $status_filter === 'cancelled'  ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div style="min-width:150px;">
                <label style="font-size:12px; font-weight:600; color:var(--clr-text-muted); display:block; margin-bottom:4px;">Zone</label>
                <select name="zone" class="form-input" style="font-size:13px; padding:8px 10px;">
                    <option value="">All Zones</option>
                    <?php foreach ($all_zones as $z): ?>
                        <option value="<?= htmlspecialchars($z) ?>" <?= $zone_filter === $z ? 'selected' : '' ?>><?= htmlspecialchars($z) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width:130px;">
                <label style="font-size:12px; font-weight:600; color:var(--clr-text-muted); display:block; margin-bottom:4px;">Date From</label>
                <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($date_from) ?>" style="font-size:13px; padding:8px 10px;">
            </div>
            <div style="min-width:130px;">
                <label style="font-size:12px; font-weight:600; color:var(--clr-text-muted); display:block; margin-bottom:4px;">Date To</label>
                <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($date_to) ?>" style="font-size:13px; padding:8px 10px;">
            </div>
            <div style="display:flex; gap:8px; align-items:flex-end;">
                <button type="submit" class="btn btn-primary" style="font-size:13px; padding:8px 18px; border-radius:8px;">
                    <span class="material-symbols-outlined" style="font-size:16px;">search</span>
                    Search
                </button>
                <a href="<?= BASE_URL ?>/admin/export-pdf.php?type=bookings&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&zone=<?= urlencode($zone_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"
                   target="_blank"
                   class="btn btn-outline"
                   style="font-size:13px; padding:8px 14px; border-radius:8px; border-color:var(--clr-secondary); color:var(--clr-secondary); font-weight:600; display:inline-flex; align-items:center; gap:5px;"
                   title="Export filtered bookings report as PDF">
                    <span class="material-symbols-outlined" style="font-size:16px;">picture_as_pdf</span>
                    <span>Export PDF</span>
                </a>
                <?php if ($search || $status_filter || $zone_filter || $date_from || $date_to): ?>
                    <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn btn-outline" style="font-size:13px; padding:8px 14px; border-radius:8px;">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Bookings Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-card-title">
            <span class="material-symbols-outlined" style="color:var(--clr-secondary);">event_available</span>
            <span>Booking Records</span>
            <span class="badge" style="background:var(--clr-surface-high); color:var(--clr-text-muted);"><?= number_format($total_records) ?></span>
        </div>
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="<?= BASE_URL ?>/admin/export-pdf.php?type=bookings&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&zone=<?= urlencode($zone_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"
               target="_blank"
               style="font-size:12px; color:var(--clr-secondary); display:inline-flex; align-items:center; gap:4px; text-decoration:none; font-weight:600;"
               title="Export filtered records as PDF">
                <span class="material-symbols-outlined" style="font-size:16px;">picture_as_pdf</span>
                <span>Export PDF</span>
            </a>
            <span style="font-size:12px; color:var(--clr-text-muted);">Page <?= $page ?> of <?= $total_pages ?></span>
        </div>
    </div>

    <div class="admin-card-body" style="padding:0;">
        <?php if (empty($bookings)): ?>
            <div class="admin-empty-state">
                <span class="material-symbols-outlined admin-empty-icon">event_busy</span>
                <div class="admin-empty-title">No Bookings Found</div>
                <div class="admin-empty-desc">No records match your search criteria. <a href="<?= BASE_URL ?>/admin/bookings.php">Clear all filters</a>.</div>
            </div>
        <?php else: ?>
            <div class="table-wrapper" style="border:none; border-radius:0; box-shadow:none;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Slot & Zone</th>
                            <th>Date</th>
                            <th>Time Window</th>
                            <th>Check-in / Out</th>
                            <th>Status</th>
                            <th>Points / Penalty</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b):
                            $is_overdue = $b['status'] === 'checked_in' && !empty($b['end_time']) &&
                                ($b['booking_date'] < $today || ($b['booking_date'] === $today && $b['end_time'] < $now_time));

                            $status_class = match($b['status']) {
                                'booked'     => 'badge-booked',
                                'checked_in' => $is_overdue ? 'badge-occupied' : 'badge-checked-in',
                                'completed'  => 'badge-completed',
                                'cancelled'  => 'badge-cancelled',
                                default      => '',
                            };
                            $status_label = match($b['status']) {
                                'booked'     => 'Reserved',
                                'checked_in' => $is_overdue ? 'Overdue' : 'Checked In',
                                'completed'  => 'Completed',
                                'cancelled'  => 'Cancelled',
                                default      => ucfirst($b['status']),
                            };
                            $time_window = '—';
                            if (!empty($b['start_time']) && !empty($b['end_time'])) {
                                $time_window = date('g:i A', strtotime($b['start_time'])) . ' – ' . date('g:i A', strtotime($b['end_time']));
                            }
                        ?>
                            <tr style="<?= $is_overdue ? 'background:rgba(220,38,38,0.04);' : '' ?>">
                                <td><strong>#<?= (int)$b['id'] ?></strong></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/admin/users.php?view=<?= (int)$b['user_id'] ?>" style="font-weight:600; color:var(--clr-secondary);"><?= htmlspecialchars($b['full_name']) ?></a><br>
                                    <span style="font-size:11px; color:var(--clr-text-muted);"><?= htmlspecialchars($b['email']) ?></span>
                                </td>
                                <td>
                                    <span class="badge" style="background:var(--clr-surface-high);"><?= htmlspecialchars($b['slot_code']) ?></span>
                                    <span style="font-size:11px; color:var(--clr-text-muted);"><?= htmlspecialchars($b['zone']) ?></span>
                                </td>
                                <td>
                                    <strong><?= date('M j, Y', strtotime($b['booking_date'])) ?></strong><br>
                                    <span style="font-size:11px; color:var(--clr-text-muted);"><?= (int)$b['duration_hours'] ?> hr(s)</span>
                                </td>
                                <td style="font-size:13px;"><?= $time_window ?></td>
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
                                    <span class="badge <?= $status_class ?>"><?= $status_label ?></span>
                                    <?php if ((int)$b['is_late_checkin'] === 1): ?>
                                        <span class="badge badge-late-checkin" style="font-size:10px; margin-top:3px; display:block; width:fit-content;">Late In</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= (int)$b['points_cost'] ?> pts</strong>
                                    <?php if ((float)$b['penalty_points_deducted'] > 0): ?>
                                        <div style="font-size:11px; color:var(--clr-error); font-weight:700;">-<?= number_format($b['penalty_points_deducted'], 2) ?> penalty</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/admin/bookings.php?view=<?= (int)$b['id'] ?><?= $search || $status_filter || $zone_filter || $date_from || $date_to ? '&' . http_build_query(['search' => $search, 'status' => $status_filter, 'zone' => $zone_filter, 'date_from' => $date_from, 'date_to' => $date_to, 'page' => $page]) : '' ?>"
                                       class="btn-checkin" style="font-size:11px; padding:5px 10px; background:var(--clr-secondary);">
                                        <span class="material-symbols-outlined" style="font-size:14px;">visibility</span>
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="admin-card-footer" style="justify-content:center; gap:8px; flex-wrap:wrap;">
        <?php if ($page > 1): ?>
            <a href="<?= BASE_URL ?>/admin/bookings.php<?= booking_query(['page' => $page - 1]) ?>" class="btn btn-outline" style="font-size:12px; padding:5px 12px;">&laquo; Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
            <a href="<?= BASE_URL ?>/admin/bookings.php<?= booking_query(['page' => $p]) ?>"
               class="btn <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>" style="font-size:12px; padding:5px 12px; border-radius:6px; min-width:34px;"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
            <a href="<?= BASE_URL ?>/admin/bookings.php<?= booking_query(['page' => $page + 1]) ?>" class="btn btn-outline" style="font-size:12px; padding:5px 12px;">Next &raquo;</a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="admin-card-footer">
        <span>Showing <?= count($bookings) ?> of <?= $total_records ?> record(s)</span>
        <span style="display:flex; align-items:center; gap:4px;"><span class="admin-status-indicator"></span><span>Live Database</span></span>
    </div>
    <?php endif; ?>
</div>

<?php
// ─── BOOKING DETAIL MODAL VIEW ───────────────────────────────────────────────
$view_id = (int) ($_GET['view'] ?? 0);
if ($view_id > 0):
    $detail = $pdo->prepare(
        "SELECT b.*, u.full_name, u.email, u.reward_points, u.late_departure_count, u.late_checkin_count,
                s.slot_code, s.zone, s.is_active
           FROM bookings b
           JOIN users u ON u.id = b.user_id
           JOIN parking_slots s ON s.id = b.slot_id
          WHERE b.id = ?"
    );
    $detail->execute([$view_id]);
    $bk = $detail->fetch();
?>
<?php if ($bk): ?>
<!-- Booking Detail Modal -->
<div id="bookingDetailModal" style="display:flex; position:fixed; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center; padding:16px;">
    <div style="background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:16px; width:100%; max-width:560px; max-height:90vh; overflow-y:auto; box-shadow:var(--shadow-2xl);">
        <!-- Modal Header -->
        <div style="padding:20px 24px; border-bottom:1px solid var(--clr-border); display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; background:var(--clr-surface); z-index:1;">
            <div>
                <h2 style="font-size:17px; font-weight:700; color:var(--clr-text); margin:0;">Booking #<?= (int)$bk['id'] ?></h2>
                <p style="font-size:12px; color:var(--clr-text-muted); margin:2px 0 0;">Detailed view — read only</p>
            </div>
            <a href="<?= BASE_URL ?>/admin/bookings.php<?= booking_query() ?>" class="material-symbols-outlined" style="color:var(--clr-text-muted); font-size:22px; cursor:pointer; text-decoration:none;">close</a>
        </div>
        <!-- Modal Body -->
        <div style="padding:20px 24px; display:flex; flex-direction:column; gap:16px;">

            <?php
            $is_overdue_d = $bk['status'] === 'checked_in' && !empty($bk['end_time']) &&
                ($bk['booking_date'] < $today || ($bk['booking_date'] === $today && $bk['end_time'] < $now_time));
            $status_class_d = match($bk['status']) {
                'booked'     => 'badge-booked',
                'checked_in' => $is_overdue_d ? 'badge-occupied' : 'badge-checked-in',
                'completed'  => 'badge-completed',
                'cancelled'  => 'badge-cancelled',
                default      => '',
            };
            $status_label_d = match($bk['status']) {
                'booked'     => 'Reserved',
                'checked_in' => $is_overdue_d ? 'Overdue' : 'Checked In',
                'completed'  => 'Completed',
                'cancelled'  => 'Cancelled',
                default      => ucfirst($bk['status']),
            };
            ?>

            <!-- Status Banner -->
            <div style="background:var(--clr-surface-low); border:1px solid var(--clr-border); border-radius:10px; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                <span class="badge <?= $status_class_d ?>" style="font-size:13px; padding:5px 14px;"><?= $status_label_d ?></span>
                <?php if ((int)$bk['is_late_checkin'] === 1): ?>
                    <span class="badge badge-late-checkin">Late Check-In</span>
                <?php endif; ?>
                <span style="font-size:12px; color:var(--clr-text-muted);">Created <?= date('M j, Y g:i A', strtotime($bk['created_at'])) ?></span>
            </div>

            <!-- User Info -->
            <div>
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--clr-text-muted); margin-bottom:8px;">Driver / User</div>
                <div style="background:var(--clr-surface-low); border:1px solid var(--clr-border); border-radius:10px; padding:14px 16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                    <div>
                        <a href="<?= BASE_URL ?>/admin/users.php?view=<?= (int)$bk['user_id'] ?>" style="font-size:15px; font-weight:700; color:var(--clr-secondary);"><?= htmlspecialchars($bk['full_name']) ?></a>
                        <div style="font-size:12px; color:var(--clr-text-muted);"><?= htmlspecialchars($bk['email']) ?></div>
                    </div>
                    <div style="text-align:right; font-size:12px; color:var(--clr-text-muted);">
                        <div><?= number_format((float)$bk['reward_points'], 2) ?> pts balance</div>
                        <div><?= (int)$bk['late_departure_count'] ?> late checkouts</div>
                    </div>
                </div>
            </div>

            <!-- Slot Info -->
            <div>
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--clr-text-muted); margin-bottom:8px;">Parking Slot</div>
                <div style="background:var(--clr-surface-low); border:1px solid var(--clr-border); border-radius:10px; padding:14px 16px; display:flex; align-items:center; gap:16px;">
                    <span class="badge" style="background:var(--clr-surface-high); font-size:18px; font-weight:700; padding:6px 16px;"><?= htmlspecialchars($bk['slot_code']) ?></span>
                    <div>
                        <div style="font-weight:600;"><?= htmlspecialchars($bk['zone']) ?></div>
                        <div style="font-size:12px; color:var(--clr-text-muted);"><?= (int)$bk['is_active'] ? 'Slot Active' : 'Slot Inactive' ?></div>
                    </div>
                </div>
            </div>

            <!-- Booking Details -->
            <div>
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--clr-text-muted); margin-bottom:8px;">Booking Details</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <?php
                    function detail_cell(string $label, string $value): string {
                        return "<div style='background:var(--clr-surface-low); border:1px solid var(--clr-border); border-radius:8px; padding:10px 14px;'>
                                    <div style='font-size:11px; color:var(--clr-text-muted); margin-bottom:3px;'>{$label}</div>
                                    <div style='font-size:14px; font-weight:600; color:var(--clr-text);'>{$value}</div>
                                </div>";
                    }
                    echo detail_cell('Booking Date', date('M j, Y', strtotime($bk['booking_date'])));
                    echo detail_cell('Duration', (int)$bk['duration_hours'] . ' hour(s)');
                    $tw = (!empty($bk['start_time']) && !empty($bk['end_time']))
                        ? date('g:i A', strtotime($bk['start_time'])) . ' – ' . date('g:i A', strtotime($bk['end_time']))
                        : '—';
                    echo detail_cell('Scheduled Window', $tw);
                    echo detail_cell('Points Cost', number_format((float)$bk['points_cost']) . ' pts');
                    echo detail_cell('Check-In', !empty($bk['check_in_time']) ? date('g:i A', strtotime($bk['check_in_time'])) : '—');
                    echo detail_cell('Check-Out', !empty($bk['check_out_time']) ? date('g:i A', strtotime($bk['check_out_time'])) : '—');
                    ?>
                </div>
            </div>

            <!-- Penalty Info -->
            <?php if ((float)$bk['penalty_points_deducted'] > 0 || (int)$bk['is_late_checkin'] === 1): ?>
            <div style="background:rgba(220,38,38,0.06); border:1px solid rgba(220,38,38,0.2); border-radius:10px; padding:14px 16px;">
                <div style="font-size:13px; font-weight:700; color:var(--clr-error); margin-bottom:8px;">
                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">warning</span>
                    Penalty Information
                </div>
                <?php if ((int)$bk['is_late_checkin'] === 1): ?>
                    <div style="font-size:12px; color:var(--clr-text-muted); margin-bottom:4px;">Late check-in recorded for this booking.</div>
                <?php endif; ?>
                <?php if ((float)$bk['penalty_points_deducted'] > 0): ?>
                    <div style="font-size:14px; font-weight:700; color:var(--clr-error);">-<?= number_format($bk['penalty_points_deducted'], 2) ?> penalty points deducted</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
        <div style="padding:14px 24px; border-top:1px solid var(--clr-border); display:flex; justify-content:space-between; align-items:center;">
            <a href="<?= BASE_URL ?>/public/export-pdf.php?booking_id=<?= (int)$bk['id'] ?>"
               target="_blank"
               class="btn btn-outline"
               style="font-size:13px; padding:8px 16px; border-radius:8px; display:inline-flex; align-items:center; gap:6px; border-color:var(--clr-secondary); color:var(--clr-secondary); font-weight:600;">
                <span class="material-symbols-outlined" style="font-size:16px;">picture_as_pdf</span>
                <span>Download Receipt PDF</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/bookings.php<?= booking_query() ?>" class="btn btn-outline" style="font-size:13px; padding:8px 20px; border-radius:8px;">Close</a>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
