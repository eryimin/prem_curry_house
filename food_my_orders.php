<?php
require_once __DIR__ . '/food_order/bootstrap.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'guest'
) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

function foodPaymentMethod(array $order): string {
    $method = strtolower((string)($order['payment_method'] ?? ''));
    $choice = strtolower((string)($order['online_choice'] ?? ''));

    if ($method === 'counter') return 'Pay at Counter';

    if (in_array($method, ['online_demo', 'online'], true)) {
        return match ($choice) {
            'tng' => "Touch 'n Go eWallet",
            'fpx' => 'FPX Online Banking',
            'card' => 'Credit / Debit Card',
            default => 'Online Payment'
        };
    }

    return 'Not Specified';
}

function foodPaymentStatus(array $order): string {
    return match (strtolower((string)($order['payment_status'] ?? ''))) {
        'paid' => 'Paid',
        'demo_paid' => 'Demo Paid',
        'demo_refunded' => 'Demo Refunded',
        'refunded' => 'Refunded',
        'unpaid' => 'Unpaid',
        'demo_unpaid', 'pending' => 'Pending Payment',
        default => 'Unknown'
    };
}

function foodOrderType(string $type): string {
    return match ($type) {
        'dine_in' => 'Dine In',
        'delivery' => 'Delivery',
        'pickup' => 'Self Pickup',
        default => 'Unknown'
    };
}

