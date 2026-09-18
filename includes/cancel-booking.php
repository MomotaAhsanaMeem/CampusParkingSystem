<?php
// cancel-booking.php — POST-only handler for cancelling a reservation.
// Sets status = 'cancelled' without refunding points (per policy).
// Never outputs HTML; redirects back to dashboard.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

$user = current_user();
$user_id = (int) $user['id'];
$booking_id = (int) ($_POST['booking_id'] ?? 0);

if ($booking_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid booking request.';
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// Fetch the booking — must belong to this user and be in 'booked' status
$stmt = $pdo->prepare("SELECT id, slot_id, booking_date, start_time, end_time FROM bookings WHERE id = ? AND user_id = ? AND status = 'booked'");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();

if (!$booking) {
    $_SESSION['flash_error'] = 'Booking cannot be cancelled or was not found.';
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// Update status to 'cancelled'
$upd = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
$upd->execute([$booking_id]);

// IMPORTANT: Points are NON-REFUNDABLE ("no returning points")
// Do not modify users.reward_points or add any refund transaction.

$formatted_id = '#CP-' . str_pad((string)$booking_id, 4, '0', STR_PAD_LEFT);
$_SESSION['flash'] = "Booking {$formatted_id} has been cancelled. Note: As per policy, points are non-refundable.";

header('Location: ' . BASE_URL . '/public/dashboard.php');
exit;
