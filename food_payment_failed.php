
<?php
// ============================================
// PREM CURRY HOUSE - DEMO PAYMENT FAILED
// ============================================

require_once __DIR__ . '/food_order/bootstrap.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$orderId = (int)(
    $_SESSION['pch_last_food_order'] ?? 0
);

if ($userId < 1 || $orderId < 1) {
    header('Location: menu.php');
    exit;
}

// Confirm the order belongs to the customer
$stmt = $pdo->prepare("
    SELECT
        order_id,
        payment_status,
        payment_method,
        total
    FROM pch_food_orders
    WHERE order_id = ?
      AND user_id = ?
    LIMIT 1
");

$stmt->execute([
    $orderId,
    $userId
]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(403);
    exit('Order not found or access denied.');
}

if (
    $order['payment_status'] === 'demo_paid'
) {
    header('Location: food_order_success.php');
    exit;
}

if (
    $order['payment_status'] !== 'demo_unpaid' ||
    $order['payment_method'] !== 'online_demo' ||
    empty($_SESSION['pch_demo_payment_failed'])
) {
    header('Location: food_order_success.php');
    exit;
}

unset($_SESSION['pch_demo_payment_failed']);

$pageTitle = 'Demo Payment Failed | Prem Curry House';

require __DIR__ . '/includes/header.php';
?>

<link
    rel="stylesheet"
    href="assets/css/food_order.css"
>

<main
    class="container pch-order-page"
    style="max-width:650px"
>

    <div
        class="pch-panel text-center"
        style="padding:40px 25px"
    >

        <i
            class="bi bi-x-circle-fill"
            style="font-size:55px;color:#dc3545"
        ></i>

        <h1 class="mt-3">
            Demo Payment Failed
        </h1>

        <p>
            Your simulated payment was not successful.
        </p>

        <p>
            Order #<?= (int)$order['order_id'] ?>
        </p>

        <h2 style="color:#527b35">
            <?= pch_money($order['total']) ?>
        </h2>

        <div class="alert alert-warning mt-4">

            Your food order has been saved,
            but the simulated payment is incomplete.

            No real money was transferred.

        </div>

        <a
            href="food_demo_payment.php"
            class="pch-primary d-inline-block mt-3"
        >
            Try Demo Payment Again
        </a>

        <div class="mt-4">

            <a href="food_order_success.php">
                View Order Summary
            </a>

        </div>

    </div>

</main>

<?php
require __DIR__ . '/includes/footer.php';
?>
