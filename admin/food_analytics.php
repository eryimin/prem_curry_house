
<?php
// ============================================
// PREM CURRY HOUSE - FOOD ANALYTICS
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true)
) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

function faEsc($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function faMoney($value): string {
    return 'RM ' . number_format((float)$value, 2);
}

// ============================================
// FILTERS
// ============================================

$today = new DateTimeImmutable('today');
$view = $_GET['view'] ?? 'monthly';

if (!in_array($view, ['daily','monthly','yearly','all'], true)) {
    $view = 'monthly';
}

$year = (int)($_GET['year'] ?? $today->format('Y'));
$month = (int)($_GET['month'] ?? $today->format('n'));
$day = (int)($_GET['day'] ?? $today->format('j'));

$year = max(2020, min(2100, $year));
$month = max(1, min(12, $month));

$maxDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$day = max(1, min($maxDay, $day));

$start = null;
$end = null;
$previousStart = null;
$previousEnd = null;

if ($view === 'daily') {
    $start = new DateTimeImmutable(
        sprintf('%04d-%02d-%02d', $year, $month, $day)
    );
    $end = $start->modify('+1 day');
    $previousStart = $start->modify('-1 day');
    $previousEnd = $start;

} elseif ($view === 'monthly') {
    $start = new DateTimeImmutable(
        sprintf('%04d-%02d-01', $year, $month)
    );
    $end = $start->modify('+1 month');
    $previousStart = $start->modify('-1 month');
    $previousEnd = $start;

} elseif ($view === 'yearly') {
    $start = new DateTimeImmutable($year . '-01-01');
    $end = $start->modify('+1 year');
    $previousStart = $start->modify('-1 year');
    $previousEnd = $start;
}

$periodLabel = match ($view) {
    'daily' => $start->format('d F Y'),
    'monthly' => $start->format('F Y'),
    'yearly' => (string)$year,
    default => 'All Time'
};

function faPeriodSql($start, $end): array {
    if (!$start || !$end) {
        return ['', []];
    }

    return [
        ' AND o.created_at >= ? AND o.created_at < ?',
        [
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        ]
    ];
}

[$where, $params] = faPeriodSql($start, $end);
[$previousWhere, $previousParams] = faPeriodSql(
    $previousStart,
    $previousEnd
);

// ============================================
// SUMMARY
// ============================================

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(
            CASE WHEN order_status = 'Pending'
            THEN 1 ELSE 0 END
        ), 0) AS pending_orders,
        COALESCE(SUM(
            CASE WHEN order_status = 'Completed'
            THEN 1 ELSE 0 END
        ), 0) AS completed_orders,
        COALESCE(SUM(
            CASE WHEN order_status = 'Completed'
            THEN total ELSE 0 END
        ), 0) AS completed_value
    FROM pch_food_orders o
    WHERE 1=1 $where
");

$stmt->execute($params);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// ============================================
// ORDER STATUS
// ============================================

$statusColors = [
    'Pending' => '#D2A047',
    'Confirmed' => '#518BAC',
    'Preparing' => '#9674BD',
    'Ready' => '#70A59C',
    'Completed' => '#527B35',
    'Rejected' => '#C75B54',
    'Cancelled' => '#9B9F9A'
];

$statusCounts = array_fill_keys(array_keys($statusColors), 0);

$stmt = $pdo->prepare("
    SELECT order_status, COUNT(*) AS qty
    FROM pch_food_orders o
    WHERE 1=1 $where
    GROUP BY order_status
");

$stmt->execute($params);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $statusCounts[$row['order_status']] = (int)$row['qty'];
}

// ============================================
// POPULAR ITEMS
// ============================================

