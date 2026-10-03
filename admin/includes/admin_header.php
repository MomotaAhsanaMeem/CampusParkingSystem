<?php
// admin/includes/admin_header.php — Shared Admin Layout Shell
// Must only be loaded on admin pages after db.php and auth.php are included,
// and after require_admin() has already been called.

require_once __DIR__ . '/../../includes/auth.php';
// Note: auth.php and require_admin() must be called by the parent page before including this file.

$admin_title      = $admin_title      ?? 'Overview';
$admin_active_nav = $admin_active_nav ?? 'dashboard';
$current_admin    = current_user();

// Fetch latest admin data from DB to guarantee current state
if (!empty($current_admin['id']) && isset($pdo)) {
    try {
        $aStmt = $pdo->prepare('SELECT id, full_name, email, role, created_at FROM users WHERE id = ?');
        $aStmt->execute([$current_admin['id']]);
        $freshAdmin = $aStmt->fetch();
        if ($freshAdmin) {
            $current_admin['full_name'] = $freshAdmin['full_name'];
            $current_admin['email']     = $freshAdmin['email'];
            $current_admin['created_at'] = $freshAdmin['created_at'];
        }
    } catch (Throwable $e) {}
}

$admin_name  = htmlspecialchars($current_admin['full_name'] ?? $current_admin['name'] ?? 'Admin');
$admin_email = htmlspecialchars($current_admin['email'] ?? 'admin@campuspark.edu');
$initials    = strtoupper(substr($admin_name, 0, 1));
if (strpos($admin_name, ' ') !== false) {
    $parts = explode(' ', $admin_name);
    $initials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($admin_title) ?> — CampusPark Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/jpeg" href="<?= BASE_URL ?>/assets/images/logo.jpg">

    <!-- Google Fonts: Plus Jakarta Sans & Geist -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">

    <!-- Early theme init script to prevent FOUC -->
    <script>
      (function() {
        var saved = localStorage.getItem('theme');
        if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
          document.documentElement.classList.add('dark');
        } else {
          document.documentElement.classList.remove('dark');
        }
      })();
    </script>

    <!-- Primary stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= file_exists(__DIR__ . '/../../assets/css/style.css') ? filemtime(__DIR__ . '/../../assets/css/style.css') : time() ?>">

    <!-- Tailwind CDN fallback for layout utilities -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              "primary": "#0891B2",
              "secondary": "#06B6D4"
            }
          }
        }
      }
    </script>
</head>
<body class="font-body-md text-body-md min-h-screen bg-background text-on-surface antialiased">

