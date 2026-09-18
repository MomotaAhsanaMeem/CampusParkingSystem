<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$current_points = refresh_user_points($pdo, $user['id']);

$packages = [
    'basic' => [
        'id'          => 'basic',
        'name'        => 'Basic Package',
        'tier_label'  => 'Basic',
        'points'      => 100,
        'price'       => '$10.00',
        'hours'       => '10 Hours',
        'tag'         => 'Essential',
        'is_popular'  => false,
        'description' => 'Great for occasional parking, quick visits, and exam weeks.',
        'features'    => [
            '100 Reward Points added immediately',
            'Equivalent to 10 hours of parking (10 pts/hr)',
            'Standard Campus Zones (North & South)',
            'Instant demo activation to your profile',
        ],
    ],
    'plus' => [
        'id'          => 'plus',
        'name'        => 'Plus Package',
        'tier_label'  => 'Plus',
        'points'      => 250,
        'price'       => '$20.00',
        'hours'       => '25 Hours',
        'tag'         => 'Most Popular',
        'is_popular'  => true,
        'description' => 'Perfect for regular commuters, weekly lectures, and lab sessions.',
        'features'    => [
            '250 Reward Points (50 bonus points included)',
            'Equivalent to 25 hours of parking (10 pts/hr)',
            'Access across all campus parking zones',
            'Priority slot reservation privileges',
            'Instant demo activation to your profile',
        ],
    ],
    'max' => [
        'id'          => 'max',
        'name'        => 'Max Package',
        'tier_label'  => 'Max',
        'points'      => 500,
        'price'       => '$35.00',
        'hours'       => '50 Hours',
        'tag'         => 'Best Value',
        'is_popular'  => false,
        'description' => 'Maximum flexibility for full-time students, staff, and faculty.',
        'features'    => [
            '500 Reward Points (150 bonus points included)',
            'Equivalent to 50 hours of parking (10 pts/hr)',
            'Full campus-wide access & priority EV bays',
            'Semester-wide points rollover guarantee',
            'Instant demo activation to your profile',
        ],
    ],
];

// ---------- POST — process package purchase ----------
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pkg_key   = trim($_POST['package'] ?? '');
    $return_to = trim($_POST['return_to'] ?? '');

    if (!isset($packages[$pkg_key])) {
        $error_msg = 'Invalid package selected. Please choose a valid package.';
    } else {
        $pkg = $packages[$pkg_key];
        $points_to_add = (int) $pkg['points'];
        $tier_name     = $pkg['tier_label'];

        // Add points and update package tier in users table
        $stmt = $pdo->prepare('UPDATE users SET reward_points = reward_points + ?, package_tier = ? WHERE id = ?');
        $stmt->execute([$points_to_add, $tier_name, $user['id']]);

        // Record transaction in point_transactions
        try {
            $stmtTx = $pdo->prepare(
                "INSERT INTO point_transactions (user_id, type, points, package_name, description)
                 VALUES (?, 'package_purchase', ?, ?, ?)"
            );
            $desc = "Purchased {$pkg['name']} (+{$points_to_add} pts)";
            $stmtTx->execute([$user['id'], $points_to_add, $pkg['name'], $desc]);
        } catch (Throwable $ignore) {}

        // Refresh session
        $new_points = refresh_user_points($pdo, $user['id']);

        $_SESSION['flash'] = "🎉 Package added! {$pkg['name']} (+{$points_to_add} points) has been credited to your profile. New balance: {$new_points} pts.";

        if ($return_to === 'book-slot') {
            header('Location: ' . BASE_URL . '/public/book-slot.php');
        } else {
            header('Location: ' . BASE_URL . '/public/dashboard.php');
        }
        exit;
    }
}

// Fetch recent point transactions for user
$transactions = [];
try {
    $stmtHistory = $pdo->prepare(
        'SELECT id, type, points, package_name, description, created_at
           FROM point_transactions
          WHERE user_id = ?
          ORDER BY created_at DESC, id DESC
          LIMIT 8'
    );
    $stmtHistory->execute([$user['id']]);
    $transactions = $stmtHistory->fetchAll();
} catch (Throwable $ignore) {}

$reason    = $_GET['reason'] ?? '';
$return_to = $_GET['return_to'] ?? '';

