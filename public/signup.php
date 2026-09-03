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

<div class="auth-page">
    <div class="auth-card">

        <div class="auth-card-header">
            <div class="auth-card-logo" aria-hidden="true">
                <span class="material-symbols-outlined" style="font-size:26px;">local_parking</span>
            </div>
            <h1 class="auth-card-title">Create your account</h1>
            <p class="auth-card-subtitle">Join thousands of students reserving smarter.</p>
        </div>

        <form id="authForm" method="POST" action="" novalidate>

            <div class="form-group">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" id="full_name" name="full_name"
                    class="form-input <?= isset($errors['full_name']) ? 'form-input--error' : '' ?>"
                    value="<?= htmlspecialchars($old['full_name'] ?? '') ?>"
                    autocomplete="name" required placeholder="e.g. Alex Johnson"
                    aria-describedby="full_name_error">
                <span id="full_name_error" class="form-error" aria-live="polite">
                    <?= htmlspecialchars($errors['full_name'] ?? '') ?>
                </span>
                <span class="form-error" data-client aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">University Email</label>
                <input type="email" id="email" name="email"
                    class="form-input <?= isset($errors['email']) ? 'form-input--error' : '' ?>"
                    value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                    autocomplete="email" required placeholder="you@university.edu"
                    aria-describedby="email_error">
                <span id="email_error" class="form-error" aria-live="polite">
                    <?= htmlspecialchars($errors['email'] ?? '') ?>
                </span>
                <span class="form-error" data-client aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password"
                    class="form-input <?= isset($errors['password']) ? 'form-input--error' : '' ?>"
                    autocomplete="new-password" required minlength="8"
                    aria-describedby="password_hint password_error">
                <span id="password_hint" class="form-hint">Minimum 8 characters.</span>
                <span id="password_error" class="form-error" aria-live="polite">
                    <?= htmlspecialchars($errors['password'] ?? '') ?>
                </span>
                <span class="form-error" data-client aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password"
                    class="form-input <?= isset($errors['confirm_password']) ? 'form-input--error' : '' ?>"
                    autocomplete="new-password" required
                    aria-describedby="confirm_password_error">
                <span id="confirm_password_error" class="form-error" aria-live="polite">
                    <?= htmlspecialchars($errors['confirm_password'] ?? '') ?>
                </span>
                <span class="form-error" data-client aria-live="polite"></span>
            </div>

            <button type="submit" class="btn btn-primary btn-full btn-lg">
                Create Account
                <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
            </button>

        </form>

        <div class="auth-divider"></div>

        <p class="auth-footer-text">
            Already have an account?
            <a href="/parking-system/public/login.php">Log in</a>
        </p>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