function faItems(PDO $pdo, string $where, array $params): array {
    $stmt = $pdo->prepare("
        SELECT
            oi.menu_item_id,
            oi.item_name,
            SUM(oi.quantity) AS quantity,
            COALESCE(MAX(c.category_name), '') AS category

        FROM pch_food_order_items oi

        JOIN pch_food_orders o
            ON o.order_id = oi.order_id

        LEFT JOIN menu_items m
            ON m.item_id = oi.menu_item_id

        LEFT JOIN categories c
            ON c.category_id = m.category_id

        WHERE o.order_status NOT IN ('Rejected', 'Cancelled')
        $where

        GROUP BY oi.menu_item_id, oi.item_name
        ORDER BY quantity DESC, oi.item_name ASC
    ");

    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function faBeverage(array $item): bool {
    $category = strtolower((string)$item['category']);

    if (preg_match('/drink|beverage|coffee|tea|juice/', $category)) {
        return true;
    }

    return (bool)preg_match(
        '/\b(teh|kopi|milo|nescafe|bru|limau|sirap|bandung|' .
        'juice|latte|cappuccino|espresso|horlicks|lassi|water|soda)\b/i',
        (string)$item['item_name']
    );
}

$currentItems = faItems($pdo, $where, $params);

$previousItems = $view === 'all'
    ? []
    : faItems($pdo, $previousWhere, $previousParams);

$previousCounts = [];

foreach ($previousItems as $item) {
    $key = $item['menu_item_id'] . '|' . $item['item_name'];
    $previousCounts[$key] = (int)$item['quantity'];
}

$food = [];
$beverages = [];

foreach ($currentItems as $item) {
    $qty = (int)$item['quantity'];
    $key = $item['menu_item_id'] . '|' . $item['item_name'];

    $previous = $view === 'all'
        ? null
        : ($previousCounts[$key] ?? 0);

    $trend = 'none';
    $percentage = null;

    if ($previous !== null) {
        if ($previous === 0) {
            $trend = 'new';
        } else {
            $percentage = round(($qty - $previous) / $previous * 100, 1);

            $trend = $percentage > 0
                ? 'up'
                : ($percentage < 0 ? 'down' : 'same');
        }
    }

    $result = [
        'name' => $item['item_name'],
        'sold' => $qty,
        'trend' => $trend,
        'percent' => $percentage
    ];

    if (faBeverage($item)) {
        $beverages[] = $result;
    } else {
        $food[] = $result;
    }
}

// ============================================
// FOOD ORDER TRENDS
// ============================================

$trendKeys = [];
$trendLabels = [];

if ($view === 'daily') {
    for ($i = 0; $i < 24; $i++) {
        $trendKeys[] = sprintf('%02d', $i);
        $trendLabels[] = sprintf('%02d:00', $i);
    }
    $bucket = "DATE_FORMAT(o.created_at, '%H')";

} elseif ($view === 'monthly') {
    for ($i = 1; $i <= $maxDay; $i++) {
        $trendKeys[] = sprintf('%02d', $i);
        $trendLabels[] = (string)$i;
    }
    $bucket = "DATE_FORMAT(o.created_at, '%d')";

} elseif ($view === 'yearly') {
    for ($i = 1; $i <= 12; $i++) {
        $trendKeys[] = sprintf('%02d', $i);
        $trendLabels[] = date('M', mktime(0, 0, 0, $i, 1));
    }
    $bucket = "DATE_FORMAT(o.created_at, '%m')";

} else {
    $bucket = "DATE_FORMAT(o.created_at, '%Y')";
}

$stmt = $pdo->prepare("
    SELECT $bucket AS period_key, COUNT(*) AS qty
    FROM pch_food_orders o
    WHERE 1=1 $where
    GROUP BY period_key
    ORDER BY period_key
");

$stmt->execute($params);

$trendCounts = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $trendCounts[$row['period_key']] = (int)$row['qty'];
}

if ($view === 'all') {
    $trendKeys = array_keys($trendCounts);
    $trendLabels = $trendKeys;
}

$trends = [];

foreach ($trendKeys as $i => $key) {
    $trends[] = [
        'label' => $trendLabels[$i],
        'count' => $trendCounts[$key] ?? 0
    ];
}

$trendMax = max(1, ...array_column($trends, 'count'));

// ============================================
// RECENT ORDERS
// ============================================

$stmt = $pdo->prepare("
    SELECT order_id, created_at, customer_name, total, order_status
    FROM pch_food_orders o
    WHERE 1=1 $where
    ORDER BY created_at DESC, order_id DESC
    LIMIT 8
");

$stmt->execute($params);
$recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// SIDEBAR
// ============================================

$sidebar = null;

foreach ([
    __DIR__ . '/includes/sidebar.php',
    __DIR__ . '/sidebar.php'
] as $path) {
    if (is_file($path)) {
        $sidebar = $path;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Food Analytics | Prem Curry House</title>

<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
:root {
    --green: #527b35;
    --dark: #213c2c;
    --muted: #6d7967;
    --edge: #e3e8df;
}

body {
    margin: 0;
    background: #f8f7f1;
    color: #233627;
    font-family: Arial, sans-serif;
    font-size: 13px;
}

/* HEADER */

.fa-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    background: #264a35;
    padding: 10px 22px;
}

.fa-brand {
    color: white;
    font-size: 17px;
    font-weight: 800;
    text-decoration: none;
}

.fa-brand:hover {
    color: white;
}

.fa-brand i {
    color: #efc54b;
    margin-right: 8px;
}

.fa-header nav {
    display: flex;
    gap: 20px;
}

.fa-header nav a {
    color: white;
    font-size: 12px;
    text-decoration: none;
}

/* LAYOUT */

.fa-shell {
    display: flex;
    min-height: 100vh;
}

.dashboard-sidebar {
    width: 220px;
    flex: 0 0 220px;
    background: white;
    border-right: 1px solid var(--edge);
    padding: 18px 10px;
}

.sidebar-heading {
    font-size: 10px;
    color: #849680;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin: 12px 8px;
}

.sidebar-link {
    display: flex;
    gap: 10px;
    align-items: center;
    color: #2e4534;
    text-decoration: none;
    font-size: 12px;
    border-radius: 8px;
    padding: 9px 10px;
    margin-bottom: 2px;
}

.sidebar-link i {
    color: var(--green);
}

.sidebar-link.selected,
.sidebar-link:hover {
    background: #e8f0e4;
    color: #315b29;
    text-decoration: none;
}

.pch-food-toggle,
.pch-orders-toggle {
    display: flex;
    align-items: center;
    width: 100%;
    border: 0;
    cursor: pointer;
}

.pch-food-label,
.pch-orders-label {
    flex: 1;
    text-align: left;
}

.pch-food-arrow,
.pch-orders-arrow {
    margin-left: auto;
}

.pch-food-submenu,
.pch-orders-submenu {
    margin-left: 16px;
    padding-left: 8px;
    border-left: 2px solid #dce8d5;
}

.pch-food-submenu[hidden],
.pch-orders-submenu[hidden] {
    display: none !important;
}

.fa-main {
    min-width: 0;
    flex: 1;
    padding: 18px;
}

/* HEADINGS */

.fa-heading {
    margin-bottom: 15px;
}

.fa-heading small {
    font-size: 10px;
    color: var(--green);
    font-weight: 800;
    letter-spacing: 1px;
}

.fa-heading h1 {
    font-size: 24px;
    font-weight: 800;
    margin: 5px 0;
}

.fa-heading p {
    color: var(--muted);
    font-size: 12px;
    margin: 0;
}

/* CARDS */

.fa-card {
    background: #fff;
    border: 1px solid var(--edge);
    border-radius: 13px;
    padding: 14px 16px;
    box-shadow: 0 3px 12px rgba(20,45,25,.025);
}

/* FILTERS */

.fa-filters {
    display: flex;
    align-items: end;
    flex-wrap: nowrap;
    gap: 10px;
}

.fa-filters .field {
    flex: 1;
    min-width: 0;
}

.fa-filters .field[hidden] {
    display: none !important;
}

.fa-filters label {
    display: block;
    color: var(--green);
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 5px;
}

.fa-filters select {
    width: 100%;
    height: 36px;
    border: 1px solid #d9e3d5;
    border-radius: 7px;
    padding: 0 9px;
    background: white;
    font-size: 12px;
}

.fa-filters .apply {
    background: var(--green);
    color: white;
    border: 0;
    border-radius: 7px;
    height: 36px;
    padding: 0 17px;
    font-size: 12px;
    font-weight: 700;
}

.fa-filter-note {
    font-size: 11px;
    color: var(--muted);
    margin-top: 9px;
}

/* SUMMARY */

.fa-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 12px;
    margin: 13px 0;
}

.fa-stat-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: var(--muted);
    font-size: 11px;
}

.fa-stat-head i {
    color: var(--green);
    font-size: 17px;
}

.fa-stat-value {
    color: var(--dark);
    font-size: 24px;
    font-weight: 800;
    margin: 7px 0;
}

.fa-stat small {
    color: #829080;
    font-size: 10px;
}

/* SECTIONS */

.fa-section {
    margin-bottom: 13px;
}

.fa-section h2 {
    font-size: 17px;
    font-weight: 800;
    margin: 0 0 4px;
}

.fa-section-desc {
    color: var(--muted);
    font-size: 11px;
    margin: 0 0 12px;
}

/* COMPACT BAR CHART */

.fa-trend {
    height: 142px;
    display: flex;
    align-items: end;
    gap: 4px;
    overflow-x: hidden;
    border-bottom: 1px solid #e1e9dd;
}

.fa-trend-col {
    flex: 1;
    min-width: 0;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: end;
    align-items: center;
    gap: 3px;
}

.fa-trend-value {
    font-size: 10px;
    color: #45643c;
}

.fa-trend-bar {
    width: 68%;
    max-width: 32px;
    border-radius: 4px 4px 0 0;
    background: #83a36f;
}

.fa-trend-label {
    font-size: 9px;
    height: 18px;
    color: #7c8879;
    white-space: nowrap;
}

/* ALL TIME LINE CHART */

.fa-year-chart {
    display: block;
    width: 100%;
    height: auto;
    max-height: 200px;
}

.fa-grid-line {
    stroke: #e5ece1;
    stroke-width: 1;
}

.fa-year-line {
    fill: none;
    stroke: #527b35;
    stroke-width: 4;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.fa-year-dot {
    fill: #527b35;
    stroke: white;
    stroke-width: 3;
}

.fa-year-number {
    fill: #2c4d32;
    text-anchor: middle;
    font: bold 14px Arial, sans-serif;
}

.fa-year-label {
    fill: #61745e;
    text-anchor: middle;
    font: 13px Arial, sans-serif;
}

/* POPULAR ITEMS */

.fa-pop-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 12px;
}

.fa-tabs {
    display: flex;
    gap: 3px;
    padding: 4px;
    background: #edf2e9;
    border-radius: 30px;
}

.fa-tab {
    border: 0;
    border-radius: 22px;
    padding: 7px 15px;
    background: transparent;
    color: #526a4b;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.fa-tab.active {
    background: var(--green);
    color: white;
}

.fa-pop-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 13px;
    align-items: start;
}

.fa-rank-list {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.fa-rank-row {
    display: flex;
    align-items: center;
    gap: 9px;
    background: #fbfcfa;
    border: 1px solid #e8ede5;
    border-radius: 9px;
    padding: 9px 11px;
}

.fa-rank-icon {
    flex-shrink: 0;
    width: 33px;
    text-align: center;
    font-size: 23px;
}

.fa-rank-num {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e7f0e2;
    color: var(--green);
    font-weight: 800;
    font-size: 13px;
}

.fa-rank-name {
    font-size: 13px;
    font-weight: 700;
}

.fa-rank-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 11px;
    margin-top: 3px;
    color: var(--muted);
    font-size: 11px;
}

.fa-up {
    color: #15804a;
    font-weight: 750;
}

.fa-down {
    color: #bd4444;
    font-weight: 750;
}

.fa-neutral {
    color: #788577;
}

.fa-show {
    display: block;
    width: 100%;
    margin-top: 9px;
    padding: 9px;
    border: 1px solid var(--green);
    border-radius: 8px;
    background: white;
    color: var(--green);
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.fa-show[hidden] {
    display: none !important;
}

/* QUANTITY BARS */

.fa-bars {
    background: #fbfcfa;
    border: 1px solid #e8ede5;
    border-radius: 11px;
    padding: 13px;
}

.fa-bars h3 {
    font-size: 14px;
    font-weight: 800;
    margin: 0 0 12px;
}

.fa-bar-row {
    margin-bottom: 12px;
}

.fa-bar-label {
    display: flex;
    justify-content: space-between;
    gap: 9px;
    margin-bottom: 5px;
    font-size: 12px;
}

.fa-bar-label strong {
    flex-shrink: 0;
}

.fa-track {
    height: 12px;
    border-radius: 5px;
    background: #e8eee5;
    overflow: hidden;
}

.fa-fill {
    height: 100%;
    border-radius: 5px;
    background: #89a976;
}

.fa-bar-row:first-child .fa-fill {
    background: var(--green);
}

/* STATUS */

.fa-status-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0,1fr));
    gap: 8px 18px;
}

