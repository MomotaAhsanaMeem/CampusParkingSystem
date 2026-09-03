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

    <!-- Geist font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Material Symbols (for icons matching landing.html) -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">

    <!-- Primary stylesheet — all custom styling lives here -->
    <link rel="stylesheet" href="/parking-system/assets/css/style.css">

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
              "primary":               "#9f3c27",
              "on-primary":            "#ffffff",
              "primary-container":     "#bf533c",
              "secondary":             "#6b38d4",
              "on-secondary":          "#ffffff",
              "secondary-container":   "#8455ef",
              "tertiary":              "#585c65",
              "surface":               "#f7f9fb",
              "surface-bright":        "#f7f9fb",
              "surface-variant":       "#e0e3e5",
              "surface-container-lowest": "#ffffff",
              "surface-container-low": "#f2f4f6",
              "surface-container":     "#eceef0",
              "surface-container-high":"#e6e8ea",
              "surface-container-highest":"#e0e3e5",
              "surface-dim":           "#d8dadc",
              "background":            "#f7f9fb",
              "on-background":         "#191c1e",
              "on-surface":            "#191c1e",
              "on-surface-variant":    "#57423d",
              "outline":               "#8a726c",
              "outline-variant":       "#ddc0ba",
              "inverse-surface":       "#2d3133",
              "inverse-on-surface":    "#eff1f3",
              "inverse-primary":       "#ffb4a4",
              "error":                 "#ba1a1a",
              "on-error":              "#ffffff",
              "error-container":       "#ffdad6",
              "on-error-container":    "#93000a"
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

    <a href="/parking-system/public/index.php" class="navbar-brand" aria-label="CampusPark home">
        <div class="navbar-brand-icon" aria-hidden="true">P</div>
        CampusPark
    </a>

    <!-- Desktop nav -->
    <nav class="navbar-nav" role="navigation" aria-label="Primary">
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
                <?= htmlspecialchars($user['name'] ?? '') ?>
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

    <!-- Mobile hamburger (visual only for Update 1) -->
    <button class="navbar-hamburger" aria-label="Open menu">
        <span class="material-symbols-outlined">menu</span>
    </button>

</header>

<!-- All page content goes inside this wrapper -->
<main id="main-content" class="flex-grow">
