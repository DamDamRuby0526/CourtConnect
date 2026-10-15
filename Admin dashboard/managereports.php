<?php
require_once __DIR__ . "/../includes/backend-api/config.php";
require_once __DIR__ . "/../includes/admin-php/facility_owner_page_guard.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
$roleId = (int) ($_SESSION["role_id"] ?? 0);
if (!$userId || !in_array($roleId, [2, 3, 4], true)) {
    header("Location: ../signin.php");
    exit();
}

$reportType = isset($_GET["report_type"]) && in_array($_GET["report_type"], ["daily", "weekly", "monthly", "custom"], true)
    ? $_GET["report_type"]
    : "monthly";
$dateBasis = isset($_GET["date_basis"]) && in_array($_GET["date_basis"], ["reservation", "payment"], true)
    ? $_GET["date_basis"]
    : "reservation";
$selectedDate = isset($_GET["date"]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET["date"]) ? $_GET["date"] : "";
$weekStartDate = isset($_GET["week_start"]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET["week_start"]) ? $_GET["week_start"] : "";
$monthDate = isset($_GET["month"]) && preg_match('/^\d{4}-\d{2}$/', $_GET["month"]) ? $_GET["month"] : "";
$customStart = isset($_GET["custom_start"]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET["custom_start"]) ? $_GET["custom_start"] : "";
$customEnd = isset($_GET["custom_end"]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET["custom_end"]) ? $_GET["custom_end"] : "";
$facilityFilter = filter_var($_GET["facility_id"] ?? null, FILTER_VALIDATE_INT);
$facilityFilter = $facilityFilter && $facilityFilter > 0 ? $facilityFilter : null;

$facilityName = "All facilities";
$facilityOptions = [];
$facilityId = null;
$selectedFacilityId = null;

if (in_array($roleId, [2, 3], true)) {
    $adminDashboardLayout = true;
    $adminNavPage = "reports";

    $accessStmt = $conn->prepare(
        "SELECT a.facility_id, f.facility_name, f.facility_img
         FROM admins a
         INNER JOIN users u ON u.user_id = a.user_id
         INNER JOIN facilities f ON f.facility_id = a.facility_id
         WHERE a.user_id = ? AND a.status = 'Active'
             AND u.role_id = ? AND u.is_verified = 1
         LIMIT 1"
    );
    $accessStmt->bind_param("ii", $userId, $roleId);
    $accessStmt->execute();
    $adminAccess = $accessStmt->get_result()->fetch_assoc();
    $accessStmt->close();

    if (!$adminAccess) {
        header("Location: ../signin.php");
        exit();
    }

    $facilityId = (int) $adminAccess["facility_id"];
    $selectedFacilityId = $facilityId;
    $facilityName = $adminAccess["facility_name"];
    $facilityLogo = $adminAccess["facility_img"];
} else {
    $courtconnectDashboardLayout = true;
    $courtconnectNavPage = "reports";

    $facilityStmt = $conn->prepare("SELECT facility_id, facility_name FROM facilities ORDER BY facility_name ASC");
    $facilityStmt->execute();
    $facilityResult = $facilityStmt->get_result();
    while ($facility = $facilityResult->fetch_assoc()) {
        $facilityOptions[] = $facility;
        if ($facilityFilter && (int) $facility["facility_id"] === (int) $facilityFilter) {
            $selectedFacilityId = (int) $facility["facility_id"];
            $facilityName = $facility["facility_name"];
        }
    }
    $facilityStmt->close();

    if ($facilityFilter && !$selectedFacilityId) {
        $facilityFilter = null;
    }
    $selectedFacilityId = $facilityFilter ?: null;
    if ($selectedFacilityId) {
        foreach ($facilityOptions as $facility) {
            if ((int) $facility["facility_id"] === $selectedFacilityId) {
                $facilityName = $facility["facility_name"];
                break;
            }
        }
    }
}