.fa-status-label {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 4px;
}

.fa-status-track {
    height: 9px;
    background: #e8ede4;
    border-radius: 10px;
    overflow: hidden;
}

.fa-status-fill {
    height: 100%;
    border-radius: 10px;
}

/* TABLE */

.fa-table-wrap {
    overflow-x: auto;
}

.fa-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.fa-table th,
.fa-table td {
    text-align: left;
    padding: 9px 10px;
    border-bottom: 1px solid #edf0eb;
}

.fa-table th {
    color: #66755f;
}

.fa-empty {
    font-size: 12px;
    color: #768473;
    padding: 10px 0;
}

/* RESPONSIVE */

@media (max-width: 1100px) {
    .fa-stat-grid {
        grid-template-columns: repeat(2,1fr);
    }

    .fa-pop-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 750px) {
    .fa-shell {
        display: block;
    }

    .dashboard-sidebar {
        width: 100%;
        border-bottom: 1px solid var(--edge);
    }

    .fa-main {
        padding: 12px;
    }

    .fa-filters {
        flex-wrap: wrap;
    }

    .fa-filters .field {
        flex: 1 1 42%;
    }
}

@media (max-width: 480px) {
    .fa-stat-grid,
    .fa-status-grid {
        grid-template-columns: 1fr;
    }
}

