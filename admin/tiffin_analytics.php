
<?php
// =============================================
// PREM CURRY HOUSE - TIFFIN ANALYTICS
// Full-width Order Trend + Export Report
// =============================================

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
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rows(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function jsonForJs($value): string {
    return json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS |
        JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
    );
}

function validDate(string $value, DateTimeZone $tz): ?DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);

    return $date && $date->format('Y-m-d') === $value
        ? $date
        : null;
}

// =============================================
// DATE FILTERS
// =============================================

$tz = new DateTimeZone('Asia/Kuala_Lumpur');
$now = new DateTimeImmutable('now', $tz);

$period = $_GET['period'] ?? 'year';

if (!in_array($period, ['month', 'year', 'custom', 'all'], true)) {
    $period = 'year';
}

$year = filter_var(
    $_GET['year'] ?? $now->format('Y'),
    FILTER_VALIDATE_INT
);

if (!$year || $year < 2000 || $year > 2100) {
    $year = (int)$now->format('Y');
}

$month = filter_var(
    $_GET['month'] ?? $now->format('n'),
    FILTER_VALIDATE_INT
);

if (!$month || $month < 1 || $month > 12) {
    $month = (int)$now->format('n');
}

$fromInput = (string)($_GET['from'] ?? $now->format('Y-m-01'));
$toInput = (string)($_GET['to'] ?? $now->format('Y-m-d'));

$start = null;
$end = null;

if ($period === 'month') {

    $start = new DateTimeImmutable(
        sprintf('%04d-%02d-01', $year, $month),
        $tz
    );

    $end = $start->modify('+1 month');

} elseif ($period === 'year') {

    $start = new DateTimeImmutable(
        sprintf('%04d-01-01', $year),
        $tz
    );

    $end = $start->modify('+1 year');

} elseif ($period === 'custom') {

    $start = validDate($fromInput, $tz);
    $last = validDate($toInput, $tz);

    if (
        !$start ||
        !$last ||
        $start > $last ||
        $start->diff($last)->days > 3660
    ) {
        $period = 'year';

        $start = new DateTimeImmutable(
            sprintf('%04d-01-01', $year),
            $tz
        );

        $end = $start->modify('+1 year');

    } else {
        $end = $last->modify('+1 day');
    }
}

$where = $start
    ? ' WHERE o.created_at >= ? AND o.created_at < ?'
    : '';

$params = $start
    ? [
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s')
    ]
    : [];

$enquiryWhere = $start
    ? ' WHERE e.created_at >= ? AND e.created_at < ?'
    : '';

$periodLabel = match ($period) {
    'month' => $start->format('F Y'),
    'year' => (string)$year,
    'custom' => $start->format('d M Y') . ' – ' .
        $end->modify('-1 day')->format('d M Y'),
    default => 'All time'
};

// =============================================
// DATABASE ANALYTICS
// =============================================

