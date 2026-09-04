<?php
// header.php — shared page shell.
// $page_title and $body_page must be set by the calling page before this include.

$page_title = $page_title ?? 'CampusPark';
$body_page  = $body_page  ?? '';
$user       = current_user();
$is_authed  = !empty($user['id']);
$current_file = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — CampusPark</title>
    <meta name="description" content="Reserve campus parking in seconds. CampusPark lets students and staff book dedicated spots across all university zones.">
    <link rel="icon" type="image/jpeg" href="/parking-system/assets/images/logo.jpg">
    <link rel="apple-touch-icon" href="/parking-system/assets/images/logo.jpg">

    <!-- Google Fonts: Plus Jakarta Sans (Display/Hero) & Geist -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Material Symbols (for icons matching landing.html) -->
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

    <!-- Leaflet CSS & JS for Interactive Campus Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- Primary stylesheet — with cache busting -->
    <link rel="stylesheet" href="/parking-system/assets/css/style.css?v=<?= file_exists($_SERVER['DOCUMENT_ROOT'] . '/parking-system/assets/css/style.css') ? filemtime($_SERVER['DOCUMENT_ROOT'] . '/parking-system/assets/css/style.css') : time() ?>">

    <!-- Tailwind CDN with same config as landing.html —
         used as fallback for trivial layout utilities (flex, gap-*, grid-cols-*, etc.)
         that don't warrant a named class in style.css. -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              "primary":               "#0891B2",
              "on-primary":            "#ffffff",
              "primary-container":     "#0e7490",
              "secondary":             "#06B6D4",
              "on-secondary":          "#ffffff",
              "secondary-container":   "#0891b2",
              "tertiary":              "#475569",
              "surface":               "#f7f9fb",
              "surface-bright":        "#f7f9fb",
              "surface-variant":       "#e2e8f0",
              "surface-container-lowest": "#ffffff",
              "surface-container-low": "#f1f5f9",
              "surface-container":     "#e2e8f0",
              "surface-container-high":"#cbd5e1",
              "surface-container-highest":"#94a3b8",
              "surface-dim":           "#cbd5e1",
              "background":            "#f7f9fb",
              "on-background":         "#0f172a",
              "on-surface":            "#0f172a",
              "on-surface-variant":    "#475569",
              "outline":               "#64748b",
              "outline-variant":       "#cbd5e1",
              "inverse-surface":       "#0f131b",
              "inverse-on-surface":    "#f8fafc",
              "inverse-primary":       "#22d3ee",
              "error":                 "#b91c1c",
              "on-error":              "#ffffff",
              "error-container":       "#fef2f2",
              "on-error-container":    "#991b1b"
            },
            borderRadius: {
              "DEFAULT": "0.25rem",
              "lg":      "0.5rem",
              "xl":      "0.75rem",
              "2xl":     "1rem",
              "3xl":     "1.5rem",
              "full":    "9999px"
            },
            spacing: {
              "xs":            "4px",
              "sm":            "12px",
              "md":            "24px",
              "lg":            "40px",
              "xl":            "64px",
              "gutter":        "24px",
              "base":          "8px",
              "margin-mobile": "16px",
              "margin-desktop":"48px"
            },
            fontFamily: {
              "display":            ["Geist", "system-ui", "sans-serif"],
              "headline-lg":        ["Geist", "system-ui", "sans-serif"],
              "headline-md":        ["Geist", "system-ui", "sans-serif"],
              "body-lg":            ["Geist", "system-ui", "sans-serif"],
              "body-md":            ["Geist", "system-ui", "sans-serif"],
              "label-md":           ["Geist", "system-ui", "sans-serif"],
              "label-sm":           ["Geist", "system-ui", "sans-serif"]
            },
            fontSize: {
              "display":     ["48px", { lineHeight:"56px", letterSpacing:"-0.02em", fontWeight:"700" }],
              "headline-lg": ["32px", { lineHeight:"40px", letterSpacing:"-0.01em", fontWeight:"600" }],
              "headline-md": ["24px", { lineHeight:"32px", fontWeight:"600" }],
              "body-lg":     ["18px", { lineHeight:"28px", fontWeight:"400" }],
              "body-md":     ["16px", { lineHeight:"24px", fontWeight:"400" }],
              "label-md":    ["14px", { lineHeight:"20px", letterSpacing:"0.01em", fontWeight:"500" }],
              "label-sm":    ["12px", { lineHeight:"16px", fontWeight:"600" }]
            }
          }
        }
      }
    </script>