/* Zero-order numbers */
.fa-trend-value.fa-zero {
    color: #a4ada0;
    font-size: 10px;
}

/* Smaller food order trend section */
.fa-trend {
    height: 125px;
    gap: 3px;
}

.fa-trend-bar {
    max-width: 27px;
}

/* Compact analytics cards */
.fa-card {
    padding: 12px 15px;
    border-radius: 12px;
}

.fa-stat-value {
    font-size: 22px;
    margin: 5px 0;
}

.fa-section h2 {
    font-size: 17px;
}

.fa-main {
    padding: 14px;
}

/* Show all dates neatly */
.fa-trend {
    gap: 2px;
}

.fa-trend-label {
    font-size: 10px;
    text-align: center;
    white-space: nowrap;
}

.fa-trend-col {
    min-width: 0;
}


</style>
</head>

<body>

<!-- ADMIN HEADER -->

<header class="fa-header">
    <a class="fa-brand" href="dashboard.php">
        <i class="bi bi-flower1"></i>
        PREM CURRY HOUSE
    </a>

    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="food_orders.php">Food Orders</a>
        <a href="tiffin_orders.php">Tiffin Orders</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>

<div class="fa-shell">

<?php if ($sidebar): ?>
    <?php require $sidebar; ?>
<?php else: ?>
    <aside class="dashboard-sidebar">
        <div class="sidebar-heading">Overview</div>
        <a class="sidebar-link" href="dashboard.php">Dashboard</a>

        <div class="sidebar-heading">Analytics</div>
        <a class="sidebar-link selected" href="food_analytics.php">
            Food Analytics
        </a>
        <a class="sidebar-link" href="menu_analytics.php">
            Menu Analytics
        </a>
        <a class="sidebar-link" href="tiffin_analytics.php">
            Tiffin Analytics
        </a>
    </aside>
