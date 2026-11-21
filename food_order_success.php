<?php

require_once __DIR__ . '/food_order/bootstrap.php';

// ============================================
// GET LATEST ORDER
// ============================================

$id = (int)($_SESSION['pch_last_food_order'] ?? 0);

if ($id < 1) {
    header('Location: menu.php');
    exit;
}

// ============================================
// GET ORDER INFORMATION
// ============================================

$stmt = $pdo->prepare("
    SELECT
        order_id,
        user_id,
        customer_name,
        phone,
        fulfilment,
        address,
        delivery_area,
        table_number,
        payment_method,
        online_choice,
        bank,
        payment_status,
        order_status,
        subtotal,
        tax_rate,
        tax_amount,
        delivery_fee,
        rounding_adjustment,
        total,
        created_at
    FROM pch_food_orders
    WHERE order_id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: menu.php');
    exit;
}

// ============================================
// VERIFY ORDER OWNERSHIP
// ============================================

$sessionUserId = (int)($_SESSION['user_id'] ?? 0);
$orderUserId = (int)($order['user_id'] ?? 0);

if (
    !$sessionUserId ||
    !$orderUserId ||
    $sessionUserId !== $orderUserId
) {
    http_response_code(403);
    exit('You are not authorized to view this order.');
}

// ============================================
// ORDER DETAILS
// ============================================

$isDelivery = $order['fulfilment'] === 'delivery';

$orderTypeLabel = $isDelivery
    ? 'Delivery'
    : 'Dine In';

// ============================================
// REQUIRE PAYMENT FOR DELIVERY
// ============================================

// Support old and new unpaid payment statuses.

$unpaidDeliveryStatuses = [
    'demo_unpaid',
    'pending'
];

if (
    $isDelivery &&
    in_array(
        $order['payment_status'],
        $unpaidDeliveryStatuses,
        true
    ) &&
    !in_array(
        $order['order_status'],
        ['Rejected', 'Cancelled'],
        true
    )
) {
    header('Location: food_demo_payment.php');
    exit;
}

// ============================================
// DELIVERY AREA
// ============================================

$deliveryArea = (string)($order['delivery_area'] ?? '');

$deliveryAreaName = pch_delivery_area_name(
    $deliveryArea
);

if ($deliveryAreaName === '') {
    $deliveryAreaName = $deliveryArea !== ''
        ? $deliveryArea
        : 'Not specified';
}

// ============================================
// PAYMENT METHOD
// ============================================

$paymentLabel = 'Pay at Counter';

if (in_array(
    $order['payment_method'],
    ['online_demo', 'online'],
    true
)) {
    $paymentLabel = match ($order['online_choice']) {
        'tng' => "Touch 'n Go eWallet",
        'fpx' => 'FPX Online Banking',
        'card' => 'Credit / Debit Card',
        default => 'Online Payment'
    };
}

// ============================================
// BANK NAMES
// ============================================

$bankNames = [
    'maybank' => 'Maybank',
    'cimb' => 'CIMB Bank',
    'public_bank' => 'Public Bank',
    'rhb' => 'RHB Bank',
    'hong_leong' => 'Hong Leong Bank',
    'bank_islam' => 'Bank Islam'
];

$bankLabel = '';

if (
    $order['online_choice'] === 'fpx' &&
    !empty($order['bank'])
) {
    $bankLabel = $bankNames[
        $order['bank']
    ] ?? $order['bank'];
}

// ============================================
// PAYMENT STATUS
// ============================================

$paymentStatus = match ($order['payment_status']) {
    'paid', 'demo_paid' => 'Paid',
    'demo_refunded', 'refunded' => 'Refunded',
    'pending', 'demo_unpaid' => 'Payment Pending',
    'unpaid' => 'Unpaid',
    default => 'Unknown'
};

$orderStatus = ucfirst(
    (string)$order['order_status']
);

$roundingAdjustment = (float)(
    $order['rounding_adjustment']
);

$pageTitle = 'Order Received | Prem Curry House';

require __DIR__ . '/includes/header.php';

?>

<link rel="stylesheet" href="assets/css/food_order.css">

<style>

/* ============================================
   SUCCESS PAGE
   ============================================ */

.pch-success-container {
    max-width: 720px;
    margin: 0 auto;
}

.pch-success-card {
    background: #ffffff;
    border: 1px solid #e4ebde;
    border-radius: 18px;
    padding: 32px;
    box-shadow: 0 8px 28px rgba(0,0,0,.06);
}

/* HEADER */

.pch-success-header {
    text-align: center;
    margin-bottom: 28px;
}

.pch-success-header i {
    font-size: 55px;
    color: #527b35;
}

.pch-success-header h1 {
    margin-top: 12px;
    color: #29432d;
    font-weight: 800;
    font-size: 29px;
}

.pch-success-header p {
    font-size: 14px;
    color: #718071;
}

.pch-success-order-number {
    display: inline-block;
    margin-top: 8px;
    padding: 8px 18px;
    background: #eaf0de;
    border-radius: 25px;
    color: #527b35;
    font-weight: 700;
}

/* SECTIONS */

