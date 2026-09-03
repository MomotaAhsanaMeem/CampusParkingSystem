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

<div class="auth-page">
    <div class="auth-card">

        <div class="auth-card-header">
            <div class="auth-card-logo" aria-hidden="true">
                <span class="material-symbols-outlined" style="font-size:26px;">local_parking</span>
            </div>
            <h1 class="auth-card-title">Welcome back</h1>
            <p class="auth-card-subtitle">Log in to manage your campus parking reservations.</p>
        </div>

        <?php if ($error_banner !== ''): ?>
            <div class="alert alert-error" role="alert">
                <span class="alert-icon material-symbols-outlined" aria-hidden="true">warning</span>
                <?= htmlspecialchars($error_banner) ?>
            </div>
        <?php endif; ?>

        <form id="authForm" method="POST" action="" novalidate>

            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email"
                    class="form-input"
                    value="<?= htmlspecialchars($old_email) ?>"
                    autocomplete="email" required
                    placeholder="you@university.edu">
                <span class="form-error" data-client aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password"
                    class="form-input"
                    autocomplete="current-password" required>
                <span class="form-error" data-client aria-live="polite"></span>
            </div>

            <button type="submit" class="btn btn-primary btn-full btn-lg">
                Log In
                <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
            </button>

        </form>

        <div class="auth-divider"></div>

        <p class="auth-footer-text">
            Don't have an account?
            <a href="/parking-system/public/signup.php">Sign up free</a>
        </p>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