<?php endif; ?>

<main class="fa-main">

<!-- PAGE HEADING -->

<div class="fa-heading">
    <small>SALES & ORDER OVERVIEW</small>
    <h1>Food Analytics</h1>
    <p>Food orders, popular dishes, beverages and order status.</p>
</div>

<!-- FILTERS -->

<section class="fa-card">

<form class="fa-filters" method="GET">

    <div class="field">
        <label for="fa-view">View by</label>
        <select id="fa-view" name="view">
            <?php foreach ([
                'daily' => 'Daily',
                'monthly' => 'Monthly',
                'yearly' => 'Yearly',
                'all' => 'All Time'
            ] as $value => $label): ?>
                <option
                    value="<?= $value ?>"
                    <?= $view === $value ? 'selected' : '' ?>
                >
                    <?= $label ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field" id="fa-year-field">
        <label for="fa-year">Year</label>
        <select name="year" id="fa-year">
            <?php for ($y = max((int)$today->format('Y'), $year);
                       $y >= 2020; $y--): ?>
                <option
                    value="<?= $y ?>"
                    <?= $year === $y ? 'selected' : '' ?>
                >
                    <?= $y ?>
                </option>
            <?php endfor; ?>
        </select>
    </div>

    <div class="field" id="fa-month-field">
        <label for="fa-month">Month</label>
        <select name="month" id="fa-month">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option
                    value="<?= $m ?>"
                    <?= $month === $m ? 'selected' : '' ?>
                >
                    <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                </option>
            <?php endfor; ?>
        </select>
    </div>

    <div class="field" id="fa-day-field">
        <label for="fa-day">Day</label>
        <select name="day" id="fa-day">
            <?php for ($d = 1; $d <= 31; $d++): ?>
                <option
                    value="<?= $d ?>"
                    <?= $day === $d ? 'selected' : '' ?>
                >
                    <?= $d ?>
                </option>
            <?php endfor; ?>
        </select>
    </div>

    <button type="submit" class="apply">Apply</button>
