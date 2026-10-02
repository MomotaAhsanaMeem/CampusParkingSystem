<?php
// admin/profile.php — Admin Profile & Security Settings
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$user = current_user();
$admin_id = (int) $user['id'];

$success_msg = '';
$error_msg   = '';

// Fetch current administrative profile details directly from database
$stmt = $pdo->prepare('SELECT id, full_name, email, role, password_hash, created_at FROM users WHERE id = ?');
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin) {
    header('Location: ' . BASE_URL . '/admin/logout.php');
    exit;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // ─────────────────────────────────────────────────────────────
    // 1. UPDATE PROFILE DETAILS (Full Name & Email)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');

        if ($full_name === '' || $email === '') {
            $error_msg = 'Full name and email are required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = 'Please enter a valid email address.';
        } else {
            // Check if email already belongs to another user
            $dupCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $dupCheck->execute([$email, $admin_id]);
            if ($dupCheck->fetch()) {
                $error_msg = 'This email address is already in use by another account.';
            } else {
                $updateStmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?');
                $updateStmt->execute([$full_name, $email, $admin_id]);

                // Update session
                $_SESSION['name']  = $full_name;
                $_SESSION['email'] = $email;

                // Refresh local variable
                $admin['full_name'] = $full_name;
                $admin['email']     = $email;

                $success_msg = 'Profile details updated successfully!';
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 2. CHANGE PASSWORD
    // ─────────────────────────────────────────────────────────────
    elseif ($action === 'change_password') {
        $current_pwd = $_POST['current_password'] ?? '';
        $new_pwd     = $_POST['new_password']     ?? '';
        $confirm_pwd = $_POST['confirm_password'] ?? '';

        if ($current_pwd === '' || $new_pwd === '' || $confirm_pwd === '') {
            $error_msg = 'All password fields are required.';
        } elseif (!password_verify($current_pwd, $admin['password_hash'])) {
            $error_msg = 'Incorrect current password. Please try again.';
        } elseif (strlen($new_pwd) < 6) {
            $error_msg = 'New password must be at least 6 characters in length.';
        } elseif ($new_pwd !== $confirm_pwd) {
            $error_msg = 'New password and confirmation password do not match.';
        } else {
            $new_hash = password_hash($new_pwd, PASSWORD_BCRYPT);
            $pwdStmt  = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $pwdStmt->execute([$new_hash, $admin_id]);

            $admin['password_hash'] = $new_hash;
            $success_msg = 'Password changed successfully! Next time you log in, use your new password.';
        }
    }
}

$admin_title      = 'Admin Profile';
$admin_active_nav = 'profile';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Admin Profile & Security</h1>
        <p class="admin-page-subtitle">Manage administrative credentials, system notification settings, and authentication.</p>
    </div>
</div>

<?php if ($success_msg !== ''): ?>
    <div class="alert alert-success mb-6" role="alert" style="border-radius:12px; padding:14px 18px; display:flex; align-items:center; gap:12px;">
        <span class="material-symbols-outlined shrink-0" style="font-size:24px; color:var(--clr-success);">check_circle</span>
        <span style="font-size:14px; font-weight:600;"><?= htmlspecialchars($success_msg) ?></span>
    </div>
<?php endif; ?>

<?php if ($error_msg !== ''): ?>
    <div class="alert alert-error mb-6" role="alert" style="border-radius:12px; padding:14px 18px; display:flex; align-items:center; gap:12px;">
        <span class="material-symbols-outlined shrink-0" style="font-size:24px; color:var(--clr-error);">error</span>
        <span style="font-size:14px; font-weight:600;"><?= htmlspecialchars($error_msg) ?></span>
    </div>
<?php endif; ?>

<div class="admin-profile-grid">

    <!-- Left Column: Identity & Privileges Card -->
    <div class="flex flex-col gap-4">
        <div class="admin-profile-card">
            <div class="admin-profile-avatar-lg">
                <?= $initials ?>
            </div>
            <h2 style="font-size:18px; font-weight:700; color:var(--clr-text); margin-bottom:4px;">
                <?= htmlspecialchars($admin['full_name']) ?>
            </h2>
            <div style="font-size:13px; color:var(--clr-text-muted); margin-bottom:12px;">
                <?= htmlspecialchars($admin['email']) ?>
            </div>
            <div style="display:inline-flex; align-items:center; gap:6px; background:rgba(8,145,178,0.12); color:var(--clr-secondary); font-size:11px; font-weight:700; padding:4px 12px; border-radius:9999px; text-transform:uppercase; letter-spacing:0.06em;">
                <span class="material-symbols-outlined" style="font-size:14px;">verified_user</span>
                <span>System Administrator</span>
            </div>

            <div style="border-top:1px solid var(--clr-border); margin:20px 0 16px; padding-top:16px; text-align:left; display:flex; flex-direction:column; gap:12px; font-size:13px;">
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--clr-text-muted);">Admin User ID</span>
                    <strong style="color:var(--clr-text);">#<?= (int) $admin['id'] ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--clr-text-muted);">Role Status</span>
                    <span class="badge badge-completed">Verified</span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--clr-text-muted);">Account Created</span>
                    <span style="color:var(--clr-text);"><?= date('M j, Y', strtotime($admin['created_at'])) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--clr-text-muted);">Console Access</span>
                    <strong style="color:var(--clr-success);">Full Privileges</strong>
                </div>
            </div>
        </div>

        <!-- Security Privileges Info Card -->
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-title">
                    <span class="material-symbols-outlined" style="color:var(--clr-secondary);">shield</span>
                    <span>Admin Capabilities</span>
                </div>
            </div>
            <div class="admin-card-body" style="padding:16px;">
                <ul style="list-style:none; display:flex; flex-direction:column; gap:10px; font-size:13px; color:var(--clr-text-muted);">
                    <li style="display:flex; align-items:center; gap:8px;">
                        <span class="material-symbols-outlined text-emerald" style="font-size:18px;">check</span>
                        <span>Telemetry & Parking Zone Live Radar</span>
                    </li>
                    <li style="display:flex; align-items:center; gap:8px;">
                        <span class="material-symbols-outlined text-emerald" style="font-size:18px;">check</span>
                        <span>Real-Time Slot Occupancy & Booking Stats</span>
                    </li>
                    <li style="display:flex; align-items:center; gap:8px;">
                        <span class="material-symbols-outlined text-emerald" style="font-size:18px;">check</span>
                        <span>Overstay & Late Departure Audit Logs</span>
                    </li>
                    <li style="display:flex; align-items:center; gap:8px;">
                        <span class="material-symbols-outlined text-emerald" style="font-size:18px;">check</span>
                        <span>Slot Maintenance Controls (Step 2)</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Column: Edit Forms -->
    <div class="flex flex-col gap-6">

        <!-- Form 1: Profile Information -->
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-title">
                    <span class="material-symbols-outlined" style="color:var(--clr-secondary);">badge</span>
                    <span>Administrative Profile Details</span>
                </div>
            </div>
            <div class="admin-card-body">
                <form method="POST" action="" novalidate>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group" style="margin-bottom:18px;">
                        <label for="fullNameInput" class="form-label" style="font-size:13px; font-weight:600; margin-bottom:6px; display:block; color:var(--clr-text);">
                            Full Name
                        </label>
                        <input type="text" id="fullNameInput" name="full_name"
                               class="auth-input-pill" style="padding-left:16px;"
                               value="<?= htmlspecialchars($admin['full_name']) ?>" required>
                    </div>

                    <div class="form-group" style="margin-bottom:24px;">
                        <label for="emailInput" class="form-label" style="font-size:13px; font-weight:600; margin-bottom:6px; display:block; color:var(--clr-text);">
                            Administrator Email
                        </label>
                        <input type="email" id="emailInput" name="email"
                               class="auth-input-pill" style="padding-left:16px;"
                               value="<?= htmlspecialchars($admin['email']) ?>" required>
                        <span style="font-size:12px; color:var(--clr-text-muted); margin-top:4px; display:block;">
                            This email is used for sign-in and system notifications.
                        </span>
                    </div>

                    <button type="submit" class="btn btn-primary" style="padding:10px 24px;">
                        <span class="material-symbols-outlined" style="font-size:18px;">save</span>
                        <span>Save Profile Changes</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Form 2: Change Password -->
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-title">
                    <span class="material-symbols-outlined" style="color:var(--clr-secondary);">lock_reset</span>
                    <span>Change Password</span>
                </div>
            </div>
            <div class="admin-card-body">
                <form method="POST" action="" novalidate>
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group" style="margin-bottom:18px;">
                        <label for="currentPasswordInput" class="form-label" style="font-size:13px; font-weight:600; margin-bottom:6px; display:block; color:var(--clr-text);">
                            Current Password
                        </label>
                        <input type="password" id="currentPasswordInput" name="current_password"
                               class="auth-input-pill" style="padding-left:16px;"
                               placeholder="Enter your current password" required>
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
                        <div class="form-group">
                            <label for="newPasswordInput" class="form-label" style="font-size:13px; font-weight:600; margin-bottom:6px; display:block; color:var(--clr-text);">
                                New Password
                            </label>
                            <input type="password" id="newPasswordInput" name="new_password"
                                   class="auth-input-pill" style="padding-left:16px;"
                                   placeholder="Minimum 6 characters" required>
                        </div>

                        <div class="form-group">
                            <label for="confirmPasswordInput" class="form-label" style="font-size:13px; font-weight:600; margin-bottom:6px; display:block; color:var(--clr-text);">
                                Confirm New Password
                            </label>
                            <input type="password" id="confirmPasswordInput" name="confirm_password"
                                   class="auth-input-pill" style="padding-left:16px;"
                                   placeholder="Re-enter new password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-outline" style="padding:10px 24px;">
                        <span class="material-symbols-outlined" style="font-size:18px;">key</span>
                        <span>Update Password</span>
                    </button>
                </form>
            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
