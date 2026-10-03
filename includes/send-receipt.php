<?php
// send-receipt.php — Compiles a booking summary for the logged-in user and
// emails it to their own address. Handles two periods: 'today' and 'month'.
// Redirects back to dashboard.php with a flash message on completion.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

require_once __DIR__ . '/../includes/mailer.php';

$user   = current_user();
$uid    = (int) $user['id'];
$email  = $user['email'] ?? '';
$name   = $user['name']  ?? 'Driver';
$period = $_POST['period'] ?? '';

// Fallback to database if email is missing from session
if (empty($email)) {
    $uStmt = $pdo->prepare('SELECT email, full_name FROM users WHERE id = ?');
    $uStmt->execute([$uid]);
    $uRow = $uStmt->fetch();
    if ($uRow) {
        $email = $uRow['email'] ?? '';
        if (!empty($uRow['full_name'])) {
            $name = $uRow['full_name'];
        }
        $_SESSION['email'] = $email;
    }
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'No valid email address associated with your account.';
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

if (!in_array($period, ['today', 'month'], true)) {
    // Smallest reasonable choice: treat unknown period as bad request → back to dashboard
    $_SESSION['flash_error'] = 'Invalid receipt period requested.';
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// ── Build date range ──────────────────────────────────────────────────────────
if ($period === 'today') {
    $dateLabel  = 'Today (' . date('F j, Y') . ')';
    $whereExtra = 'AND b.booking_date = CURDATE()';
} else {
    // 'month' — current calendar month
    $dateLabel  = date('F Y');
    $whereExtra = 'AND YEAR(b.booking_date)  = YEAR(CURDATE())
                   AND MONTH(b.booking_date) = MONTH(CURDATE())';
}

// ── Fetch bookings ────────────────────────────────────────────────────────────
$sql = "
    SELECT b.id, b.booking_date, b.start_time, b.end_time,
           b.duration_hours, b.points_cost, b.status,
           s.slot_code, s.zone
      FROM bookings b
      JOIN parking_slots s ON s.id = b.slot_id
     WHERE b.user_id = ? {$whereExtra}
     ORDER BY b.booking_date ASC, b.start_time ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$uid]);
$rows = $stmt->fetchAll();

// ── Build HTML email body ─────────────────────────────────────────────────────
$rowsHtml = '';
$totalPts  = 0;

if (empty($rows)) {
    $rowsHtml = '<tr><td colspan="7" style="text-align:center; color:#94a3b8; padding:16px;">No bookings found for this period.</td></tr>';
} else {
    foreach ($rows as $b) {
        $timeRange = '—';
        if (!empty($b['start_time']) && !empty($b['end_time'])) {
            $timeRange = date('g:i A', strtotime($b['start_time']))
                       . ' – '
                       . date('g:i A', strtotime($b['end_time']));
        }
        $pts        = (int) ($b['points_cost'] ?? 10);
        $totalPts  += $pts;
        $statusLbl  = ucfirst(str_replace('_', ' ', $b['status']));
        $dateStr    = date('M j, Y', strtotime($b['booking_date']));
        $bid        = '#CP-' . str_pad((string) $b['id'], 4, '0', STR_PAD_LEFT);
        $rowsHtml  .= "
            <tr>
                <td style='padding:8px 10px; font-size:13px; color:#64748b; font-family:monospace;'>{$bid}</td>
                <td style='padding:8px 10px; font-size:13px; font-weight:700;'>{$b['slot_code']}</td>
                <td style='padding:8px 10px; font-size:13px;'>{$b['zone']}</td>
                <td style='padding:8px 10px; font-size:13px;'>{$dateStr}</td>
                <td style='padding:8px 10px; font-size:13px; color:#0891B2;'>{$timeRange}</td>
                <td style='padding:8px 10px; font-size:13px;'>{$pts} pts</td>
                <td style='padding:8px 10px; font-size:13px;'>{$statusLbl}</td>
            </tr>
        ";
    }
}

$safeName   = htmlspecialchars($name);
$safeLabel  = htmlspecialchars($dateLabel);
$safeEmail  = htmlspecialchars($email);
$totalPtsStr = number_format($totalPts);

$subject = "CampusPark Parking Receipt - {$dateLabel}";
$body    = "
<div style='font-family:sans-serif; color:#0f172a; max-width:640px; margin:auto; padding:24px;'>
    <h2 style='color:#0891B2; margin-bottom:4px;'>CampusPark Parking Receipt</h2>
    <p>Hi {$safeName},</p>
    <p>Here is your parking summary for <strong>{$safeLabel}</strong>.</p>

    <table style='width:100%; border-collapse:collapse; margin:20px 0; font-size:13px;'>
        <thead>
            <tr style='background:#f1f5f9;'>
                <th style='padding:8px 10px; text-align:left; color:#475569;'>Booking ID</th>
                <th style='padding:8px 10px; text-align:left; color:#475569;'>Slot</th>
                <th style='padding:8px 10px; text-align:left; color:#475569;'>Zone</th>
                <th style='padding:8px 10px; text-align:left; color:#475569;'>Date</th>
                <th style='padding:8px 10px; text-align:left; color:#475569;'>Time</th>
                <th style='padding:8px 10px; text-align:left; color:#475569;'>Points</th>
                <th style='padding:8px 10px; text-align:left; color:#475569;'>Status</th>
            </tr>
        </thead>
        <tbody>
            {$rowsHtml}
        </tbody>
        <tfoot>
            <tr style='border-top:2px solid #e2e8f0;'>
                <td colspan='5' style='padding:10px; font-weight:700; text-align:right;'>Total Points Used:</td>
                <td colspan='2' style='padding:10px; font-weight:700; color:#0891B2;'>{$totalPtsStr} pts</td>
            </tr>
        </tfoot>
    </table>

    <p style='font-size:12px; color:#94a3b8; margin-top:24px;'>
        This receipt was sent to {$safeEmail} on " . date('F j, Y \a\t g:i A') . ".
        <br>&mdash; CampusPark
    </p>
</div>
";

$ok = send_email($email, $subject, $body);

if ($ok) {
    $_SESSION['flash'] = "Receipt for {$dateLabel} sent to {$email}.";
} else {
    // Reuse the existing email-failure wording the codebase already shows on other pages
    $_SESSION['flash_error'] = 'Email delivery failed. Please check your SMTP settings and try again.';
}

header('Location: ' . BASE_URL . '/public/dashboard.php');
exit;