<div class="admin-layout">

    <!-- Mobile Drawer Overlay -->
    <div class="admin-sidebar-overlay" id="adminSidebarOverlay" aria-hidden="true"></div>

    <!-- Admin Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar" role="navigation" aria-label="Admin Navigation">
        <!-- Sidebar Brand Header -->
        <div class="admin-sidebar-header">
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="admin-brand">
                <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="CampusPark" style="width:28px;height:28px;border-radius:6px;object-fit:cover;">
                <span>CampusPark</span>
                <span class="admin-brand-badge">ADMIN</span>
            </a>
            <button type="button" class="admin-menu-toggle" id="adminSidebarCloseBtn" aria-label="Close menu" style="display:none;">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <!-- Sidebar Navigation Groups -->
        <div class="admin-sidebar-scroll">
            <!-- Group 1: Monitoring & Overview -->
            <div class="admin-nav-group">
                <div class="admin-nav-heading">Console Overview</div>
                <a href="<?= BASE_URL ?>/admin/dashboard.php"
                   class="admin-nav-link <?= $admin_active_nav === 'dashboard' ? 'admin-nav-link--active' : '' ?>">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/profile.php"
                   class="admin-nav-link <?= $admin_active_nav === 'profile' ? 'admin-nav-link--active' : '' ?>">
                    <span class="material-symbols-outlined">account_circle</span>
                    <span>Admin Profile</span>
                </a>
            </div>

            <!-- Group 2: Operations (Step 2) -->
            <div class="admin-nav-group">
                <div class="admin-nav-heading">Management</div>
                <a href="<?= BASE_URL ?>/admin/slots.php"
                   class="admin-nav-link <?= $admin_active_nav === 'slots' ? 'admin-nav-link--active' : '' ?>">
                    <span class="material-symbols-outlined">local_parking</span>
                    <span>Slot Management</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/bookings.php"
                   class="admin-nav-link <?= $admin_active_nav === 'bookings' ? 'admin-nav-link--active' : '' ?>">
                    <span class="material-symbols-outlined">event_available</span>
                    <span>All Bookings</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/users.php"
                   class="admin-nav-link <?= $admin_active_nav === 'users' ? 'admin-nav-link--active' : '' ?>">
                    <span class="material-symbols-outlined">group</span>
                    <span>Users Directory</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/complaints.php"
                   class="admin-nav-link <?= $admin_active_nav === 'complaints' ? 'admin-nav-link--active' : '' ?>">
                    <span class="material-symbols-outlined">campaign</span>
                    <span>Complaints</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/export-pdf.php?type=audit" target="_blank"
                   class="admin-nav-link">
                    <span class="material-symbols-outlined">picture_as_pdf</span>
                    <span>Export Audit PDF</span>
                </a>
            </div>

            <!-- Group 4: Navigation -->
            <div class="admin-nav-group" style="margin-top:auto;">
                <div class="admin-nav-heading">External</div>
                <a href="<?= BASE_URL ?>/public/dashboard.php" class="admin-nav-link" target="_blank" rel="noopener">
                    <span class="material-symbols-outlined">open_in_new</span>
                    <span>View User Portal</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/logout.php" class="admin-nav-link" style="color:var(--clr-error);">
                    <span class="material-symbols-outlined">logout</span>
                    <span>Log Out</span>
                </a>
            </div>
        </div>

        <!-- Sidebar Footer Admin Mini Profile -->
        <div class="admin-sidebar-footer">
            <a href="<?= BASE_URL ?>/admin/profile.php" class="admin-profile-mini" title="View Admin Profile">
                <div class="admin-avatar"><?= $initials ?></div>
                <div class="admin-profile-meta">
                    <div class="admin-profile-name"><?= $admin_name ?></div>
                    <div class="admin-profile-role">
                        <span class="admin-status-indicator"></span>
                        <span>Administrator</span>
                    </div>
                </div>
            </a>
            <a href="<?= BASE_URL ?>/admin/logout.php" title="Log Out" style="color:var(--clr-text-muted); display:flex; align-items:center;">
                <span class="material-symbols-outlined" style="font-size:18px;">logout</span>
            </a>
        </div>
    </aside>

    <!-- Admin Main Body -->
    <div class="admin-main">
        <!-- Top Navbar -->
        <header class="admin-topbar" role="banner">
            <div class="admin-topbar-left">
                <button type="button" class="admin-menu-toggle" id="adminMenuToggle" aria-label="Open sidebar menu">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div class="admin-breadcrumb">
                    <span>Admin</span>
                    <span class="material-symbols-outlined" style="font-size:14px;">chevron_right</span>
                    <span class="admin-breadcrumb-active"><?= htmlspecialchars($admin_title) ?></span>
                </div>
            </div>

            <div class="admin-topbar-right">
                <div class="admin-pill-status admin-pill-status--pulse hidden sm:inline-flex">
                    <span>Campus Live Telemetry</span>
                </div>

                <!-- Theme Toggle Button -->
                <button aria-label="Toggle Theme" class="theme-toggle-btn material-symbols-outlined" style="cursor:pointer; background:var(--clr-surface-low); border:1px solid var(--clr-border); border-radius:8px; padding:6px 10px; color:var(--clr-text);">
                    dark_mode
                </button>

                <!-- User Portal Quick Link -->
                <a href="<?= BASE_URL ?>/public/dashboard.php" class="btn btn-outline" style="padding:6px 12px; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:16px;">directions_car</span>
                    <span class="hidden sm:inline">User Portal</span>
                </a>

                <!-- Profile Link -->
                <a href="<?= BASE_URL ?>/admin/profile.php" class="admin-avatar" style="width:32px;height:32px;font-size:12px;text-decoration:none;" title="Admin Profile">
                    <?= $initials ?>
                </a>
            </div>
        </header>

        <!-- Main Content Wrapper -->
        <main class="admin-body" id="adminMainContent">
