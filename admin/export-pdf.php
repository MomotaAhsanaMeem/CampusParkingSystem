<?php
// admin/export-pdf.php — Admin Audit & Compliance PDF Export Generator
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/CampusParkPDF.php';

require_admin();

$admin_user = current_user();
$type       = trim($_GET['type'] ?? 'bookings');
$inline     = isset($_GET['inline']) && $_GET['inline'] === '1';

// ═══════════════════════════════════════════════════════════════════════════
// CASE 1: EXECUTIVE SYSTEM AUDIT REPORT
// ═══════════════════════════════════════════════════════════════════════════
if ($type === 'audit' || $type === 'executive') {
    // 1. Core KPIs
    $total_slots     = (int)$pdo->query("SELECT COUNT(*) FROM parking_slots WHERE is_active = 1")->fetchColumn();
    $total_users     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $total_bookings  = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $active_sessions = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'checked_in'")->fetchColumn();
    
    // Penalties & overstays
    $active_overstays = (int)$pdo->query(
        "SELECT COUNT(*) FROM bookings 
          WHERE status = 'checked_in' 
            AND (booking_date < CURDATE() OR (booking_date = CURDATE() AND end_time IS NOT NULL AND end_time < CURTIME()))"
    )->fetchColumn();
    $late_checkins    = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE is_late_checkin = 1")->fetchColumn();
    $penalties_sum    = (float)$pdo->query("SELECT COALESCE(SUM(penalty_points_deducted), 0) FROM bookings WHERE penalty_points_deducted > 0")->fetchColumn();
    $total_pts_spent  = (float)$pdo->query("SELECT COALESCE(SUM(points_cost), 0) FROM bookings WHERE status != 'cancelled'")->fetchColumn();

    // 2. Zone Breakdown
    $zones = ['North Campus', 'South Campus', 'Central Campus'];
    $zone_rows = [];
    foreach ($zones as $z) {
        $slots_in_z = (int)$pdo->prepare("SELECT COUNT(*) FROM parking_slots WHERE zone = ? AND is_active = 1");
        $slots_in_z = (int)$pdo->query("SELECT COUNT(*) FROM parking_slots WHERE zone = " . $pdo->quote($z) . " AND is_active = 1")->fetchColumn();
        
        $active_in_z = (int)$pdo->query(
            "SELECT COUNT(*) FROM bookings b 
               JOIN parking_slots s ON s.id = b.slot_id 
              WHERE s.zone = " . $pdo->quote($z) . " 
                AND b.status IN ('booked', 'checked_in') 
                AND b.booking_date = CURDATE()"
        )->fetchColumn();

        $rate = $slots_in_z > 0 ? round(($active_in_z / $slots_in_z) * 100) : 0;
        $zone_rows[] = [
            'zone'        => $z,
            'capacity'    => $slots_in_z,
            'active_bays' => $active_in_z,
            'utilization' => $rate . '%'
        ];
    }

    // 3. Locked accounts
    $locked_stmt = $pdo->query("SELECT full_name, email, late_departure_count, booking_locked_until FROM users WHERE booking_locked_until IS NOT NULL AND booking_locked_until > NOW() ORDER BY booking_locked_until ASC");
    $locked_accounts = $locked_stmt->fetchAll();

    // 4. Recent Complaints
    $comp_stmt = $pdo->query(
        "SELECT c.id, c.created_at, c.penalty_deducted, u1.full_name AS complainant, u2.full_name AS overstayer, s.slot_code, s.zone
           FROM complaints c
           JOIN bookings b1 ON b1.id = c.blocked_booking_id
           JOIN parking_slots s ON s.id = b1.slot_id
           JOIN users u1 ON u1.id = c.complainant_id
           JOIN bookings b2 ON b2.id = c.occupying_booking_id
           JOIN users u2 ON u2.id = b2.user_id
          ORDER BY c.created_at DESC LIMIT 5"
    );
    $complaints = $comp_stmt->fetchAll();

    $pdf = new CampusParkPDF('P', 'Executive System Audit & Compliance Report', 'Platform Health & Policy Compliance', $admin_user['full_name']);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Top Summary Cards
    $cardY = 28;
    $cardW = 43;
    $cardH = 20;
    $gap   = 3.3;

    $pdf->KpiCard(14 + (0 * ($cardW + $gap)), $cardY, $cardW, $cardH, 'Active Capacity', $total_slots . ' Bays', 'Active parking slots', [8, 145, 178]);
    $pdf->KpiCard(14 + (1 * ($cardW + $gap)), $cardY, $cardW, $cardH, 'Registered Users', (string)$total_users, 'Student/Staff accounts', [100, 116, 139]);
    $pdf->KpiCard(14 + (2 * ($cardW + $gap)), $cardY, $cardW, $cardH, 'Total Bookings', (string)$total_bookings, 'All-time reservations', [8, 145, 178]);
    $pdf->KpiCard(14 + (3 * ($cardW + $gap)), $cardY, $cardW, $cardH, 'Active Overstays', (string)$active_overstays, 'Urgent policy alerts', [220, 38, 38]);

    $pdf->SetY($cardY + $cardH + 8);

    // Section 1: Campus Zones Capacity & Live Utilization
    $pdf->SectionTitle('1. Campus Zones Capacity & Daily Load', 'Live Sensor & Reservation Grid');
    
    $zCols = ['Campus Zone', 'Total Bays', 'Reserved / Occupied Today', 'Live Utilization Rate'];
    $zWidths = [60, 35, 47, 40];
    
    $pdf->SetFillColor(8, 145, 178);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 8.5);
    foreach ($zCols as $idx => $zc) {
        $pdf->Cell($zWidths[$idx], 7, CampusParkPDF::safe($zc), 1, 0, $idx === 0 ? 'L' : 'C', true);
    }
    $pdf->Ln(7);

    $pdf->SetFont('Arial', '', 8.5);
    foreach ($zone_rows as $i => $zr) {
        $fill = ($i % 2 === 1);
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell($zWidths[0], 7, CampusParkPDF::safe($zr['zone']), 1, 0, 'L', $fill);
        $pdf->Cell($zWidths[1], 7, $zr['capacity'] . ' bays', 1, 0, 'C', $fill);
        $pdf->Cell($zWidths[2], 7, $zr['active_bays'] . ' bays', 1, 0, 'C', $fill);
        
        $uRate = (int)$zr['utilization'];
        if ($uRate >= 80) {
            $pdf->SetTextColor(220, 38, 38);
        } elseif ($uRate >= 50) {
            $pdf->SetTextColor(217, 119, 6);
        } else {
            $pdf->SetTextColor(16, 185, 129);
        }
        $pdf->Cell($zWidths[3], 7, $zr['utilization'], 1, 1, 'C', $fill);
    }

    $pdf->Ln(6);

    // Section 2: Compliance, Overstay & Penalty Analytics
    $pdf->SectionTitle('2. Compliance & Enforced Penalties Summary');

    $pdf->SetFillColor(248, 250, 252);
    $pdf->SetDrawColor(226, 232, 240);
    $compY = $pdf->GetY();
    $pdf->Rect(14, $compY, 182, 22, 'DF');

    $pdf->SetXY(18, $compY + 3);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(85, 5, 'TOTAL OVERSTAY PENALTIES DEDUCTED:', 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(220, 38, 38);
    $pdf->Cell(40, 5, number_format($penalties_sum, 2) . ' pts', 0, 1, 'L');

    $pdf->SetX(18);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(85, 5, 'LATE CHECK-IN INCIDENTS RECORDED:', 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(217, 119, 6);
    $pdf->Cell(40, 5, (string)$late_checkins . ' occurrences', 0, 1, 'L');

    $pdf->SetX(18);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(85, 5, 'TOTAL RESERVATION REVENUE (POINTS):', 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(8, 145, 178);
    $pdf->Cell(40, 5, number_format($total_pts_spent, 2) . ' pts', 0, 1, 'L');

    $pdf->Ln(8);

    // Section 3: Currently Restricted / Frozen Accounts
    $pdf->SectionTitle('3. Temporarily Frozen User Accounts', count($locked_accounts) . ' active suspension(s)');

    if (empty($locked_accounts)) {
        $pdf->SetFont('Arial', 'I', 8.5);
        $pdf->SetTextColor(16, 185, 129);
        $pdf->Cell(182, 8, CampusParkPDF::safe('No user accounts are currently locked. Good campus compliance!'), 1, 1, 'C');
    } else {
        $lCols = ['Name', 'Email Address', 'Late Strikes', 'Lock Expiration'];
        $lWidths = [45, 60, 32, 45];
        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetFont('Arial', 'B', 8);
        foreach ($lCols as $idx => $lc) {
            $pdf->Cell($lWidths[$idx], 6.5, CampusParkPDF::safe($lc), 1, 0, 'L', true);
        }
        $pdf->Ln(6.5);

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(15, 23, 42);
        foreach ($locked_accounts as $i => $la) {
            $fill = ($i % 2 === 1);
            $pdf->SetFillColor(254, 242, 242);
            $pdf->Cell($lWidths[0], 6.5, CampusParkPDF::safe($la['full_name']), 1, 0, 'L', $fill);
            $pdf->Cell($lWidths[1], 6.5, CampusParkPDF::safe($la['email']), 1, 0, 'L', $fill);
            $pdf->Cell($lWidths[2], 6.5, $la['late_departure_count'] . ' strikes', 1, 0, 'C', $fill);
            $pdf->Cell($lWidths[3], 6.5, date('M j, Y g:i A', strtotime($la['booking_locked_until'])), 1, 1, 'L', $fill);
        }
    }

    $pdf->Ln(6);

    // Section 4: Recent Overstay Complaints
    $pdf->SectionTitle('4. Recent Obstruction Complaints & Resolution Audit', count($complaints) . ' logged');
    if (!empty($complaints)) {
        $cCols = ['ID', 'Date/Time', 'Complainant', 'Overstayer', 'Bay / Zone', 'Penalty Awarded'];
        $cWidths = [14, 30, 42, 42, 28, 26];

        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetFont('Arial', 'B', 7.5);
        foreach ($cCols as $idx => $cc) {
            $pdf->Cell($cWidths[$idx], 6.5, CampusParkPDF::safe($cc), 1, 0, 'L', true);
        }
        $pdf->Ln(6.5);

        $pdf->SetFont('Arial', '', 7.5);
        $pdf->SetTextColor(15, 23, 42);
        foreach ($complaints as $i => $cp) {
            $fill = ($i % 2 === 1);
            $pdf->SetFillColor(248, 250, 252);
            $pdf->Cell($cWidths[0], 6.5, '#' . $cp['id'], 1, 0, 'C', $fill);
            $pdf->Cell($cWidths[1], 6.5, date('Y-m-d g:ia', strtotime($cp['created_at'])), 1, 0, 'L', $fill);
            $pdf->Cell($cWidths[2], 6.5, CampusParkPDF::safe($cp['complainant']), 1, 0, 'L', $fill);
            $pdf->Cell($cWidths[3], 6.5, CampusParkPDF::safe($cp['overstayer']), 1, 0, 'L', $fill);
            $pdf->Cell($cWidths[4], 6.5, CampusParkPDF::safe($cp['slot_code'] . ' (' . $cp['zone'] . ')'), 1, 0, 'L', $fill);
            $pdf->Cell($cWidths[5], 6.5, '+' . number_format($cp['penalty_deducted'], 1) . ' pts', 1, 1, 'R', $fill);
        }
    }

    $filename = 'CampusPark_Executive_Audit_' . date('Y-m-d') . '.pdf';
    $dest = $inline ? 'I' : 'D';
    $pdf->Output($dest, $filename);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// CASE 2: ALL BOOKINGS AUDIT REPORT (WITH ACTIVE FILTER CRITERIA)
// ═══════════════════════════════════════════════════════════════════════════
$search        = trim($_GET['search']   ?? '');
$status_filter = trim($_GET['status']   ?? '');
$zone_filter   = trim($_GET['zone']     ?? '');
$date_from     = trim($_GET['date_from'] ?? '');
$date_to       = trim($_GET['date_to']   ?? '');

$valid_statuses = ['booked', 'checked_in', 'completed', 'cancelled'];

$where  = ['1=1'];
$params = [];
$filterLabels = [];

if ($search !== '') {
    $where[]  = '(u.full_name LIKE ? OR u.email LIKE ? OR s.slot_code LIKE ? OR b.id = ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = is_numeric($search) ? (int)$search : -1;
    $filterLabels[] = 'Search: "' . $search . '"';
}
if (in_array($status_filter, $valid_statuses, true)) {
    $where[]  = 'b.status = ?';
    $params[] = $status_filter;
    $filterLabels[] = 'Status: ' . ucfirst(str_replace('_', ' ', $status_filter));
}
if ($zone_filter !== '') {
    $where[]  = 's.zone = ?';
    $params[] = $zone_filter;
    $filterLabels[] = 'Zone: ' . $zone_filter;
}
if ($date_from !== '') {
    $where[]  = 'b.booking_date >= ?';
    $params[] = $date_from;
    $filterLabels[] = 'From: ' . $date_from;
}
if ($date_to !== '') {
    $where[]  = 'b.booking_date <= ?';
    $params[] = $date_to;
    $filterLabels[] = 'To: ' . $date_to;
}

$where_sql = implode(' AND ', $where);
$filterStr = !empty($filterLabels) ? implode(' | ', $filterLabels) : 'All Records (Unfiltered)';

// Fetch all matching records (capped at 500 for single audit PDF performance)
$stmt = $pdo->prepare(
    "SELECT b.id, b.booking_date, b.duration_hours, b.points_cost, b.status,
            b.start_time, b.end_time, b.check_in_time, b.check_out_time,
            b.is_late_checkin, b.penalty_points_deducted, b.created_at,
            u.full_name, u.email,
            s.slot_code, s.zone
       FROM bookings b
       JOIN users u ON u.id = b.user_id
       JOIN parking_slots s ON s.id = b.slot_id
      WHERE {$where_sql}
      ORDER BY b.created_at DESC
      LIMIT 500"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Aggregates
$total_count  = count($rows);
$total_points = 0.0;
$total_pen    = 0.0;
$completed_ct = 0;
$checkedin_ct = 0;

foreach ($rows as $r) {
    $total_points += (float)$r['points_cost'];
    $total_pen    += (float)($r['penalty_points_deducted'] ?? 0);
    if ($r['status'] === 'completed')  $completed_ct++;
    if ($r['status'] === 'checked_in') $checkedin_ct++;
}

// Orientation: Landscape for detailed data grid
$pdf = new CampusParkPDF('L', 'Bookings Master Audit Report', $filterStr, $admin_user['full_name']);
$pdf->AliasNbPages();
$pdf->AddPage();

// 4 KPI Cards (Landscape width: 297mm, margins: 14mm -> content: 269mm)
$cardY = 28;
$cardW = 64;
$cardH = 18;
$gap   = 4.3;

$pdf->KpiCard(14 + (0 * ($cardW + $gap)), $cardY, $cardW, $cardH, 'Matched Records', (string)$total_count, 'Filtered entries', [8, 145, 178]);
$pdf->KpiCard(14 + (1 * ($cardW + $gap)), $cardY, $cardW, $cardH, 'Total Points Billed', number_format($total_points, 1) . ' pts', 'Base parking fees', [100, 116, 139]);
$pdf->KpiCard(14 + (2 * ($cardW + $gap)), $cardY, $cardW, $cardH, 'Penalties Deducted', number_format($total_pen, 1) . ' pts', 'Assessed fines', [220, 38, 38]);
$pdf->KpiCard(14 + (3 * ($cardW + $gap)), $cardY, $cardW, $cardH, 'Completed / Active', $completed_ct . ' / ' . $checkedin_ct, 'Turnover ratio', [16, 185, 129]);

$pdf->SetY($cardY + $cardH + 7);

// Active Filter Parameters Box
$pdf->SetFillColor(241, 245, 249);
$pdf->SetDrawColor(226, 232, 240);
$pdf->Rect(14, $pdf->GetY(), 269, 7.5, 'DF');

$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetTextColor(71, 85, 105);
$pdf->SetX(18);
$pdf->Cell(25, 7.5, 'AUDIT SCOPE: ', 0, 0, 'L');
$pdf->SetFont('Arial', '', 7.5);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(170, 7.5, CampusParkPDF::safe($filterStr), 0, 0, 'L');
$pdf->SetFont('Arial', 'I', 7.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(0, 7.5, 'Exported by ' . CampusParkPDF::safe($admin_user['full_name']) . ' on ' . date('M j, Y g:i A'), 0, 1, 'R');

$pdf->Ln(3);

// Table Columns (Total width: 269mm)
$cols   = ['ID', 'User / Account', 'Bay', 'Zone', 'Date', 'Window', 'Dur', 'Cost', 'Check-In', 'Check-Out', 'Penalty', 'Status'];
$widths = [14,   46,               12,    28,     22,     30,       12,    18,     28,         28,          15,        16];
$aligns = ['C',  'L',              'C',   'L',    'C',    'C',      'C',   'R',    'C',        'C',         'R',       'C'];

$pdf->SetFillColor(8, 145, 178);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 7.5);
foreach ($cols as $idx => $col) {
    $pdf->Cell($widths[$idx], 6.5, CampusParkPDF::safe($col), 1, 0, $aligns[$idx], true);
}
$pdf->Ln(6.5);

if (empty($rows)) {
    $pdf->SetFont('Arial', 'I', 8.5);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(269, 12, CampusParkPDF::safe('No bookings matched the selected filter criteria.'), 1, 1, 'C');
} else {
    $pdf->SetFont('Arial', '', 7);
    foreach ($rows as $i => $row) {
        $fill = ($i % 2 === 1);
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetTextColor(15, 23, 42);

        $bid      = '#' . $row['id'];
        $userTxt  = $row['full_name'];
        $slot     = $row['slot_code'];
        $zone     = $row['zone'];
        $dateStr  = date('Y-m-d', strtotime($row['booking_date']));
        $timeStr  = date('g:ia', strtotime($row['start_time'])) . '-' . date('g:ia', strtotime($row['end_time']));
        $durStr   = $row['duration_hours'] . 'h';
        $costStr  = number_format($row['points_cost'], 1);
        $checkin  = !empty($row['check_in_time'])  ? date('m/d H:i', strtotime($row['check_in_time']))  : '-';
        $checkout = !empty($row['check_out_time']) ? date('m/d H:i', strtotime($row['check_out_time'])) : '-';
        $penVal   = (float)($row['penalty_points_deducted'] ?? 0);
        $penStr   = $penVal > 0 ? ('+' . number_format($penVal, 0)) : '-';
        $statusStr = ucfirst(str_replace('_', ' ', $row['status']));

        $pdf->Cell($widths[0], 5.8, $bid, 1, 0, $aligns[0], $fill);
        $pdf->Cell($widths[1], 5.8, CampusParkPDF::safe($userTxt), 1, 0, $aligns[1], $fill);
        $pdf->Cell($widths[2], 5.8, $slot, 1, 0, $aligns[2], $fill);
        $pdf->Cell($widths[3], 5.8, CampusParkPDF::safe($zone), 1, 0, $aligns[3], $fill);
        $pdf->Cell($widths[4], 5.8, $dateStr, 1, 0, $aligns[4], $fill);
        $pdf->Cell($widths[5], 5.8, $timeStr, 1, 0, $aligns[5], $fill);
        $pdf->Cell($widths[6], 5.8, $durStr, 1, 0, $aligns[6], $fill);
        $pdf->Cell($widths[7], 5.8, $costStr, 1, 0, $aligns[7], $fill);
        $pdf->Cell($widths[8], 5.8, $checkin, 1, 0, $aligns[8], $fill);
        $pdf->Cell($widths[9], 5.8, $checkout, 1, 0, $aligns[9], $fill);

        if ($penVal > 0) {
            $pdf->SetTextColor(220, 38, 38);
            $pdf->SetFont('Arial', 'B', 7);
        }
        $pdf->Cell($widths[10], 5.8, $penStr, 1, 0, $aligns[10], $fill);
        $pdf->SetFont('Arial', '', 7);

        // Status color
        if ($row['status'] === 'checked_in') {
            $pdf->SetTextColor(16, 185, 129);
        } elseif ($row['status'] === 'cancelled') {
            $pdf->SetTextColor(220, 38, 38);
        } elseif ($row['status'] === 'completed') {
            $pdf->SetTextColor(8, 145, 178);
        } else {
            $pdf->SetTextColor(100, 116, 139);
        }
        $pdf->Cell($widths[11], 5.8, CampusParkPDF::safe($statusStr), 1, 1, $aligns[11], $fill);
    }

    // Totals row
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetFillColor(241, 245, 249);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell($widths[0] + $widths[1] + $widths[2] + $widths[3] + $widths[4] + $widths[5] + $widths[6], 6.5, 'Report Totals: ', 1, 0, 'R', true);
    $pdf->SetTextColor(8, 145, 178);
    $pdf->Cell($widths[7], 6.5, number_format($total_points, 1), 1, 0, 'R', true);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell($widths[8] + $widths[9], 6.5, '', 1, 0, 'C', true);
    $pdf->SetTextColor(220, 38, 38);
    $pdf->Cell($widths[10], 6.5, '+' . number_format($total_pen, 1), 1, 0, 'R', true);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell($widths[11], 6.5, '', 1, 1, 'C', true);
}

$filename = 'CampusPark_Bookings_Audit_' . date('Y-m-d') . '.pdf';
$dest = $inline ? 'I' : 'D';
$pdf->Output($dest, $filename);
exit;
