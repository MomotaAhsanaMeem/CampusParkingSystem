<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user = current_user();
if (!empty($user['id'])) {
    header('Location: /parking-system/public/dashboard.php');
    exit;
}

// ---------- Form handling ----------
$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['email']     = trim($_POST['email']     ?? '');

    $full_name        = $old['full_name'];
    $email            = $old['email'];
    $password         = $_POST['password']         ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($full_name) < 2) {
        $errors['full_name'] = 'Please enter your full name (at least 2 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors['email'])) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'An account with that email already exists.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$full_name, $email, $hash, 'user']);
        $new_id = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$new_id]);
        login_user($stmt->fetch());

        header('Location: /parking-system/public/dashboard.php');
        exit;
    }
}

$page_title = 'Create Account';
$body_page  = 'signup';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-split-wrapper">
    <div class="auth-split-box">

        <!-- Left Form Side -->
        <div class="auth-form-side">

            <div class="auth-brand-badge">
                <img src="/parking-system/assets/images/logo.jpg" alt="CampusPark" style="width:20px;height:20px;border-radius:5px;object-fit:cover;" />
                CampusPark Mobility
            </div>

            <h1 class="auth-header-title">Create your account</h1>
            <p class="auth-header-subtitle">Join thousands of students and faculty reserving spots in minutes.</p>

            <form id="authForm" method="POST" action="" novalidate>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="full_name" class="form-label" style="font-size:13px; font-weight:600; margin-bottom:6px; color:var(--clr-text);">Full Name</label>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon material-symbols-outlined">person</span>
                        <input type="text" id="full_name" name="full_name"
                            class="auth-input-pill <?= isset($errors['full_name']) ? 'form-input--error' : '' ?>"
                            value="<?= htmlspecialchars($old['full_name'] ?? '') ?>"
                            autocomplete="name" required placeholder="e.g. Alex Johnson"
                            aria-describedby="full_name_error">
                    </div>
                    <span id="full_name_error" class="form-error" aria-live="polite">
                        <?= htmlspecialchars($errors['full_name'] ?? '') ?>
                    </span>
                    <span class="form-error" data-client aria-live="polite"></span>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="email" class="form-label" style="font-size:13px; font-weight:600; margin-bottom:6px; color:var(--clr-text);">University Email</label>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon material-symbols-outlined">mail</span>
                        <input type="email" id="email" name="email"
                            class="auth-input-pill <?= isset($errors['email']) ? 'form-input--error' : '' ?>"
                            value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                            autocomplete="email" required placeholder="you@university.edu"
                            aria-describedby="email_error">
                    </div>
                    <span id="email_error" class="form-error" aria-live="polite">
                        <?= htmlspecialchars($errors['email'] ?? '') ?>
                    </span>
                    <span class="form-error" data-client aria-live="polite"></span>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="password" class="form-label" style="font-size:13px; font-weight:600; margin-bottom:6px; color:var(--clr-text);">Password</label>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon material-symbols-outlined">lock</span>
                        <input type="password" id="password" name="password"
                            class="auth-input-pill <?= isset($errors['password']) ? 'form-input--error' : '' ?>"
                            autocomplete="new-password" required minlength="8"
                            placeholder="Minimum 8 characters"
                            aria-describedby="password_error">
                        <button type="button" class="password-toggle-btn" id="togglePasswordBtn" aria-label="Toggle password visibility">
                            <span class="material-symbols-outlined" style="font-size:20px;">visibility</span>
                        </button>
                    </div>
                    <span id="password_error" class="form-error" aria-live="polite">
                        <?= htmlspecialchars($errors['password'] ?? '') ?>
                    </span>
                    <span class="form-error" data-client aria-live="polite"></span>
                </div>

                <div class="form-group" style="margin-bottom: 22px;">
                    <label for="confirm_password" class="form-label" style="font-size:13px; font-weight:600; margin-bottom:6px; color:var(--clr-text);">Confirm Password</label>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon material-symbols-outlined">lock_clock</span>
                        <input type="password" id="confirm_password" name="confirm_password"
                            class="auth-input-pill <?= isset($errors['confirm_password']) ? 'form-input--error' : '' ?>"
                            autocomplete="new-password" required
                            placeholder="Repeat password"
                            aria-describedby="confirm_password_error">
                        <button type="button" class="password-toggle-btn" id="toggleConfirmPasswordBtn" aria-label="Toggle confirm password visibility">
                            <span class="material-symbols-outlined" style="font-size:20px;">visibility</span>
                        </button>
                    </div>
                    <span id="confirm_password_error" class="form-error" aria-live="polite">
                        <?= htmlspecialchars($errors['confirm_password'] ?? '') ?>
                    </span>
                    <span class="form-error" data-client aria-live="polite"></span>
                </div>

                <button type="submit" class="auth-submit-btn">
                    <span>Get started for free</span>
                    <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                </button>

            </form>

            <p class="auth-switch-link">
                Already have an account?
                <a href="/parking-system/public/login.php">Log in</a>
            </p>

        </div>

        <!-- Right Preview / Showcase Side (Inspiration from Image 1 & 2) -->
        <div class="auth-preview-side">
            <img src="/parking-system/assets/images/hero-aerial-ev.jpg"
                 alt="Smart campus vehicle navigation visual"
                 class="auth-preview-bg">
            <div class="auth-preview-overlay"></div>

            <div class="auth-preview-inner">
                <div>
                    <div class="auth-preview-status-pill">
                        <span class="status-dots">●</span> GUARANTEED RESERVATIONS
                    </div>
                </div>

                <div class="auth-preview-mini-card">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                        <span style="background:#10B981; color:#fff; font-size:11px; font-weight:700; padding:2px 8px; border-radius:9999px;">4.9/5</span>
                        <span style="font-size:12px; color:#94A3B8;">Student Trust Score</span>
                    </div>
                    <h3 style="font-size:16px; font-weight:700; color:#fff; margin-bottom:4px;">Direct Turn-By-Turn Stall Guidance</h3>
                    <p style="font-size:12px; color:#CBD5E1; margin-bottom:12px;">Pre-book your exact bay and cruise straight in without circling.</p>
                    <div style="display:flex; align-items:center; justify-content:space-between; font-size:12px; border-top:1px solid rgba(255,255,255,0.1); padding-top:10px;">
                        <span style="color:#94A3B8;">On-Time Rate</span>
                        <span style="color:#34D399; font-weight:700;">98% verified</span>
                    </div>
                </div>

                <div>
                    <ul class="auth-preview-features-list">
                        <li class="auth-preview-feature-item">
                            <span class="auth-preview-feature-check">✓</span>
                            <span>Reserve spots in minutes before leaving home</span>
                        </li>
                        <li class="auth-preview-feature-item">
                            <span class="auth-preview-feature-check">✓</span>
                            <span>Direct proximity to lecture halls & campus hubs</span>
                        </li>
                        <li class="auth-preview-feature-item">
                            <span class="auth-preview-feature-check">✓</span>
                            <span>Student permit rates automatically applied</span>
                        </li>
                        <li class="auth-preview-feature-item">
                            <span class="auth-preview-feature-check">✓</span>
                            <span>24/7 dedicated campus parking support</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
document.getElementById('togglePasswordBtn')?.addEventListener('click', function() {
    const input = document.getElementById('password');
    const icon = this.querySelector('.material-symbols-outlined');
    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
    } else {
        input.type = 'password';
        icon.textContent = 'visibility';
    }
});

document.getElementById('toggleConfirmPasswordBtn')?.addEventListener('click', function() {
    const input = document.getElementById('confirm_password');
    const icon = this.querySelector('.material-symbols-outlined');
    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
    } else {
        input.type = 'password';
        icon.textContent = 'visibility';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