$tz = new DateTimeZone("Asia/Manila");
$now = new DateTimeImmutable("now", $tz);
$reportStart = null;
$reportEnd = null;
$displayPeriod = "";

function normalizeDateValue($value)
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Manila'));
    if (!$date || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $date;
}

switch ($reportType) {
    case 'daily':
        $selectedDateObj = normalizeDateValue($selectedDate ?: $now->format('Y-m-d'));
        $reportStart = $selectedDateObj ?: $now;
        $reportEnd = $selectedDateObj ?: $now;
        $displayPeriod = $reportStart->format('F j, Y');
        break;
    case 'weekly':
        $weekBase = normalizeDateValue($weekStartDate ?: $now->format('Y-m-d'));
        if ($weekBase) {
            $reportStart = $weekBase->modify('monday this week');
            $reportEnd = $reportStart->modify('+6 days');
        } else {
            $reportStart = $now->modify('monday this week');
            $reportEnd = $reportStart->modify('+6 days');
        }
        $displayPeriod = $reportStart->format('F j, Y') . ' - ' . $reportEnd->format('F j, Y');
        break;
    case 'monthly':
        $monthBase = $monthDate ? DateTimeImmutable::createFromFormat('!Y-m', $monthDate, $tz) : $now;
        if (!$monthBase) {
            $monthBase = $now;
        }
        $reportStart = $monthBase->modify('first day of this month')->setTime(0, 0, 0);
        $reportEnd = $monthBase->modify('last day of this month')->setTime(23, 59, 59);
        $displayPeriod = $reportStart->format('F Y');
        break;
    case 'custom':
        $customStartObj = normalizeDateValue($customStart ?: $now->modify('-30 days')->format('Y-m-d'));
        $customEndObj = normalizeDateValue($customEnd ?: $now->format('Y-m-d'));
        if ($customStartObj && $customEndObj && $customStartObj <= $customEndObj) {
            $reportStart = $customStartObj->setTime(0, 0, 0);
            $reportEnd = $customEndObj->setTime(23, 59, 59);
        } elseif ($customStartObj && $customEndObj) {
            $reportStart = $customEndObj->setTime(0, 0, 0);
            $reportEnd = $customStartObj->setTime(23, 59, 59);
        } else {
            $reportStart = $now->modify('-30 days')->setTime(0, 0, 0);
            $reportEnd = $now->setTime(23, 59, 59);
        }
        $displayPeriod = $reportStart->format('F j, Y') . ' - ' . $reportEnd->format('F j, Y');
        break;
    default:
        $reportStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $reportEnd = $reportStart->modify('last day of this month')->setTime(23, 59, 59);
        $displayPeriod = $reportStart->format('F Y');
        break;
}

$reportStartText = $reportStart->format('Y-m-d');
$reportEndText = $reportEnd->format('Y-m-d');
$dateFilterLabel = $dateBasis === 'payment' ? 'Payment date' : 'Booking date';

$bookingSql = "
    SELECT
        b.booking_id,
        COALESCE(NULLIF(b.customer_name, ''), NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ''), 'Customer') AS customer_name,
        COALESCE(NULLIF(b.customer_phone, ''), u.phone_number) AS customer_phone,
        f.facility_name,
        cd.court_no,
        f.sport_type,
        cs.court_date,
        DATE_FORMAT(cs.court_date, '%Y-%m-%d') AS reservation_date,
        DATE_FORMAT(COALESCE(p.paid_at, p.created_at), '%Y-%m-%d') AS payment_date,
        TIME_FORMAT(cs.court_time, '%h:%i %p') AS court_time,
        b.total_amount,
        b.status AS booking_status,
        b.booking_type,
        COALESCE(p.payment_status, b.payment_status) AS payment_status,
        CASE
            WHEN LOWER(COALESCE(p.payment_status, b.payment_status)) = 'paid'
                AND LOWER(b.status) <> 'cancelled' THEN b.total_amount
            ELSE 0
        END AS verified_amount
    FROM bookings b
    LEFT JOIN users u ON u.user_id = b.user_id
    INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
    INNER JOIN court_details cd ON cd.court_id = b.court_id
    INNER JOIN facilities f ON f.facility_id = cd.facility_id
    LEFT JOIN (
        SELECT booking_id, MAX(payments_id) AS payments_id
        FROM payments
        GROUP BY booking_id
    ) latest_payment ON latest_payment.booking_id = b.booking_id
    LEFT JOIN payments p ON p.payments_id = latest_payment.payments_id
    WHERE 1 = 1";

