<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_login();

$user = current_user();

// ---------- Load current profile data from DB ----------
$stmt = $pdo->prepare('SELECT full_name, email, reminder_minutes_before FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

$errors         = [];
$success        = '';
$email_send_err = false; // set true when send_email() returns false

// ---------- Form handling ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name     = trim($_POST['full_name']               ?? '');
    $new_email    = trim($_POST['email']                   ?? '');
    $new_reminder = (int) ($_POST['reminder_minutes_before'] ?? 30);
    $new_password = $_POST['new_password']                 ?? '';
    $confirm_pass = $_POST['confirm_password']             ?? '';

    // --- Validation ---
    if (strlen($new_name) < 2) {
        $errors['full_name'] = 'Please enter your full name (at least 2 characters).';
    }
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if ($new_reminder < 0 || $new_reminder > 1440) {
        // Cap at 24 h; 0 means "don't send a reminder"
        $errors['reminder'] = 'Reminder must be between 0 and 1440 minutes.';
    }
    if ($new_password !== '') {
        if (strlen($new_password) < 8) {
            $errors['new_password'] = 'New password must be at least 8 characters.';
        } elseif ($new_password !== $confirm_pass) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
    }

    // Email uniqueness — only check if it changed
    if (empty($errors['email']) && $new_email !== $profile['email']) {
        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $chk->execute([$new_email, $user['id']]);
        if ($chk->fetch()) {
            $errors['email'] = 'An account with that email already exists.';
        }
    }

    if (empty($errors)) {
        if ($new_password !== '') {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET full_name = ?, email = ?, password_hash = ?, reminder_minutes_before = ? WHERE id = ?')
                ->execute([$new_name, $new_email, $hash, $new_reminder, $user['id']]);
        } else {
            $pdo->prepare('UPDATE users SET full_name = ?, email = ?, reminder_minutes_before = ? WHERE id = ?')
                ->execute([$new_name, $new_email, $new_reminder, $user['id']]);
        }

        // Refresh session identity fields
        $_SESSION['name']  = $new_name;
        $_SESSION['email'] = $new_email;

        // Re-fetch so the form repopulates from the saved values
        $stmt = $pdo->prepare('SELECT full_name, email, reminder_minutes_before FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();

        // Send a confirmation email; show inline warning if it fails
        $sent = send_email(
            $new_email,
            'CampusPark - Profile Updated',
            '<p>Hi ' . htmlspecialchars($new_name) . ',</p>'
            . '<p>Your CampusPark profile was just updated successfully.</p>'
            . '<p>If you did not make this change, please contact support immediately.</p>'
            . '<p style="color:#64748b;font-size:13px;">&mdash; The CampusPark Team</p>'
        );

        if (!$sent) {
            $email_send_err = true;
        }

        $success = 'Your profile has been saved.';
    }
}

$page_title = 'My Profile';
$body_page  = 'profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="pt-24 pb-16 px-margin-mobile md:px-margin-desktop w-full max-w-7xl mx-auto">

    <!-- Page header -->
    <div class="mb-8">
        <h1 style="font-size:28px; font-weight:700; color:var(--clr-text); letter-spacing:-0.02em; margin-bottom:4px;">
            My Profile
        </h1>
        <p style="font-size:15px; color:var(--clr-text-muted);">
            Update your name, email, reminder preference, and password.
        </p>
    </div>

    <!-- Success banner -->
    <?php if ($success !== ''): ?>
        <div class="alert alert-success mb-6" role="alert">
            <span class="material-symbols-outlined alert-icon">check_circle</span>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- Email delivery warning (shown when send_email returned false) -->
    <?php if ($email_send_err): ?>
        <div class="alert alert-error mb-6" role="alert">
            <span class="material-symbols-outlined alert-icon">mail_off</span>
            <span>
                We couldn't send this email — please verify your email address in your profile.
                <a href="<?= BASE_URL ?>/public/profile.php" style="font-weight:700; text-decoration:underline; margin-left:4px;">
                    Update email →
                </a>
            </span>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width:640px;">

        <h2 class="card-title" style="font-size:20px; margin-bottom:4px;">Account details</h2>
        <p class="card-subtitle" style="margin-bottom:24px;">Changes take effect immediately.</p>

        <form id="profileForm" method="POST" action="" novalidate>

            <!-- Full name -->
            <div class="form-group" style="margin-bottom:18px;">
                <label for="full_name"
                       style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--clr-text);">
                    Full Name
                </label>
                <input type="text" id="full_name" name="full_name"
                       style="width:100%; padding:10px 14px; border:1px solid var(--clr-border);
                              border-radius:var(--r); font-size:15px; background:var(--clr-surface);
                              color:var(--clr-text); transition:border-color 150ms;"
                       value="<?= htmlspecialchars($profile['full_name'] ?? '') ?>"
                       autocomplete="name" required
                       aria-describedby="full_name_error">
                <span id="full_name_error" class="form-error" aria-live="polite">
                    <?= htmlspecialchars($errors['full_name'] ?? '') ?>
                </span>
            </div>

            <!-- Email -->
            <div class="form-group" style="margin-bottom:18px;">
                <label for="email"
                       style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--clr-text);">
                    Email Address
                </label>
                <input type="email" id="email" name="email"
                       style="width:100%; padding:10px 14px; border:1px solid var(--clr-border);
                              border-radius:var(--r); font-size:15px; background:var(--clr-surface);
                              color:var(--clr-text); transition:border-color 150ms;"
                       value="<?= htmlspecialchars($profile['email'] ?? '') ?>"
                       autocomplete="email" required
                       aria-describedby="email_error">
                <span id="email_error" class="form-error" aria-live="polite">
                    <?= htmlspecialchars($errors['email'] ?? '') ?>
                </span>
            </div>

            <!-- Reminder preference -->
            <div class="form-group" style="margin-bottom:18px;">
                <label for="reminder_minutes_before"
                       style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--clr-text);">
                    Remind me before check-in (minutes)
                </label>
                <input type="number" id="reminder_minutes_before" name="reminder_minutes_before"
                       style="width:100%; padding:10px 14px; border:1px solid var(--clr-border);
                              border-radius:var(--r); font-size:15px; background:var(--clr-surface);
                              color:var(--clr-text); transition:border-color 150ms;"
                       value="<?= (int) ($profile['reminder_minutes_before'] ?? 30) ?>"
                       min="0" max="1440"
                       aria-describedby="reminder_error">
                <span class="form-hint" style="display:block; margin-top:4px;">
                    Set to 0 to disable email reminders. Max 1440 (24 h).
                </span>
                <span id="reminder_error" class="form-error" aria-live="polite">
                    <?= htmlspecialchars($errors['reminder'] ?? '') ?>
                </span>
            </div>

            <hr style="border:none; border-top:1px solid var(--clr-border); margin:24px 0;">

            <p style="font-size:13px; font-weight:600; color:var(--clr-text-muted); margin-bottom:16px; text-transform:uppercase; letter-spacing:0.05em;">
                Change Password <span style="font-weight:400; text-transform:none;">(leave blank to keep current)</span>
            </p>

            <!-- New password -->
            <div class="form-group" style="margin-bottom:18px;">
                <label for="new_password"
                       style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--clr-text);">
                    New Password
                </label>
                <input type="password" id="new_password" name="new_password"
                       style="width:100%; padding:10px 14px; border:1px solid var(--clr-border);
                              border-radius:var(--r); font-size:15px; background:var(--clr-surface);
                              color:var(--clr-text); transition:border-color 150ms;"
                       autocomplete="new-password" minlength="8"
                       placeholder="Minimum 8 characters"
                       aria-describedby="new_password_error">
                <span id="new_password_error" class="form-error" aria-live="polite">
                    <?= htmlspecialchars($errors['new_password'] ?? '') ?>
                </span>
            </div>

            <!-- Confirm password -->
            <div class="form-group" style="margin-bottom:28px;">
                <label for="confirm_password"
                       style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--clr-text);">
                    Confirm New Password
                </label>
                <input type="password" id="confirm_password" name="confirm_password"
                       style="width:100%; padding:10px 14px; border:1px solid var(--clr-border);
                              border-radius:var(--r); font-size:15px; background:var(--clr-surface);
                              color:var(--clr-text); transition:border-color 150ms;"
                       autocomplete="new-password"
                       placeholder="Repeat new password"
                       aria-describedby="confirm_password_error">
                <span id="confirm_password_error" class="form-error" aria-live="polite">
                    <?= htmlspecialchars($errors['confirm_password'] ?? '') ?>
                </span>
            </div>

            <button type="submit" class="nav-link--cta"
                    style="display:inline-flex; align-items:center; gap:8px;
                           padding:11px 24px; border-radius:var(--r); border:none;
                           background:var(--clr-secondary); color:#fff;
                           font-size:15px; font-weight:600; cursor:pointer;
                           transition:background 150ms, transform 100ms;">
                <span class="material-symbols-outlined" style="font-size:18px;">save</span>
                Save Changes
            </button>

        </form>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
