<?php
// admin/users.php — Users Directory & Detail (Step 2)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// ─── CHECK IF VIEWING A SINGLE USER ─────────────────────────────────────────
$view_user_id = (int) ($_GET['view'] ?? 0);
if ($view_user_id > 0) {
    // Fetch user detail
    $uStmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $uStmt->execute([$view_user_id]);
    $profile = $uStmt->fetch();
    if (!$profile) {
        header('Location: ' . BASE_URL . '/admin/users.php');
        exit;
    }

    // Booking history for this user
    $bhStmt = $pdo->prepare(
        "SELECT b.id, b.booking_date, b.duration_hours, b.points_cost, b.status,
                b.start_time, b.end_time, b.check_in_time, b.check_out_time,
                b.is_late_checkin, b.penalty_points_deducted, b.created_at,
                s.slot_code, s.zone
           FROM bookings b
           JOIN parking_slots s ON s.id = b.slot_id
          WHERE b.user_id = ?
          ORDER BY b.created_at DESC"
    );
    $bhStmt->execute([$view_user_id]);
    $user_bookings = $bhStmt->fetchAll();

    // Point transactions
    $ptStmt = $pdo->prepare(
        'SELECT * FROM point_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20'
    );
    $ptStmt->execute([$view_user_id]);
    $point_txns = $ptStmt->fetchAll();

    // Complaints filed by user
    $complStmt = $pdo->prepare(
        "SELECT c.id, c.created_at, c.penalty_deducted, c.status,
                bb.id AS blocked_bid, bs.slot_code AS blocked_slot,
                ob.id AS occ_bid, os.slot_code AS occ_slot, ou.full_name AS overstayer_name
           FROM complaints c
           JOIN bookings bb ON bb.id = c.blocked_booking_id
           JOIN parking_slots bs ON bs.id = bb.slot_id
           JOIN bookings ob ON ob.id = c.occupying_booking_id
           JOIN parking_slots os ON os.id = ob.slot_id
           JOIN users ou ON ou.id = ob.user_id
          WHERE c.complainant_id = ?
          ORDER BY c.created_at DESC"
    );
    $complStmt->execute([$view_user_id]);
    $user_complaints = $complStmt->fetchAll();

    $today = date('Y-m-d');
    $now_time = date('H:i:s');

    $is_locked = !empty($profile['booking_locked_until']) &&
                 strtotime($profile['booking_locked_until']) > time();

    $admin_title      = 'User: ' . htmlspecialchars($profile['full_name']);
    $admin_active_nav = 'users';
    require_once __DIR__ . '/includes/admin_header.php';
    ?>

    <div class="admin-page-header">
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline" style="font-size:12px; padding:6px 12px; border-radius:8px; display:inline-flex; align-items:center; gap:4px;">
                <span class="material-symbols-outlined" style="font-size:16px;">arrow_back</span>
                Back
            </a>
            <div>
                <h1 class="admin-page-title"><?= htmlspecialchars($profile['full_name']) ?></h1>
                <p class="admin-page-subtitle"><?= htmlspecialchars($profile['email']) ?> &bull; User #<?= (int)$profile['id'] ?></p>
            </div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:320px 1fr; gap:20px; align-items:start; flex-wrap:wrap;">
        <!-- Left Profile Card -->
        <div>
            <div class="admin-card" style="margin-bottom:0;">
                <div class="admin-card-body" style="text-align:center; padding:24px;">
                    <div class="admin-profile-avatar-lg" style="margin:0 auto 16px;">
                        <?= strtoupper(substr($profile['full_name'], 0, 1)) ?><?php
                            $parts = explode(' ', $profile['full_name']);
                            if (count($parts) > 1) echo strtoupper(substr(end($parts), 0, 1));
                        ?>
                    </div>
                    <div style="font-size:18px; font-weight:700; color:var(--clr-text); margin-bottom:4px;"><?= htmlspecialchars($profile['full_name']) ?></div>
                    <div style="font-size:13px; color:var(--clr-text-muted); margin-bottom:12px;"><?= htmlspecialchars($profile['email']) ?></div>
                    <span class="badge <?= $profile['role'] === 'admin' ? 'badge-checked-in' : 'badge-booked' ?>" style="font-size:13px; padding:4px 14px;">
                        <?= ucfirst(htmlspecialchars($profile['role'])) ?>
                    </span>
                    <?php if ($is_locked): ?>
                        <div style="margin-top:10px;">
                            <span class="badge badge-occupied" style="font-size:12px; padding:4px 12px;">
                                <span class="material-symbols-outlined" style="font-size:14px;">lock</span>
                                Booking Locked
                            </span>
                            <div style="font-size:11px; color:var(--clr-text-muted); margin-top:4px;">
                                Until <?= date('M j, g:i A', strtotime($profile['booking_locked_until'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="border-top:1px solid var(--clr-border);">
                    <?php
                    function profile_row(string $icon, string $label, string $value, string $color = ''): void {
                        echo "<div style='display:flex; align-items:center; justify-content:space-between; padding:12px 20px; border-bottom:1px solid var(--clr-border);'>
                                <div style='display:flex; align-items:center; gap:8px; font-size:13px; color:var(--clr-text-muted);'>
                                    <span class='material-symbols-outlined' style='font-size:17px;'>{$icon}</span>
                                    {$label}
                                </div>
                                <span style='font-size:13px; font-weight:700; color:" . ($color ?: 'var(--clr-text)') . ";'>{$value}</span>
                              </div>";
                    }
                    profile_row('star', 'Reward Points', number_format((float)$profile['reward_points'], 2) . ' pts', 'var(--clr-secondary)');
                    profile_row('workspace_premium', 'Package', htmlspecialchars($profile['package_tier'] ?? 'Starter'));
                    profile_row('timer_off', 'Late Checkouts', (int)$profile['late_departure_count'] . ' times', (int)$profile['late_departure_count'] > 0 ? 'var(--clr-amber)' : '');
                    profile_row('login', 'Late Check-Ins', (int)$profile['late_checkin_count'] . ' times', (int)$profile['late_checkin_count'] > 0 ? 'var(--clr-amber)' : '');
                    profile_row('event', 'Member Since', date('M j, Y', strtotime($profile['created_at'])));
                    ?>
                </div>
            </div>
        </div>

        <!-- Right Side -->
        <div style="display:flex; flex-direction:column; gap:20px;">
            <!-- Booking History -->
            <div class="admin-card" style="margin-bottom:0;">
                <div class="admin-card-header">
                    <div class="admin-card-title">
                        <span class="material-symbols-outlined" style="color:var(--clr-secondary);">history</span>
                        <span>Booking History</span>
                        <span class="badge" style="background:var(--clr-surface-high);"><?= count($user_bookings) ?></span>
                    </div>
                </div>
                <div class="admin-card-body" style="padding:0;">
                    <?php if (empty($user_bookings)): ?>
                        <div class="admin-empty-state" style="padding:24px;">
                            <span class="material-symbols-outlined admin-empty-icon">event_busy</span>
                            <div class="admin-empty-title">No Bookings Yet</div>
                        </div>
                    <?php else: ?>
                        <div class="table-wrapper" style="border:none; border-radius:0; box-shadow:none; max-height:380px; overflow-y:auto;">
                            <table class="data-table" style="font-size:13px;">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Slot & Zone</th>
                                        <th>Date</th>
                                        <th>Window</th>
                                        <th>Status</th>
                                        <th>Points</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_bookings as $bk):
                                        $is_od = $bk['status'] === 'checked_in' && !empty($bk['end_time']) &&
                                            ($bk['booking_date'] < $today || ($bk['booking_date'] === $today && $bk['end_time'] < $now_time));
                                        $sc = match($bk['status']) {
                                            'booked'     => 'badge-booked',
                                            'checked_in' => $is_od ? 'badge-occupied' : 'badge-checked-in',
                                            'completed'  => 'badge-completed',
                                            'cancelled'  => 'badge-cancelled',
                                            default      => '',
                                        };
                                        $sl = match($bk['status']) {
                                            'booked'     => 'Reserved',
                                            'checked_in' => $is_od ? 'Overdue' : 'Active',
                                            'completed'  => 'Done',
                                            'cancelled'  => 'Cancelled',
                                            default      => ucfirst($bk['status']),
                                        };
                                        $tw = (!empty($bk['start_time']) && !empty($bk['end_time']))
                                            ? date('g:i', strtotime($bk['start_time'])) . '–' . date('g:i A', strtotime($bk['end_time']))
                                            : '—';
                                    ?>
                                        <tr>
                                            <td>#<?= (int)$bk['id'] ?></td>
                                            <td>
                                                <span class="badge" style="background:var(--clr-surface-high);"><?= htmlspecialchars($bk['slot_code']) ?></span>
                                                <span style="font-size:11px; color:var(--clr-text-muted);"><?= htmlspecialchars($bk['zone']) ?></span>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($bk['booking_date'])) ?></td>
                                            <td style="font-size:11px;"><?= $tw ?></td>
                                            <td>
                                                <span class="badge <?= $sc ?>" style="font-size:11px;"><?= $sl ?></span>
                                                <?php if ((int)$bk['is_late_checkin']): ?>
                                                    <span class="badge badge-late-checkin" style="font-size:10px;">Late In</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= (int)$bk['points_cost'] ?> pts
                                                <?php if ((float)$bk['penalty_points_deducted'] > 0): ?>
                                                    <div style="font-size:10px; color:var(--clr-error);">-<?= number_format($bk['penalty_points_deducted'], 2) ?></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Point Transactions -->
            <?php if (!empty($point_txns)): ?>
            <div class="admin-card" style="margin-bottom:0;">
                <div class="admin-card-header">
                    <div class="admin-card-title">
                        <span class="material-symbols-outlined" style="color:var(--clr-secondary);">toll</span>
                        <span>Recent Point Transactions</span>
                        <span class="badge" style="background:var(--clr-surface-high);">Last 20</span>
                    </div>
                </div>
                <div class="admin-card-body" style="padding:0;">
                    <div class="table-wrapper" style="border:none; border-radius:0; box-shadow:none; max-height:280px; overflow-y:auto;">
                        <table class="data-table" style="font-size:13px;">
                            <thead>
                                <tr><th>Date</th><th>Type</th><th>Points</th><th>Description</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($point_txns as $tx): ?>
                                    <tr>
                                        <td style="font-size:11px;"><?= date('M j, g:i A', strtotime($tx['created_at'])) ?></td>
                                        <td><span class="badge" style="background:var(--clr-surface-high); font-size:11px;"><?= htmlspecialchars($tx['type']) ?></span></td>
                                        <td style="font-weight:700; color:<?= (float)$tx['points'] >= 0 ? 'var(--clr-success)' : 'var(--clr-error)' ?>;">
                                            <?= (float)$tx['points'] >= 0 ? '+' : '' ?><?= number_format((float)$tx['points'], 2) ?>
                                        </td>
                                        <td style="font-size:11px; color:var(--clr-text-muted);"><?= htmlspecialchars($tx['description'] ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Complaints filed -->
            <?php if (!empty($user_complaints)): ?>
            <div class="admin-card" style="margin-bottom:0;">
                <div class="admin-card-header">
                    <div class="admin-card-title">
                        <span class="material-symbols-outlined" style="color:var(--clr-amber);">campaign</span>
                        <span>Complaints Filed</span>
                        <span class="badge" style="background:var(--clr-surface-high);"><?= count($user_complaints) ?></span>
                    </div>
                </div>
                <div class="admin-card-body" style="padding:0;">
                    <div class="table-wrapper" style="border:none; border-radius:0; box-shadow:none;">
                        <table class="data-table" style="font-size:13px;">
                            <thead>
                                <tr><th>ID</th><th>Slot</th><th>Against</th><th>Penalty Issued</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_complaints as $cm): ?>
                                    <tr>
                                        <td>#<?= (int)$cm['id'] ?></td>
                                        <td><span class="badge" style="background:var(--clr-surface-high);"><?= htmlspecialchars($cm['blocked_slot']) ?></span></td>
                                        <td><?= htmlspecialchars($cm['overstayer_name']) ?></td>
                                        <td>
                                            <?php if (($cm['status'] ?? 'resolved') === 'pending'): ?>
                                                <span class="badge" style="background:rgba(245,158,11,0.15); color:#B45309; font-size:11px;">Pending</span>
                                            <?php else: ?>
                                                <span style="color:var(--clr-error); font-weight:700;">-<?= number_format((float)$cm['penalty_deducted'], 2) ?> pts</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:11px;"><?= date('M j, Y', strtotime($cm['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    require_once __DIR__ . '/includes/admin_footer.php';
    exit;
}

// ─── USER DIRECTORY LIST VIEW ────────────────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role']   ?? '');
$lock_filter = trim($_GET['locked'] ?? '');
$page        = max(1, (int) ($_GET['page'] ?? 1));
$per_page    = 25;

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}
if (in_array($role_filter, ['user', 'admin'], true)) {
    $where[]  = 'u.role = ?';
    $params[] = $role_filter;
}
if ($lock_filter === '1') {
    $where[]  = '(u.booking_locked_until IS NOT NULL AND u.booking_locked_until > NOW())';
}

