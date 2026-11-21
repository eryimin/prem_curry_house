
<?php
// ============================================
// PREM CURRY HOUSE - ADMIN DASHBOARD
// ============================================

session_start();

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true)
) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rows(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function validDate(string $value, DateTimeZone $tz): ?DateTimeImmutable {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);
    return $d && $d->format('Y-m-d') === $value ? $d : null;
}

function formatDate($value, bool $withTime = false): string {
    if (empty($value)) return '';
    $timestamp = strtotime((string) $value);
    return $timestamp === false
        ? ''
        : date($withTime ? 'd-m-Y H:i:s' : 'd-m-Y', $timestamp);
}

// ============================================
// DATE FILTER
// ============================================

$tz = new DateTimeZone('Asia/Kuala_Lumpur');
$now = new DateTimeImmutable('now', $tz);

$period = $_GET['period'] ?? 'year';
if (!in_array($period, ['month', 'year', 'custom', 'all'], true)) {
    $period = 'year';
}

$year = filter_var($_GET['year'] ?? $now->format('Y'), FILTER_VALIDATE_INT);
if (!$year || $year < 2000 || $year > 2100) {
    $year = (int) $now->format('Y');
}

$month = filter_var($_GET['month'] ?? $now->format('n'), FILTER_VALIDATE_INT);
if (!$month || $month < 1 || $month > 12) {
    $month = (int) $now->format('n');
}

$fromInput = (string) ($_GET['from'] ?? $now->format('Y-m-01'));
$toInput = (string) ($_GET['to'] ?? $now->format('Y-m-d'));

$start = null;
$end = null;

if ($period === 'month') {
    $start = new DateTimeImmutable(
        sprintf('%04d-%02d-01', $year, $month),
        $tz
    );
    $end = $start->modify('+1 month');

} elseif ($period === 'year') {
    $start = new DateTimeImmutable(sprintf('%04d-01-01', $year), $tz);
    $end = $start->modify('+1 year');

} elseif ($period === 'custom') {
    $start = validDate($fromInput, $tz);
    $last = validDate($toInput, $tz);

    if (
        !$start || !$last ||
        $start > $last ||
        $start->diff($last)->days > 3660
    ) {
        $period = 'year';
        $start = new DateTimeImmutable(sprintf('%04d-01-01', $year), $tz);
        $end = $start->modify('+1 year');
    } else {
        $end = $last->modify('+1 day');
    }
}

$where = $start
    ? ' WHERE o.created_at >= ? AND o.created_at < ?'
    : '';

$params = $start
    ? [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]
    : [];

$enquiryWhere = $start
    ? ' WHERE e.created_at >= ? AND e.created_at < ?'
    : '';

$periodLabel = match ($period) {
    'month' => $start->format('F Y'),
    'year' => (string) $year,
    'custom' => $start->format('d M Y') . ' – ' .
        $end->modify('-1 day')->format('d M Y'),
    default => 'All time'
};

// ============================================
// DATABASE QUERIES
// ============================================

