<?php
// admin/slots.php — Parking Slot Management (Step 2)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$errors   = [];
$success  = '';

// ─── HANDLE POST ACTIONS ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD SLOT
    if ($action === 'add_slot') {
        $slot_code = strtoupper(trim($_POST['slot_code'] ?? ''));
        $zone      = trim($_POST['zone'] ?? '');
        if ($slot_code === '') {
            $errors[] = 'Slot code is required.';
        } elseif (!preg_match('/^[A-Z0-9]{1,10}$/', $slot_code)) {
            $errors[] = 'Slot code must be 1–10 alphanumeric characters (e.g. A1, B12).';
        }
        if ($zone === '') {
            $errors[] = 'Zone is required.';
        }
        if (empty($errors)) {
            // Check uniqueness
            $chk = $pdo->prepare('SELECT id FROM parking_slots WHERE slot_code = ?');
            $chk->execute([$slot_code]);
            if ($chk->fetch()) {
                $errors[] = "Slot code '{$slot_code}' already exists.";
            } else {
                $ins = $pdo->prepare('INSERT INTO parking_slots (slot_code, zone, is_active) VALUES (?, ?, 1)');
                $ins->execute([$slot_code, $zone]);
                $success = "Slot '{$slot_code}' added successfully.";
            }
        }
    }

    // EDIT SLOT
    elseif ($action === 'edit_slot') {
        $id        = (int) ($_POST['slot_id'] ?? 0);
        $slot_code = strtoupper(trim($_POST['slot_code'] ?? ''));
        $zone      = trim($_POST['zone'] ?? '');
        if ($id <= 0)           $errors[] = 'Invalid slot ID.';
        if ($slot_code === '')  $errors[] = 'Slot code is required.';
        elseif (!preg_match('/^[A-Z0-9]{1,10}$/', $slot_code)) $errors[] = 'Slot code must be 1–10 alphanumeric characters.';
        if ($zone === '')       $errors[] = 'Zone is required.';
        if (empty($errors)) {
            // Uniqueness check excluding current slot
            $chk = $pdo->prepare('SELECT id FROM parking_slots WHERE slot_code = ? AND id != ?');
            $chk->execute([$slot_code, $id]);
            if ($chk->fetch()) {
                $errors[] = "Slot code '{$slot_code}' is already taken by another slot.";
            } else {
                $upd = $pdo->prepare('UPDATE parking_slots SET slot_code = ?, zone = ? WHERE id = ?');
                $upd->execute([$slot_code, $zone, $id]);
                $success = "Slot #{$id} updated successfully.";
            }
        }
    }

    // TOGGLE ACTIVE/INACTIVE
    elseif ($action === 'toggle_slot') {
        $id        = (int) ($_POST['slot_id'] ?? 0);
        $new_state = (int) ($_POST['new_state'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'Invalid slot ID.';
        } else {
            // Prevent deactivating a slot that is currently checked-in
            if ($new_state === 0) {
                $activeChk = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE slot_id = ? AND status = 'checked_in'");
                $activeChk->execute([$id]);
                if ((int) $activeChk->fetchColumn() > 0) {
                    $errors[] = 'Cannot deactivate a slot while a vehicle is checked in.';
                }
            }
            if (empty($errors)) {
                $pdo->prepare('UPDATE parking_slots SET is_active = ? WHERE id = ?')->execute([$new_state, $id]);
                $success = 'Slot ' . ($new_state ? 'activated' : 'deactivated') . ' successfully.';
            }
        }
    }
}

// ─── FETCH SLOTS WITH CURRENT STATUS ────────────────────────────────────────
$slots_stmt = $pdo->query(
    "SELECT s.id, s.slot_code, s.zone, s.is_active, s.created_at,
            (SELECT COUNT(*) FROM bookings b WHERE b.slot_id = s.id AND b.status = 'checked_in') AS checked_in_now,
            (SELECT COUNT(*) FROM bookings b WHERE b.slot_id = s.id AND b.status = 'booked'     AND b.booking_date >= CURDATE()) AS upcoming_bookings,
            (SELECT u.full_name FROM bookings b JOIN users u ON u.id = b.user_id WHERE b.slot_id = s.id AND b.status = 'checked_in' LIMIT 1) AS occupier_name
       FROM parking_slots s
      ORDER BY s.zone ASC, s.slot_code ASC"
);
$slots = $slots_stmt->fetchAll();