$where_sql = implode(' AND ', $where);

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$where_sql}");
$count_stmt->execute($params);
$total_records = (int) $count_stmt->fetchColumn();
$total_pages   = max(1, (int) ceil($total_records / $per_page));
$page          = min($page, $total_pages);
$offset        = ($page - 1) * $per_page;

$users_stmt = $pdo->prepare(
    "SELECT u.id, u.full_name, u.email, u.role, u.reward_points, u.package_tier,
            u.late_departure_count, u.late_checkin_count, u.booking_locked_until, u.created_at,
            (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) AS total_bookings,
            (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id AND b.status IN ('booked','checked_in')) AS active_bookings
       FROM users u
      WHERE {$where_sql}
      ORDER BY u.created_at DESC
      LIMIT {$per_page} OFFSET {$offset}"
);
$users_stmt->execute($params);
$users = $users_stmt->fetchAll();

// Stats
$total_users  = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$total_admins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$locked_count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE booking_locked_until IS NOT NULL AND booking_locked_until > NOW()")->fetchColumn();

function user_query(array $extra = []): string {
    $p = array_merge([
        'search' => $_GET['search'] ?? '',
        'role'   => $_GET['role']   ?? '',
        'locked' => $_GET['locked'] ?? '',
    ], $extra);
    $p = array_filter($p, fn($v) => $v !== '');
    return $p ? '?' . http_build_query($p) : '?';
}