$params = [];
$paramTypes = "";

if ($dateBasis === 'payment') {
    $bookingSql .= " AND DATE(COALESCE(p.paid_at, p.created_at)) BETWEEN ? AND ?";
    $params[] = $reportStartText;
    $params[] = $reportEndText;
    $paramTypes .= "ss";
} else {
    $bookingSql .= " AND cs.court_date BETWEEN ? AND ?";
    $params[] = $reportStartText;
    $params[] = $reportEndText;
    $paramTypes .= "ss";
}

if ($selectedFacilityId) {
    $bookingSql .= " AND cd.facility_id = ?";
    $params[] = $selectedFacilityId;
    $paramTypes .= "i";
}

$bookingSql .= " ORDER BY cs.court_date DESC, cs.court_time DESC";

$stmt = $conn->prepare($bookingSql);
$references = [];
foreach ($params as $index => $value) {
    $references[] = &$params[$index];
}
call_user_func_array([$stmt, "bind_param"], array_merge([$paramTypes], $references));
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalBookings = count($rows);
$cancelledBookings = 0;
$paidBookings = 0;
$pendingPayments = 0;
$rejectedPayments = 0;
$verifiedRevenue = 0.0;

foreach ($rows as $row) {
    $bookingStatus = strtolower((string) ($row["booking_status"] ?? ""));
    $paymentStatus = strtolower((string) ($row["payment_status"] ?? ""));
    $amount = (float) ($row["verified_amount"] ?? 0);
    $verifiedRevenue += $amount;

    if ($bookingStatus === "cancelled") {
        $cancelledBookings++;
    }
    if ($paymentStatus === "paid") {
        $paidBookings++;
    } elseif ($paymentStatus === "pending") {
        $pendingPayments++;
    } elseif ($paymentStatus === "rejected") {
        $rejectedPayments++;
    }
}

function formatCurrency($amount)
{
    return '₱' . number_format((float) $amount, 2, '.', ',');
}

function escapeHtml($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatReportDate($value)
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value, new DateTimeZone('Asia/Manila'));
    return $date ? $date->format('M j, Y') : (string) $value;
}

function isPdfExportRequest()
{
    return isset($_GET['export']) && $_GET['export'] === 'pdf';
}