.pch-success-section {
    margin-top: 24px;
}

.pch-success-section h2 {
    font-size: 18px;
    font-weight: 750;
    color: #29432d;
    margin-bottom: 14px;
}

.pch-success-details {
    background: #f7faf4;
    padding: 18px;
    border-radius: 11px;
}

/* INFORMATION ROWS */

.pch-success-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    padding: 10px 0;
    border-bottom: 1px solid #e4ebde;
    font-size: 14px;
}

.pch-success-row:last-child {
    border-bottom: none;
}

.pch-success-row > span {
    color: #718071;
}

.pch-success-row > strong {
    color: #29432d;
    text-align: right;
    overflow-wrap: anywhere;
}

/* DELIVERY ADDRESS */

.pch-success-address {
    margin-top: 12px;
    padding: 14px;
    background: #ffffff;
    border: 1px solid #e4ebde;
    border-radius: 9px;
}

.pch-success-address strong {
    display: block;
    margin-bottom: 8px;
    color: #29432d;
}

.pch-success-address p {
    margin: 0;
    font-size: 14px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

/* TOTAL */

.pch-success-total {
    margin-top: 18px;
    padding: 17px;
    background: #eaf0de;
    border-radius: 11px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.pch-success-total > span {
    font-size: 16px;
    font-weight: 700;
}

.pch-success-total > strong {
    font-size: 25px;
    color: #527b35;
    white-space: nowrap;
}

/* ORDER MESSAGE */

.pch-success-note {
    margin-top: 22px;
    padding: 15px;
    background: #fff8e5;
    border-left: 4px solid #e5ae36;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.7;
}

/* BUTTON */

.pch-success-actions {
    margin-top: 25px;
    text-align: center;
}

.pch-success-actions a {
    display: inline-block;
    text-decoration: none;
}

/* ============================================
   MONEY ALIGNMENT
   ============================================ */

.pch-success-row .pch-money-align {
    display: grid;
    grid-template-columns: 12px 24px 48px;
    align-items: center;
    justify-content: end;
    column-gap: 2px;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.pch-success-row .pch-money-align span {
    color: #29432d;
    font-weight: 700;
}

.pch-success-row .pch-money-align span:last-child {
    text-align: right;
}

.pch-success-row .pch-money-sign {
    text-align: right;
}

/* RESPONSIVE */

@media (max-width: 576px) {

    .pch-success-card {
        padding: 20px;
    }

    .pch-success-header h1 {
        font-size: 24px;
    }

    .pch-success-row {
        gap: 12px;
    }
}

</style>

<!-- ============================================
     ORDER SUCCESS PAGE
     ============================================ -->

<main class="container pch-order-page">

    <div class="pch-success-container">

        <div class="pch-success-card">

            <!-- HEADER -->

            <div class="pch-success-header">

                <i class="bi bi-check-circle-fill"></i>

                <h1>Order Placed Successfully!</h1>

                <p>
                    Thank you for ordering from Prem Curry House.
                </p>

                <div class="pch-success-order-number">
                    Order #<?= (int)$order['order_id'] ?>
                </div>

            </div>

            <!-- ====================================
                 CUSTOMER INFORMATION
                 ==================================== -->

            <div class="pch-success-section">

                <h2>Customer Information</h2>

                <div class="pch-success-details">

                    <div class="pch-success-row">

                        <span>Customer Name</span>

                        <strong>
                            <?= pch_h($order['customer_name']) ?>
                        </strong>

                    </div>

                    <div class="pch-success-row">

                        <span>Contact Number</span>

                        <strong>
                            <?= pch_h($order['phone'] ?: '—') ?>
                        </strong>

                    </div>

                    <div class="pch-success-row">

                        <span>Order Type</span>

                        <strong>
                            <?= pch_h($orderTypeLabel) ?>
                        </strong>

                    </div>

                    <?php if (!$isDelivery): ?>

                        <div class="pch-success-row">

                            <span>Table Number</span>

                            <strong>
                                Table <?= pch_h(
                                    $order['table_number'] ?? '—'
                                ) ?>
                            </strong>

                        </div>

                    <?php else: ?>

                        <div class="pch-success-row">

                            <span>Delivery Area</span>

                            <strong>
                                <?= pch_h($deliveryAreaName) ?>
                            </strong>

                        </div>

                    <?php endif; ?>

                </div>

                <?php if ($isDelivery): ?>

                    <div class="pch-success-address">

                        <strong>
                            <i class="bi bi-geo-alt"></i>
                            Delivery Address
                        </strong>

                        <p>
                            <?= nl2br(
                                pch_h($order['address'] ?? '')
                            ) ?>
                        </p>

                    </div>

                <?php endif; ?>

            </div>

            <!-- ====================================
                 ORDER STATUS
                 ==================================== -->

            <div class="pch-success-section">

                <h2>Order Status</h2>

                <div class="pch-success-details">

                    <div class="pch-success-row">

                        <span>Order Status</span>

                        <strong>
                            <?= pch_h($orderStatus) ?>
                        </strong>

                    </div>

                    <div class="pch-success-row">

                        <span>Payment Status</span>

                        <strong>
                            <?= pch_h($paymentStatus) ?>
                        </strong>

                    </div>

                </div>

            </div>

            <!-- ====================================
                 PAYMENT INFORMATION
                 ==================================== -->

            <div class="pch-success-section">

                <h2>Payment Information</h2>

                <div class="pch-success-details">

                    <div class="pch-success-row">

                        <span>Payment Method</span>

                        <strong>
                            <?= pch_h($paymentLabel) ?>
                        </strong>

                    </div>

                    <?php if ($bankLabel !== ''): ?>

                        <div class="pch-success-row">

                            <span>Selected Bank</span>

                            <strong>
                                <?= pch_h($bankLabel) ?>
                            </strong>

                        </div>

                    <?php endif; ?>

                    <!-- SUBTOTAL -->

                    <div class="pch-success-row">

                        <span>Subtotal</span>

                        <strong class="pch-money-align">
                            <span class="pch-money-sign"></span>
                            <span>RM</span>
                            <span>
                                <?= number_format(
                                    (float)$order['subtotal'],
                                    2
                                ) ?>
                            </span>
                        </strong>

                    </div>

                    <!-- SERVICE TAX -->

                    <div class="pch-success-row">

                        <span>
                            Service Tax
                            (<?= number_format(
                                (float)$order['tax_rate'] * 100,
                                0
                            ) ?>%)
                        </span>

                        <strong class="pch-money-align">
                            <span class="pch-money-sign"></span>
                            <span>RM</span>
                            <span>
                                <?= number_format(
                                    (float)$order['tax_amount'],
                                    2
                                ) ?>
                            </span>
                        </strong>

                    </div>

                    <!-- DELIVERY FEE -->

                    <div class="pch-success-row">

                        <span>Delivery Fee</span>

                        <strong class="pch-money-align">
                            <span class="pch-money-sign"></span>
                            <span>RM</span>
                            <span>
                                <?= number_format(
                                    (float)$order['delivery_fee'],
                                    2
                                ) ?>
                            </span>
                        </strong>

                    </div>

                    <!-- ROUNDING ADJUSTMENT -->

                    <?php if (!$isDelivery): ?>

                        <div class="pch-success-row">

                            <span>Rounding Adjustment</span>

                            <strong class="pch-money-align">

                                <span class="pch-money-sign">
                                    <?= $roundingAdjustment > 0
                                        ? '+'
                                        : (
                                            $roundingAdjustment < 0
                                                ? '-'
                                                : ''
                                        )
                                    ?>
                                </span>

                                <span>RM</span>

                                <span>
                                    <?= number_format(
                                        abs($roundingAdjustment),
                                        2
                                    ) ?>
                                </span>

                            </strong>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="pch-success-total">

                    <span>Total Amount</span>

                    <strong>
                        <?= pch_money($order['total']) ?>
                    </strong>

                </div>

            </div>

            <!-- ====================================
                 ORDER MESSAGE
                 ==================================== -->

            <div class="pch-success-note">

                <?php if (!$isDelivery): ?>

                    <strong>
                        <i class="bi bi-info-circle"></i>
                        Dine In Order
                    </strong>

                    <p class="mb-0 mt-2">
                        Your order has been received.
                        Please proceed to the restaurant counter
                        to complete your payment.
                    </p>

                    <p class="mb-0 mt-2">
                        The final amount has been rounded
                        to the nearest 5 sen.
                    </p>

                <?php elseif (in_array(
                    $order['payment_status'],
                    ['demo_paid', 'paid'],
                    true
                )): ?>

                    <strong>
                        <i class="bi bi-check-circle-fill"></i>
                        Payment Successful!
                    </strong>

                    <p class="mb-0 mt-2">
                        Your payment using
                        <?= pch_h($paymentLabel) ?>
                        has been confirmed.
                    </p>

                    <p class="mb-0 mt-2">
                        Your order is now waiting for
                        restaurant confirmation.
                    </p>

                    <p class="mb-0 mt-2 text-muted">
                        Test transaction — no real money was transferred.
                    </p>

                <?php elseif (in_array(
                    $order['payment_status'],
                    ['demo_refunded', 'refunded'],
                    true
                )): ?>

                    <strong>
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Payment Refunded
                    </strong>

                    <p class="mb-0 mt-2">
                        A refund has been recorded for this order.
                    </p>

                <?php else: ?>

                    <strong>
                        <i class="bi bi-clock"></i>
                        Payment Pending
                    </strong>

                    <p class="mb-0 mt-2">
                        Your order has been received,
                        but payment has not been completed.
                    </p>

                    <?php if (!in_array(
                        $order['order_status'],
                        ['Rejected', 'Cancelled', 'Completed'],
                        true
                    )): ?>

                        <a
                            href="food_demo_payment.php"
                            class="pch-primary mt-3"
                        >
                            Complete Payment
                        </a>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

            <!-- ====================================
                 ACTION BUTTONS
                 ==================================== -->

            <div class="pch-success-actions">

                <a href="menu.php" class="pch-primary">

                    <i class="bi bi-arrow-left"></i>
                    Back to Menu

                </a>

            </div>

        </div>

    </div>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>