$admin_title      = 'Users Directory';
$admin_active_nav = 'users';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Users Directory</h1>
        <p class="admin-page-subtitle">View, search and inspect all registered accounts and their parking activity.</p>
    </div>
</div>

<!-- KPI Cards -->
<div class="admin-stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom:20px;">
    <div class="admin-kpi-card">
        <div class="admin-kpi-top"><span class="admin-kpi-label">Total Users</span><div class="admin-kpi-icon admin-kpi-icon--cyan"><span class="material-symbols-outlined">group</span></div></div>
        <div class="admin-kpi-value"><?= number_format($total_users) ?></div>
        <div class="admin-kpi-sub"><span>Registered student/staff</span></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-top"><span class="admin-kpi-label">Admins</span><div class="admin-kpi-icon admin-kpi-icon--purple"><span class="material-symbols-outlined">admin_panel_settings</span></div></div>
        <div class="admin-kpi-value"><?= number_format($total_admins) ?></div>
        <div class="admin-kpi-sub"><span>System administrators</span></div>
    </div>
    <div class="admin-kpi-card" style="<?= $locked_count > 0 ? 'border-top:3px solid var(--clr-error);' : '' ?>">
        <div class="admin-kpi-top"><span class="admin-kpi-label">Booking Locked</span><div class="admin-kpi-icon admin-kpi-icon--error"><span class="material-symbols-outlined">lock</span></div></div>
        <div class="admin-kpi-value" style="color:<?= $locked_count > 0 ? 'var(--clr-error)' : 'var(--clr-text)' ?>"><?= number_format($locked_count) ?></div>
        <div class="admin-kpi-sub"><span>Currently serving freeze</span></div>
    </div>