try {

    $summary = rows($pdo, "
        SELECT COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN o.status = 'Pending'
            THEN 1 ELSE 0 END), 0) AS pending,
        COALESCE(SUM(CASE WHEN o.status = 'Confirmed'
            THEN 1 ELSE 0 END), 0) AS confirmed,
        COALESCE(SUM(CASE WHEN o.status = 'Completed'
            THEN 1 ELSE 0 END), 0) AS completed,
        COALESCE(SUM(CASE WHEN o.status = 'Rejected'
            THEN 1 ELSE 0 END), 0) AS rejected
        FROM tiffin_orders o
        " . $where, $params
    )[0];

    $enquirySummary = rows($pdo, "
        SELECT COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN e.status = 'New'
            THEN 1 ELSE 0 END), 0) AS new_count,
        COALESCE(SUM(CASE WHEN e.status = 'In Progress'
            THEN 1 ELSE 0 END), 0) AS in_progress,
        COALESCE(SUM(CASE WHEN e.status = 'Resolved'
            THEN 1 ELSE 0 END), 0) AS resolved
        FROM tiffin_enquiries e
        " . $enquiryWhere, $params
    )[0];

    $valueWhere = $where
        ? $where . " AND o.status IN ('Confirmed','Completed')"
        : " WHERE o.status IN ('Confirmed','Completed')";

    $estimatedValue = (float) rows($pdo, "
        SELECT COALESCE(SUM(p.price), 0) AS amount
        FROM tiffin_orders o
        JOIN tiffin_plans p ON p.plan_id = o.plan_id
        " . $valueWhere, $params
    )[0]['amount'];

    $menuStats = rows($pdo, "
        SELECT COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN is_available = 1
            THEN 1 ELSE 0 END), 0) AS available,
        COALESCE(SUM(CASE WHEN is_available = 0
            THEN 1 ELSE 0 END), 0) AS unavailable
        FROM menu_items
    ")[0];

    // Recent tiffin orders
    $recentOrders = rows($pdo, "
        SELECT
            o.order_id,
            o.customer_name,
            o.phone,
            o.delivery_address,
            o.start_date,
            o.special_requests,
            o.status,
            o.created_at,
            p.plan_name,
            p.price
        FROM tiffin_orders o
        JOIN tiffin_plans p ON p.plan_id = o.plan_id
        " . $where . "
        ORDER BY o.created_at DESC, o.order_id DESC
        LIMIT 6
    ", $params);

    // Recent enquiries - get all existing fields
    $recentEnquiries = rows($pdo, "
        SELECT e.*
        FROM tiffin_enquiries e
        " . $enquiryWhere . "
        ORDER BY e.created_at DESC, e.enquiry_id DESC
        LIMIT 6
    ", $params);

} catch (Throwable $ex) {
    error_log('Dashboard error: ' . $ex->getMessage());
    http_response_code(500);
    exit('Unable to load dashboard. Check the PHP error log.');
}

$kpis = [
    ['Total Tiffin Orders', (int)$summary['total'],
        'bag-check', 'Orders placed in this period'],
    ['Pending Orders', (int)$summary['pending'],
        'hourglass-split', 'Awaiting admin review'],
    ['Completed Orders', (int)$summary['completed'],
        'check-circle', 'Marked completed'],
    ['Est. Order Value', 'RM ' . number_format($estimatedValue, 2),
        'cash-stack', 'Confirmed + completed orders'],
    ['Total Enquiries', (int)$enquirySummary['total'],
        'chat-square-text', 'Enquiries in this period'],
    ['New Enquiries', (int)$enquirySummary['new_count'],
        'envelope-exclamation', 'Awaiting response'],
    ['Available Menu Items', (int)$menuStats['available'],
        'journal-check', 'Currently available'],
    ['Unavailable Items', (int)$menuStats['unavailable'],
        'slash-circle', 'Currently unavailable']
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Admin Dashboard | Prem Curry House</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
:root {
    --forest: #294e35;
    --green: #527b35;
    --cream: #f8f6ef;
    --stroke: #e5e9df;
}

body.analytics-page {
    background: var(--cream);
    color: #253b2a;
    font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
    font-size: 15px;
}

/* Navigation */

.analytics-nav {
    background: var(--forest) !important;
    min-height: 52px;
    box-shadow: 0 4px 16px #203d2918;
}

.analytics-nav .navbar-brand {
    color: #fff !important;
    font-size: 18px;
    font-weight: 800;
}

.analytics-nav .nav-link {
    color: #ebf2e9 !important;
    font-size: 16px;
    font-weight: 700;
}

.analytics-nav .nav-link:hover {
    color: #f6d89b !important;
}

/* Main layout */

.dashboard-layout {
    display: flex;
    min-height: calc(100vh - 58px);
}

.dashboard-sidebar {
    width: 205px;
    flex: 0 0 205px;
    background: #fff;
    border-right: 1px solid var(--stroke);
    padding: 20px 11px;
    position: sticky;
    top: 0;
    height: calc(100vh - 58px);
    overflow-y: auto;
}

.sidebar-heading {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .13em;
    color: #8b9a8b;
    text-transform: uppercase;
    margin: 7px 10px 10px;
}

.sidebar-link {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 10px 11px;
    margin: 3px 0;
    border-radius: 11px;
    font-size: 13px;
    font-weight: 600;
    color: #435846;
    text-decoration: none;
}

.sidebar-link i {
    font-size: 15px;
    width: 19px;
    text-align: center;
    color: var(--green);
}

.sidebar-link:hover {
    background: #eff5eb;
    color: #24472e;
}

.sidebar-link.selected {
    background: #e7f1e4;
    color: #285b35;
    font-weight: 800;
}

.dashboard-main {
    flex: 1;
    min-width: 0;
}

.analytics-container {
    width: 100%;
    max-width: none;
    padding: 22px 26px 45px;
}

.eyebrow {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .13em;
    text-transform: uppercase;
    color: var(--green);
}

.page-title {
    font-size: 27px;
    font-weight: 800;
    color: #203e2a;
}

.subtle {
    font-size: 12px;
    color: #728173;
}

.panel {
    background: #fff;
    border: 1px solid var(--stroke);
    border-radius: 16px;
    box-shadow: 0 5px 26px #26472b0b;
}

/* Filters */

.filter-panel {
    padding: 15px 18px;
}

.filter-panel label {
    font-size: 12px;
    font-weight: 700;
    color: #58715b;
}

.form-select,
.form-control {
    font-size: 13px;
    border-color: #dbe4d7;
}

.btn-forest {
    background: var(--forest);
    border: 1px solid var(--forest);
    color: white;
}

.btn-forest:hover {
    background: #3b6845;
    color: white;
}

/* KPI cards */

.kpi {
    min-height: 128px;
    padding: 16px 17px;
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.kpi .k-label {
    font-size: 12px;
    color: #738272;
    font-weight: 600;
    margin-bottom: 8px;
}

.kpi .k-value {
    font-size: 25px;
    color: #24432b;
    font-weight: 800;
    line-height: 1.1;
}

.kpi .k-hint {
    font-size: 11px;
    color: #89968a;
    margin-top: 7px;
}

.kpi .k-icon {
    width: 43px;
    height: 43px;
    background: #eff4e9;
    color: var(--green);
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 22px;
    flex: none;
}

.section-heading {
    font-size: 17px;
    font-weight: 800;
    color: #24432b;
}

/* Recent Activity */

.data-panel {
    padding: 17px 18px;
    height: 100%;
}

.data-panel h3 {
    font-size: 17px;
    font-weight: 800;
    color: #28472c;
}

.action-link {
    color: #426d3a;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}

.action-link:hover {
    text-decoration: underline;
}

/* 70% orders / 30% enquiries */

.recent-orders-column {
    width: 70%;
}

.recent-enquiries-column {
    width: 30%;
}

/* Tables */

.table {
    font-size: 12px;
    margin-bottom: 0;
}

.table thead th {
    background: #f6f8f4;
    color: #617062;
    border: 0;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
    padding: 11px 9px;
}

.table td {
    color: #39483c;
    vertical-align: middle;
    padding: 10px 9px;
}

.recent-orders-table {
    min-width: 760px;
}

.recent-orders-table td,
.recent-orders-table th {
    white-space: nowrap;
}

.recent-orders-table .plan-cell {
    white-space: normal;
    min-width: 155px;
}

.recent-enquiries-column .table {
    font-size: 11px;
}

.recent-enquiries-column .table td,
.recent-enquiries-column .table th {
    padding: 9px 6px;
}

.recent-enquiries-column .table-responsive {
    overflow-x: auto;
}

/* Status colours */

.chip {
    display: inline-block;
    font-size: 11px;
    font-weight: 750;
    padding: 5px 9px;
    border-radius: 7px;
    white-space: nowrap;
}

.chip-Pending,
.chip-New {
    color: #a66e14;
    background: #fff4df;
}

.chip-Confirmed,
.chip-In-Progress {
    color: #356ca1;
    background: #e6f2ff;
}

.chip-Completed,
.chip-Resolved {
    color: #27804b;
    background: #e4f4e9;
}

.chip-Rejected {
    color: #b44c43;
    background: #fde8e6;
}

/* Small View buttons */

.btn-view-order {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    background: #f5f9f3;
    border: 1px solid #d9e7d5;
    color: #426d3a;
    border-radius: 6px;
    padding: 4px 7px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.2;
    white-space: nowrap;
    cursor: pointer;
}

.btn-view-order i {
    font-size: 12px;
}

.btn-view-order:hover {
    background: #426d3a;
    border-color: #426d3a;
    color: #fff;
}

/* Popups */

.order-modal .modal-content {
    border: 0;
    border-radius: 16px;
    overflow: hidden;
}

.order-modal .modal-header {
    background: #f7faf5;
    padding: 18px 23px;
    border-bottom: 1px solid #e5e9df;
}

.order-modal .modal-title {
    font-size: 20px;
    font-weight: 800;
    color: #24432b;
}

.order-modal .modal-body {
    padding: 22px 24px;
}

.order-detail-label {
    font-size: 12px;
    color: #758476;
    font-weight: 600;
    margin-bottom: 5px;
}

.order-detail-value {
    font-size: 14px;
    font-weight: 600;
    color: #304a35;
    overflow-wrap: anywhere;
}

.order-detail-box {
    background: #f8faf7;
    border: 1px solid #e9eee5;
    border-radius: 10px;
    padding: 13px 15px;
}

.order-modal .modal-footer {
    border-top: 1px solid #edf1e9;
    padding: 13px 24px;
}

.note {
    font-size: 12px;
    color: #798777;
}

/* Responsive */

@media (max-width: 1199.98px) {
    .recent-orders-column,
    .recent-enquiries-column {
        width: 100%;
    }
}

@media (max-width: 991.98px) {
    .dashboard-layout {
        display: block;
    }

    .dashboard-sidebar {
        width: 100%;
        height: auto;
        position: static;
        display: flex;
        flex-wrap: wrap;
        border-right: 0;
        border-bottom: 1px solid var(--stroke);
    }

    .sidebar-heading {
        width: 100%;
    }

    .analytics-container {
        padding: 20px 15px;
    }
}

@media (max-width: 575px) {
    .page-title {
        font-size: 24px;
    }

    .kpi {
        padding: 14px;
    }

    .kpi .k-value {
        font-size: 21px;
    }

    .order-modal .modal-body {
        padding: 16px;
    }
}
</style>
</head>

<body class="analytics-page">

<!-- =========================================
     TOP NAVIGATION
     ========================================= -->

<nav class="navbar navbar-expand-lg analytics-nav navbar-dark">
    <div class="container-fluid px-3 px-lg-4">

        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-flower1 me-2"></i>
            PREM CURRY HOUSE
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#adminNav"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNav">

            <div class="navbar-nav ms-lg-4 gap-lg-2">
                <a class="nav-link active" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="menu_items.php">Menu</a>
                <a class="nav-link" href="tiffin_orders.php">Tiffin Orders</a>
                <a class="nav-link" href="tiffin_enquiries.php">Enquiries</a>
            </div>

            <div class="ms-auto d-flex align-items-center gap-3 mt-2 mt-lg-0">

                <span class="text-white-50 small">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= e($_SESSION['full_name'] ?? 'Staff') ?>
                    (<?= e(ucfirst($_SESSION['role'] ?? 'staff')) ?>)
                </span>

                <a class="btn btn-outline-light btn-sm" href="../logout.php">
                    <i class="bi bi-box-arrow-right me-1"></i>
                    Logout
                </a>

            </div>
        </div>
    </div>
</nav>

<div class="dashboard-layout">

    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <div class="dashboard-main">

        <main class="analytics-container">

            <!-- DASHBOARD HEADING -->

            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">

                <div>
                    <div class="eyebrow mb-1">Operations overview</div>

                    <h1 class="page-title mb-1">
                        Admin Dashboard
                    </h1>

                    <div class="subtle">
                        Overview of tiffin orders, menu availability and enquiries.
                    </div>
                </div>

                <div class="subtle">
                    <i class="bi bi-calendar3 me-1"></i>
                    Showing: <strong><?= e($periodLabel) ?></strong>
                </div>

            </div>

            <!-- DATE FILTER -->

            <form method="get" class="panel filter-panel mb-4">

                <div class="row g-3 align-items-end">

                    <div class="col-6 col-md-3 col-lg-2">
                        <label class="form-label" for="period">View by</label>

                        <select class="form-select" name="period" id="period">
                            <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>
                                Month
                            </option>
                            <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>
                                Whole year
                            </option>
                            <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>
                                Custom dates
                            </option>
                            <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>
                                All time
                            </option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2" data-filter="month year">
                        <label class="form-label" for="year">Year</label>

                        <select class="form-select" name="year" id="year">
                            <?php for ($y = (int)$now->format('Y') + 1; $y >= 2020; $y--): ?>
                                <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>>
                                    <?= $y ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-2" data-filter="month">
                        <label class="form-label" for="month">Month</label>

                        <select class="form-select" name="month" id="month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>>
                                    <?= e(DateTimeImmutable::createFromFormat(
                                        '!m',
                                        str_pad((string)$m, 2, '0', STR_PAD_LEFT)
                                    )->format('F')) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-2" data-filter="custom">
                        <label class="form-label" for="from">From</label>

                        <input
                            class="form-control"
                            type="date"
                            name="from"
                            id="from"
                            value="<?= e($fromInput) ?>"
                        >
                    </div>

                    <div class="col-6 col-md-2" data-filter="custom">
                        <label class="form-label" for="to">To</label>

                        <input
                            class="form-control"
                            type="date"
                            name="to"
                            id="to"
                            value="<?= e($toInput) ?>"
                        >
                    </div>

                    <div class="col-auto">
                        <button class="btn btn-forest" type="submit">
                            <i class="bi bi-funnel me-1"></i>
                            Apply Filter
                        </button>
                    </div>

                    <div class="col-auto">
                        <a class="btn btn-outline-secondary" href="dashboard.php">
                            Reset
                        </a>
                    </div>

                </div>
            </form>

            <!-- DASHBOARD STATISTICS -->

            <div class="row g-3 mb-4">

                <?php foreach ($kpis as $k): ?>

                    <div class="col-6 col-lg-3">

                        <div class="panel kpi">

                            <div>
                                <div class="k-label"><?= e($k[0]) ?></div>
                                <div class="k-value"><?= e($k[1]) ?></div>
                                <div class="k-hint"><?= e($k[3]) ?></div>
                            </div>

                            <div class="k-icon">
                                <i class="bi bi-<?= e($k[2]) ?>"></i>
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <!-- RECENT ACTIVITY HEADING -->

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

                <h2 class="section-heading mb-0">
                    Recent activity
                </h2>

                <div class="d-flex gap-2 flex-wrap">

                    <a class="btn btn-forest btn-sm" href="tiffin_analytics.php">
                        <i class="bi bi-graph-up-arrow me-1"></i>
                        Tiffin Analytics
                    </a>

                    <a class="btn btn-outline-success btn-sm" href="menu_analytics.php">
                        <i class="bi bi-bar-chart-line me-1"></i>
                        Menu Analytics
                    </a>

                </div>

            </div>

            <!-- RECENT ACTIVITY TABLES -->

            <div class="row g-3 mb-4">

                <!-- ===================================
                     RECENT TIFFIN ORDERS - 70%
                     =================================== -->

                <div class="col-12 recent-orders-column">

                    <div class="panel data-panel">

                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">

                            <h3 class="mb-0">Recent Tiffin Orders</h3>

                            <a class="action-link" href="tiffin_orders.php">
                                Manage orders
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>

                        <div class="table-responsive">

                            <table class="table table-hover recent-orders-table">

                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Plan</th>
                                        <th>Start Date</th>
                                        <th>Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php if (!$recentOrders): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                No orders in this period.
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php foreach ($recentOrders as $r): ?>

                                        <?php
                                        $orderData = [
                                            'order_id' => (int) $r['order_id'],
                                            'customer_name' => $r['customer_name'],
                                            'phone' => $r['phone'],
                                            'delivery_address' => $r['delivery_address'],
                                            'plan_name' => $r['plan_name'],
                                            'start_date' => formatDate($r['start_date']),
                                            'special_requests' => $r['special_requests'],
                                            'status' => $r['status'],
                                            'created_at' => formatDate($r['created_at'], true),
                                            'price' => number_format((float)$r['price'], 2)
                                        ];
                                        ?>

                                        <tr>
                                            <td class="fw-bold">
                                                #<?= (int)$r['order_id'] ?>
                                            </td>

                                            <td><?= e($r['customer_name']) ?></td>

                                            <td class="plan-cell">
                                                <?= e($r['plan_name']) ?>
                                            </td>

                                            <td><?= e(formatDate($r['start_date'])) ?></td>

                                            <td>
                                                <span class="chip chip-<?= e($r['status']) ?>">
                                                    <?= e($r['status']) ?>
                                                </span>
                                            </td>

                                            <td class="text-center">
                                                <button
                                                    type="button"
                                                    class="btn-view-order"
                                                    data-order="<?= e(json_encode(
                                                        $orderData,
                                                        JSON_HEX_TAG |
                                                        JSON_HEX_AMP |
                                                        JSON_HEX_APOS |
                                                        JSON_HEX_QUOT |
                                                        JSON_INVALID_UTF8_SUBSTITUTE
                                                    )) ?>"
                                                >
                                                    <i class="bi bi-eye"></i>
                                                    View
                                                </button>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>

                <!-- ===================================
                     RECENT ENQUIRIES - 30%
                     =================================== -->

                <div class="col-12 recent-enquiries-column">

                    <div class="panel data-panel">

                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">

                            <h3 class="mb-0">Recent Enquiries</h3>

                            <a class="action-link" href="tiffin_enquiries.php">
                                Manage enquiries
                                <i class="bi bi-arrow-right"></i>
                            </a>

                        </div>

                        <div class="table-responsive">

                            <table class="table table-hover">

                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php if (!$recentEnquiries): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                No enquiries in this period.
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php foreach ($recentEnquiries as $r): ?>

                                        <?php
                                        $enquiryData = [
                                            'enquiry_id' => (int)$r['enquiry_id'],
                                            'customer_name' => $r['customer_name'] ?? '',
                                            'email' => $r['email'] ?? $r['customer_email'] ?? '',
                                            'phone' => $r['phone'] ?? $r['customer_phone'] ?? '',
                                            'subject' => $r['subject'] ?? '',
                                            'message' => $r['message']
                                                ?? $r['enquiry_message']
                                                ?? $r['description']
                                                ?? '',
                                            'status' => $r['status'] ?? '',
                                            'created_at' => formatDate($r['created_at'] ?? '', true)
                                        ];
                                        ?>

                                        <tr>
                                            <td><?= e($r['customer_name'] ?? '') ?></td>

                                            <td><?= e($r['subject'] ?? '') ?></td>

                                            <td>
                                                <span class="chip chip-<?= e(str_replace(' ', '-', $r['status'] ?? '')) ?>">
                                                    <?= e($r['status'] ?? '') ?>
                                                </span>
                                            </td>

                                            <td>
                                                <button
                                                    type="button"
                                                    class="btn-view-order btn-view-enquiry"
                                                    data-enquiry="<?= e(json_encode(
                                                        $enquiryData,
                                                        JSON_HEX_TAG |
                                                        JSON_HEX_AMP |
                                                        JSON_HEX_APOS |
                                                        JSON_HEX_QUOT |
                                                        JSON_INVALID_UTF8_SUBSTITUTE
                                                    )) ?>"
                                                >
                                                    <i class="bi bi-eye"></i>
                                                    View
                                                </button>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>
                    </div>
                </div>

            </div>

            <p class="note mt-3 mb-0">
                Estimated order value uses current plan prices for confirmed
                or completed orders; it is not verified payment revenue.
                Menu availability is live and does not use the date filter.
            </p>

        </main>

    </div>
