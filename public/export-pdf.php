<?php
// public/export-pdf.php — User Parking History & Receipt PDF Generator
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/CampusParkPDF.php';

require_login();

$user = current_user();
$user_id = (int)$user['id'];

$period     = trim($_GET['period'] ?? '');
$booking_id = (int)($_GET['booking_id'] ?? 0);
$inline     = isset($_GET['inline']) && $_GET['inline'] === '1';

// ═══════════════════════════════════════════════════════════════════════════
// CASE 1: SINGLE BOOKING OFFICIAL RECEIPT & PERMIT
// ═══════════════════════════════════════════════════════════════════════════
if ($booking_id > 0) {
    $stmt = $pdo->prepare(
        "SELECT b.*, s.slot_code, s.zone, u.full_name, u.email, u.package_tier
           FROM bookings b
           JOIN parking_slots s ON s.id = b.slot_id
           JOIN users u ON u.id = b.user_id
          WHERE b.id = ? AND b.user_id = ?"
    );
    $stmt->execute([$booking_id, $user_id]);
    $b = $stmt->fetch();

    if (!$b) {
        http_response_code(404);
        die('Booking not found or unauthorized.');
    }

    $pdf = new CampusParkPDF(
        'P',
        'Official Parking Receipt',
        'Booking #CP-' . str_pad((string)$b['id'], 5, '0', STR_PAD_LEFT),
        $user['full_name']
    );
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Receipt Banner Box
    $pdf->SetFillColor(241, 245, 249);
    $pdf->SetDrawColor(203, 213, 225);
    $pdf->RoundedRect(14, 28, 182, 38, 3, 'DF');

    $pdf->SetXY(20, 32);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(8, 145, 178);
    $pdf->Cell(100, 6, CampusParkPDF::safe('RESERVATION PERMIT & EXPENSE RECEIPT'), 0, 0, 'L');

    // Status pill
    $pdf->SetXY(145, 33);
    $statusText = strtoupper($b['status']);
    if ($b['status'] === 'checked_in')  { $pdf->SetFillColor(16, 185, 129); $pdf->SetTextColor(255, 255, 255); }
    elseif ($b['status'] === 'completed') { $pdf->SetFillColor(8, 145, 178); $pdf->SetTextColor(255, 255, 255); }
    elseif ($b['status'] === 'cancelled') { $pdf->SetFillColor(239, 68, 68); $pdf->SetTextColor(255, 255, 255); }
    else { $pdf->SetFillColor(100, 116, 139); $pdf->SetTextColor(255, 255, 255); }
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(45, 6, $statusText, 0, 0, 'C', true);

    $pdf->SetXY(20, 40);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(120, 5, CampusParkPDF::safe('Issued To: ' . $b['full_name'] . ' (' . $b['email'] . ')'), 0, 1, 'L');
    $pdf->SetX(20);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(120, 5, CampusParkPDF::safe('User Tier: ' . ($b['package_tier'] ?? 'Starter') . ' | Issued On: ' . date('M j, Y g:i A', strtotime($b['created_at']))), 0, 1, 'L');

    $pdf->Ln(18);

    // Reservation Details Table
    $pdf->SectionTitle('Reservation & Location Details', 'Permit Verification');

    $details = [
        ['Booking Reference ID', '#CP-' . str_pad((string)$b['id'], 5, '0', STR_PAD_LEFT), 'Parking Bay / Slot', $b['slot_code']],
        ['Campus Zone', $b['zone'], 'Reservation Date', date('F j, Y', strtotime($b['booking_date']))],
        ['Scheduled Time Window', date('g:i A', strtotime($b['start_time'])) . ' - ' . date('g:i A', strtotime($b['end_time'])), 'Allocated Duration', $b['duration_hours'] . ' hour(s)'],
        ['Actual Check-In Time', !empty($b['check_in_time']) ? date('M j, Y g:i A', strtotime($b['check_in_time'])) : 'Not checked in', 'Late Check-In Status', $b['is_late_checkin'] ? 'YES (Late penalty applied)' : 'On-time / Regular'],
        ['Actual Check-Out Time', !empty($b['check_out_time']) ? date('M j, Y g:i A', strtotime($b['check_out_time'])) : 'Pending check-out', 'Active Status', ucfirst($b['status'])],
    ];

    $wCol = [45, 46, 45, 46];
    foreach ($details as $rowIdx => $row) {
        $fill = ($rowIdx % 2 === 0);
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetLineWidth(0.2);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->Cell($wCol[0], 8, CampusParkPDF::safe($row[0]), 1, 0, 'L', $fill);

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell($wCol[1], 8, CampusParkPDF::safe($row[1]), 1, 0, 'L', $fill);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->Cell($wCol[2], 8, CampusParkPDF::safe($row[2]), 1, 0, 'L', $fill);

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell($wCol[3], 8, CampusParkPDF::safe($row[3]), 1, 1, 'L', $fill);
    }

    $pdf->Ln(8);

    // Financial & Points Accounting Breakdown
    $pdf->SectionTitle('Points Accounting & Charges', 'Billed to CampusPark Account');

    $pdf->SetFillColor(8, 145, 178);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(110, 8, ' Charge Description', 1, 0, 'L', true);
    $pdf->Cell(36, 8, 'Rate', 1, 0, 'C', true);
    $pdf->Cell(36, 8, 'Points', 1, 1, 'R', true);

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->SetFillColor(255, 255, 255);

    // Row 1: Bay reservation
    $pdf->Cell(110, 8, CampusParkPDF::safe(' Standard Bay Reservation (' . $b['slot_code'] . ' - ' . $b['zone'] . ')'), 1, 0, 'L');
    $pdf->Cell(36, 8, CampusParkPDF::safe($b['duration_hours'] . ' hr @ 10 pts/hr'), 1, 0, 'C');
    $pdf->Cell(36, 8, number_format($b['points_cost'], 2) . ' pts ', 1, 1, 'R');

    // Row 2: Overstay / Late Penalty if any
    $penalty = (float)($b['penalty_points_deducted'] ?? 0);
    if ($penalty > 0) {
        $pdf->SetFillColor(254, 242, 242);
        $pdf->SetTextColor(185, 28, 28);
        $pdf->Cell(110, 8, CampusParkPDF::safe(' Overstay / Late Penalty Surcharge'), 1, 0, 'L', true);
        $pdf->Cell(36, 8, 'Assessed', 1, 0, 'C', true);
        $pdf->Cell(36, 8, '+' . number_format($penalty, 2) . ' pts ', 1, 1, 'R', true);
    }

    // Total Row
    $totalCharged = (float)$b['points_cost'] + $penalty;
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(241, 245, 249);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(146, 9, ' Net Total Points Billed: ', 1, 0, 'R', true);
    $pdf->SetTextColor(8, 145, 178);
    $pdf->Cell(36, 9, number_format($totalCharged, 2) . ' pts ', 1, 1, 'R', true);

    $pdf->Ln(8);

    // Terms & Conditions notice box
    $pdf->SetFillColor(248, 250, 252);
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->Rect(14, $pdf->GetY(), 182, 32, 'DF');
    $boxY = $pdf->GetY() + 4;
    $pdf->SetXY(18, $boxY);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(174, 4, CampusParkPDF::safe('Campus Parking Rules & Verification Notice:'), 0, 1, 'L');

    $pdf->SetX(18);
    $pdf->SetFont('Arial', '', 7.5);
    $pdf->SetTextColor(100, 116, 139);
    $notices = [
        '1. Check-In Window: Drivers must check in via the online portal within 15 minutes of scheduled start.',
        '2. Overstay Policy: Exceeding scheduled end time incurs automatic point deductions and risks account freeze.',
        '3. Display / Audit: This electronic receipt serves as official campus parking verification for enforcement audits.',
        '4. Inquiries: Contact Campus Parking Administration at parking-admin@campuspark.edu for disputes or assistance.'
    ];
    foreach ($notices as $notice) {
        $pdf->SetX(18);
        $pdf->Cell(174, 4, CampusParkPDF::safe($notice), 0, 1, 'L');
    }

    $filename = 'CampusPark_Receipt_CP-' . $b['id'] . '.pdf';
    $dest = $inline ? 'I' : 'D';
    $pdf->Output($dest, $filename);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// CASE 2: PERIOD STATEMENT (TODAY, MONTH, OR ALL-TIME)
// ═══════════════════════════════════════════════════════════════════════════
$where = ['b.user_id = ?'];
$params = [$user_id];

if ($period === 'today') {
    $where[] = 'b.booking_date = CURDATE()';
    $titlePeriod = "Today's Parking Activity (" . date('F j, Y') . ")";
    $fileSuffix = 'Today_' . date('Y-m-d');
} elseif ($period === 'month') {
    $where[] = 'MONTH(b.booking_date) = MONTH(CURDATE()) AND YEAR(b.booking_date) = YEAR(CURDATE())';
    $titlePeriod = 'Monthly Parking Statement (' . date('F Y') . ')';
    $fileSuffix = 'Month_' . date('Y-m');
} else {
    $titlePeriod = 'Complete Parking Activity History';
    $fileSuffix = 'AllTime_' . date('Y-m-d');
}

$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare(
    "SELECT b.*, s.slot_code, s.zone
       FROM bookings b
       JOIN parking_slots s ON s.id = b.slot_id
      WHERE {$whereSql}
      ORDER BY b.booking_date DESC, b.start_time DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Calculate aggregate stats
$totalBookings = count($rows);
$totalCost     = 0.0;
$totalHours    = 0;
$completedCt   = 0;
$lateCheckinCt = 0;

foreach ($rows as $r) {
    $totalCost += (float)$r['points_cost'] + (float)($r['penalty_points_deducted'] ?? 0);
    $totalHours += (int)$r['duration_hours'];
    if ($r['status'] === 'completed') {
        $completedCt++;
    }
    if ($r['is_late_checkin']) {
        $lateCheckinCt++;
    }
}

$pdf = new CampusParkPDF(
    'P',
    'User Parking Expense Report',
    $titlePeriod,
    $user['full_name']
);
$pdf->AliasNbPages();
$pdf->AddPage();

// 4 KPI Summary Cards
$cardY = 28;
$cardW = 43;
$cardH = 20;
$gap   = 3.3;

$pdf->KpiCard(14 + (0 * ($cardW + $gap)), $cardY, $cardW, $cardH, 'Total Sessions', (string)$totalBookings, 'Reserved bays', [8, 145, 178]);
$pdf->KpiCard(14 + (1 * ($cardW + $gap)), $cardY, $cardW, $cardH, 'Total Duration', $totalHours . ' hrs', 'Parking time', [100, 116, 139]);
$pdf->KpiCard(14 + (2 * ($cardW + $gap)), $cardY, $cardW, $cardH, 'Points Billed', number_format($totalCost, 0) . ' pts', 'Net points used', [8, 145, 178]);
$pdf->KpiCard(14 + (3 * ($cardW + $gap)), $cardY, $cardW, $cardH, 'Completed', (string)$completedCt, 'Finished bays', [16, 185, 129]);

$pdf->SetY($cardY + $cardH + 7);

// Account Holder Summary Strip
$pdf->SetFillColor(241, 245, 249);
$pdf->SetDrawColor(226, 232, 240);
$pdf->Rect(14, $pdf->GetY(), 182, 10, 'DF');

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(71, 85, 105);
$pdf->SetX(18);
$pdf->Cell(28, 10, 'ACCOUNT HOLDER:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(70, 10, CampusParkPDF::safe($user['full_name'] . ' (' . $user['email'] . ')'), 0, 0, 'L');

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(25, 10, 'CURRENT BALANCE:', 0, 0, 'R');
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(8, 145, 178);
$pdf->Cell(35, 10, number_format((float)($user['reward_points'] ?? 0), 2) . ' pts', 0, 1, 'L');

$pdf->Ln(4);

// Table Header
$pdf->SectionTitle('Session History Breakdown', $totalBookings . ' record(s) found');

$cols   = ['ID', 'Bay', 'Campus Zone', 'Date', 'Time Window', 'Duration', 'Points', 'Status'];
$widths = [16,   14,    34,            24,     36,            18,         18,       22];
$aligns = ['C',  'C',   'L',           'C',    'C',           'C',        'R',      'C'];

$pdf->SetFillColor(8, 145, 178);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 8);
foreach ($cols as $idx => $col) {
    $pdf->Cell($widths[$idx], 7, CampusParkPDF::safe($col), 1, 0, $aligns[$idx], true);
}
$pdf->Ln(7);

// Table Rows
if (empty($rows)) {
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(182, 12, CampusParkPDF::safe('No parking reservations found for this period.'), 1, 1, 'C');
} else {
    $pdf->SetFont('Arial', '', 8);
    foreach ($rows as $i => $row) {
        // Automatic page break handling will call Header()
        $fill = ($i % 2 === 1);
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetTextColor(15, 23, 42);

        $bid      = '#CP-' . str_pad((string)$row['id'], 4, '0', STR_PAD_LEFT);
        $slot     = $row['slot_code'];
        $zone     = $row['zone'];
        $dateStr  = date('Y-m-d', strtotime($row['booking_date']));
        $timeStr  = date('g:i A', strtotime($row['start_time'])) . '-' . date('g:i A', strtotime($row['end_time']));
        $durStr   = $row['duration_hours'] . ' hr';
        $costStr  = number_format($row['points_cost'], 0) . ' pts';
        $statusStr = ucfirst(str_replace('_', ' ', $row['status']));

        $pdf->Cell($widths[0], 6.5, $bid, 1, 0, $aligns[0], $fill);
        $pdf->Cell($widths[1], 6.5, $slot, 1, 0, $aligns[1], $fill);
        $pdf->Cell($widths[2], 6.5, CampusParkPDF::safe($zone), 1, 0, $aligns[2], $fill);
        $pdf->Cell($widths[3], 6.5, $dateStr, 1, 0, $aligns[3], $fill);
        $pdf->Cell($widths[4], 6.5, $timeStr, 1, 0, $aligns[4], $fill);
        $pdf->Cell($widths[5], 6.5, $durStr, 1, 0, $aligns[5], $fill);
        $pdf->Cell($widths[6], 6.5, $costStr, 1, 0, $aligns[6], $fill);

        // Highlight status cell
        if ($row['status'] === 'checked_in') {
            $pdf->SetTextColor(16, 185, 129);
        } elseif ($row['status'] === 'cancelled') {
            $pdf->SetTextColor(220, 38, 38);
        } elseif ($row['status'] === 'completed') {
            $pdf->SetTextColor(8, 145, 178);
        } else {
            $pdf->SetTextColor(100, 116, 139);
        }
        $pdf->Cell($widths[7], 6.5, CampusParkPDF::safe($statusStr), 1, 1, $aligns[7], $fill);
    }

    // Totals row
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(241, 245, 249);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell($widths[0] + $widths[1] + $widths[2] + $widths[3] + $widths[4], 7, 'Total Aggregate: ', 1, 0, 'R', true);
    $pdf->Cell($widths[5], 7, $totalHours . ' hrs', 1, 0, 'C', true);
    $pdf->SetTextColor(8, 145, 178);
    $pdf->Cell($widths[6], 7, number_format($totalCost, 0) . ' pts', 1, 0, 'R', true);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell($widths[7], 7, '', 1, 1, 'C', true);
}

$filename = 'CampusPark_Report_' . $fileSuffix . '.pdf';
$dest = $inline ? 'I' : 'D';
$pdf->Output($dest, $filename);
exit;