</div>

<!-- Search & Filter -->
<div class="admin-card" style="margin-bottom:16px;">
    <div class="admin-card-body" style="padding:16px 20px;">
        <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:1; min-width:220px;">
                <label style="font-size:12px; font-weight:600; color:var(--clr-text-muted); display:block; margin-bottom:4px;">Search</label>
                <input type="text" name="search" class="form-input" placeholder="Name or email…"
                       value="<?= htmlspecialchars($search) ?>" style="font-size:13px; padding:8px 12px;">
            </div>
            <div style="min-width:130px;">
                <label style="font-size:12px; font-weight:600; color:var(--clr-text-muted); display:block; margin-bottom:4px;">Role</label>
                <select name="role" class="form-input" style="font-size:13px; padding:8px 10px;">
                    <option value="">All Roles</option>
                    <option value="user"  <?= $role_filter === 'user'  ? 'selected' : '' ?>>Users</option>
                    <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admins</option>
                </select>
            </div>
            <div style="min-width:160px;">
                <label style="font-size:12px; font-weight:600; color:var(--clr-text-muted); display:block; margin-bottom:4px;">Account Status</label>
                <select name="locked" class="form-input" style="font-size:13px; padding:8px 10px;">
                    <option value="">All Accounts</option>
                    <option value="1" <?= $lock_filter === '1' ? 'selected' : '' ?>>Booking Locked Only</option>
                </select>
            </div>
            <div style="display:flex; gap:8px; align-items:flex-end;">
                <button type="submit" class="btn btn-primary" style="font-size:13px; padding:8px 18px; border-radius:8px;">
                    <span class="material-symbols-outlined" style="font-size:16px;">search</span>
                    Search
                </button>
                <?php if ($search || $role_filter || $lock_filter): ?>
                    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline" style="font-size:13px; padding:8px 14px; border-radius:8px;">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-card-title">
            <span class="material-symbols-outlined" style="color:var(--clr-secondary);">group</span>
            <span>Registered Accounts</span>
            <span class="badge" style="background:var(--clr-surface-high); color:var(--clr-text-muted);"><?= number_format($total_records) ?></span>
        </div>
        <span style="font-size:12px; color:var(--clr-text-muted);">Page <?= $page ?> of <?= $total_pages ?></span>
    </div>

    <div class="admin-card-body" style="padding:0;">
        <?php if (empty($users)): ?>
            <div class="admin-empty-state">
                <span class="material-symbols-outlined admin-empty-icon">person_off</span>
                <div class="admin-empty-title">No Users Found</div>
                <div class="admin-empty-desc">No accounts match the search criteria.</div>
            </div>
        <?php else: ?>
            <div class="table-wrapper" style="border:none; border-radius:0; box-shadow:none;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name / Email</th>
                            <th>Role</th>
                            <th>Points Balance</th>
                            <th>Package</th>
                            <th>Late Activity</th>
                            <th>Account Status</th>
                            <th>Bookings</th>
                            <th>Joined</th>
                            <th>View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u):
                            $is_locked_u = !empty($u['booking_locked_until']) && strtotime($u['booking_locked_until']) > time();
                        ?>
                            <tr>
                                <td><strong>#<?= (int)$u['id'] ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($u['full_name']) ?></strong><br>
                                    <span style="font-size:11px; color:var(--clr-text-muted);"><?= htmlspecialchars($u['email']) ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $u['role'] === 'admin' ? 'badge-checked-in' : 'badge-booked' ?>" style="font-size:11px;">
                                        <?= ucfirst(htmlspecialchars($u['role'])) ?>
                                    </span>
                                </td>
                                <td style="font-weight:700; color:var(--clr-secondary);"><?= number_format((float)$u['reward_points'], 2) ?> pts</td>
                                <td><?= htmlspecialchars($u['package_tier'] ?? 'Starter') ?></td>
                                <td style="font-size:12px;">
                                    <?php if ((int)$u['late_departure_count'] > 0): ?>
                                        <div style="color:var(--clr-amber);">
                                            <span class="material-symbols-outlined" style="font-size:14px;">timer_off</span>
                                            <?= (int)$u['late_departure_count'] ?> late out
                                        </div>
                                    <?php endif; ?>
                                    <?php if ((int)$u['late_checkin_count'] > 0): ?>
                                        <div style="color:var(--clr-amber);">
                                            <span class="material-symbols-outlined" style="font-size:14px;">login</span>
                                            <?= (int)$u['late_checkin_count'] ?> late in
                                        </div>
                                    <?php endif; ?>
                                    <?php if ((int)$u['late_departure_count'] === 0 && (int)$u['late_checkin_count'] === 0): ?>
                                        <span style="color:var(--clr-success);">Clean</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_locked_u): ?>
                                        <span class="badge badge-occupied" style="font-size:11px;">
                                            <span class="material-symbols-outlined" style="font-size:13px;">lock</span>
                                            Locked
                                        </span>
                                        <div style="font-size:10px; color:var(--clr-text-muted); margin-top:3px;">
                                            Until <?= date('g:i A', strtotime($u['booking_locked_until'])) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge badge-completed" style="font-size:11px;">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:12px;">
                                    <strong><?= (int)$u['total_bookings'] ?></strong> total<br>
                                    <?php if ((int)$u['active_bookings'] > 0): ?>
                                        <span style="color:var(--clr-success);"><?= (int)$u['active_bookings'] ?> active</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:11px; color:var(--clr-text-muted);"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/admin/users.php?view=<?= (int)$u['id'] ?>" class="btn-checkin" style="font-size:11px; padding:5px 10px; background:var(--clr-secondary);">
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
            <a href="<?= BASE_URL ?>/admin/users.php<?= user_query(['page' => $page - 1]) ?>" class="btn btn-outline" style="font-size:12px; padding:5px 12px;">&laquo; Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
            <a href="<?= BASE_URL ?>/admin/users.php<?= user_query(['page' => $p]) ?>"
               class="btn <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>" style="font-size:12px; padding:5px 12px; border-radius:6px; min-width:34px;"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
            <a href="<?= BASE_URL ?>/admin/users.php<?= user_query(['page' => $page + 1]) ?>" class="btn btn-outline" style="font-size:12px; padding:5px 12px;">Next &raquo;</a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="admin-card-footer">
        <span>Showing <?= count($users) ?> of <?= $total_records ?> account(s)</span>
        <span style="display:flex; align-items:center; gap:4px;"><span class="admin-status-indicator"></span><span>Live Database</span></span>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