$page_title = 'Payment & Recharge Packages';
$body_page  = 'payment';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- pt-24 clears fixed navbar -->
<div class="pt-24 pb-16 px-margin-mobile md:px-margin-desktop w-full max-w-7xl mx-auto">

    <!-- Back link & page title -->
    <div class="flex items-center justify-between flex-wrap gap-md mb-md">
        <div class="page-header" style="margin-bottom:0;">
            <div class="flex items-center gap-sm mb-xs">
                <span class="badge" style="background:rgba(8,145,178,0.12); color:var(--clr-secondary); font-weight:700; border:1px solid var(--clr-border-violet);">
                    ⚡ DEMO PAYMENT PORTAL
                </span>
            </div>
            <h1 class="page-title">Top Up Parking Points</h1>
            <p class="page-subtitle">Select a package to replenish your balance. Each parking slot costs 10 points per hour.</p>
        </div>
        <div class="flex items-center gap-sm flex-wrap">
            <a href="<?= BASE_URL ?>/public/book-slot.php" class="btn btn-outline flex items-center gap-sm">
                <span class="material-symbols-outlined" style="font-size:18px;">local_parking</span>
                Book a Slot
            </a>
            <a href="<?= BASE_URL ?>/public/dashboard.php" class="btn btn-outline flex items-center gap-sm">
                <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span>
                Dashboard
            </a>
        </div>
    </div>

    <!-- Reason notification (e.g. redirected because rewards ran out) -->
    <?php if ($reason === 'insufficient_points'): ?>
    <div class="alert alert-warning mb-md" role="alert" style="border-left: 4px solid var(--clr-amber);">
        <span class="alert-icon material-symbols-outlined" aria-hidden="true" style="font-size:24px;">error_outline</span>
        <div>
            <strong style="font-size:15px;">Your points are insufficient or exhausted!</strong>
            <p style="margin-top:2px; font-size:13px;">
                Reserving a parking bay requires at least <strong>10 points per hour</strong>. You currently have <strong><?= $current_points ?> points</strong>.
                Choose any package below to instantly replenish your points and continue booking.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Demo Payment Simulation Notice -->
    <div class="payment-demo-banner mb-lg">
        <div class="flex items-start gap-md">
            <span class="material-symbols-outlined payment-demo-icon">verified_user</span>
            <div>
                <h2 style="font-size:15px; font-weight:700; margin-bottom:2px;">Simulation & Demo Payment System</h2>
                <p style="font-size:13px; opacity:0.9; line-height:1.5;">
                    No credit card or real monetary transaction is required. Simply choose <strong>Basic</strong>, <strong>Plus</strong>, or <strong>Max</strong>, and the package points will immediately be added to your profile balance.
                </p>
            </div>
        </div>
    </div>

    <!-- User Profile & Balance Status Strip -->
    <div class="payment-wallet-card mb-lg">
        <div class="flex items-center justify-between flex-wrap gap-md">
            <div class="flex items-center gap-md">
                <div class="payment-wallet-avatar">
                    <span class="material-symbols-outlined" style="font-size:28px;">toll</span>
                </div>
                <div>
                    <span class="text-muted" style="font-size:12px; text-transform:uppercase; letter-spacing:0.05em; font-weight:700;">Account Profile</span>
                    <h3 style="font-size:18px; font-weight:700; color:var(--clr-text);"><?= htmlspecialchars($user['name'] ?? 'Student Driver') ?></h3>
                    <p class="text-muted" style="font-size:13px;">Current Tier: <strong style="color:var(--clr-secondary);"><?= htmlspecialchars($_SESSION['package_tier'] ?? 'Starter') ?></strong></p>
                </div>
            </div>
            <div class="flex items-center gap-lg flex-wrap">
                <div class="text-right sm:text-left">
                    <span class="text-muted" style="font-size:12px; font-weight:600;">Available Balance</span>
                    <div style="font-size:28px; font-weight:800; color:var(--clr-secondary); line-height:1.1;">
                        <?= ($current_points == (int)$current_points) ? (int)$current_points : number_format($current_points, 2) ?> <span style="font-size:16px; font-weight:600; color:var(--clr-text-muted);">pts</span>
                    </div>
                </div>
                <div class="payment-wallet-divider"></div>
                <div>
                    <span class="text-muted" style="font-size:12px; font-weight:600;">Equivalent Parking</span>
                    <div style="font-size:20px; font-weight:700; color:var(--clr-text); line-height:1.2;">
                        <?= floor($current_points / 10) ?> <span style="font-size:14px; font-weight:500; color:var(--clr-text-muted);">hours</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error_msg !== ''): ?>
    <div class="alert alert-error mb-md" role="alert">
        <span class="alert-icon material-symbols-outlined">error</span>
        <?= htmlspecialchars($error_msg) ?>
    </div>
    <?php endif; ?>

    <!-- Pricing / Packages Section -->
    <div class="mb-xl">
        <div class="text-center mb-lg">
            <h2 style="font-size:26px; font-weight:800; color:var(--clr-text); margin-bottom:6px;">
                Choose Your Parking Package
            </h2>
            <p class="text-muted" style="font-size:15px; max-width:600px; margin:0 auto;">
                Select between Basic, Plus, or Max. The package points are immediately credited to your profile upon selection.
            </p>
        </div>

        <div class="pricing-grid">
            <?php foreach ($packages as $pkg): ?>
            <div class="pricing-card <?= $pkg['is_popular'] ? 'pricing-card--popular' : '' ?>">
                
                <?php if ($pkg['is_popular']): ?>
                <div class="pricing-badge-popular">
                    <span class="material-symbols-outlined" style="font-size:14px;">stars</span>
                    <?= htmlspecialchars($pkg['tag']) ?>
                </div>
                <?php endif; ?>

                <div class="pricing-header">
                    <div class="flex items-center justify-between mb-xs">
                        <span class="pricing-tier-tag"><?= htmlspecialchars($pkg['tier_label']) ?></span>
                        <span class="pricing-hours-tag"><?= htmlspecialchars($pkg['hours']) ?></span>
                    </div>
                    <h3 class="pricing-title"><?= htmlspecialchars($pkg['name']) ?></h3>
                    <p class="pricing-desc"><?= htmlspecialchars($pkg['description']) ?></p>
                </div>

                <div class="pricing-price-wrap">
                    <div class="flex items-baseline gap-xs">
                        <span class="pricing-price"><?= htmlspecialchars($pkg['price']) ?></span>
                        <span class="pricing-period">demo</span>
                    </div>
                    <div class="pricing-points-reward">
                        <span class="material-symbols-outlined" style="font-size:18px; color:var(--clr-secondary);">toll</span>
                        <span><strong>+<?= $pkg['points'] ?> Points</strong> to profile</span>
                    </div>
                </div>

                <div class="pricing-divider"></div>

                <ul class="pricing-features">
                    <?php foreach ($pkg['features'] as $feat): ?>
                    <li class="pricing-feature-item">
                        <span class="pricing-check material-symbols-outlined">check_circle</span>
                        <span><?= htmlspecialchars($feat) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <form method="POST" action="" class="mt-auto">
                    <input type="hidden" name="package" value="<?= htmlspecialchars($pkg['id']) ?>">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to) ?>">
                    <button type="submit" class="btn w-full flex items-center justify-center gap-sm <?= $pkg['is_popular'] ? 'btn-primary' : 'btn-outline' ?>" style="font-weight:700; padding:12px 18px;">
                        <span>Select <?= htmlspecialchars($pkg['tier_label']) ?> Package</span>
                        <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                    </button>
                </form>

            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Points Ledger / Recent Transactions -->
    <section class="mt-lg">
        <div class="flex items-center justify-between flex-wrap gap-sm mb-md">
            <div>
                <h2 style="font-size:20px; font-weight:700; color:var(--clr-text);">Points Activity & Purchases</h2>
                <p class="text-muted" style="font-size:13px;">Record of bonus awards, package top-ups, and parking slot reservations.</p>
            </div>
        </div>

        <?php if (empty($transactions)): ?>
        <div class="empty-state" style="padding:var(--sp-md);">
            <span class="empty-state-icon material-symbols-outlined" style="font-size:40px;">receipt_long</span>
            <p class="empty-state-title" style="font-size:16px;">No points transactions yet</p>
            <p class="empty-state-desc" style="font-size:13px;">Your points activity will show up here after booking or selecting a package.</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table" aria-label="Points transaction history">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Event Type</th>
                        <th scope="col">Description</th>
                        <th scope="col" style="text-align:right;">Points Impact</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx):
                        $is_credit = ($tx['points'] > 0);
                    ?>
                    <tr>
                        <td class="font-semi text-muted" style="white-space:nowrap;">
                            <?= htmlspecialchars(date('M j, Y • g:i A', strtotime($tx['created_at']))) ?>
                        </td>
                        <td>
                            <?php if ($tx['type'] === 'signup_bonus'): ?>
                                <span class="badge" style="background:rgba(16,185,129,0.12); color:#059669; border:1px solid rgba(16,185,129,0.25);">
                                    🎁 Welcome Bonus
                                </span>
                            <?php elseif ($tx['type'] === 'package_purchase'): ?>
                                <span class="badge" style="background:rgba(8,145,178,0.12); color:var(--clr-secondary); border:1px solid var(--clr-border-violet);">
                                    ⚡ Package Recharge
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background:rgba(100,116,139,0.12); color:#475569; border:1px solid #CBD5E1;">
                                    🅿️ Slot Booking
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($tx['description'] ?? 'Points transaction') ?></strong>
                            <?php if (!empty($tx['package_name'])): ?>
                                <span class="text-muted" style="font-size:12px;">(<?= htmlspecialchars($tx['package_name']) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right; font-weight:800; font-size:15px; color:<?= $is_credit ? '#059669' : '#DC2626' ?>;">
                            <?php 
                                $tx_pts_val = (float)$tx['points'];
                                $tx_pts_str = ($tx_pts_val == (int)$tx_pts_val) ? (int)$tx_pts_val : number_format($tx_pts_val, 2);
                            ?>
                            <?= $is_credit ? '+' . $tx_pts_str : $tx_pts_str ?> pts
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

</div><!-- /max-w-7xl -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