try {

    // Total and status counts
    $summary = rows($pdo, "
        SELECT
            COUNT(*) AS total,

            COALESCE(SUM(
                CASE WHEN o.status = 'Pending'
                THEN 1 ELSE 0 END
            ), 0) AS pending,

            COALESCE(SUM(
                CASE WHEN o.status = 'Confirmed'
                THEN 1 ELSE 0 END
            ), 0) AS confirmed,

            COALESCE(SUM(
                CASE WHEN o.status = 'Completed'
                THEN 1 ELSE 0 END
            ), 0) AS completed,

            COALESCE(SUM(
                CASE WHEN o.status = 'Rejected'
                THEN 1 ELSE 0 END
            ), 0) AS rejected

        FROM tiffin_orders o
        " . $where,
        $params
    )[0];

    // =========================================
    // KPI GROWTH COMPARISON
    // =========================================

    $growthStats = [];
    $growthLabel = '';
    $previousStart = null;

    if ($start !== null && $end !== null) {

        if ($period === 'month') {

            $previousStart = $start->modify('-1 month');
            $growthLabel = 'vs previous month';

        } elseif ($period === 'year') {

            $previousStart = $start->modify('-1 year');
            $growthLabel = 'vs previous year';

        } else {

            $days = (int)$start->diff($end)->days;
            $previousStart = $start->modify("-{$days} days");
            $growthLabel = 'vs previous period';
        }

        $previousSummary = rows($pdo, "
            SELECT
                COUNT(*) AS total,

                COALESCE(SUM(
                    CASE WHEN status = 'Completed'
                    THEN 1 ELSE 0 END
                ), 0) AS completed

            FROM tiffin_orders

            WHERE created_at >= ?
              AND created_at < ?
        ", [
            $previousStart->format('Y-m-d H:i:s'),
            $start->format('Y-m-d H:i:s')
        ])[0];

        $comparisons = [
            'Total Tiffin Orders' => [
                (int)$summary['total'],
                (int)$previousSummary['total']
            ],
            'Completed Orders' => [
                (int)$summary['completed'],
                (int)$previousSummary['completed']
            ]
        ];

        foreach ($comparisons as $name => $counts) {

            [$current, $previous] = $counts;

            if ($previous === 0) {

                $growthStats[$name] = [
                    'text' => $current > 0 ? 'New orders' : 'No change',
                    'class' => $current > 0
                        ? 'growth-up'
                        : 'growth-same'
                ];

            } else {

                $percentage = (
                    ($current - $previous) / $previous
                ) * 100;

                $growthStats[$name] = [
                    'text' => sprintf('%+.1f%%', $percentage),

                    'class' => $percentage > 0
                        ? 'growth-up'
                        : (
                            $percentage < 0
                                ? 'growth-down'
                                : 'growth-same'
                        )
                ];
            }
        }
    }

    // =========================================
    // ESTIMATED ORDER VALUE
    // =========================================

    $valueWhere = $where
        ? $where . " AND o.status IN ('Confirmed','Completed')"
        : " WHERE o.status IN ('Confirmed','Completed')";

    $estimatedValue = (float)rows($pdo, "
        SELECT COALESCE(SUM(p.price), 0) AS amount

        FROM tiffin_orders o

        JOIN tiffin_plans p
            ON p.plan_id = o.plan_id

        " . $valueWhere,
        $params
    )[0]['amount'];

    // =========================================
    // POPULAR TIFFIN PLANS
    // =========================================

    $planJoinFilter = $start
        ? ' AND o.created_at >= ? AND o.created_at < ?'
        : '';

    $popularPlans = rows($pdo, "
        SELECT
            p.plan_name AS label,

            COUNT(o.order_id) AS total,

            COALESCE(SUM(
                CASE WHEN o.status = 'Pending'
                THEN 1 ELSE 0 END
            ), 0) AS pending,

            COALESCE(SUM(
                CASE WHEN o.status = 'Confirmed'
                THEN 1 ELSE 0 END
            ), 0) AS confirmed,

            COALESCE(SUM(
                CASE WHEN o.status = 'Completed'
                THEN 1 ELSE 0 END
            ), 0) AS completed,

            COALESCE(SUM(
                CASE WHEN o.status = 'Rejected'
                THEN 1 ELSE 0 END
            ), 0) AS rejected,

            COALESCE(SUM(
                CASE
                    WHEN o.status IN ('Confirmed','Completed')
                    THEN p.price
                    ELSE 0
                END
            ), 0) AS value

        FROM tiffin_plans p

        LEFT JOIN tiffin_orders o
            ON o.plan_id = p.plan_id
            " . $planJoinFilter . "

        GROUP BY p.plan_id, p.plan_name

        ORDER BY total DESC, p.plan_name ASC
    ", $params);

    // =========================================
    // RECENT ORDERS AND ENQUIRIES
    // =========================================

    $recentOrders = rows($pdo, "
        SELECT
            o.order_id,
            o.customer_name,
            o.start_date,
            o.status,
            o.created_at,
            p.plan_name

        FROM tiffin_orders o

        JOIN tiffin_plans p
            ON p.plan_id = o.plan_id

        " . $where . "

        ORDER BY o.created_at DESC, o.order_id DESC
        LIMIT 6
    ", $params);

    $recentEnquiries = rows($pdo, "
        SELECT
            e.enquiry_id,
            e.customer_name,
            e.subject,
            e.status,
            e.created_at

        FROM tiffin_enquiries e

        " . $enquiryWhere . "

        ORDER BY e.created_at DESC, e.enquiry_id DESC
        LIMIT 6
    ", $params);

    // =========================================
    // ORDER TREND
    // =========================================

    $trendLabels = [];
    $trendValues = [];
    $trendDates = [];

    $dailyTrend = $period === 'month' ||
        (
            $period === 'custom' &&
            $start !== null &&
            $start->diff($end)->days <= 62
        );

    if ($dailyTrend) {

        $groupExpr = 'DATE(o.created_at)';

        for (
            $d = $start;
            $d < $end;
            $d = $d->modify('+1 day')
        ) {
            $key = $d->format('Y-m-d');

            $trendLabels[$key] = $d->format('d M');
            $trendValues[$key] = 0;
            $trendDates[$key] = $d;
        }

    } elseif ($period === 'all') {

        $groupExpr = 'YEAR(o.created_at)';

    } else {

        $groupExpr = "DATE_FORMAT(o.created_at,'%Y-%m')";

        for (
            $d = $start;
            $d < $end;
            $d = $d->modify('first day of next month')
        ) {
            $key = $d->format('Y-m');

            $trendLabels[$key] = $d->format('M Y');
            $trendValues[$key] = 0;
            $trendDates[$key] = $d;
        }
    }

    $trendRows = rows($pdo, "
        SELECT
            {$groupExpr} AS bucket,
            COUNT(*) AS total

        FROM tiffin_orders o

        " . $where . "

        GROUP BY {$groupExpr}

        ORDER BY bucket ASC
    ", $params);

    foreach ($trendRows as $r) {

        $key = (string)$r['bucket'];

        if ($period === 'all') {

            $trendLabels[$key] = $key;
            $trendValues[$key] = (int)$r['total'];

        } elseif (array_key_exists($key, $trendValues)) {

            $trendValues[$key] = (int)$r['total'];
        }
    }

    // Do not display future dates as zero
    if ($period !== 'all') {

        foreach ($trendDates as $key => $date) {

            if ($dailyTrend) {

                if ($date->format('Y-m-d') > $now->format('Y-m-d')) {
                    $trendValues[$key] = null;
                }

            } else {

                if ($date->format('Y-m') > $now->format('Y-m')) {
                    $trendValues[$key] = null;
                }
            }
        }
    }

    // =========================================
    // PREVIOUS-PERIOD ORDER TREND
    // =========================================

    $previousTrendValues = [];

    $canCompareTrend = $start !== null &&
        (
            $period === 'month' ||
            $period === 'year' ||
            (
                $period === 'custom' &&
                $start->diff($end)->days <= 62
            )
        );

    if ($canCompareTrend) {

        $previousExpr = $dailyTrend
            ? 'DATE(o.created_at)'
            : "DATE_FORMAT(o.created_at,'%Y-%m')";

        $previousRows = rows($pdo, "
            SELECT
                {$previousExpr} AS bucket,
                COUNT(*) AS total

            FROM tiffin_orders o

            WHERE o.created_at >= ?
              AND o.created_at < ?

            GROUP BY {$previousExpr}
        ", [
            $previousStart->format('Y-m-d H:i:s'),
            $start->format('Y-m-d H:i:s')
        ]);

        $previousCounts = [];

        foreach ($previousRows as $r) {
            $previousCounts[(string)$r['bucket']] = (int)$r['total'];
        }

        if ($period === 'year') {

            for (
                $d = $previousStart;
                $d < $start;
                $d = $d->modify('first day of next month')
            ) {
                $previousTrendValues[] =
                    $previousCounts[$d->format('Y-m')] ?? 0;
            }

        } else {

            for (
                $d = $previousStart;
                $d < $start;
                $d = $d->modify('+1 day')
            ) {
                $previousTrendValues[] =
                    $previousCounts[$d->format('Y-m-d')] ?? 0;
            }
        }

        $previousTrendValues = array_slice(
            $previousTrendValues,
            0,
            count($trendValues)
        );

        while (count($previousTrendValues) < count($trendValues)) {
            $previousTrendValues[] = null;
        }
    }

    // =========================================
    // DATA SENT TO CHART.JS
    // =========================================

    $chartPayload = [

        'trend' => [
            'labels' => array_values($trendLabels),
            'values' => array_values($trendValues)
        ],

        'previousTrend' => [
            'values' => $previousTrendValues
        ],

        'status' => [
            'labels' => [
                'Pending',
                'Confirmed',
                'Completed',
                'Rejected'
            ],

            'values' => [
                (int)$summary['pending'],
                (int)$summary['confirmed'],
                (int)$summary['completed'],
                (int)$summary['rejected']
            ]
        ],

        'planValue' => [
            'labels' => array_column($popularPlans, 'label'),

            'values' => array_map(
                'floatval',
                array_column($popularPlans, 'value')
            )
        ]
    ];

} catch (Throwable $ex) {

    error_log('Tiffin analytics error: ' . $ex->getMessage());

    http_response_code(500);

    exit('Unable to load Tiffin Analytics. Check your PHP error log.');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Tiffin Analytics | Prem Curry House</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>

/* =========================================
   GENERAL
   ========================================= */

:root {
    --forest: #294e35;
    --green: #527b35;
    --cream: #f8f6ef;
    --gold: #c99c51;
    --stroke: #e5e9df;
}

body.analytics-page {
    background: var(--cream);
    color: #253b2a;
    font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
    font-size: 15px;
}

.analytics-nav {
    background: var(--forest) !important;
    box-shadow: 0 4px 16px #203d2918;
}

.analytics-nav .navbar-brand {
    color: white !important;
    font-size: 18px !important;
    font-weight: 800;
}

.analytics-nav .nav-link {
    color: #ebf2e9 !important;
    font-size: 16px !important;
    font-weight: 700;
}

.dashboard-layout {
    display: flex;
    align-items: stretch;
    min-height: calc(100vh - 58px);
}

.dashboard-sidebar {
    width: 205px;
    flex: 0 0 205px;
    background: white;
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
    text-transform: uppercase;
    letter-spacing: .13em;
    color: #8b9a8b;
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
    color: var(--green);
    font-size: 15px;
    width: 19px;
    text-align: center;
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
    min-width: 0;
    flex: 1;
}

.analytics-container {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 22px 26px 45px;
}

.eyebrow {
    text-transform: uppercase;
    letter-spacing: .13em;
    color: var(--green);
    font-size: 10px;
    font-weight: 800;
}

.page-title {
    font-size: 27px;
    font-weight: 800;
    color: #203e2a;
}

.subtle {
    color: #728173;
    font-size: 12px;
}

.panel {
    background: #fff;
    border: 1px solid var(--stroke);
    border-radius: 16px;
    box-shadow: 0 5px 26px #26472b0b;
}

.section-heading {
    font-size: 17px;
    font-weight: 800;
    color: #24432b;
}

.note {
    color: #798777;
    font-size: 12px;
}

/* =========================================
   FILTERS
   ========================================= */

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
    border-color: #dbe4d7;
    font-size: 13px;
}

.btn-forest {
    background: var(--forest);
    color: white;
    border: 1px solid var(--forest);
}

.btn-forest:hover {
    background: #3b6845;
    color: white;
}

/* =========================================
   KPI CARDS
   ========================================= */

.kpi {
    padding: 16px 17px;
    min-height: 128px;
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.kpi .k-label {
    font-size: 12px;
    font-weight: 600;
    color: #738272;
    margin-bottom: 8px;
}

.kpi .k-value {
    font-size: 25px;
    font-weight: 800;
    color: #24432b;
    line-height: 1.1;
}

.kpi .k-hint {
    font-size: 11px;
    color: #89968a;
    margin-top: 6px;
}

.kpi .k-icon {
    width: 43px;
    height: 43px;
    border-radius: 12px;
    background: #eff4e9;
    color: var(--green);
    display: grid;
    place-items: center;
    font-size: 22px;
    flex: none;
}

.growth-info {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    align-items: center;
    margin: 8px 0 5px;
}

.growth-up,
.growth-down,
.growth-same {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 9px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 800;
}

.growth-up {
    color: #16803c;
    background: #e6f5eb;
}

.growth-down {
    color: #c0392b;
    background: #fdecea;
}

.growth-same {
    color: #667085;
    background: #f1f3f5;
}

.growth-period {
    font-size: 11px;
    color: #819083;
}

/* =========================================
   CHARTS
   ========================================= */

.chart-card {
    padding: 18px;
    height: 100%;
    min-height: 306px;
    display: flex;
    flex-direction: column;
}

.chart-title {
    font-size: 16px;
    font-weight: 800;
    margin: 0;
    color: #28472c;
}

.chart-subtitle {
    font-size: 12px;
    color: #5c705f;
    margin-top: 3px;
}

.zoom-btn {
    border: 1px solid #e1e9dc;
    background: #f7faf5;
    color: var(--green);
    border-radius: 8px;
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    flex: none;
}

.zoom-btn:hover {
    background: #edf4e9;
}

.chart-holder {
    height: 230px;
    position: relative;
    min-height: 0;
    flex: 1;
    margin-top: 14px;
}

.chart-holder canvas {
    width: 100% !important;
    height: 100% !important;
}

/* Larger full-width Order Trend */

.trend-chart-card {
    height: auto;
    min-height: 0;
}

.trend-chart-card .chart-holder {
    height: 380px;
    min-height: 380px;
    flex: 0 0 380px;
}

/* =========================================
   POPULAR TIFFIN PLANS
   ========================================= */

.plan-bars {
    margin-top: 15px;
    display: flex;
    flex-direction: column;
    justify-content: space-evenly;
    gap: 18px;
    flex: 1;
    min-height: 0;
    padding-bottom: 10px;
}

.plan-line {
    min-width: 0;
    width: 100%;
}

.plan-name {
    font-size: 14px;
    font-weight: 700;
    color: #304a35;
}

.plan-total {
    font-size: 13px;
    font-weight: 800;
    color: #466647;
    white-space: nowrap;
}

.plan-track {
    height: 18px;
    border-radius: 20px;
    background: #edf1e9;
    overflow: hidden;
    display: flex;
    margin-top: 9px;
}

.plan-segment {
    display: block;
    height: 100%;
    flex: none;
}

.plan-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 9px 12px;
    margin-top: 8px;
}

.plan-legend span {
    font-size: 12px;
    color: #526755;
    white-space: nowrap;
}

.plan-legend i {
    display: inline-block;
    vertical-align: middle;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 4px;
}

.plan-zero {
    font-size: 12px;
    color: #819083;
    margin-top: 5px;
}

.plan-status-key {
    margin-top: auto;
    padding-top: 14px;
    border-top: 1px solid #edf1e9;
    display: flex;
    flex-wrap: wrap;
    gap: 9px 13px;
}

.plan-status-key-label {
    width: 100%;
    font-size: 12px;
    font-weight: 800;
    color: #536b56;
}

.plan-status-key-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: #536b56;
}

.plan-status-key-dot {
    width: 10px;
    height: 10px;
    display: inline-block;
    border-radius: 3px;
}

.plan-bars-large {
    max-height: 65vh;
    overflow-y: auto;
    gap: 27px;
    padding: 15px 6px;
}

/* =========================================
   ESTIMATED VALUE
   ========================================= */

.value-chart-card {
    min-height: 0;
    height: auto;
}

.value-chart-card .chart-holder {
    height: 300px;
    min-height: 300px;
    flex: 0 0 300px;
}

.value-colour-guide {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px 20px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #edf1e9;
}

.value-colour-guide span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #526755;
    font-size: 12px;
    font-weight: 600;
}