// Unique zones (for filter + add-form dropdown)
$zones_stmt = $pdo->query("SELECT DISTINCT zone FROM parking_slots ORDER BY zone ASC");
$db_zones   = array_column($zones_stmt->fetchAll(), 'zone');
// Always include the canonical 3 campus zones
$canonical_zones = ['Central Campus', 'North Campus', 'South Campus'];
$all_zones = array_unique(array_merge($canonical_zones, $db_zones));
sort($all_zones);

// Filter
$zone_filter = trim($_GET['zone'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
if ($zone_filter !== '') {
    $slots = array_filter($slots, fn($s) => $s['zone'] === $zone_filter);
}
if ($status_filter === 'active') {
    $slots = array_filter($slots, fn($s) => (int)$s['is_active'] === 1);
} elseif ($status_filter === 'inactive') {
    $slots = array_filter($slots, fn($s) => (int)$s['is_active'] === 0);
} elseif ($status_filter === 'occupied') {
    $slots = array_filter($slots, fn($s) => (int)$s['checked_in_now'] > 0);
} elseif ($status_filter === 'reserved') {
    $slots = array_filter($slots, fn($s) => (int)$s['upcoming_bookings'] > 0);
}
$slots = array_values($slots);

// Stats
$total_slots    = count($slots);
$active_count   = count(array_filter($slots, fn($s) => (int)$s['is_active'] === 1));
$occupied_count = count(array_filter($slots, fn($s) => (int)$s['checked_in_now'] > 0));

$admin_title      = 'Slot Management';
$admin_active_nav = 'slots';
require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Parking Slot Management</h1>
        <p class="admin-page-subtitle">View, add, edit and toggle parking slots across all campus zones.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openModal('addSlotModal')"
            style="display:inline-flex; align-items:center; gap:6px; padding:10px 18px; font-size:13px; border-radius:8px;">
        <span class="material-symbols-outlined" style="font-size:18px;">add</span>
        Add New Slot
    </button>
</div>

<!-- Alerts -->
<?php if (!empty($errors)): ?>
    <div class="alert alert-error" style="margin-bottom:16px;">
        <span class="material-symbols-outlined alert-icon">error</span>
        <div><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom:16px;">
        <span class="material-symbols-outlined alert-icon">check_circle</span>
        <div><?= htmlspecialchars($success) ?></div>
    </div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="admin-stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom:20px;">
    <div class="admin-kpi-card">
        <div class="admin-kpi-top">
            <span class="admin-kpi-label">Total Slots</span>
            <div class="admin-kpi-icon admin-kpi-icon--purple"><span class="material-symbols-outlined">local_parking</span></div>
        </div>
        <div class="admin-kpi-value"><?= count($pdo->query("SELECT id FROM parking_slots")->fetchAll()) ?></div>
        <div class="admin-kpi-sub"><span>Across all zones</span></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-top">
            <span class="admin-kpi-label">Active</span>
            <div class="admin-kpi-icon admin-kpi-icon--emerald"><span class="material-symbols-outlined">check_circle</span></div>
        </div>
        <div class="admin-kpi-value text-emerald"><?= (int)$pdo->query("SELECT COUNT(*) FROM parking_slots WHERE is_active=1")->fetchColumn() ?></div>
        <div class="admin-kpi-sub"><span>Available for booking</span></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-top">
            <span class="admin-kpi-label">Inactive</span>
            <div class="admin-kpi-icon admin-kpi-icon--error"><span class="material-symbols-outlined">block</span></div>
        </div>
        <div class="admin-kpi-value" style="color:var(--clr-error)"><?= (int)$pdo->query("SELECT COUNT(*) FROM parking_slots WHERE is_active=0")->fetchColumn() ?></div>
        <div class="admin-kpi-sub"><span>Deactivated slots</span></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-top">
            <span class="admin-kpi-label">Occupied Now</span>
            <div class="admin-kpi-icon admin-kpi-icon--amber"><span class="material-symbols-outlined">directions_car</span></div>
        </div>
        <div class="admin-kpi-value" style="color:var(--clr-amber)"><?= (int)$pdo->query("SELECT COUNT(DISTINCT slot_id) FROM bookings WHERE status='checked_in'")->fetchColumn() ?></div>
        <div class="admin-kpi-sub"><span>Vehicles checked in</span></div>
    </div>
</div>

<!-- Slots Table Card -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-card-title">
            <span class="material-symbols-outlined" style="color:var(--clr-secondary);">local_parking</span>
            <span>All Parking Slots</span>
            <span class="badge" style="background:var(--clr-surface-high); color:var(--clr-text-muted);"><?= count($slots) ?></span>
        </div>
        <!-- Filters -->
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <select name="zone" class="form-input" style="font-size:12px; padding:6px 10px; height:34px; width:auto; min-width:150px;">
                    <option value="">All Zones</option>
                    <?php foreach ($all_zones as $z): ?>
                        <option value="<?= htmlspecialchars($z) ?>" <?= $zone_filter === $z ? 'selected' : '' ?>><?= htmlspecialchars($z) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-input" style="font-size:12px; padding:6px 10px; height:34px; width:auto; min-width:130px;">
                    <option value="">All Status</option>
                    <option value="active"   <?= $status_filter === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="occupied" <?= $status_filter === 'occupied' ? 'selected' : '' ?>>Occupied Now</option>
                    <option value="reserved" <?= $status_filter === 'reserved' ? 'selected' : '' ?>>Reserved</option>
                </select>
                <button type="submit" class="btn btn-outline" style="font-size:12px; padding:6px 14px; height:34px;">Filter</button>
                <?php if ($zone_filter || $status_filter): ?>
                    <a href="<?= BASE_URL ?>/admin/slots.php" class="btn btn-ghost" style="font-size:12px; padding:6px 10px; height:34px;">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="admin-card-body" style="padding:0;">
        <?php if (empty($slots)): ?>
            <div class="admin-empty-state">
                <span class="material-symbols-outlined admin-empty-icon">local_parking</span>
                <div class="admin-empty-title">No Slots Found</div>
                <div class="admin-empty-desc">No parking slots match the current filter. <a href="<?= BASE_URL ?>/admin/slots.php">Clear filters</a> or add a new slot.</div>
            </div>
        <?php else: ?>
            <div class="table-wrapper" style="border:none; border-radius:0; box-shadow:none;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Slot Code</th>
                            <th>Zone</th>
                            <th>Status</th>
                            <th>Current Occupancy</th>
                            <th>Upcoming Bookings</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slots as $slot): ?>
                            <tr>
                                <td><strong>#<?= (int)$slot['id'] ?></strong></td>
                                <td>
                                    <span class="badge" style="background:var(--clr-surface-high); font-size:14px; font-weight:700; padding:4px 12px;">
                                        <?= htmlspecialchars($slot['slot_code']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($slot['zone']) ?></td>
                                <td>
                                    <?php if ((int)$slot['is_active'] === 1): ?>
                                        <span class="badge badge-completed">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-cancelled">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$slot['checked_in_now'] > 0): ?>
                                        <span class="badge badge-occupied" style="background:#FEE2E2; color:#991B1B;">
                                            <span class="material-symbols-outlined" style="font-size:13px;">directions_car</span>
                                            Occupied
                                        </span>
                                        <?php if ($slot['occupier_name']): ?>
                                            <div style="font-size:11px; color:var(--clr-text-muted); margin-top:3px;"><?= htmlspecialchars($slot['occupier_name']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--clr-text-muted); font-size:12px;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$slot['upcoming_bookings'] > 0): ?>
                                        <span class="badge badge-booked"><?= (int)$slot['upcoming_bookings'] ?> booked</span>
                                    <?php else: ?>
                                        <span style="color:var(--clr-text-muted); font-size:12px;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:12px; color:var(--clr-text-muted);"><?= date('M j, Y', strtotime($slot['created_at'])) ?></td>
                                <td>
                                    <div style="display:flex; gap:6px; align-items:center;">
                                        <!-- Edit -->
                                        <button type="button" class="btn-checkin" style="font-size:11px; padding:5px 10px;"
                                                onclick="openEditModal(<?= (int)$slot['id'] ?>, '<?= htmlspecialchars(addslashes($slot['slot_code'])) ?>', '<?= htmlspecialchars(addslashes($slot['zone'])) ?>')">
                                            <span class="material-symbols-outlined" style="font-size:14px;">edit</span>
                                            Edit
                                        </button>
                                        <!-- Toggle Active -->
                                        <?php if ((int)$slot['is_active'] === 1): ?>
                                            <form method="POST" onsubmit="return confirm('Deactivate slot <?= htmlspecialchars(addslashes($slot['slot_code'])) ?>? Users will not be able to book it until reactivated.');">
                                                <input type="hidden" name="action"    value="toggle_slot">
                                                <input type="hidden" name="slot_id"   value="<?= (int)$slot['id'] ?>">
                                                <input type="hidden" name="new_state" value="0">
                                                <button type="submit" class="btn-cancel-action" style="font-size:11px; padding:5px 10px;">
                                                    <span class="material-symbols-outlined" style="font-size:14px;">block</span>
                                                    Deactivate
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST">
                                                <input type="hidden" name="action"    value="toggle_slot">
                                                <input type="hidden" name="slot_id"   value="<?= (int)$slot['id'] ?>">
                                                <input type="hidden" name="new_state" value="1">
                                                <button type="submit" class="btn-checkin" style="font-size:11px; padding:5px 10px; background:var(--clr-success);">
                                                    <span class="material-symbols-outlined" style="font-size:14px;">check_circle</span>
                                                    Activate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="admin-card-footer">
        <span>Showing <?= count($slots) ?> slot(s)</span>
        <span style="display:flex; align-items:center; gap:4px;">
            <span class="admin-status-indicator"></span>
            <span>Live Database</span>
        </span>
    </div>
</div>

<!-- ── ADD SLOT MODAL ─────────────────────────────────────────────────────── -->
<div id="addSlotModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center; padding:16px;">
    <div style="background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:16px; padding:28px; width:100%; max-width:420px; box-shadow:var(--shadow-2xl);">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
            <h2 style="font-size:18px; font-weight:700; color:var(--clr-text);">Add New Parking Slot</h2>
            <button type="button" onclick="closeModal('addSlotModal')" style="background:none; border:none; cursor:pointer; color:var(--clr-text-muted); font-size:22px;" class="material-symbols-outlined">close</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_slot">
            <div class="form-group">
                <label class="form-label">Slot Code *</label>
                <input type="text" name="slot_code" class="form-input" placeholder="e.g. A1, B12, C3"
                       pattern="[A-Za-z0-9]{1,10}" maxlength="10" required style="text-transform:uppercase;">
                <span class="form-hint">1–10 alphanumeric characters (letters will be uppercased)</span>
            </div>
            <div class="form-group">
                <label class="form-label">Zone *</label>
                <select name="zone" class="form-input" required>
                    <option value="">— Select Zone —</option>
                    <?php foreach ($all_zones as $z): ?>
                        <option value="<?= htmlspecialchars($z) ?>"><?= htmlspecialchars($z) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="button" onclick="closeModal('addSlotModal')" class="btn btn-outline" style="flex:1;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1; border-radius:8px;">Add Slot</button>
            </div>
        </form>
    </div>
</div>

<!-- ── EDIT SLOT MODAL ────────────────────────────────────────────────────── -->
<div id="editSlotModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center; padding:16px;">
    <div style="background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:16px; padding:28px; width:100%; max-width:420px; box-shadow:var(--shadow-2xl);">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
            <h2 style="font-size:18px; font-weight:700; color:var(--clr-text);">Edit Parking Slot</h2>
            <button type="button" onclick="closeModal('editSlotModal')" style="background:none; border:none; cursor:pointer; color:var(--clr-text-muted); font-size:22px;" class="material-symbols-outlined">close</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action"  value="edit_slot">
            <input type="hidden" name="slot_id" id="edit_slot_id">
            <div class="form-group">
                <label class="form-label">Slot Code *</label>
                <input type="text" name="slot_code" id="edit_slot_code" class="form-input"
                       pattern="[A-Za-z0-9]{1,10}" maxlength="10" required style="text-transform:uppercase;">
            </div>
            <div class="form-group">
                <label class="form-label">Zone *</label>
                <select name="zone" id="edit_slot_zone" class="form-input" required>
                    <option value="">— Select Zone —</option>
                    <?php foreach ($all_zones as $z): ?>
                        <option value="<?= htmlspecialchars($z) ?>"><?= htmlspecialchars($z) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="button" onclick="closeModal('editSlotModal')" class="btn btn-outline" style="flex:1;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1; border-radius:8px;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    var el = document.getElementById(id);
    if (el) { el.style.display = 'flex'; }
}
function closeModal(id) {
    var el = document.getElementById(id);
    if (el) { el.style.display = 'none'; }
}
function openEditModal(id, code, zone) {
    document.getElementById('edit_slot_id').value   = id;
    document.getElementById('edit_slot_code').value = code;
    var sel = document.getElementById('edit_slot_zone');
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === zone) { sel.selectedIndex = i; break; }
    }
    openModal('editSlotModal');
}
// Close modal on backdrop click
document.querySelectorAll('#addSlotModal, #editSlotModal').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal(modal.id);
    });
});
// Auto-uppercase slot code input
document.querySelectorAll('input[name="slot_code"]').forEach(function(inp) {
    inp.addEventListener('input', function() { this.value = this.value.toUpperCase(); });
});
<?php if (!empty($errors) && isset($_POST['action']) && $_POST['action'] === 'add_slot'): ?>
    openModal('addSlotModal');
<?php elseif (!empty($errors) && isset($_POST['action']) && $_POST['action'] === 'edit_slot'): ?>
    openModal('editSlotModal');
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
