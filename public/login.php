<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user = current_user();
if (!empty($user['id'])) {
    header('Location: /parking-system/public/dashboard.php');
    exit;
}

$error_banner = '';
$old_email    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = trim($_POST['email']    ?? '');
    $password  = $_POST['password']      ?? '';
    $old_email = $email;
    $fail_msg  = 'Invalid email or password.';  // intentionally generic

    if ($email === '' || $password === '') {
        $error_banner = $fail_msg;
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, $row['password_hash'])) {
            login_user($row);
            header('Location: /parking-system/public/dashboard.php');
            exit;
        } else {
            $error_banner = $fail_msg;
        }
    }
}

$page_title = 'Log In';
$body_page  = 'login';
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

            <h1 class="auth-header-title">Welcome back</h1>
            <p class="auth-header-subtitle">Log in to manage your reservations and live radar telemetry.</p>

            <?php if ($error_banner !== ''): ?>
                <div class="alert alert-error mb-4" role="alert" style="border-radius:12px; padding:12px 16px; display:flex; align-items:center; gap:10px; background:#FEF2F2; color:#B91C1C; border:1px solid #FCA5A5;">
                    <span class="material-symbols-outlined" aria-hidden="true" style="font-size:20px;">warning</span>
                    <span style="font-size:14px; font-weight:600;"><?= htmlspecialchars($error_banner) ?></span>
                </div>
            <?php endif; ?>

            <form id="authForm" method="POST" action="" novalidate>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="email" class="form-label" style="font-size:13px; font-weight:600; margin-bottom:6px; color:var(--clr-text);">University Email</label>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon material-symbols-outlined">mail</span>
                        <input type="email" id="email" name="email"
                            class="auth-input-pill"
                            value="<?= htmlspecialchars($old_email) ?>"
                            autocomplete="email" required
                            placeholder="you@university.edu">
                    </div>
                    <span class="form-error" data-client aria-live="polite"></span>
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label for="password" class="form-label" style="font-size:13px; font-weight:600; color:var(--clr-text); margin-bottom:0;">Password</label>
                    </div>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon material-symbols-outlined">lock</span>
                        <input type="password" id="password" name="password"
                            class="auth-input-pill"
                            autocomplete="current-password" required
                            placeholder="Enter your password">
                        <button type="button" class="password-toggle-btn" id="togglePasswordBtn" aria-label="Toggle password visibility">
                            <span class="material-symbols-outlined" style="font-size:20px;">visibility</span>
                        </button>
                    </div>
                    <span class="form-error" data-client aria-live="polite"></span>
                </div>

                <button type="submit" class="auth-submit-btn">
                    <span>Log In</span>
                    <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                </button>

            </form>

            <p class="auth-switch-link">
                Don't have an account?
                <a href="/parking-system/public/signup.php">Sign up for free</a>
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
                        <span class="status-dots">●</span> LIVE RADAR TELEMETRY
                    </div>
                </div>

                <div class="auth-preview-mini-card">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                        <span style="background:#10B981; color:#fff; font-size:11px; font-weight:700; padding:2px 8px; border-radius:9999px;">4.9/5</span>
                        <span style="font-size:12px; color:#94A3B8;">Zone A • Core Campus</span>
                    </div>
                    <h3 style="font-size:16px; font-weight:700; color:#fff; margin-bottom:4px;">Main Library & Tech Quad</h3>
                    <p style="font-size:12px; color:#CBD5E1; margin-bottom:12px;">Instant contactless check-in with license plate & QR sync.</p>
                    <div style="display:flex; align-items:center; justify-content:space-between; font-size:12px; border-top:1px solid rgba(255,255,255,0.1); padding-top:10px;">
                        <span style="color:#94A3B8;">Availability</span>
                        <span style="color:#34D399; font-weight:700;">8 spots open</span>
                    </div>
                </div>

                <div>
                    <ul class="auth-preview-features-list">
                        <li class="auth-preview-feature-item">
                            <span class="auth-preview-feature-check">✓</span>
                            <span>Guaranteed parking spot before departing</span>
                        </li>
                        <li class="auth-preview-feature-item">
                            <span class="auth-preview-feature-check">✓</span>
                            <span>Live radar telemetry across 3 campus zones</span>
                        </li>
                        <li class="auth-preview-feature-item">
                            <span class="auth-preview-feature-check">✓</span>
                            <span>Zero parking citations guarantee</span>
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