.value-colour-guide i {
    display: inline-block;
    width: 11px;
    height: 11px;
    border-radius: 3px;
}

/* =========================================
   RECENT RECORDS
   ========================================= */

.records-stack {
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-width: 0;
}

.data-panel {
    padding: 17px 18px;
}

.data-panel h3 {
    font-size: 17px;
    font-weight: 800;
}

.records-stack .table-responsive {
    max-height: 240px;
    overflow-y: auto;
}

.table {
    font-size: 12px;
    margin-bottom: 0;
}

.table thead th {
    background: #f6f8f4;
    color: #617062;
    border-bottom: 0;
    white-space: nowrap;
    font-size: 11px;
    font-weight: 700;
    position: sticky;
    top: 0;
    z-index: 1;
}

.table td {
    vertical-align: middle;
    color: #39483c;
    white-space: nowrap;
}

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
    background: #fff4df;
    color: #a66e14;
}

.chip-Confirmed,
.chip-In-Progress {
    background: #e6f2ff;
    color: #356ca1;
}

.chip-Completed,
.chip-Resolved {
    background: #e4f4e9;
    color: #27804b;
}

.chip-Rejected {
    background: #fde8e6;
    color: #b44c43;
}

.action-link {
    color: #426d3a;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
}

.action-link:hover {
    text-decoration: underline;
}