if (isPdfExportRequest()) {
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        http_response_code(500);
        echo '<!doctype html><html><head><meta charset="UTF-8"><title>PDF export unavailable</title></head><body style="font-family:Arial,sans-serif;padding:32px;line-height:1.6;">';
        echo '<h1>PDF export library not installed</h1>';
        echo '<p>This project does not yet have a PDF library available. Install Dompdf with the commands below, then refresh the report.</p>';
        echo '<pre style="background:#f4f4f4;padding:16px;border-radius:8px;">cd C:\\xampp\\htdocs\\CourtConnect
C:\\xampp\\php\\php.exe -r "copy(\'https://getcomposer.org/installer\', \'composer-setup.php\');"
C:\\xampp\\php\\php.exe composer-setup.php
C:\\xampp\\php\\php.exe -d detect_unicode=0 C:\\xampp\\htdocs\\CourtConnect\\composer.phar require dompdf/dompdf</pre>';
        echo '</body></html>';
        exit();
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    $pdfHtml = '<!doctype html><html><head><meta charset="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><style>';
    $pdfHtml .= 'body{font-family:DejaVu Sans, Arial, sans-serif;color:#1f2937;margin:20px;} h1{font-size:22px;margin:0 0 6px;} .header{border-bottom:2px solid #0f3154;padding-bottom:10px;margin-bottom:14px;} .meta{font-size:11px;color:#475569;line-height:1.5;} .card-grid{width:100%;border-collapse:separate;border-spacing:8px 0;margin:12px -8px 10px;} .box{width:33.33%;border:1px solid #dbe2ec;border-radius:6px;padding:10px;background:#f8fafc;} .label{color:#64748b;font-size:10px;text-transform:uppercase;letter-spacing:0.04em;} .value{font-size:20px;font-weight:bold;margin-top:4px;} table{width:100%;border-collapse:collapse;font-size:10.5px;margin-top:10px;} thead{display:table-header-group;} tr{page-break-inside:avoid;} th,td{border-bottom:1px solid #dbe2ec;padding:6px 7px;text-align:left;vertical-align:top;} th{background:#e9f0ed;color:#0f3154;} .empty{padding:16px;border:1px dashed #94a3b8;border-radius:6px;color:#475569;} .status{font-weight:bold;}';
    $pdfHtml .= '</style></head><body>';
    $pdfHtml .= '<div class="header"><h1>CourtConnect Bataan — Sales Report</h1><div class="meta">Facility: ' . escapeHtml($facilityName) . '<br>Period: ' . escapeHtml($displayPeriod) . '<br>Generated: ' . escapeHtml($now->format('F j, Y g:i A')) . '</div></div>';
    $pdfHtml .= '<table class="card-grid"><tr><td class="box"><div class="label">Verified Revenue</div><div class="value">' . formatCurrency($verifiedRevenue) . '</div></td><td class="box"><div class="label">Paid Bookings</div><div class="value">' . (int) $paidBookings . '</div></td><td class="box"><div class="label">Total Bookings</div><div class="value">' . (int) $totalBookings . '</div></td></tr></table>';
    $pdfHtml .= '<div class="meta">Pending: ' . (int) $pendingPayments . ' &nbsp;|&nbsp; Rejected: ' . (int) $rejectedPayments . ' &nbsp;|&nbsp; Cancelled: ' . (int) $cancelledBookings . '</div>';
    if ($rows) {
        $pdfHtml .= '<table><thead><tr><th>Customer Name</th>' . ($roleId === 4 ? '<th>Facility</th>' : '') . '<th>Booking Date</th><th>Court</th><th>Sport</th><th>Amount</th><th>Payment Status</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $pdfHtml .= '<tr><td>' . escapeHtml((string) ($row["customer_name"] ?? "Customer")) . '</td>' . ($roleId === 4 ? '<td>' . escapeHtml((string) ($row["facility_name"] ?? "")) . '</td>' : '') . '<td>' . escapeHtml(formatReportDate($row["reservation_date"] ?? "")) . '</td><td>Court ' . escapeHtml((string) ($row["court_no"] ?? "")) . '</td><td>' . escapeHtml((string) ($row["sport_type"] ?? "")) . '</td><td>' . formatCurrency((float) ($row["verified_amount"] ?? 0)) . '</td><td class="status">' . escapeHtml((string) ($row["payment_status"] ?? "Pending")) . '</td></tr>';
        }
        $pdfHtml .= '</tbody></table>';
    } else {
        $pdfHtml .= '<div class="empty">No verified sales were recorded for the selected period.</div>';
    }
    $pdfHtml .= '</body></html>';

    $options = new Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($pdfHtml, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $filename = 'courtconnect-sales-report-' . $reportStartText . '-to-' . $reportEndText . '.pdf';
    $dompdf->stream($filename, ["Attachment" => true, "compress" => true]);
    exit();
}