</form>

<div class="fa-filter-note">
    <i class="bi bi-calendar3"></i>
    Showing: <strong><?= faEsc($periodLabel) ?></strong>
</div>

</section>

<!-- SUMMARY CARDS -->

<section class="fa-stat-grid">

    <div class="fa-card fa-stat">
        <div class="fa-stat-head">
            Total Food Orders
            <i class="bi bi-bag"></i>
        </div>
        <div class="fa-stat-value">
            <?= (int)$summary['total_orders'] ?>
        </div>
        <small>Orders received</small>
    </div>

    <div class="fa-card fa-stat">
        <div class="fa-stat-head">
            Pending Orders
            <i class="bi bi-clock-history"></i>
        </div>
        <div class="fa-stat-value">
            <?= (int)$summary['pending_orders'] ?>
        </div>
        <small>Waiting for approval</small>
    </div>

    <div class="fa-card fa-stat">
        <div class="fa-stat-head">
            Completed Orders
            <i class="bi bi-check-circle"></i>
        </div>
        <div class="fa-stat-value">
            <?= (int)$summary['completed_orders'] ?>
        </div>
        <small>Completed orders</small>
    </div>

    <div class="fa-card fa-stat">
        <div class="fa-stat-head">
            Completed Order Value
            <i class="bi bi-cash-stack"></i>
        </div>
        <div class="fa-stat-value">
            <?= faMoney($summary['completed_value']) ?>
        </div>
        <small>Not necessarily collected payments</small>
    </div>

</section>

<!-- FOOD ORDER TRENDS -->

<section class="fa-card fa-section">
    <h2>Food Order Trends</h2>

    <p class="fa-section-desc">
        <?= $view === 'all'
            ? 'Yearly food order trends.'
            : 'Orders during the selected period.' ?>
    </p>

    <?php if ($view === 'all' && count($trends) > 0): ?>

        <?php
        $points = [];
        $n = count($trends);

        foreach ($trends as $i => $trend) {
            $x = $n === 1 ? 500 : 65 + $i * 870 / ($n - 1);
            $y = 170 - $trend['count'] / $trendMax * 125;

            $points[] = [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'label' => $trend['label'],
                'count' => $trend['count']
            ];
        }
        ?>

        <svg
            class="fa-year-chart"
            viewBox="0 0 1000 230"
            role="img"
            aria-label="Yearly food order trends"
        >

            <?php foreach ([45, 105, 170] as $gy): ?>
                <line
                    class="fa-grid-line"
                    x1="50"
                    x2="950"
                    y1="<?= $gy ?>"
                    y2="<?= $gy ?>"
                />
            <?php endforeach; ?>

            <?php if (count($points) > 1): ?>
                <polyline
                    class="fa-year-line"
                    points="<?= faEsc(implode(' ', array_map(
                        fn($p) => $p['x'] . ',' . $p['y'],
                        $points
                    ))) ?>"
                />
            <?php endif; ?>

            <?php foreach ($points as $point): ?>
                <circle
                    class="fa-year-dot"
                    cx="<?= $point['x'] ?>"
                    cy="<?= $point['y'] ?>"
                    r="6"
                >
                    <title>
                        <?= faEsc(
                            $point['label'] . ': ' .
                            $point['count'] . ' orders'
                        ) ?>
                    </title>
                </circle>

                <text
                    class="fa-year-number"
                    x="<?= $point['x'] ?>"
                    y="<?= max(20, $point['y'] - 15) ?>"
                >
                    <?= $point['count'] ?>
                </text>

                <text
                    class="fa-year-label"
                    x="<?= $point['x'] ?>"
                    y="207"
                >
                    <?= faEsc($point['label']) ?>
                </text>
            <?php endforeach; ?>
        </svg>

    <?php else: ?>

        <div class="fa-trend">
            <?php foreach ($trends as $i => $trend): ?>

                <div class="fa-trend-col">

                    
                    <span
                        class="fa-trend-value <?= $trend['count'] == 0 ? 'fa-zero' : '' ?>"
                    >
                        <?= $trend['count'] ?>
                    </span>


                    <div
                        class="fa-trend-bar"
                        style="height:<?= $trend['count']
                            ? max(
                                4,
                                round($trend['count'] / $trendMax * 91)
                            )
                            : 0
                        ?>px"
                        title="<?= faEsc(
                            $trend['label'] . ': ' .
                            $trend['count'] . ' orders'
                        ) ?>"
                    ></div>

                    
                    <span class="fa-trend-label">
                        <?= faEsc($trend['label']) ?>
                    </span>


                </div>

            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</section>

