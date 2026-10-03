<?php
// check-reminders.php — Pseudo-cron endpoint called by the JS poller in footer.php.
// Finds any 'booked' booking for today whose start_time is within the owner's
// reminder_minutes_before window, sends one check-in reminder email, then marks
// reminder_sent = 1 so the email is never duplicated.
// Returns JSON so the JS caller can log the result without crashing.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Only callable while a user session is active (guards against anonymous polling)
if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'reason' => 'not_logged_in']);
    exit;
}

require_once __DIR__ . '/../includes/mailer.php';

// Find all booked-today bookings where we are inside the user's reminder window
// and reminder_sent is still 0. Join users to get email + reminder_minutes_before.
$sql = "
    SELECT b.id,
           b.start_time,
           b.booking_date,
           u.email,
           u.full_name,
           u.reminder_minutes_before,
           s.slot_code,
           s.zone
      FROM bookings b
      JOIN users         u ON u.id = b.user_id
      JOIN parking_slots s ON s.id = b.slot_id
     WHERE b.status          = 'booked'
       AND b.reminder_sent   = 0
       AND b.booking_date    = CURDATE()
       AND b.start_time IS NOT NULL
       AND TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(b.booking_date, ' ', b.start_time))
           BETWEEN 0 AND u.reminder_minutes_before
";

$rows = $pdo->query($sql)->fetchAll();

$sent   = 0;
$failed = 0;

foreach ($rows as $row) {
    $to      = $row['email'];
    $name    = htmlspecialchars($row['full_name']);
    $slot    = htmlspecialchars($row['slot_code']);
    $zone    = htmlspecialchars($row['zone']);
    $date    = date('F j, Y', strtotime($row['booking_date']));
    $timeStr = date('g:i A', strtotime($row['start_time']));
    $mins    = (int) $row['reminder_minutes_before'];

    $subject = "Reminder: Your CampusPark check-in starts in {$mins} minutes";
    $body    = "
        <div style='font-family:sans-serif; color:#0f172a; max-width:520px; margin:auto; padding:24px;'>
            <h2 style='color:#0891B2; margin-bottom:4px;'>CampusPark Check-in Reminder</h2>
            <p>Hi {$name},</p>
            <p>This is your <strong>{$mins}-minute</strong> reminder to check in for your parking reservation today.</p>
            <table style='width:100%; border-collapse:collapse; margin:16px 0;'>
                <tr><td style='padding:6px 0; color:#475569; font-size:13px;'>Parking Slot</td>
                    <td style='padding:6px 0; font-weight:700;'>{$slot}</td></tr>
                <tr><td style='padding:6px 0; color:#475569; font-size:13px;'>Zone</td>
                    <td style='padding:6px 0;'>{$zone}</td></tr>
                <tr><td style='padding:6px 0; color:#475569; font-size:13px;'>Date</td>
                    <td style='padding:6px 0;'>{$date}</td></tr>
                <tr><td style='padding:6px 0; color:#475569; font-size:13px;'>Start Time</td>
                    <td style='padding:6px 0; font-weight:700; color:#0891B2;'>{$timeStr}</td></tr>
            </table>
            <p style='font-size:13px; color:#475569;'>
                Please head to your slot and check in on the CampusPark dashboard before your scheduled time to avoid a late check-in penalty.
            </p>
            <p style='font-size:12px; color:#94a3b8; margin-top:24px;'>&mdash; CampusPark Automated Reminder</p>
        </div>
    ";

    $ok = send_email($to, $subject, $body);
    if ($ok) {
        // Mark sent so this booking is never emailed again
        $pdo->prepare('UPDATE bookings SET reminder_sent = 1 WHERE id = ?')->execute([$row['id']]);
        $sent++;
    } else {
        $failed++;
    }
}

echo json_encode(['ok' => true, 'sent' => $sent, 'failed' => $failed, 'checked' => count($rows)]);