</div>

<!-- =========================================
     TIFFIN ORDER DETAILS POPUP
     ========================================= -->

<div
    class="modal fade order-modal"
    id="orderDetailsModal"
    tabindex="-1"
    aria-labelledby="orderDetailsTitle"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="orderDetailsTitle">
                        Order Details
                    </h5>
                    <div class="subtle mt-1">
                        Prem Curry House · Tiffin Order
                    </div>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>
            </div>

            <div class="modal-body">

                <div class="row g-4">

                    <div class="col-6">
                        <div class="order-detail-label">Order ID</div>
                        <div class="order-detail-value" id="detailOrderId"></div>
                    </div>

                    <div class="col-6">
                        <div class="order-detail-label">Order Status</div>
                        <div id="detailStatus"></div>
                    </div>

                    <div class="col-6">
                        <div class="order-detail-label">Customer Name</div>
                        <div class="order-detail-value" id="detailCustomer"></div>
                    </div>

                    <div class="col-6">
                        <div class="order-detail-label">Phone Number</div>
                        <div class="order-detail-value" id="detailPhone"></div>
                    </div>

                    <div class="col-6">
                        <div class="order-detail-label">Tiffin Plan</div>
                        <div class="order-detail-value" id="detailPlan"></div>
                    </div>

                    <div class="col-6">
                        <div class="order-detail-label">Plan Price</div>
                        <div class="order-detail-value" id="detailPrice"></div>
                    </div>

                    <div class="col-6">
                        <div class="order-detail-label">Start Date</div>
                        <div class="order-detail-value" id="detailStartDate"></div>
                    </div>

                    <div class="col-6">
                        <div class="order-detail-label">Order Placed</div>
                        <div class="order-detail-value" id="detailCreatedAt"></div>
                    </div>

                    <div class="col-12">
                        <div class="order-detail-box">
                            <div class="order-detail-label">
                                <i class="bi bi-geo-alt me-1"></i>
                                Delivery Address
                            </div>
                            <div class="order-detail-value" id="detailAddress"></div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="order-detail-box">
                            <div class="order-detail-label">
                                <i class="bi bi-chat-left-text me-1"></i>
                                Special Requests
                            </div>
                            <div class="order-detail-value" id="detailRequests"></div>
                        </div>
                    </div>

                </div>

            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    data-bs-dismiss="modal"
                >
                    Close
                </button>
            </div>

        </div>
    </div>
