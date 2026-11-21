
<?php
// Prem Curry House - Export Tiffin Orders Report

session_start();

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true)
) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Prevent Excel from treating user text as formulas
function safeCsv($value): string {
    $value = (string)($value ?? '');

    if (preg_match('/^\s*[=+\-@]/u', $value)) {
        return "'" . $value;
    }

    return $value;
}

function validDate(string $value): ?DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value,
        new DateTimeZone('Asia/Kuala_Lumpur')
    );

    return $date && $date->format('Y-m-d') === $value
        ? $date
        : null;
}

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

    $from = validDate((string)($_GET['from'] ?? ''));
    $to = validDate((string)($_GET['to'] ?? ''));

    if (
        !$from ||
        !$to ||
        $from > $to ||
        $from->diff($to)->days > 3660
    ) {
        http_response_code(400);
        exit('Invalid date range.');
    }

    $start = $from;
    $end = $to->modify('+1 day');
}

try {

    $sql = "
        SELECT
            o.order_id,
            o.customer_name,
            p.plan_name,
            o.start_date,
            o.status,
            o.created_at,
            p.price,
            o.special_requests

        FROM tiffin_orders o

        JOIN tiffin_plans p
            ON p.plan_id = o.plan_id
    ";

    $params = [];

    if ($start !== null && $end !== null) {

        $sql .= "
            WHERE o.created_at >= ?
              AND o.created_at < ?
        ";

        $params = [
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        ];
    }

    $sql .= "
        ORDER BY o.created_at DESC, o.order_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $filename = 'Prem_Curry_House_Tiffin_Report_'
        . $period . '_'
        . date('Ymd_His')
        . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header(
        'Content-Disposition: attachment; filename="' .
        $filename . '"'
    );

    header('Cache-Control: no-store, no-cache');
    header('Pragma: no-cache');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM helps Excel read text correctly
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'Order ID',
        'Customer Name',
        'Tiffin Plan',
        'Start Date',
        'Status',
        'Order Created',
        'Plan Price (RM)',
        'Estimated Value (RM)',
        'Special Requests'
    ]);

    while ($order = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $price = (float)$order['price'];

        $estimatedValue = in_array(
            $order['status'],
            ['Confirmed', 'Completed'],
            true
        ) ? $price : 0;

        fputcsv($output, [
            $order['order_id'],
            safeCsv($order['customer_name']),
            safeCsv($order['plan_name']),
            $order['start_date'],
            $order['status'],
            $order['created_at'],
            number_format($price, 2, '.', ''),
            number_format($estimatedValue, 2, '.', ''),
            safeCsv($order['special_requests'])
        ]);
    }

    fclose($output);
    exit;

} catch (Throwable $ex) {

    error_log(
        'Tiffin export error: ' . $ex->getMessage()
    );

    http_response_code(500);
    exit('Unable to export the report.');
}