// Load customer orders
$stmt = $pdo->prepare("
    SELECT
        order_id,
        created_at,
        subtotal,
        tax_rate,
        tax_amount,
        delivery_fee,
        rounding_adjustment,
        total,
        fulfilment,
        delivery_area,
        address,
        table_number,
        payment_method,
        online_choice,
        payment_status,
        order_status,
        rejection_reason,
        cancel_reason,
        refund_amount,
        cancelled_at
    FROM pch_food_orders
    WHERE user_id = ?
    ORDER BY order_id DESC
    LIMIT 100
");

$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load food items
$itemsByOrder = [];

if ($orders) {
    $ids = array_map('intval', array_column($orders, 'order_id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT
            order_id,
            item_name,
            quantity,
            line_total,
            remark,
            options_json
        FROM pch_food_order_items
        WHERE order_id IN ($placeholders)
        ORDER BY id
    ");

    $stmt->execute($ids);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $itemsByOrder[(int)$item['order_id']][] = $item;
    }
}

$steps = [
    'Pending',
    'Confirmed',
    'Preparing',
    'Ready',
    'Completed'
];

$statusMessages = [
    'Pending' => 'Waiting for the restaurant to accept your order.',
    'Confirmed' => 'Your order has been accepted.',
    'Preparing' => 'The kitchen is preparing your order.',
    'Ready' => 'Your food is ready.',
    'Completed' => 'Your order has been completed.'
];

$cancelReasons = [
    'Ordered by mistake',
    'Wrong food items selected',
    'Wrong delivery address',
    'Changed my mind',
    'Delivery taking too long',
    'Other'
];

$pageTitle = 'My Food Orders | Prem Curry House';
require __DIR__ . '/includes/header.php';
?>

<style>
.pch-history {
    max-width: 1050px;
    margin: 0 auto;
    padding: 38px 18px 70px;
    color: #263727;
}
.pch-history-heading {
    text-align: center;
    margin-bottom: 30px;
}
.pch-history-heading small {
    color: #527b35;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 2px;
}
.pch-history-heading h1 {
    font-size: 31px;
    font-weight: 800;
    margin: 7px 0;
}
.pch-history-heading p {
    font-size: 13px;
    color: #718071;
}
.pch-order-card {
    background: #fff;
    border: 1px solid #e4eade;
    border-radius: 15px;
    padding: 23px;
    margin-bottom: 18px;
    box-shadow: 0 5px 18px rgba(30,55,30,.04);
}
.pch-order-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
}
.pch-order-top h2 {
    font-size: 18px;
    font-weight: 750;
    margin-bottom: 5px;
}
.pch-muted {
    font-size: 12px;
    color: #758170;
}
.pch-order-badge {
    display: inline-block;
    background: #edf3e9;
    color: #527b35;
    border-radius: 25px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 750;
}
.pch-order-badge.Pending {
    background: #fff3d7;
    color: #8c6400;
}
.pch-order-badge.Rejected,
.pch-order-badge.Cancelled {
    background: #fde8e7;
    color: #a1332e;
}
.pch-order-badge.Completed {
    background: #e2f3e5;
    color: #23693a;
}
.pch-order-items {
    background: #f8faf6;
    border-radius: 11px;
    padding: 13px 16px;
    margin: 17px 0;
}
.pch-order-item {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    padding: 9px 0;
    border-bottom: 1px solid #e7ede3;
    font-size: 13px;
}
.pch-order-item:last-child {
    border-bottom: none;
}
.pch-order-item-note {
    color: #7a8576;
    font-size: 11px;
    margin-top: 5px;
}
.pch-order-progress {
    display: flex;
    gap: 5px;
    margin-top: 20px;
}
.pch-order-step {
    flex: 1;
    border-top: 4px solid #e2e9df;
    padding-top: 9px;
    text-align: center;
    color: #929b8e;
    font-size: 11px;
}
.pch-order-step.done {
    border-color: #527b35;
    color: #527b35;
    font-weight: 700;
}
.pch-order-message {
    margin: 13px 0;
    font-size: 12px;
    color: #6e7c69;
}
.pch-rejected-box {
    background: #fff4f2;
    border: 1px solid #f0d0cc;
    border-radius: 10px;
    padding: 15px;
    margin-top: 18px;
    color: #94342e;
    font-size: 13px;
}
.pch-rejected-box strong {
    display: block;
    margin-bottom: 7px;
}
.pch-refund-box {
    background: #edf7ed;
    border: 1px solid #cfe5cf;
    border-radius: 10px;
    padding: 15px;
    margin-top: 12px;
    color: #286a38;
    font-size: 13px;
}
.pch-payment-details {
    border-top: 1px solid #ebefe8;
    padding-top: 16px;
    margin-top: 18px;
}
.pch-payment-details h3 {
    font-size: 15px;
    font-weight: 750;
    margin-bottom: 14px;
}
.pch-payment-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}
.pch-payment-label {
    display: block;
    color: #788474;
    font-size: 11px;
    margin-bottom: 6px;
}
.pch-payment-value {
    font-size: 13px;
    color: #263727;
    font-weight: 700;
}
.pch-payment-status {
    display: inline-block;
    background: #fff3d7;
    color: #8c6400;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}
.pch-payment-status.paid {
    background: #e2f3e5;
    color: #23693a;
}
.pch-order-summary {
    background: #f8faf6;
    border: 1px solid #e4eade;
    border-radius: 11px;
    padding: 17px;
    margin-top: 18px;
}
.pch-order-summary h3 {
    font-size: 15px;
    font-weight: 750;
    color: #263727;
    margin-bottom: 13px;
}
.pch-summary-line {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 110px;
    align-items: center;
    column-gap: 12px;
    padding: 7px 0;
    font-size: 13px;
}

.pch-summary-line > span {
    color: #718071;
    min-width: 0;
}

.pch-money-align {
    display: grid;
    grid-template-columns: 12px 22px 40px;
    align-items: center;
    justify-content: end;
    column-gap: 2px;
    width: 110px;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.pch-money-align span {
    color: #29432d;
    font-weight: 700;
}

.pch-money-sign {
    text-align: right;
}

.pch-money-align span:nth-child(2) {
    text-align: left;
}

.pch-money-align span:last-child {
    text-align: right;
}
.pch-order-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    border-top: 1px solid #ebefe8;
    margin-top: 18px;
    padding-top: 16px;
}
.pch-order-total-label {
    font-size: 13px;
    font-weight: 700;
}
.pch-order-total {
    font-size: 22px;
    font-weight: 800;
    color: #527b35;
    white-space: nowrap;
}
.pch-cancel-btn {
    background: #fff;
    border: 1px solid #c94e46;
    border-radius: 9px;
    padding: 10px 16px;
    color: #b93c35;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}
.pch-cancel-btn:hover {
    background: #fff2f1;
}
.pch-cancel-form {
    display: none;
    background: #fff8f7;
    border: 1px solid #efd4cf;
    border-radius: 11px;
    padding: 17px;
    margin-top: 14px;
}
.pch-cancel-form label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 8px;
}
.pch-cancel-form select,
.pch-cancel-form textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #dce5d8;
    border-radius: 8px;
    margin-bottom: 12px;
}
.pch-cancel-success {
    background: #e9f5e8;
    border: 1px solid #cde5cb;
    color: #286f38;
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 13px;
}
.pch-back-link {
    display: inline-block;
    color: #527b35;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    margin-top: 18px;
}
.pch-back-link:hover {
    text-decoration: underline;
}
@media (max-width: 650px) {
    .pch-history {
        padding: 28px 12px 50px;
    }
    .pch-order-card {
        padding: 16px;
    }
    .pch-history-heading h1 {
        font-size: 27px;
    }
    .pch-payment-grid {
        grid-template-columns: 1fr;
    }
    .pch-order-step {
        font-size: 9px;
    }
    .pch-summary-line {
        grid-template-columns: minmax(0,1fr) 115px;
        column-gap: 8px;
    }
}

</style>