</div>

<!-- =========================================
     ENQUIRY DETAILS POPUP
     ========================================= -->

<div
    class="modal fade order-modal"
    id="enquiryDetailsModal"
    tabindex="-1"
    aria-labelledby="enquiryDetailsTitle"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="enquiryDetailsTitle">
                        Enquiry Details
                    </h5>

                    <div class="subtle mt-1">
                        Prem Curry House · Customer Enquiry
                    </div>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>
            </div>

            <div class="modal-body">

                <div class="row g-3">

                    <div class="col-6">
                        <div class="order-detail-label">Enquiry ID</div>
                        <div class="order-detail-value" id="enquiryId"></div>
                    </div>

                    <div class="col-6">
                        <div class="order-detail-label">Status</div>
                        <div id="enquiryStatus"></div>
                    </div>

                    <div class="col-12">
                        <div class="order-detail-label">Customer Name</div>
                        <div class="order-detail-value" id="enquiryCustomer"></div>
                    </div>

                    <div class="col-6">
                        <div class="order-detail-label">Email</div>
                        <div class="order-detail-value" id="enquiryEmail"></div>
                    </div>

                    <div class="col-6">
                        <div class="order-detail-label">Phone Number</div>
                        <div class="order-detail-value" id="enquiryPhone"></div>
                    </div>

                    <div class="col-12">
                        <div class="order-detail-label">Subject</div>
                        <div class="order-detail-value" id="enquirySubject"></div>
                    </div>

                    <div class="col-12">
                        <div class="order-detail-label">Submitted Date</div>
                        <div class="order-detail-value" id="enquiryDate"></div>
                    </div>

                    <div class="col-12">
                        <div class="order-detail-box">
                            <div class="order-detail-label">
                                <i class="bi bi-chat-left-text me-1"></i>
                                Customer Message
                            </div>

                            <div
                                class="order-detail-value"
                                id="enquiryMessage"
                                style="white-space:pre-wrap;"
                            ></div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    data-bs-dismiss="modal"
                >
                    Close
                </button>
            </div>

        </div>
    </div>