<!-- MOST POPULAR ITEMS -->

<section class="fa-card fa-section">

    <div class="fa-pop-head">
        <div>
            <h2>Most Popular Items</h2>
            <p class="fa-section-desc">
                Ranked by quantity ordered
            </p>
        </div>

        <div class="fa-tabs">
            <button
                type="button"
                class="fa-tab active"
                data-type="food"
            >
                Food
            </button>

            <button
                type="button"
                class="fa-tab"
                data-type="drink"
            >
                Beverages
            </button>
        </div>
    </div>

    <div class="fa-pop-grid">

        <div>
            <div
                class="fa-rank-list"
                id="fa-rank-list"
            ></div>

            <button
                type="button"
                class="fa-show"
                id="fa-show-more"
                hidden
            >
                Show More
            </button>
        </div>

        <div class="fa-bars">
            <h3>Quantity Sold</h3>
            <div id="fa-bar-list"></div>
        </div>

    </div>

</section>

<!-- STATUS BREAKDOWN -->

<section class="fa-card fa-section">
    <h2>Order Status Breakdown</h2>
    <p class="fa-section-desc">
        Orders by status in the selected period.
    </p>

    <div class="fa-status-grid">

        <?php $largest = max(1, ...array_values($statusCounts)); ?>

        <?php foreach ($statusCounts as $name => $count): ?>

            <div>
                <div class="fa-status-label">
                    <span><?= faEsc($name) ?></span>
                    <strong><?= (int)$count ?></strong>
                </div>

                <div class="fa-status-track">
                    <div
                        class="fa-status-fill"
                        style="
                            width:<?= round(
                                $count / $largest * 100,
                                2
                            ) ?>%;
                            background:<?= faEsc(
                                $statusColors[$name] ?? '#527b35'
                            ) ?>;
                        "
                    ></div>
                </div>
            </div>

        <?php endforeach; ?>

    </div>
</section>

<!-- RECENT FOOD ORDERS -->

<section class="fa-card fa-section">
    <h2>Recent Food Orders</h2>
    <p class="fa-section-desc">
        Latest orders in the selected period.
    </p>

    <div class="fa-table-wrap">
        <table class="fa-table">

            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td>#<?= (int)$order['order_id'] ?></td>
                        <td><?= faEsc($order['customer_name']) ?></td>
                        <td><?= faEsc($order['created_at']) ?></td>
                        <td><?= faMoney($order['total']) ?></td>
                        <td><?= faEsc($order['order_status']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$recentOrders): ?>
                    <tr>
                        <td colspan="5">
                            No orders for this period.
                        </td>
                    </tr>
                <?php endif; ?>

            </tbody>
        </table>
    </div>
</section>

</main>
</div>

<!-- JAVASCRIPT -->