$reportQueryString = http_build_query([
    'report_type' => $reportType,
    'date_basis' => $dateBasis,
    'date' => $selectedDate,
    'week_start' => $weekStartDate,
    'month' => $monthDate,
    'custom_start' => $customStart,
    'custom_end' => $customEnd,
    'facility_id' => $selectedFacilityId,
]);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <title>Sales Reports | CourtConnect</title>
    <style>
        body.admin-ui { background: #f6f8f4; }
        .admin-content.sales-report-content { padding-top: 25px; padding-bottom: 16px; }
        .sales-report-head { align-items: center; margin-bottom: 14px; }
        .sales-report-head .admin-subtitle { margin-top: 3px; font-size: 10px; }
        .report-actions { display: flex; flex: 0 0 auto; align-items: center; gap: 8px; }
        .report-actions a { min-height: 30px; display: inline-flex; align-items: center; justify-content: center; padding: 0 12px; border: 1px solid transparent; border-radius: 5px; font-size: 10px; font-weight: 600; text-decoration: none; white-space: nowrap; }
        .report-actions .primary { background: #0d3157; color: #fff; }
        .report-actions .secondary { border-color: #d8e2dc; background: #fff; color: #0d3157; }
        .reports-layout { display: grid; width: 100%; min-width: 0; grid-template-columns: minmax(0, 1fr); gap: 10px; }
        .report-toolbar { width: 100%; padding: 10px 12px; border: 1px solid #d8e2dc; border-radius: 7px; background: #fff; }
        .report-form { display: flex; width: 100%; min-width: 0; flex-wrap: wrap; align-items: end; gap: 9px; margin: 0; padding: 0; border: 0; border-radius: 0; background: transparent; box-shadow: none; }
        .report-form label { display: grid; min-width: 0; flex: 0 1 145px; gap: 4px; margin: 0; color: #586d78; font-size: 11px; font-weight: 600; }
        .report-form input, .report-form select { width: 100%; min-width: 0; height: 34px; box-sizing: border-box; margin: 0; padding: 4px 8px; border: 1px solid #d8e2dc; border-radius: 4px; background: #fff; color: #0d3157; font: 500 12px/1.25 "Poppins", "Trebuchet MS", sans-serif !important; }
        .report-form button { width: auto; height: 34px; flex: 0 0 auto; margin: 0; padding: 0 13px; border: 0; border-radius: 4px; background: #78b936; color: #0d3157; font: 700 12px/1.25 "Poppins", "Trebuchet MS", sans-serif; cursor: pointer; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .summary-card { min-height: 66px; padding: 10px 13px; border: 1px solid #d8e2dc; border-radius: 7px; background: #fff; }
        .summary-card .label { color: #586d78; font-size: 9px; font-weight: 600; letter-spacing: .02em; }
        .summary-card .value { margin-top: 4px; color: #0d3157; font-size: 20px; font-weight: 700; line-height: 1.15; }
        .summary-card.revenue { border-color: #0d3157; background: #0d3157; }
        .summary-card.revenue .label, .summary-card.revenue .value { color: #fff; }
        .secondary-stats { margin: -2px 2px 0; color: #65777e; font-size: 9px; }
        .transactions-card, .empty-state { min-width: 0; padding: 12px; border: 1px solid #d8e2dc; border-radius: 7px; background: #fff; }
        .transactions-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
        .transactions-heading h2 { margin: 0; color: #0d3157; font-family: "Poppins", sans-serif; font-size: 14px; font-weight: 600; }
        .transactions-heading span { color: #687b81; font-size: 9px; }
        .table-shell { width: 100%; min-width: 0; overflow-x: auto; }
        .table-shell table { width: 100%; min-width: 0; border: 0; border-collapse: collapse; box-shadow: none; outline: 0; }
        .table-shell table.all-facilities-table { min-width: 680px; }
        .table-shell th, .table-shell td { padding: 11px 12px; border: 0; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: middle; font-size: 10px; }
        .table-shell th { background: #f7f9f8; color: #405b65; font-size: 9px; font-weight: 700; white-space: nowrap; }
        .table-shell tbody tr:last-child td { border-bottom: 0; }
        .table-shell td.amount { color: #0d3157; font-weight: 600; white-space: nowrap; }
        .status-pill { display: inline-flex; align-items: center; padding: 3px 7px; border-radius: 999px; font-size: 8px; font-weight: 700; }
        .status-paid { background: #eaf5e3; color: #397a2b; }
        .status-pending { background: #fff5dc; color: #946312; }
        .status-rejected { background: #fff0ee; color: #b93832; }
        .status-cancelled { background: #f2f5f1; color: #586d78; }
        .report-empty { padding: 16px 4px 5px; color: #65777e; font-size: 11px; }
        @media (max-width: 900px) {
            .report-form label { flex: 1 1 145px; }
            .summary-card .value { font-size: 18px; }
        }
        @media (max-width: 650px) {
            .admin-content.sales-report-content { padding-top: 20px; }
            .sales-report-head { align-items: flex-start; flex-direction: column; gap: 10px; margin-bottom: 13px; }
            .report-actions { width: 100%; }
            .report-actions a { flex: 1; }
            .report-form label { flex: 1 1 140px; }
            .report-form button { flex: 1 1 100%; }
            .summary-grid { grid-template-columns: 1fr; gap: 7px; }
            .summary-card { min-height: 0; }
            .summary-card .value { font-size: 18px; }
            .transactions-heading { align-items: flex-start; }
        }
    </style>
</head>
<body class="admin-ui">
    <div class="admin-layout">
        <?php include '../includes/navigation/admin_header.php'; ?>
        <main class="admin-content sales-report-content">
            <header class="admin-page-head sales-report-head">
                <div>
                    <h1>Sales Report</h1>
                    <p class="admin-subtitle"><?= escapeHtml($facilityName) ?> · <?= escapeHtml($displayPeriod) ?> · <?= escapeHtml($dateFilterLabel) ?></p>
                </div>
                <div class="report-actions">
                    <a class="primary" href="?<?= $reportQueryString ?>&export=pdf" target="_blank" rel="noopener">Export PDF</a>
                    <a class="secondary" href="managereports.php">Reset</a>
                </div>
            </header>

            <div class="reports-layout">
                <section class="report-toolbar" aria-label="Report filters">
                    <form class="report-form" method="get">
                            <label>
                                Report type
                                <select name="report_type" onchange="this.form.submit()">
                                    <option value="daily" <?= $reportType === 'daily' ? 'selected' : '' ?>>Daily</option>
                                    <option value="weekly" <?= $reportType === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                    <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                    <option value="custom" <?= $reportType === 'custom' ? 'selected' : '' ?>>Custom date range</option>
                                </select>
                            </label>

                            <label>
                                Date basis
                                <select name="date_basis">
                                    <option value="reservation" <?= $dateBasis === 'reservation' ? 'selected' : '' ?>>Booking date</option>
                                    <option value="payment" <?= $dateBasis === 'payment' ? 'selected' : '' ?>>Payment date</option>
                                </select>
                            </label>

                            <?php if ($roleId === 4): ?>
                                <label>
                                    Facility
                                    <select name="facility_id">
                                        <option value="">All facilities</option>
                                        <?php foreach ($facilityOptions as $facility): ?>
                                            <option value="<?= (int) $facility['facility_id'] ?>" <?= (int) $facility['facility_id'] === (int) $selectedFacilityId ? 'selected' : '' ?>><?= escapeHtml($facility['facility_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endif; ?>

                            <?php if ($reportType === 'daily'): ?>
                                <label>
                                    Date
                                    <input type="date" name="date" value="<?= escapeHtml($reportStartText) ?>" required>
                                </label>
                            <?php elseif ($reportType === 'weekly'): ?>
                                <label>
                                    Week start
                                    <input type="date" name="week_start" value="<?= escapeHtml($reportStartText) ?>" required>
                                </label>
                            <?php elseif ($reportType === 'monthly'): ?>
                                <label>
                                    Month
                                    <input type="month" name="month" value="<?= escapeHtml($reportStart->format('Y-m')) ?>" required>
                                </label>
                            <?php elseif ($reportType === 'custom'): ?>
                                <label>
                                    Start date
                                    <input type="date" name="custom_start" value="<?= escapeHtml($reportStartText) ?>" required>
                                </label>
                                <label>
                                    End date
                                    <input type="date" name="custom_end" value="<?= escapeHtml($reportEndText) ?>" required>
                                </label>
                            <?php endif; ?>

                            <button type="submit">Apply</button>
                    </form>
                </section>

                <section class="summary-grid">
                    <article class="summary-card revenue">
                        <div class="label">Verified Revenue</div>
                        <div class="value"><?= formatCurrency($verifiedRevenue) ?></div>
                    </article>
                    <article class="summary-card">
                        <div class="label">Paid Bookings</div>
                        <div class="value"><?= (int) $paidBookings ?></div>
                    </article>
                    <article class="summary-card">
                        <div class="label">Total Bookings</div>
                        <div class="value"><?= (int) $totalBookings ?></div>
                    </article>
                </section>
                <p class="secondary-stats">Pending <?= (int) $pendingPayments ?> · Rejected <?= (int) $rejectedPayments ?> · Cancelled <?= (int) $cancelledBookings ?></p>

                <?php if ($rows): ?>
                    <section class="transactions-card" aria-labelledby="transactionsHeading">
                        <div class="transactions-heading">
                            <h2 id="transactionsHeading">Sales Transactions</h2>
                            <span><?= number_format($totalBookings) ?> <?= $totalBookings === 1 ? 'record' : 'records' ?></span>
                        </div>
                        <div class="table-shell">
                            <table class="<?= $roleId === 4 ? 'all-facilities-table' : '' ?>">
                            <thead>
                                <tr>
                                    <th>Customer Name</th>
                                    <?php if ($roleId === 4): ?>
                                        <th>Facility</th>
                                    <?php endif; ?>
                                    <th>Booking Date</th>
                                    <th>Court</th>
                                    <th>Sport</th>
                                    <th>Amount</th>
                                    <th>Payment Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <?php
                                        $paymentStatus = strtolower((string) ($row["payment_status"] ?? "pending"));
                                        $statusClass = 'status-pending';
                                        if ($paymentStatus === 'paid') { $statusClass = 'status-paid'; }
                                        elseif ($paymentStatus === 'rejected') { $statusClass = 'status-rejected'; }
                                        elseif ($paymentStatus === 'cancelled') { $statusClass = 'status-cancelled'; }
                                    ?>
                                    <tr>
                                        <td><?= escapeHtml((string) ($row["customer_name"] ?? "Customer")) ?></td>
                                        <?php if ($roleId === 4): ?>
                                            <td><?= escapeHtml((string) ($row["facility_name"] ?? "Facility")) ?></td>
                                        <?php endif; ?>
                                        <td><?= escapeHtml(formatReportDate($row["reservation_date"] ?? "")) ?></td>
                                        <td>Court <?= escapeHtml((string) ($row["court_no"] ?? "-")) ?></td>
                                        <td><?= escapeHtml((string) ($row["sport_type"] ?? "-")) ?></td>
                                        <td class="amount"><?= formatCurrency((float) ($row["verified_amount"] ?? 0)) ?></td>
                                        <td><span class="status-pill <?= $statusClass ?>"><?= escapeHtml(ucfirst((string) ($row["payment_status"] ?? "Pending"))) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            </table>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="transactions-card" aria-labelledby="transactionsHeading">
                        <div class="transactions-heading">
                            <h2 id="transactionsHeading">Sales Transactions</h2>
                            <span>0 records</span>
                        </div>
                        <p class="report-empty">No sales transactions for this period. Adjust the date range or facility filter to try again.</p>
                    </section>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
