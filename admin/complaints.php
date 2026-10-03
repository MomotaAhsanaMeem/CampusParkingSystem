<?php
// admin/complaints.php — Complaints Management (Step 2)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// ─── FILTERS ────────────────────────────────────────────────────────────────
$search   = trim($_GET['search']    ?? '');
$zone_filter = trim($_GET['zone']   ?? '');
$date_from   = trim($_GET['date_from'] ?? '');
$date_to     = trim($_GET['date_to']   ?? '');
$page        = max(1, (int) ($_GET['page'] ?? 1));
$per_page    = 20;

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(u_comp.full_name LIKE ? OR u_comp.email LIKE ? OR u_over.full_name LIKE ? OR s.slot_code LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($zone_filter !== '') {
    $where[]  = 's.zone = ?';
    $params[] = $zone_filter;
}
if ($date_from !== '') {
    $where[]  = 'DATE(c.created_at) >= ?';
    $params[] = $date_from;
}
if ($date_to !== '') {
    $where[]  = 'DATE(c.created_at) <= ?';
    $params[] = $date_to;
}

$where_sql = implode(' AND ', $where);

// Count
$count_stmt = $pdo->prepare(
    "SELECT COUNT(*)
       FROM complaints c
       JOIN bookings bb ON bb.id = c.blocked_booking_id
       JOIN bookings ob ON ob.id = c.occupying_booking_id
       JOIN parking_slots s ON s.id = bb.slot_id
       JOIN users u_comp ON u_comp.id = c.complainant_id
       JOIN users u_over ON u_over.id = ob.user_id
      WHERE {$where_sql}"
);
$count_stmt->execute($params);
$total_records = (int) $count_stmt->fetchColumn();
$total_pages   = max(1, (int) ceil($total_records / $per_page));
$page          = min($page, $total_pages);
$offset        = ($page - 1) * $per_page;

// Fetch complaints
$complaints_stmt = $pdo->prepare(
    "SELECT c.id, c.created_at, c.penalty_deducted, c.status,
            c.blocked_booking_id, c.occupying_booking_id,
            c.complainant_id,
            u_comp.id AS complainant_id, u_comp.full_name AS complainant_name, u_comp.email AS complainant_email,
            u_over.id AS overstayer_id, u_over.full_name AS overstayer_name, u_over.email AS overstayer_email,
            bb.booking_date AS blocked_date, bb.start_time AS blocked_start, bb.end_time AS blocked_end,
            ob.booking_date AS occ_date, ob.start_time AS occ_start, ob.end_time AS occ_end,
            s.slot_code, s.zone, s.id AS slot_id
       FROM complaints c
       JOIN bookings bb ON bb.id = c.blocked_booking_id
       JOIN bookings ob ON ob.id = c.occupying_booking_id
       JOIN parking_slots s ON s.id = bb.slot_id
       JOIN users u_comp ON u_comp.id = c.complainant_id
       JOIN users u_over ON u_over.id = ob.user_id
      WHERE {$where_sql}
      ORDER BY c.created_at DESC
      LIMIT {$per_page} OFFSET {$offset}"
);
$complaints_stmt->execute($params);
$complaints = $complaints_stmt->fetchAll();

// Zones for filter
$zones_stmt = $pdo->query("SELECT DISTINCT zone FROM parking_slots ORDER BY zone ASC");
$all_zones  = array_column($zones_stmt->fetchAll(), 'zone');

// Stats
$total_complaints = (int) $pdo->query("SELECT COUNT(*) FROM complaints")->fetchColumn();
$total_penalty    = (float) $pdo->query("SELECT COALESCE(SUM(penalty_deducted),0) FROM complaints")->fetchColumn();
$unique_reporters = (int) $pdo->query("SELECT COUNT(DISTINCT complainant_id) FROM complaints")->fetchColumn();
$unique_offenders = (int) $pdo->query("SELECT COUNT(DISTINCT c.occupying_booking_id) FROM complaints c JOIN bookings b ON b.id = c.occupying_booking_id")->fetchColumn();

function complaint_query(array $extra = []): string {
    $p = array_merge([
        'search'    => $_GET['search']    ?? '',
        'zone'      => $_GET['zone']      ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to'   => $_GET['date_to']   ?? '',
    ], $extra);
    $p = array_filter($p, fn($v) => $v !== '');
    return $p ? '?' . http_build_query($p) : '?';
}

$admin_title      = 'Complaints';
$admin_active_nav = 'complaints';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Bay Obstruction Complaints</h1>
        <p class="admin-page-subtitle">All slot-blockage reports filed by users against overstaying drivers.</p>
    </div>
</div>

<!-- KPI Cards -->
<div class="admin-stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom:20px;">
    <div class="admin-kpi-card">
        <div class="admin-kpi-top"><span class="admin-kpi-label">Total Complaints</span><div class="admin-kpi-icon admin-kpi-icon--error"><span class="material-symbols-outlined">campaign</span></div></div>
        <div class="admin-kpi-value"><?= number_format($total_complaints) ?></div>
        <div class="admin-kpi-sub"><span>All-time reports</span></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-top"><span class="admin-kpi-label">Total Fines Issued</span><div class="admin-kpi-icon admin-kpi-icon--amber"><span class="material-symbols-outlined">payments</span></div></div>
        <div class="admin-kpi-value" style="color:var(--clr-error);"><?= number_format($total_penalty, 2) ?></div>
        <div class="admin-kpi-sub"><span>Points deducted from offenders</span></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-top"><span class="admin-kpi-label">Unique Reporters</span><div class="admin-kpi-icon admin-kpi-icon--cyan"><span class="material-symbols-outlined">person</span></div></div>
        <div class="admin-kpi-value"><?= number_format($unique_reporters) ?></div>
        <div class="admin-kpi-sub"><span>Distinct complainants</span></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-top"><span class="admin-kpi-label">Unique Offenders</span><div class="admin-kpi-icon admin-kpi-icon--error"><span class="material-symbols-outlined">person_off</span></div></div>
        <div class="admin-kpi-value" style="color:var(--clr-amber);"><?= number_format($unique_offenders) ?></div>
        <div class="admin-kpi-sub"><span>Distinct overstayers penalised</span></div>
    </div>
</div>

<!-- Search & Filters -->
<div class="admin-card" style="margin-bottom:16px;">
    <div class="admin-card-body" style="padding:16px 20px;">
        <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:1; min-width:220px;">
                <label style="font-size:12px; font-weight:600; color:var(--clr-text-muted); display:block; margin-bottom:4px;">Search</label>
                <input type="text" name="search" class="form-input" placeholder="Complainant, overstayer name, or slot code…"
                       value="<?= htmlspecialchars($search) ?>" style="font-size:13px; padding:8px 12px;">
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
                <?php if ($search || $zone_filter || $date_from || $date_to): ?>
                    <a href="<?= BASE_URL ?>/admin/complaints.php" class="btn btn-outline" style="font-size:13px; padding:8px 14px; border-radius:8px;">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Complaints Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-card-title">
            <span class="material-symbols-outlined" style="color:var(--clr-secondary);">campaign</span>
            <span>Complaint Records</span>
            <span class="badge" style="background:var(--clr-surface-high); color:var(--clr-text-muted);"><?= number_format($total_records) ?></span>
        </div>
        <span style="font-size:12px; color:var(--clr-text-muted);">Page <?= $page ?> of <?= $total_pages ?></span>
    </div>

    <div class="admin-card-body" style="padding:0;">
        <?php if (empty($complaints)): ?>
            <div class="admin-empty-state">
                <span class="material-symbols-outlined admin-empty-icon">verified</span>
                <div class="admin-empty-title">No Complaints Found</div>
                <div class="admin-empty-desc">
                    <?= ($search || $zone_filter || $date_from || $date_to)
                        ? 'No records match your search. <a href="' . BASE_URL . '/admin/complaints.php">Clear filters</a>.'
                        : 'No slot-blockage complaints have been filed. System is running cleanly.' ?>
                </div>
            </div>
        <?php else: ?>
            <div class="table-wrapper" style="border:none; border-radius:0; box-shadow:none;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Slot & Zone</th>
                            <th>Complainant</th>
                            <th>Blocked Booking</th>
                            <th>Overstayer</th>
                            <th>Occupying Booking</th>
                            <th>Penalty Issued</th>
                            <th>Reported On</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $cm): ?>
                            <tr>
                                <td><strong>#<?= (int)$cm['id'] ?></strong></td>
                                <td>
                                    <span class="badge" style="background:var(--clr-surface-high); font-weight:700;"><?= htmlspecialchars($cm['slot_code']) ?></span><br>
                                    <span style="font-size:11px; color:var(--clr-text-muted);"><?= htmlspecialchars($cm['zone']) ?></span>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/admin/users.php?view=<?= (int)$cm['complainant_id'] ?>"
                                       style="font-weight:600; color:var(--clr-secondary); font-size:13px;"><?= htmlspecialchars($cm['complainant_name']) ?></a><br>
                                    <span style="font-size:11px; color:var(--clr-text-muted);"><?= htmlspecialchars($cm['complainant_email']) ?></span>
                                </td>
                                <td style="font-size:12px;">
                                    <strong>#<?= (int)$cm['blocked_booking_id'] ?></strong><br>
                                    <span style="color:var(--clr-text-muted);"><?= date('M j', strtotime($cm['blocked_date'])) ?></span>
                                    <?php if (!empty($cm['blocked_start']) && !empty($cm['blocked_end'])): ?>
                                        <span style="color:var(--clr-text-muted);">
                                            <?= date('g:i', strtotime($cm['blocked_start'])) ?>–<?= date('g:i A', strtotime($cm['blocked_end'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/admin/users.php?view=<?= (int)$cm['overstayer_id'] ?>"
                                       style="font-weight:600; color:var(--clr-error); font-size:13px;"><?= htmlspecialchars($cm['overstayer_name']) ?></a><br>
                                    <span style="font-size:11px; color:var(--clr-text-muted);"><?= htmlspecialchars($cm['overstayer_email']) ?></span>
                                </td>
                                <td style="font-size:12px;">
                                    <strong>#<?= (int)$cm['occupying_booking_id'] ?></strong><br>
                                    <span style="color:var(--clr-text-muted);"><?= date('M j', strtotime($cm['occ_date'])) ?></span>
                                    <?php if (!empty($cm['occ_start']) && !empty($cm['occ_end'])): ?>
                                        <span style="color:var(--clr-text-muted);">
                                            <?= date('g:i', strtotime($cm['occ_start'])) ?>–<?= date('g:i A', strtotime($cm['occ_end'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($cm['status'] ?? 'resolved') === 'pending'): ?>
                                        <span class="badge" style="background:rgba(245,158,11,0.15); color:#B45309; border:1px solid #F59E0B; font-weight:600; font-size:11px;">
                                            <span class="material-symbols-outlined" style="font-size:12px; vertical-align:middle;">hourglass_top</span>
                                            Pending Checkout
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size:15px; font-weight:700; color:var(--clr-error);">
                                            -<?= number_format((float)$cm['penalty_deducted'], 2) ?> pts
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:12px; color:var(--clr-text-muted);"><?= date('M j, Y g:i A', strtotime($cm['created_at'])) ?></td>
                                <td>
                                    <a href="?view=<?= (int)$cm['id'] ?><?= $search || $zone_filter || $date_from || $date_to ? '&' . http_build_query(['search' => $search, 'zone' => $zone_filter, 'date_from' => $date_from, 'date_to' => $date_to, 'page' => $page]) : '' ?>"
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
            <a href="<?= BASE_URL ?>/admin/complaints.php<?= complaint_query(['page' => $page - 1]) ?>" class="btn btn-outline" style="font-size:12px; padding:5px 12px;">&laquo; Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
            <a href="<?= BASE_URL ?>/admin/complaints.php<?= complaint_query(['page' => $p]) ?>"
               class="btn <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>" style="font-size:12px; padding:5px 12px; border-radius:6px; min-width:34px;"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
            <a href="<?= BASE_URL ?>/admin/complaints.php<?= complaint_query(['page' => $page + 1]) ?>" class="btn btn-outline" style="font-size:12px; padding:5px 12px;">Next &raquo;</a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="admin-card-footer">
        <span>Showing <?= count($complaints) ?> of <?= $total_records ?> complaint(s)</span>
        <span style="display:flex; align-items:center; gap:4px;"><span class="admin-status-indicator"></span><span>Live Database</span></span>
    </div>
    <?php endif; ?>
</div>

<?php
// ─── COMPLAINT DETAIL VIEW ───────────────────────────────────────────────────
$view_id = (int) ($_GET['view'] ?? 0);
if ($view_id > 0):
    $detail = $pdo->prepare(
        "SELECT c.*,
                u_comp.id AS complainant_uid, u_comp.full_name AS complainant_name, u_comp.email AS complainant_email,
                u_comp.reward_points AS comp_points,
                u_over.id AS overstayer_uid, u_over.full_name AS overstayer_name, u_over.email AS overstayer_email,
                u_over.reward_points AS over_points, u_over.late_departure_count AS over_late,
                bb.booking_date AS blocked_date, bb.start_time AS blocked_start, bb.end_time AS blocked_end,
                bb.status AS blocked_status,
                ob.booking_date AS occ_date, ob.start_time AS occ_start, ob.end_time AS occ_end,
                ob.check_in_time AS occ_checkin, ob.check_out_time AS occ_checkout, ob.status AS occ_status,
                ob.penalty_points_deducted AS occ_penalty,
                s.slot_code, s.zone
           FROM complaints c
           JOIN bookings bb ON bb.id = c.blocked_booking_id
           JOIN bookings ob ON ob.id = c.occupying_booking_id
           JOIN parking_slots s ON s.id = bb.slot_id
           JOIN users u_comp ON u_comp.id = c.complainant_id
           JOIN users u_over ON u_over.id = ob.user_id
          WHERE c.id = ?"
    );
    $detail->execute([$view_id]);
    $cmp = $detail->fetch();
?>
<?php if ($cmp): ?>
<!-- Complaint Detail Modal -->
<div style="display:flex; position:fixed; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center; padding:16px;">
    <div style="background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:16px; width:100%; max-width:620px; max-height:90vh; overflow-y:auto; box-shadow:var(--shadow-2xl);">
        <!-- Header -->
        <div style="padding:20px 24px; border-bottom:1px solid var(--clr-border); display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; background:var(--clr-surface); z-index:1;">
            <div>
                <h2 style="font-size:17px; font-weight:700; color:var(--clr-text);">Complaint #<?= (int)$cmp['id'] ?></h2>
                <p style="font-size:12px; color:var(--clr-text-muted);">Filed on <?= date('M j, Y g:i A', strtotime($cmp['created_at'])) ?></p>
            </div>
            <a href="<?= BASE_URL ?>/admin/complaints.php<?= complaint_query() ?>" class="material-symbols-outlined" style="color:var(--clr-text-muted); font-size:22px; cursor:pointer; text-decoration:none;">close</a>
        </div>

        <div style="padding:20px 24px; display:flex; flex-direction:column; gap:18px;">

            <!-- Slot & Fine Summary -->
            <div style="background:rgba(220,38,38,0.06); border:1px solid rgba(220,38,38,0.2); border-radius:12px; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                <div>
                    <div style="font-size:13px; font-weight:700; color:var(--clr-error); margin-bottom:4px;">
                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">campaign</span>
                        Bay Obstruction Report
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <span class="badge" style="background:var(--clr-surface-high); font-size:16px; font-weight:700; padding:6px 16px;"><?= htmlspecialchars($cmp['slot_code']) ?></span>
                        <span style="font-size:13px; color:var(--clr-text-muted);"><?= htmlspecialchars($cmp['zone']) ?></span>
                    </div>
                </div>
                <div style="text-align:right;">
                    <?php if (($cmp['status'] ?? 'resolved') === 'pending'): ?>
                        <div style="font-size:11px; color:#D97706; font-weight:700;">Status: Pending Checkout</div>
                        <div style="font-size:12px; color:var(--clr-text-muted); margin-top:2px;">Reward calculating until slot is emptied</div>
                    <?php else: ?>
                        <div style="font-size:11px; color:var(--clr-text-muted);">Penalty Deducted</div>
                        <div style="font-size:24px; font-weight:800; color:var(--clr-error);">-<?= number_format((float)$cmp['penalty_deducted'], 2) ?> pts</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Two-column: Complainant vs Overstayer -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <!-- Complainant -->
                <div style="background:var(--clr-surface-low); border:1px solid var(--clr-border); border-radius:10px; padding:14px 16px;">
                    <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--clr-success); margin-bottom:10px;">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">thumb_up</span>
                        Complainant (Reporter)
                    </div>
                    <a href="<?= BASE_URL ?>/admin/users.php?view=<?= (int)$cmp['complainant_uid'] ?>"
                       style="font-size:14px; font-weight:700; color:var(--clr-secondary); display:block; margin-bottom:4px;"><?= htmlspecialchars($cmp['complainant_name']) ?></a>
                    <div style="font-size:12px; color:var(--clr-text-muted); margin-bottom:8px;"><?= htmlspecialchars($cmp['complainant_email']) ?></div>
                    <div style="font-size:12px; color:var(--clr-text-muted);">
                        Balance: <strong><?= number_format((float)$cmp['comp_points'], 2) ?> pts</strong>
                    </div>
                    <div style="margin-top:8px; font-size:11px; color:var(--clr-text-muted);">
                        Blocked Booking: <strong>#<?= (int)$cmp['blocked_booking_id'] ?></strong><br>
                        <?= date('M j, Y', strtotime($cmp['blocked_date'])) ?>
                        <?php if (!empty($cmp['blocked_start']) && !empty($cmp['blocked_end'])): ?>
                            &bull; <?= date('g:i', strtotime($cmp['blocked_start'])) ?>–<?= date('g:i A', strtotime($cmp['blocked_end'])) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Overstayer -->
                <div style="background:rgba(220,38,38,0.04); border:1px solid rgba(220,38,38,0.2); border-radius:10px; padding:14px 16px;">
                    <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--clr-error); margin-bottom:10px;">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">timer_off</span>
                        Overstayer (Offender)
                    </div>
                    <a href="<?= BASE_URL ?>/admin/users.php?view=<?= (int)$cmp['overstayer_uid'] ?>"
                       style="font-size:14px; font-weight:700; color:var(--clr-error); display:block; margin-bottom:4px;"><?= htmlspecialchars($cmp['overstayer_name']) ?></a>
                    <div style="font-size:12px; color:var(--clr-text-muted); margin-bottom:8px;"><?= htmlspecialchars($cmp['overstayer_email']) ?></div>
                    <div style="font-size:12px; color:var(--clr-text-muted);">
                        Balance: <strong><?= number_format((float)$cmp['over_points'], 2) ?> pts</strong><br>
                        Late checkouts: <strong style="color:<?= (int)$cmp['over_late'] > 0 ? 'var(--clr-amber)' : 'inherit' ?>"><?= (int)$cmp['over_late'] ?></strong>
                    </div>
                    <div style="margin-top:8px; font-size:11px; color:var(--clr-text-muted);">
                        Occupying Booking: <strong>#<?= (int)$cmp['occupying_booking_id'] ?></strong><br>
                        <?= date('M j, Y', strtotime($cmp['occ_date'])) ?>
                        <?php if (!empty($cmp['occ_start']) && !empty($cmp['occ_end'])): ?>
                            &bull; <?= date('g:i', strtotime($cmp['occ_start'])) ?>–<?= date('g:i A', strtotime($cmp['occ_end'])) ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($cmp['occ_checkin'])): ?>
                        <div style="font-size:11px; color:var(--clr-text-muted); margin-top:4px;">
                            Checked in: <?= date('g:i A', strtotime($cmp['occ_checkin'])) ?>
                            <?php if (!empty($cmp['occ_checkout'])): ?>
                                &bull; Out: <?= date('g:i A', strtotime($cmp['occ_checkout'])) ?>
                            <?php else: ?>
                                <span class="badge badge-occupied" style="font-size:10px;">Still parked</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ((float)($cmp['occ_penalty'] ?? 0) > 0): ?>
                        <div style="font-size:11px; font-weight:700; color:var(--clr-error); margin-top:4px;">
                            Total penalty on booking: -<?= number_format((float)$cmp['occ_penalty'], 2) ?> pts
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- View Links -->
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="<?= BASE_URL ?>/admin/bookings.php?view=<?= (int)$cmp['blocked_booking_id'] ?>"
                   class="btn btn-outline" style="font-size:13px; padding:8px 16px; border-radius:8px; display:inline-flex; align-items:center; gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:15px;">confirmation_number</span>
                    View Blocked Booking #<?= (int)$cmp['blocked_booking_id'] ?>
                </a>
                <a href="<?= BASE_URL ?>/admin/bookings.php?view=<?= (int)$cmp['occupying_booking_id'] ?>"
                   class="btn btn-outline" style="font-size:13px; padding:8px 16px; border-radius:8px; display:inline-flex; align-items:center; gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:15px;">directions_car</span>
                    View Occupying Booking #<?= (int)$cmp['occupying_booking_id'] ?>
                </a>
            </div>
        </div>

        <div style="padding:14px 24px; border-top:1px solid var(--clr-border); text-align:right;">
            <a href="<?= BASE_URL ?>/admin/complaints.php<?= complaint_query() ?>" class="btn btn-outline" style="font-size:13px; padding:8px 20px; border-radius:8px;">Close</a>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