/* =========================================
   CHART ENLARGE MODAL
   ========================================= */

.modal-content {
    border-radius: 17px;
    border: 0;
}

.large-chart {
    height: min(67vh, 560px);
    position: relative;
}

/* =========================================
   RESPONSIVE
   ========================================= */

@media (max-width: 1199.98px) {

    .value-chart-card .chart-holder {
        height: 280px;
        min-height: 280px;
        flex-basis: 280px;
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
        gap: 3px;
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

@media (max-width: 767.98px) {

    .trend-chart-card .chart-holder {
        height: 270px;
        min-height: 270px;
        flex-basis: 270px;
    }

    .page-title {
        font-size: 24px;
    }
}


/* ==========================================
   SMALLER POPULAR PLANS AND ORDER STATUS
   ========================================== */

/* Reduce the height of both cards */
.row:has(#popularPlanBars) .chart-card {
    height: 400px;
    min-height: 400px;
}

/* Make the Order Status donut smaller */
.row:has(#popularPlanBars) .chart-holder {
    height: 290px;
    min-height: 290px;
    flex: 0 0 290px;
}

/* Keep plan bars closer together */
.row:has(#popularPlanBars) .plan-bars {
    gap: 12px;
    justify-content: flex-start;
}

/* Keep the status colour guide at the bottom */
.row:has(#popularPlanBars) .plan-status-key {
    margin-top: auto;
}

/* Mobile layout */
@media (max-width: 767.98px) {
    .row:has(#popularPlanBars) .chart-card {
        height: auto;
        min-height: 350px;
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

        <a class="navbar-brand fw-bold" href="dashboard.php">
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

                <a class="nav-link" href="dashboard.php">
                    Dashboard
                </a>

                <a class="nav-link" href="menu_items.php">
                    Menu
                </a>

                <a class="nav-link" href="tiffin_orders.php">
                    Tiffin Orders
                </a>

                <a class="nav-link" href="tiffin_enquiries.php">
                    Enquiries
                </a>

            </div>

            <div class="ms-auto d-flex align-items-center gap-3 mt-2 mt-lg-0">

                <span class="text-white-50 small">

                    <i class="bi bi-person-circle me-1"></i>

                    <?= e($_SESSION['full_name'] ?? 'Staff') ?>

                    (<?= e(ucfirst($_SESSION['role'] ?? 'staff')) ?>)

                </span>

                <a
                    class="btn btn-outline-light btn-sm"
                    href="../logout.php"
                >
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

            <!-- =====================================
                 PAGE HEADING
                 ===================================== -->

            <div class="d-flex flex-wrap gap-3 justify-content-between align-items-end mb-4">

                <div>

                    <div class="eyebrow mb-1">
                        Operations overview
                    </div>

                    <h1 class="page-title mb-1">
                        Tiffin Analytics
                    </h1>

                    <div class="subtle">
                        Order trends, status breakdown and estimated tiffin value.
                    </div>

                </div>

                <div class="subtle">

                    <i class="bi bi-calendar3 me-1"></i>

                    Showing:
                    <strong><?= e($periodLabel) ?></strong>

                </div>

            </div>

            <!-- =====================================
                 DATE FILTERS
                 ===================================== -->

            <form method="get" class="panel filter-panel mb-4">

                <div class="row g-3 align-items-end">

                    <div class="col-6 col-md-3 col-lg-2">

                        <label class="form-label" for="period">
                            View by
                        </label>

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

                        <label class="form-label" for="year">
                            Year
                        </label>

                        <select class="form-select" name="year" id="year">

                            <?php for (
                                $y = (int)$now->format('Y') + 1;
                                $y >= 2020;
                                $y--
                            ): ?>

                                <option
                                    value="<?= $y ?>"
                                    <?= $year === $y ? 'selected' : '' ?>
                                >
                                    <?= $y ?>
                                </option>

                            <?php endfor; ?>

                        </select>

                    </div>

                    <div class="col-6 col-md-2" data-filter="month">

                        <label class="form-label" for="month">
                            Month
                        </label>

                        <select class="form-select" name="month" id="month">

                            <?php for ($m = 1; $m <= 12; $m++): ?>

                                <option
                                    value="<?= $m ?>"
                                    <?= $month === $m ? 'selected' : '' ?>
                                >
                                    <?= e(
                                        DateTimeImmutable::createFromFormat(
                                            '!m',
                                            str_pad((string)$m, 2, '0', STR_PAD_LEFT)
                                        )->format('F')
                                    ) ?>
                                </option>

                            <?php endfor; ?>

                        </select>

                    </div>

                    <div class="col-6 col-md-2" data-filter="custom">

                        <label class="form-label" for="from">
                            From
                        </label>

                        <input
                            class="form-control"
                            type="date"
                            name="from"
                            id="from"
                            value="<?= e($fromInput) ?>"
                        >

                    </div>

                    <div class="col-6 col-md-2" data-filter="custom">

                        <label class="form-label" for="to">
                            To
                        </label>

                        <input
                            class="form-control"
                            type="date"
                            name="to"
                            id="to"
                            value="<?= e($toInput) ?>"
                        >

                    </div>

                    <div class="col-auto">

                        <button type="submit" class="btn btn-forest">
                            <i class="bi bi-funnel me-1"></i>
                            Apply Filter
                        </button>

                    </div>

                    <div class="col-auto">

                        <a
                            href="tiffin_analytics.php"
                            class="btn btn-outline-secondary"
                        >
                            Reset
                        </a>

                    </div>

                </div>

            </form>

            <!-- =====================================
                 KPI CARDS
                 ===================================== -->

            <?php

            $kpis = [
                [
                    'Total Tiffin Orders',
                    (int)$summary['total'],
                    'bag-check',
                    'Orders placed in this period'
                ],

                [
                    'Pending Orders',
                    (int)$summary['pending'],
                    'hourglass-split',
                    'Awaiting admin review'
                ],

                [
                    'Completed Orders',
                    (int)$summary['completed'],
                    'check-circle',
                    'Marked completed'
                ],

                [
                    'Est. Order Value',
                    'RM ' . number_format($estimatedValue, 2),
                    'cash-stack',
                    'Confirmed + completed; not payments'
                ]
            ];

            ?>

            <div class="row g-3 mb-4">

                <?php foreach ($kpis as $k): ?>

                    <div class="col-6 col-lg-3">

                        <div class="panel kpi">

                            <div>

                                <div class="k-label">
                                    <?= e($k[0]) ?>
                                </div>

                                <div class="k-value">
                                    <?= e($k[1]) ?>
                                </div>

                                <?php if (isset($growthStats[$k[0]])): ?>

                                    <div class="growth-info">

                                        <span class="<?= e($growthStats[$k[0]]['class']) ?>">

                                            <?php if ($growthStats[$k[0]]['class'] === 'growth-up'): ?>

                                                <i class="bi bi-graph-up-arrow"></i>

                                            <?php elseif ($growthStats[$k[0]]['class'] === 'growth-down'): ?>

                                                <i class="bi bi-graph-down-arrow"></i>

                                            <?php else: ?>

                                                <i class="bi bi-dash"></i>

                                            <?php endif; ?>

                                            <?= e($growthStats[$k[0]]['text']) ?>

                                        </span>

                                        <span class="growth-period">
                                            <?= e($growthLabel) ?>
                                        </span>

                                    </div>

                                <?php endif; ?>

                                <div class="k-hint">
                                    <?= e($k[3]) ?>
                                </div>

                            </div>

                            <div class="k-icon">

                                <i class="bi bi-<?= e($k[2]) ?>"></i>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <!-- =====================================
                 BUSINESS INSIGHTS + EXPORT
                 ===================================== -->

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">

                <h2 class="section-heading mb-0">
                    Orders &amp; Business Insights
                </h2>

                <div class="d-flex align-items-center gap-3 flex-wrap">

                    <span class="note">

                        Click
                        <i class="bi bi-arrows-fullscreen"></i>
                        to enlarge a chart

                    </span>

                    <a
                        class="btn btn-forest btn-sm"
                        href="export_tiffin_report.php?<?= e(http_build_query([
                            'period' => $period,
                            'year' => $year,
                            'month' => $month,
                            'from' => $fromInput,
                            'to' => $toInput
                        ])) ?>"
                    >

                        <i class="bi bi-file-earmark-excel me-1"></i>
                        Export Report

                    </a>

                </div>

            </div>

            <!-- =====================================
                 ROW 1 - FULL WIDTH ORDER TREND
                 ===================================== -->

            <div class="row g-3 mb-3">

                <div class="col-12">

                    <div class="panel chart-card trend-chart-card">

                        <div class="d-flex justify-content-between gap-2">

                            <div>

                                <h3 class="chart-title">
                                    Order Trend
                                </h3>

                                <div class="chart-subtitle">
                                    Orders created over time
                                </div>

                            </div>

                            <button
                                type="button"
                                class="zoom-btn"
                                data-chart="trend"
                                data-title="Order Trend"
                                aria-label="Enlarge Order Trend"
                            >
                                <i class="bi bi-arrows-fullscreen"></i>
                            </button>

                        </div>

                        <div class="chart-holder">

                            <canvas id="trendChart"></canvas>

                        </div>

                    </div>

                </div>

            </div>

            <!-- =====================================
                 ROW 2 - POPULAR PLANS + STATUS
                 ===================================== -->

            <div class="row g-3 mb-4">

                <!-- MOST POPULAR TIFFIN PLANS -->

                <div class="col-12 col-md-6">

                    <div class="panel chart-card">

                        <div class="d-flex justify-content-between gap-2">

                            <div>

                                <h3 class="chart-title">
                                    Most Popular Tiffin Plans
                                </h3>

                                <div class="chart-subtitle">
                                    Orders by plan and status · <?= e($periodLabel) ?>
                                </div>

                            </div>

                            <button
                                type="button"
                                class="zoom-btn"
                                data-chart="plans"
                                data-title="Popular Tiffin Plans"
                                aria-label="Enlarge Popular Plans"
                            >
                                <i class="bi bi-arrows-fullscreen"></i>
                            </button>

                        </div>

                        <div class="plan-bars" id="popularPlanBars">

                            <?php

                            $maxPlanOrders = max(
                                1,
                                ...array_map(
                                    static fn($p) => (int)$p['total'],
                                    $popularPlans
                                )
                            );

                            ?>

                            <?php foreach ($popularPlans as $plan): ?>

                                <div class="plan-line">

                                    <div class="d-flex align-items-start justify-content-between gap-2">

                                        <span class="plan-name">
                                            <?= e($plan['label']) ?>
                                        </span>

                                        <span class="plan-total">

                                            <?= (int)$plan['total'] ?>

                                            <?= (int)$plan['total'] === 1
                                                ? 'order'
                                                : 'orders' ?>

                                        </span>

                                    </div>

                                    <div class="plan-track">

                                        <?php foreach ([
                                            'Pending' => '#e1a84a',
                                            'Confirmed' => '#5d91c9',
                                            'Completed' => '#3e8660',
                                            'Rejected' => '#c76b64'
                                        ] as $status => $color): ?>

                                            <?php
                                            $count = (int)$plan[strtolower($status)];
                                            ?>

                                            <?php if ($count > 0): ?>

                                                <span
                                                    class="plan-segment"
                                                    style="width:<?= ($count / $maxPlanOrders) * 100 ?>%;background:<?= $color ?>"
                                                    title="<?= e($status) ?>: <?= $count ?>"
                                                ></span>

                                            <?php endif; ?>

                                        <?php endforeach; ?>

                                    </div>

                                    <?php if ((int)$plan['total'] === 0): ?>

                                        <div class="plan-zero">
                                            No orders yet
                                        </div>

                                    <?php else: ?>

                                        <div class="plan-legend">

                                            <?php foreach ([
                                                'Pending' => '#e1a84a',
                                                'Confirmed' => '#5d91c9',
                                                'Completed' => '#3e8660',
                                                'Rejected' => '#c76b64'
                                            ] as $status => $color): ?>

                                                <?php
                                                $count = (int)$plan[strtolower($status)];
                                                ?>

                                                <?php if ($count > 0): ?>

                                                    <span>

                                                        <i style="background:<?= $color ?>"></i>

                                                        <?= e($status) ?>:
                                                        <?= $count ?>

                                                    </span>

                                                <?php endif; ?>

                                            <?php endforeach; ?>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            <?php endforeach; ?>

                            <div class="plan-status-key">

                                <div class="plan-status-key-label">
                                    Status colour guide
                                </div>

                                <?php foreach ([
                                    'Pending' => '#e1a84a',
                                    'Confirmed' => '#5d91c9',
                                    'Completed' => '#3e8660',
                                    'Rejected' => '#c76b64'
                                ] as $status => $color): ?>

                                    <span class="plan-status-key-item">

                                        <i
                                            class="plan-status-key-dot"
                                            style="background:<?= $color ?>"
                                        ></i>

                                        <?= e($status) ?>

                                    </span>

                                <?php endforeach; ?>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- ORDER STATUS -->

                <div class="col-12 col-md-6">

                    <div class="panel chart-card">

                        <div class="d-flex justify-content-between gap-2">

                            <div>

                                <h3 class="chart-title">
                                    Order Status
                                </h3>

                                <div class="chart-subtitle">
                                    Status breakdown with counts and percentages
                                </div>

                            </div>

                            <button
                                type="button"
                                class="zoom-btn"
                                data-chart="status"
                                data-title="Order Status"
                                aria-label="Enlarge Order Status"
                            >
                                <i class="bi bi-arrows-fullscreen"></i>
                            </button>

                        </div>

                        <div class="chart-holder">

                            <canvas id="statusChart"></canvas>

                        </div>

                    </div>

                </div>

            </div>

            <!-- =====================================
                 ROW 3 - VALUE + RECENT RECORDS
                 ===================================== -->

            <div class="row g-3 mb-4 align-items-start">

                <div class="col-12 col-xl-5">

                    <div class="panel chart-card value-chart-card">

                        <div class="d-flex justify-content-between gap-2">

                            <div>

                                <h3 class="chart-title">
                                    Estimated Value by Tiffin Plan
                                </h3>

                                <div class="chart-subtitle">
                                    RM · confirmed + completed orders, using current plan prices
                                </div>

                            </div>

                            <button
                                type="button"
                                class="zoom-btn"
                                data-chart="planValue"
                                data-title="Estimated Order Value by Plan"
                                aria-label="Enlarge Order Value"
                            >
                                <i class="bi bi-arrows-fullscreen"></i>
                            </button>

                        </div>

                        <div class="chart-holder">

                            <canvas id="planValueChart"></canvas>

                        </div>

                        <div class="value-colour-guide">

                            <span>

                                <i style="background:#527B35"></i>
                                Vegetarian

                            </span>

                            <span>

                                <i style="background:#C99C51"></i>
                                Mixed

                            </span>

                            <span>

                                <i style="background:#8CB18B"></i>
                                Non-Vegetarian

                            </span>

                        </div>

                    </div>

                </div>

                <div class="col-12 col-xl-7">

                    <div class="records-stack">

                        <!-- RECENT ORDERS -->

                        <div class="panel data-panel">

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

                                <h3 class="mb-0">
                                    Recent Tiffin Orders
                                </h3>

                                <a
                                    class="action-link"
                                    href="tiffin_orders.php"
                                >
                                    Manage orders
                                    <i class="bi bi-arrow-right"></i>
                                </a>

                            </div>

                            <div class="table-responsive">

                                <table class="table table-hover">

                                    <thead>

                                        <tr>
                                            <th>Order</th>
                                            <th>Customer</th>
                                            <th>Plan</th>
                                            <th>Start Date</th>
                                            <th>Status</th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php if (!$recentOrders): ?>

                                            <tr>

                                                <td
                                                    colspan="5"
                                                    class="text-center text-muted py-4"
                                                >
                                                    No orders in this period.
                                                </td>

                                            </tr>

                                        <?php endif; ?>

                                        <?php foreach ($recentOrders as $r): ?>

                                            <tr>

                                                <td class="fw-bold">
                                                    #<?= (int)$r['order_id'] ?>
                                                </td>

                                                <td>
                                                    <?= e($r['customer_name']) ?>
                                                </td>

                                                <td>
                                                    <?= e($r['plan_name']) ?>
                                                </td>

                                                <td>
                                                    <?= e($r['start_date']) ?>
                                                </td>

                                                <td>

                                                    <span class="chip chip-<?= e($r['status']) ?>">
                                                        <?= e($r['status']) ?>
                                                    </span>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                        <!-- RECENT ENQUIRIES -->

                        <div class="panel data-panel">

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

                                <h3 class="mb-0">
                                    Recent Enquiries
                                </h3>

                                <a
                                    class="action-link"
                                    href="tiffin_enquiries.php"
                                >
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
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php if (!$recentEnquiries): ?>

                                            <tr>

                                                <td
                                                    colspan="3"
                                                    class="text-center text-muted py-4"
                                                >
                                                    No enquiries in this period.
                                                </td>

                                            </tr>

                                        <?php endif; ?>

                                        <?php foreach ($recentEnquiries as $r): ?>

                                            <tr>

                                                <td>
                                                    <?= e($r['customer_name']) ?>
                                                </td>

                                                <td>
                                                    <?= e($r['subject']) ?>
                                                </td>

                                                <td>

                                                    <span class="chip chip-<?= e(str_replace(' ', '-', $r['status'])) ?>">
                                                        <?= e($r['status']) ?>
                                                    </span>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <p class="note mt-3 mb-0">

                Estimated value uses current plan prices for confirmed or
                completed orders. It is not verified payment revenue or profit.

            </p>

        </main>

    </div>

</div>

<!-- =========================================
     ENLARGE CHART MODAL
     ========================================= -->

<div
    class="modal fade"
    id="chartModal"
    tabindex="-1"
    aria-labelledby="chartModalTitle"
    aria-hidden="true"
>

    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5
                    class="modal-title fw-bold"
                    id="chartModalTitle"
                >
                    Chart Detail
                </h5>

                <button
                    class="btn-close"
                    type="button"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>

            <div class="modal-body">

                <div class="large-chart">

                    <canvas id="expandedChart"></canvas>

                </div>

            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

<script>
(function() {

    // =====================================
    // SHOW/HIDE DATE FILTERS
    // =====================================

    const picker = document.getElementById('period');

    function showFilters() {

        document.querySelectorAll('[data-filter]').forEach(el => {

            const visible = el.dataset.filter
                .split(' ')
                .includes(picker.value);

            el.style.display = visible ? '' : 'none';

            el.querySelectorAll('input,select').forEach(input => {
                input.disabled = !visible;
            });
        });
    }

    picker.addEventListener('change', showFilters);
    showFilters();

    if (!window.Chart) {

        document.querySelectorAll('.chart-holder').forEach(el => {

            el.innerHTML =
                '<p class="text-muted small">Chart.js requires internet access.</p>';
        });

        return;
    }

    if (window.ChartDataLabels) {
        Chart.register(ChartDataLabels);
    }

    // =====================================
    // PHP DATA
    // =====================================

    const data = <?= jsonForJs($chartPayload) ?>;

    const forest = '#527B35';
    const gold = '#C99C51';
    const soft = '#8CB18B';

    const fmt = number =>
        Number(number).toLocaleString('en-MY');

    function planColour(name) {

        const plan = String(name).toLowerCase();

        if (
            plan.includes('non-vegetarian') ||
            plan.includes('non vegetarian')
        ) {
            return soft;
        }

        if (plan.includes('mixed')) {
            return gold;
        }

        return forest;
    }

    // =====================================
    // DOUGHNUT CENTRE TOTAL
    // =====================================

    const centerTotalPlugin = {

        id: 'orderStatusCenterTotal',

        afterDatasetsDraw(chart) {

            if (chart.config.type !== 'doughnut') {
                return;
            }

            const arc = chart.getDatasetMeta(0).data[0];

            if (!arc) {
                return;
            }

            const values = chart.data.datasets[0].data;

            const total = values.reduce(
                (sum, value) => sum + Number(value || 0),
                0
            );

            const {ctx} = chart;

            ctx.save();

            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            ctx.fillStyle = '#24432b';
            ctx.font = '800 27px system-ui';

            ctx.fillText(
                fmt(total),
                arc.x,
                arc.y - 7
            );

            ctx.fillStyle = '#738272';
            ctx.font = '11px system-ui';

            ctx.fillText(
                'Total orders',
                arc.x,
                arc.y + 17
            );

            ctx.restore();
        }
    };

    // =====================================
    // CREATE CHART
    // =====================================

    function makeChart(canvas, key, large = false) {

        let type;
        let labels;
        let datasets;

        if (key === 'trend') {

            type = 'line';
            labels = data.trend.labels;

            datasets = [
                {
                    label: 'Current period',
                    data: data.trend.values,

                    borderColor: forest,
                    backgroundColor: '#527B3520',

                    borderWidth: 3,
                    tension: 0.35,

                    pointRadius: 3,
                    pointHoverRadius: 6,

                    spanGaps: false,
                    fill: true
                }
            ];

            if (data.previousTrend.values.length > 0) {

                datasets.push({
                    label: 'Previous period',
                    data: data.previousTrend.values,

                    borderColor: gold,
                    backgroundColor: gold,

                    borderWidth: 3,
                    borderDash: [7, 5],

                    tension: 0.35,
                    pointRadius: 3,

                    spanGaps: false,
                    fill: false
                });
            }

        } else if (key === 'status') {

            type = 'doughnut';
            labels = data.status.labels;

            datasets = [
                {
                    label: 'Orders',
                    data: data.status.values,

                    backgroundColor: [
                        '#E1A84A',
                        '#5D91C9',
                        '#3E8660',
                        '#C76B64'
                    ],

                    borderColor: '#ffffff',
                    borderWidth: 3,

                    cutout: '66%'
                }
            ];

        } else if (key === 'planValue') {

            type = 'bar';
            labels = data.planValue.labels;

            datasets = [
                {
                    label: 'Estimated Value (RM)',
                    data: data.planValue.values,

                    backgroundColor: labels.map(planColour),
                    borderRadius: 6,

                    maxBarThickness: large ? 68 : 46
                }
            ];

        } else {
            return null;
        }

        const isStatus = key === 'status';
        const isValue = key === 'planValue';

        return new Chart(canvas, {

            type: type,

            data: {
                labels: labels,
                datasets: datasets
            },

            plugins: isStatus ? [centerTotalPlugin] : [],

            options: {

                responsive: true,
                maintainAspectRatio: false,

                animation: {
                    duration: 400
                },

                interaction: {
                    intersect: false,
                    mode: 'index'
                },

                plugins: {

                    legend: {

                        display: isStatus || key === 'trend',
                        position: isStatus ? 'right' : 'bottom',

                        labels: {

                            boxWidth: 11,
                            usePointStyle: true,
                            padding: 12,

                            font: {
                                size: large ? 14 : 11
                            },

                            generateLabels(chart) {

                                if (!isStatus) {

                                    return Chart.defaults.plugins.legend.labels
                                        .generateLabels(chart);
                                }

                                const original =
                                    Chart.overrides.doughnut.plugins.legend.labels
                                        .generateLabels(chart);

                                const values = chart.data.datasets[0].data;

                                const total = values.reduce(
                                    (sum, value) => sum + Number(value || 0),
                                    0
                                );

                                return original.map(item => {

                                    const count = Number(
                                        values[item.index] || 0
                                    );

                                    const percentage = total > 0
                                        ? (count / total * 100).toFixed(1)
                                        : '0.0';

                                    return {
                                        ...item,

                                        text:
                                            `${item.text}: ${fmt(count)} (${percentage}%)`
                                    };
                                });
                            }
                        }
                    },

                    tooltip: {

                        callbacks: {

                            label(context) {

                                const parsed = context.parsed;

                                const number = typeof parsed === 'object'
                                    ? (parsed.y ?? parsed.x ?? 0)
                                    : parsed;

                                return context.dataset.label +
                                    ': ' +
                                    (isValue ? 'RM ' : '') +
                                    fmt(number);
                            }
                        }
                    },

                    datalabels: {

                        display: context =>
                            isValue &&
                            Number(
                                context.dataset.data[context.dataIndex]
                            ) > 0,

                        color: 'white',

                        font: {
                            size: large ? 14 : 11,
                            weight: 'bold'
                        },

                        formatter: value => 'RM ' + fmt(value),

                        anchor: 'center',
                        align: 'center',
                        clip: true
                    }
                },

                scales: isStatus ? {} : {

                    x: {

                        grid: {
                            display: false
                        },

                        ticks: {
                            color: '#788779',
                            autoSkip: true,
                            maxRotation: 0,

                            font: {
                                size: large ? 12 : 10
                            }
                        }
                    },

                    y: {

                        beginAtZero: true,

                        grid: {
                            color: '#edf1e9'
                        },

                        ticks: {

                            color: '#788779',
                            precision: 0,

                            callback: value =>
                                isValue
                                    ? 'RM ' + fmt(value)
                                    : fmt(value)
                        }
                    }
                }
            }
        });
    }

    // =====================================
    // INITIALISE CHARTS
    // =====================================

    makeChart(
        document.getElementById('trendChart'),
        'trend'
    );

    makeChart(
        document.getElementById('statusChart'),
        'status'
    );

    makeChart(
        document.getElementById('planValueChart'),
        'planValue'
    );

    // =====================================
    // ENLARGE CHARTS
    // =====================================

    const modalElement = document.getElementById('chartModal');

    const modal = new bootstrap.Modal(modalElement);

    let enlargedChart = null;
    let enlargedKey = null;

    document.querySelectorAll('[data-chart]').forEach(button => {

        button.addEventListener('click', () => {

            enlargedKey = button.dataset.chart;

            document.getElementById('chartModalTitle').textContent =
                button.dataset.title;

            if (enlargedChart) {

                enlargedChart.destroy();
                enlargedChart = null;
            }

            const canvas = document.getElementById('expandedChart');
            const holder = canvas.parentElement;

            const old = holder.querySelector('.plan-bars-large');

            if (old) {
                old.remove();
            }

            if (enlargedKey === 'plans') {

                canvas.style.display = 'none';

                const clone = document.getElementById('popularPlanBars')
                    .cloneNode(true);

                clone.removeAttribute('id');
                clone.classList.add('plan-bars-large');

                holder.appendChild(clone);

            } else {

                canvas.style.display = 'block';
            }

            modal.show();
        });
    });

    modalElement.addEventListener('shown.bs.modal', () => {

        if (enlargedKey && enlargedKey !== 'plans') {

            enlargedChart = makeChart(
                document.getElementById('expandedChart'),
                enlargedKey,
                true
            );
        }
    });

    modalElement.addEventListener('hidden.bs.modal', () => {

        if (enlargedChart) {

            enlargedChart.destroy();
            enlargedChart = null;
        }
    });

})();
</script>

</body>
</html>