<script>
(function () {
    'use strict';

    // FILTER CONTROLS

    const viewSelect = document.getElementById('fa-view');

    function updateFilters() {
        const selected = viewSelect.value;

        document.getElementById('fa-year-field').hidden =
            selected === 'all';

        document.getElementById('fa-month-field').hidden =
            !['daily', 'monthly'].includes(selected);

        document.getElementById('fa-day-field').hidden =
            selected !== 'daily';
    }

    viewSelect.addEventListener('change', updateFilters);
    updateFilters();

    // MYSQL DATA

    const foods = <?= json_encode(
        $food,
        JSON_HEX_TAG | JSON_HEX_AMP |
        JSON_HEX_APOS | JSON_HEX_QUOT |
        JSON_INVALID_UTF8_SUBSTITUTE
    ) ?: '[]' ?>;

    const drinks = <?= json_encode(
        $beverages,
        JSON_HEX_TAG | JSON_HEX_AMP |
        JSON_HEX_APOS | JSON_HEX_QUOT |
        JSON_INVALID_UTF8_SUBSTITUTE
    ) ?: '[]' ?>;

    // POPULAR ITEMS

    const rankList = document.getElementById('fa-rank-list');
    const barList = document.getElementById('fa-bar-list');
    const showButton = document.getElementById('fa-show-more');

    let currentType = 'food';
    let showAll = false;

    function make(tag, className = '', value) {
        const element = document.createElement(tag);

        if (className) {
            element.className = className;
        }

        if (value !== undefined) {
            element.textContent = String(value);
        }

        return element;
    }

    function renderPopular() {
        const items = currentType === 'food'
            ? foods
            : drinks;

        const visible = showAll
            ? items
            : items.slice(0, 5);

        rankList.replaceChildren();
        barList.replaceChildren();

        if (items.length === 0) {
            rankList.appendChild(
                make('p', 'fa-empty', 'No items for this period.')
            );

            barList.appendChild(
                make('p', 'fa-empty', 'No quantities available.')
            );
        }

        const maxSold = Math.max(
            1,
            ...items.map(item => Number(item.sold) || 0)
        );

        visible.forEach((item, index) => {

            // LEFT RANKING

            const row = make('div', 'fa-rank-row');
            const badge = make('div', 'fa-rank-icon');

            if (index < 3) {
                badge.textContent = ['🥇', '🥈', '🥉'][index];
            } else {
                badge.appendChild(
                    make('span', 'fa-rank-num', index + 1)
                );
            }

            const info = make('div');
            const name = make(
                'div',
                'fa-rank-name',
                item.name
            );

            const meta = make('div', 'fa-rank-meta');

            meta.appendChild(
                make('span', '', item.sold + ' sold')
            );

            let trendText = '—';
            let trendClass = 'fa-neutral';

            if (item.trend === 'up') {
                trendText = '↗ +' + item.percent + '%';
                trendClass = 'fa-up';
            } else if (item.trend === 'down') {
                trendText = '↘ ' + item.percent + '%';
                trendClass = 'fa-down';
            } else if (item.trend === 'new') {
                trendText = 'New';
                trendClass = 'fa-up';
            } else if (item.trend === 'same') {
                trendText = '→ 0%';
            }

            meta.appendChild(
                make('span', trendClass, trendText)
            );

            info.append(name, meta);
            row.append(badge, info);
            rankList.appendChild(row);

            // RIGHT HORIZONTAL BARS

            const barRow = make('div', 'fa-bar-row');
            const label = make('div', 'fa-bar-label');

            label.appendChild(
                make('span', '', (index + 1) + '. ' + item.name)
            );

            label.appendChild(
                make('strong', '', item.sold)
            );

            const track = make('div', 'fa-track');
            const fill = make('div', 'fa-fill');

            fill.style.width =
                (Number(item.sold) / maxSold * 100) + '%';

            track.appendChild(fill);
            barRow.append(label, track);
            barList.appendChild(barRow);
        });

        showButton.hidden = items.length <= 5;

        showButton.replaceChildren(
            document.createTextNode(
                showAll ? 'Show Less ' : 'Show More '
            ),
            make(
                'i',
                showAll
                    ? 'bi bi-chevron-up'
                    : 'bi bi-chevron-down'
            )
        );
    }

    // FOOD / BEVERAGES SWITCH

    document.querySelectorAll('.fa-tab').forEach(button => {
        button.addEventListener('click', () => {
            currentType = button.dataset.type;
            showAll = false;

            document.querySelectorAll('.fa-tab').forEach(tab => {
                tab.classList.toggle(
                    'active',
                    tab === button
                );
            });

            renderPopular();
        });
    });

    // SHOW MORE / LESS

    showButton.addEventListener('click', () => {
        showAll = !showAll;
        renderPopular();
    });

    // INITIAL RENDER

    renderPopular();

})();
</script>

</body>
</html>
