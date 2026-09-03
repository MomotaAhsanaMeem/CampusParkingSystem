# CONVENTIONS.md — Parking Reservation System

Read this before writing any code. Follow it exactly so every file looks
like it came from the same hand, no matter which session wrote it.

## Stack rules
- Plain PHP (no frameworks, no Composer, no build tools).
- MySQL via **PDO only**, always with prepared statements. Never
  concatenate variables into SQL strings.
- Vanilla JS. No jQuery.
- **CSS: `assets/css/style.css` is the primary styling source** — it
  should match DESIGN.md's actual look (DESIGN.md uses plain/custom
  classes, not Tailwind). The Tailwind CDN script
  (`<script src="https://cdn.tailwindcss.com"></script>`, loaded in
  `header.php`) is available as a fallback for quick one-off layout
  utilities (e.g. `flex`, `gap-4`) ONLY when writing a new custom CSS
  rule would be overkill for something small and one-off. Don't use
  Tailwind classes for anything that already has (or should have) a
  named class in `style.css` — that would fragment styling across two
  systems. When in doubt, add it to `style.css` instead.

## File & folder structure
```
/public      → every page a user can visit directly (index.php, login.php,
               signup.php, book-slot.php, dashboard.php, logout.php)
/includes    → shared logic, never output directly (db.php, auth.php,
               header.php, footer.php)
/admin       → admin-only pages (later update)
/assets/css  → style.css (all styling lives here — no <style> tags)
/assets/js   → main.js (no inline <script> logic beyond calling functions)
/sql         → schema.sql and any migration files
```
- One feature = one file in `/public`, using shared pieces from
  `/includes`. Don't split a single feature across multiple files unless
  it's genuinely reused elsewhere.

## Naming
- Files: `snake_case.php` (e.g. `book_slot.php`, not `BookSlot.php`).
- PHP variables/functions: `snake_case` (`$user_id`, `require_login()`).
- DB tables/columns: `snake_case`, plural table names (`users`,
  `parking_slots`, `bookings`) — matches `Schema.sql`.
- CSS classes: lowercase-hyphenated, lightly BEM-style
  (`.card`, `.card-title`, `.btn`, `.btn-primary`, `.form-group`).
- IDs only for JS hooks, never for styling (`id="bookSlotForm"`).

## Every page in /public follows this shape
```php
<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login(); // omit only on login.php / signup.php / index.php

// --- form handling / DB logic up here, before any HTML output ---

require_once __DIR__ . '/../includes/header.php';
?>
<!-- page HTML here -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
```
Logic stays at the top of the file, above the HTML. Don't mix DB calls
into the middle of markup.

## Database access
- All connections go through `includes/db.php` — never open a new PDO
  connection elsewhere.
- Every query uses `?` placeholders with `->prepare()` / `->execute()`.
- Passwords: `password_hash()` on signup, `password_verify()` on login.
  Never store or compare plaintext.

## Auth
- Session-based (`$_SESSION['user_id']`, `$_SESSION['role']`).
- `includes/auth.php` owns all session logic: `require_login()`,
  `require_admin()`, `current_user()`. Pages call these — they don't
  touch `$_SESSION` directly.

## Errors & validation
- Validate all form input server-side, even if JS also validates it.
- Show errors inline near the field, in a `.form-error` element — no
  raw `die()` or `var_dump()` left in finished code.
- User-facing error text stays generic where it matters (e.g. login
  failure = "invalid email or password", not "email not found").

## Comments
- One short comment per function explaining *why*, not *what* (the code
  already shows what).
- No commented-out old code left behind — delete it.

## Scope discipline
- Only build what the current update explicitly asks for. If a feature
  needs something from a later update (e.g. notifications), stub it
  with a TODO comment instead of building it early.