</head>
<body data-page="<?= htmlspecialchars($body_page) ?>"
      class="font-body-md text-body-md min-h-screen flex flex-col bg-background text-on-surface">

<!-- =========================================================
     Frosted-glass sticky navbar  (matches landing.html header)
     ========================================================= -->
<header class="navbar" role="banner">
  <div class="navbar-inner">
    <a href="/parking-system/public/index.php" class="navbar-brand" aria-label="CampusPark home">
        <img src="/parking-system/assets/images/logo.jpg" alt="CampusPark Logo" class="navbar-brand-logo" width="32" height="32">
        CampusPark
    </a>

    <!-- Desktop nav -->
    <nav class="navbar-nav" role="navigation" aria-label="Primary">
        <button aria-label="Toggle Theme" class="theme-toggle-btn material-symbols-outlined nav-link" style="cursor:pointer; background:none; border:none; padding:6px 10px;">
            dark_mode
        </button>
        <?php if ($is_authed): ?>
            <a href="/parking-system/public/dashboard.php"
               class="nav-link <?= $current_file === 'dashboard.php' ? 'nav-link--active' : '' ?>">
                Dashboard
            </a>
            <a href="/parking-system/public/book-slot.php"
               class="nav-link <?= $current_file === 'book-slot.php' ? 'nav-link--active' : '' ?>">
                Book a Slot
            </a>
            <span class="nav-link text-muted" style="cursor:default; font-size:13px;">
                <?= htmlspecialchars($user['full_name'] ?? $user['name'] ?? '') ?>
            </span>
            <a href="/parking-system/public/logout.php" class="nav-link nav-link--cta">
                Log Out
            </a>
        <?php else: ?>
            <a href="/parking-system/public/login.php"
               class="nav-link nav-link--ghost <?= $current_file === 'login.php' ? 'nav-link--active' : '' ?>">
                Log In
            </a>
            <a href="/parking-system/public/signup.php"
               class="nav-link nav-link--cta">
                Sign Up Free
            </a>
        <?php endif; ?>
    </nav>

    <!-- Mobile hamburger -->
    <button class="navbar-hamburger" id="navbarHamburger" aria-label="Open menu" aria-expanded="false">
        <span class="material-symbols-outlined">menu</span>
    </button>
  </div>
</header>

<!-- Mobile Nav Drawer -->
<div id="navbarMobileMenu" class="navbar-mobile-menu" aria-hidden="true">
    <button aria-label="Toggle Theme" class="theme-toggle-btn mobile-nav-link flex items-center gap-2" style="cursor:pointer; background:none; border:none; width:100%; text-align:left;">
        <span class="material-symbols-outlined">dark_mode</span>
        <span class="theme-toggle-label">Dark Mode</span>
    </button>
    <?php if ($is_authed): ?>
        <a href="/parking-system/public/dashboard.php"
           class="mobile-nav-link <?= $current_file === 'dashboard.php' ? 'mobile-nav-link--active' : '' ?>">
            Dashboard
        </a>
        <a href="/parking-system/public/book-slot.php"
           class="mobile-nav-link <?= $current_file === 'book-slot.php' ? 'mobile-nav-link--active' : '' ?>">
            Book a Slot
        </a>
        <div class="mobile-nav-user">
            Logged in as <strong><?= htmlspecialchars($user['full_name'] ?? $user['name'] ?? '') ?></strong>
        </div>
        <a href="/parking-system/public/logout.php" class="mobile-nav-link mobile-nav-link--cta">
            Log Out
        </a>
    <?php else: ?>
        <a href="/parking-system/public/login.php"
           class="mobile-nav-link <?= $current_file === 'login.php' ? 'mobile-nav-link--active' : '' ?>">
            Log In
        </a>
        <a href="/parking-system/public/signup.php"
           class="mobile-nav-link mobile-nav-link--cta">
            Sign Up Free
        </a>
    <?php endif; ?>
</div>

<!-- All page content goes inside this wrapper -->
<main id="main-content" class="flex-grow">