<main class="pch-history">
    <div class="pch-history-heading">
        <small>MY ACCOUNT</small>
        <h1>My Food Orders</h1>
        <p>View your food orders and track their status.</p>
    </div>

    <?php if (isset($_GET['cancelled'])): ?>
        <div class="pch-cancel-success">
            <i class="bi bi-check-circle me-1"></i>
            Your order has been cancelled successfully.
            If a simulated payment was completed, a simulated full refund has been recorded.
        </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <div class="pch-order-card">
            <p>You have no food orders yet.</p>
            <a href="menu.php" class="pch-back-link">Browse Our Menu</a>
        </div>
    <?php endif; ?>

    <?php foreach ($orders as $order): ?>
        <?php
        $id = (int)$order['order_id'];
        $status = (string)$order['order_status'];
        $currentStep = array_search($status, $steps, true);
        $paymentMethod = foodPaymentMethod($order);
        $paymentStatus = foodPaymentStatus($order);
        $rounding = (float)$order['rounding_adjustment'];

        $canCancel = in_array($status, ['Pending', 'Confirmed'], true)
            && in_array(
                $order['payment_status'],
                ['unpaid', 'demo_unpaid', 'demo_paid'],
                true
            );
        ?>

        <article class="pch-order-card">
            <div class="pch-order-top">
                <div>
                    <h2>Order #<?= $id ?></h2>
                    <div class="pch-muted">
                        <?= pch_h($order['created_at']) ?> ·
                        <?= pch_h(foodOrderType($order['fulfilment'])) ?>
                    </div>
                </div>

                <span class="pch-order-badge <?= pch_h($status) ?>">
                    <?= pch_h($status) ?>
                </span>
            </div>

            <div class="pch-order-items">
                <?php foreach ($itemsByOrder[$id] ?? [] as $item): ?>
                    <div class="pch-order-item">
                        <div>
                            <strong><?= pch_h($item['item_name']) ?></strong>
                            × <?= (int)$item['quantity'] ?>

                            <?php
                            $options = json_decode(
                                (string)$item['options_json'],
                                true
                            );
                            ?>

                            <?php if (is_array($options)): ?>
                                <?php foreach ($options as $group => $choice): ?>
                                    <div class="pch-order-item-note">
                                        <?= pch_h($group . ': ' . $choice) ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if (trim((string)$item['remark']) !== ''): ?>
                                <div class="pch-order-item-note">
                                    Note: <?= pch_h($item['remark']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <strong><?= pch_money($item['line_total']) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($status === 'Rejected'): ?>
                <div class="pch-rejected-box">
                    <strong>
                        <i class="bi bi-x-circle me-1"></i>
                        Order Rejected
                    </strong>
                    Reason:
                    <?= pch_h(
                        $order['rejection_reason']
                        ?: 'Please contact the restaurant for details.'
                    ) ?>
                </div>

                <?php if ($order['payment_status'] === 'demo_refunded'): ?>
                    <div class="pch-refund-box">
                        <strong>Refund Successful (Simulation)</strong>
                        <p class="mb-1 mt-2">
                            Refund Amount:
                            <strong><?= pch_money($order['refund_amount']) ?></strong>
                        </p>
                        <small>No actual money was transferred.</small>
                    </div>
                <?php endif; ?>

            <?php elseif ($status === 'Cancelled'): ?>
                <div class="pch-rejected-box">
                    <strong>
                        <i class="bi bi-x-circle me-1"></i>
                        Order Cancelled
                    </strong>
                    Reason:
                    <?= pch_h($order['cancel_reason'] ?: 'Not specified') ?>
                </div>

                <?php if ($order['payment_status'] === 'demo_refunded'): ?>
                    <div class="pch-refund-box">
                        <strong>
                            <i class="bi bi-arrow-counterclockwise me-1"></i>
                            Refund Successful (Simulation)
                        </strong>
                        <p class="mb-1 mt-2">
                            Refund Amount:
                            <strong><?= pch_money($order['refund_amount']) ?></strong>
                        </p>
                        <small>No actual money was transferred.</small>
                    </div>
                <?php else: ?>
                    <div class="pch-refund-box">
                        No refund was needed because no payment was collected.
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="pch-order-progress">
                    <?php foreach ($steps as $i => $step): ?>
                        <div class="pch-order-step <?= (
                            $currentStep !== false &&
                            $i <= $currentStep
                        ) ? 'done' : '' ?>">
                            <?= pch_h($step) ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="pch-order-message">
                    <?= pch_h(
                        $statusMessages[$status] ??
                        'Your order status has been updated.'
                    ) ?>
                </div>
            <?php endif; ?>

            <div class="pch-payment-details">
                <h3>Payment Details</h3>

                <div class="pch-payment-grid">
                    <div>
                        <span class="pch-payment-label">Payment Method</span>
                        <div class="pch-payment-value">
                            <i class="bi bi-wallet2 me-1" style="color:#527b35"></i>
                            <?= pch_h($paymentMethod) ?>
                        </div>
                    </div>

                    <div>
                        <span class="pch-payment-label">Payment Status</span>
                        <span class="pch-payment-status <?= in_array(
                            $order['payment_status'],
                            ['paid', 'demo_paid', 'refunded', 'demo_refunded'],
                            true
                        ) ? 'paid' : '' ?>">
                            <?= pch_h($paymentStatus) ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="pch-order-summary">
                <h3>Order Summary</h3>

                <div class="pch-summary-line">
                    <span>Subtotal</span>
                    <strong class="pch-money-align">
                        <span class="pch-money-sign"></span>
                        <span>RM</span>
                        <span><?= number_format((float)$order['subtotal'], 2) ?></span>
                    </strong>
                </div>

                <div class="pch-summary-line">
                    <span>
                        SST (<?= number_format(
                            (float)$order['tax_rate'] * 100,
                            0
                        ) ?>%)
                    </span>
                    <strong class="pch-money-align">
                        <span class="pch-money-sign"></span>
                        <span>RM</span>
                        <span><?= number_format((float)$order['tax_amount'], 2) ?></span>
                    </strong>
                </div>

                <div class="pch-summary-line">
                    <span>Delivery Fee</span>
                    <strong class="pch-money-align">
                        <span class="pch-money-sign"></span>
                        <span>RM</span>
                        <span><?= number_format((float)$order['delivery_fee'], 2) ?></span>
                    </strong>
                </div>

                <?php if ($order['fulfilment'] === 'dine_in'): ?>
                    <div class="pch-summary-line">
                        <span>Rounding Adjustment</span>
                        <strong class="pch-money-align">
                            <span class="pch-money-sign"><?= $rounding > 0 ? '+' : ($rounding < 0 ? '-' : '') ?></span>
                            <span>RM</span>
                            <span><?= number_format(abs($rounding), 2) ?></span>
                        </strong>
                    </div>
                <?php endif; ?>
            </div>

            <div class="pch-order-bottom">
                <span class="pch-order-total-label">Order Total</span>
                <strong class="pch-order-total">
                    <?= pch_money($order['total']) ?>
                </strong>
            </div>

            <?php if ($canCancel): ?>
                <div class="mt-3">
                    <button
                        type="button"
                        class="pch-cancel-btn"
                        onclick="toggleCancelForm(<?= $id ?>)"
                    >
                        <i class="bi bi-x-circle me-1"></i>
                        Cancel Order
                    </button>
                </div>

                <form
                    method="POST"
                    action="food_cancel_order.php"
                    class="pch-cancel-form"
                    id="cancel-form-<?= $id ?>"
                >
                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= pch_h(pch_csrf()) ?>"
                    >
                    <input
                        type="hidden"
                        name="order_id"
                        value="<?= $id ?>"
                    >

                    <label for="cancel-reason-<?= $id ?>">
                        Why do you want to cancel this order?
                    </label>

                    <select
                        name="cancel_reason"
                        id="cancel-reason-<?= $id ?>"
                        required
                        onchange="updateCancelReason(<?= $id ?>)"
                    >
                        <option value="">Select a reason</option>

                        <?php foreach ($cancelReasons as $reason): ?>
                            <option value="<?= pch_h($reason) ?>">
                                <?= pch_h($reason) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div
                        id="cancel-other-box-<?= $id ?>"
                        style="display:none"
                    >
                        <label for="cancel-other-<?= $id ?>">
                            Please explain your reason
                        </label>
                        <textarea
                            id="cancel-other-<?= $id ?>"
                            name="other_reason"
                            maxlength="500"
                            rows="3"
                            placeholder="Enter your cancellation reason..."
                        ></textarea>
                    </div>

                    <p class="pch-muted">
                        Cancellation is available before food preparation starts.
                        Completed simulated payments receive a simulated full refund.
                    </p>

                    <button
                        type="submit"
                        class="pch-cancel-btn"
                        onclick="return confirm('Are you sure you want to cancel this order?')"
                    >
                        Confirm Cancellation
                    </button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <a href="my_orders.php" class="pch-back-link">
        <i class="bi bi-arrow-left me-1"></i>
        Back to My Orders
    </a>
</main>

<script>
function toggleCancelForm(id) {
    const form = document.getElementById('cancel-form-' + id);
    form.style.display = form.style.display === 'block' ? 'none' : 'block';
}

function updateCancelReason(id) {
    const select = document.getElementById('cancel-reason-' + id);
    const box = document.getElementById('cancel-other-box-' + id);
    const textarea = document.getElementById('cancel-other-' + id);
    const isOther = select.value === 'Other';

    box.style.display = isOther ? 'block' : 'none';
    textarea.required = isOther;

    if (!isOther) {
        textarea.value = '';
    }
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>