</div>

<!-- =========================================
     JAVASCRIPT
     ========================================= -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {

    // DATE FILTER VISIBILITY

    const picker = document.getElementById('period');

    function showFilters() {
        document.querySelectorAll('[data-filter]').forEach(element => {

            const visible = element.dataset.filter
                .split(' ')
                .includes(picker.value);

            element.style.display = visible ? '' : 'none';

            element.querySelectorAll('input,select').forEach(input => {
                input.disabled = !visible;
            });
        });
    }

    picker.addEventListener('change', showFilters);
    showFilters();

    // SHARED POPUP HELPER

    function displayValue(id, value) {
        document.getElementById(id).textContent =
            value === null || value === undefined || value === ''
                ? 'Not provided'
                : String(value);
    }

    function displayStatus(id, value, allowedStatuses) {
        const container = document.getElementById(id);
        container.textContent = '';

        const badge = document.createElement('span');

        const status = allowedStatuses.includes(value)
            ? value
            : 'Unknown';

        badge.className = 'chip chip-' + status.replaceAll(' ', '-');
        badge.textContent = status;

        container.appendChild(badge);
    }

    // TIFFIN ORDER DETAILS POPUP

    const orderModal = new bootstrap.Modal(
        document.getElementById('orderDetailsModal')
    );

    document.querySelectorAll(
        '.btn-view-order:not(.btn-view-enquiry)'
    ).forEach(button => {

        button.addEventListener('click', function () {

            const order = JSON.parse(this.dataset.order);

            displayValue('detailOrderId', '#' + order.order_id);
            displayValue('detailCustomer', order.customer_name);
            displayValue('detailPhone', order.phone);
            displayValue('detailPlan', order.plan_name);
            displayValue('detailPrice', 'RM ' + order.price);
            displayValue('detailStartDate', order.start_date);
            displayValue('detailCreatedAt', order.created_at);
            displayValue('detailAddress', order.delivery_address);
            displayValue('detailRequests', order.special_requests);

            displayStatus(
                'detailStatus',
                order.status,
                ['Pending', 'Confirmed', 'Completed', 'Rejected']
            );

            orderModal.show();
        });
    });

    // ENQUIRY DETAILS POPUP

    const enquiryModal = new bootstrap.Modal(
        document.getElementById('enquiryDetailsModal')
    );

    document.querySelectorAll('.btn-view-enquiry').forEach(button => {

        button.addEventListener('click', function () {

            const enquiry = JSON.parse(this.dataset.enquiry);

            displayValue('enquiryId', '#' + enquiry.enquiry_id);
            displayValue('enquiryCustomer', enquiry.customer_name);
            displayValue('enquiryEmail', enquiry.email);
            displayValue('enquiryPhone', enquiry.phone);
            displayValue('enquirySubject', enquiry.subject);
            displayValue('enquiryDate', enquiry.created_at);
            displayValue('enquiryMessage', enquiry.message);

            displayStatus(
                'enquiryStatus',
                enquiry.status,
                ['New', 'In Progress', 'Resolved']
            );

            enquiryModal.show();
        });
    });

})();
</script>

</body>
</html>
