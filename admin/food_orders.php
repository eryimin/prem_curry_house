<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Administrator access required.');
}

require_once __DIR__ . '/../food_order/bootstrap.php';

// Allowed order status changes
$nextStatus = [
    'Pending' => ['Confirmed', 'Rejected'],
    'Confirmed' => ['Preparing'],
    'Preparing' => ['Ready'],
    'Ready' => ['Completed']
];

$reasonChoices = [
    'Food item unavailable',
    'Insufficient ingredients',
    'Restaurant is too busy',
    'Restaurant is closing / closed',
    'Payment issue',
    'Delivery location unavailable'
];

function adminPaymentLabel(array $order): string {
    $method = strtolower((string)($order['payment_method'] ?? ''));
    $choice = strtolower((string)($order['online_choice'] ?? ''));

    if ($method === 'counter') {
        return 'Pay at Counter';
    }

    if ($method === 'online_demo' || $method === 'online') {
        return match ($choice) {
            'tng' => "Touch 'n Go eWallet",
            'fpx' => 'FPX Online Banking',
            'card' => 'Credit / Debit Card',
            default => 'Online Payment'
        };
    }

    return 'Payment Method Not Specified';
}

function adminBankLabel(array $order): string {
    $banks = [
        'maybank' => 'Maybank',
        'cimb' => 'CIMB Bank',
        'public_bank' => 'Public Bank',
        'rhb' => 'RHB Bank',
        'hong_leong' => 'Hong Leong Bank',
        'bank_islam' => 'Bank Islam'
    ];

    $bank = (string)($order['bank'] ?? '');
    return $banks[$bank] ?? $bank;
}

function adminPaymentStatus(array $order): string {
    return match (strtolower((string)($order['payment_status'] ?? ''))) {
        'paid' => 'Paid',
        'demo_paid' => 'Demo Paid',
        'unpaid' => 'Unpaid',
        'demo_unpaid' => 'Demo Payment Pending',
        default => 'Payment Status Unknown'
    };
}

function adminOrderType(string $type): string {
    return match ($type) {
        'dine_in' => 'Dine In',
        'delivery' => 'Delivery',
        'pickup' => 'Self Pickup',
        default => 'Unknown'
    };
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pch_verify();

    $id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $target = (string)($_POST['status'] ?? '');

    if (!$id || $id < 1) {
        $error = 'Invalid order ID.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT
                    order_status,
                    payment_status,
                    total
                FROM pch_food_orders
                WHERE order_id = ?
                FOR UPDATE
            ");

            $stmt->execute([$id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new RuntimeException('Order not found.');
            }

            $current = $order['order_status'];

            if (!in_array($target, $nextStatus[$current] ?? [], true)) {
                throw new RuntimeException(
                    'This order cannot be updated to the selected status.'
                );
            }

            $reason = null;
            $paymentStatus = $order['payment_status'];
            $refundAmount = 0.00;

            if ($target === 'Rejected') {
                $selected = trim((string)($_POST['reason'] ?? ''));

                if ($selected === 'Other') {
                    $reason = trim((string)($_POST['other_reason'] ?? ''));
                } elseif (in_array($selected, $reasonChoices, true)) {
                    $reason = $selected;
                }

                if (
                    $reason === null ||
                    $reason === '' ||
                    mb_strlen($reason, 'UTF-8') > 500
                ) {
                    throw new RuntimeException(
                        'Please provide a valid rejection reason.'
                    );
                }

                // Simulated payment: record full simulated refund
                if ($paymentStatus === 'demo_paid') {
                    $paymentStatus = 'demo_refunded';
                    $refundAmount = (float)$order['total'];
                }

                // No payment collected
                elseif (in_array(
                    $paymentStatus,
                    ['unpaid', 'demo_unpaid'],
                    true
                )) {
                    $refundAmount = 0.00;
                }

                // Real payments require separate refund processing
                else {
                    throw new RuntimeException(
                        'This payment requires manual refund review. ' .
                        'Please resolve the payment before rejecting.'
                    );
                }
            }

            if ($target === 'Rejected') {
                $update = $pdo->prepare("
                    UPDATE pch_food_orders
                    SET
                        order_status = ?,
                        rejection_reason = ?,
                        payment_status = ?,
                        refund_amount = ?
                    WHERE order_id = ?
                ");

                $update->execute([
                    $target,
                    $reason,
                    $paymentStatus,
                    $refundAmount,
                    $id
                ]);
            } else {
                $update = $pdo->prepare("
                    UPDATE pch_food_orders
                    SET order_status = ?
                    WHERE order_id = ?
                ");

                $update->execute([$target, $id]);
            }

            $pdo->commit();

            header('Location: food_orders.php?updated=1');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof RuntimeException) {
                $error = $e->getMessage();
            } else {
                error_log('Admin food orders: ' . $e->getMessage());
                $error = 'Unable to update order.';
            }
        }
    }
}

// Load food orders
$orders = $pdo->query("
    SELECT
        order_id,
        created_at,
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
        subtotal,
        tax_rate,
        tax_amount,
        delivery_fee,
        total,
        order_status,
        rejection_reason
    FROM pch_food_orders
    ORDER BY order_id DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

// Load ordered food items
$itemsByOrder = [];

if (!empty($orders)) {
    $ids = array_map('intval', array_column($orders, 'order_id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT
            order_id,
            item_name,
            quantity,
            line_total,
            options_json,
            remark
        FROM pch_food_order_items
        WHERE order_id IN ($placeholders)
        ORDER BY id
    ");

    $stmt->execute($ids);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $itemsByOrder[(int)$item['order_id']][] = $item;
    }
}

$pageTitle = 'Manage Food Orders | Prem Curry House';
require __DIR__ . '/../includes/header.php';
?>

<style>
.pfo-page {
    max-width: 1180px;
    margin: 0 auto;
    padding: 35px 20px 70px;
    color: #263727;
}
.pfo-heading {
    margin-bottom: 28px;
}
.pfo-heading small {
    color: #527b35;
    letter-spacing: 1.5px;
    font-weight: 700;
}
.pfo-heading h1 {
    font-size: 30px;
    font-weight: 800;
    margin: 7px 0;
}
.pfo-heading p {
    color: #718071;
    font-size: 13px;
}
.pfo-grid {
    display: grid;
    gap: 18px;
}
.pfo-card {
    background: #fff;
    border: 1px solid #e4eadf;
    border-radius: 15px;
    padding: 22px;
    box-shadow: 0 5px 18px rgba(30,55,30,.04);
}
.pfo-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.pfo-top h2 {
    font-size: 17px;
    font-weight: 750;
    margin: 0 0 6px;
}
.pfo-muted {
    color: #718071;
    font-size: 12px;
}
.pfo-badge {
    background: #edf3e9;
    color: #527b35;
    padding: 6px 12px;
    border-radius: 25px;
    font-weight: 700;
    font-size: 12px;
}
.pfo-badge.Pending {
    background: #fff3d7;
    color: #8c6400;
}
.pfo-badge.Rejected {
    background: #fde8e7;
    color: #a1332e;
}
.pfo-badge.Completed {
    background: #e2f3e5;
    color: #23693a;
}
.pfo-info {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 22px;
    margin: 17px 0;
    color: #62705f;
    font-size: 13px;
}
.pfo-info strong {
    color: #263727;
}
.pfo-delivery-box {
    background: #f1f7ec;
    border: 1px solid #d8e8cf;
    border-radius: 11px;
    padding: 16px;
    margin-bottom: 18px;
}
.pfo-delivery-box h3 {
    color: #527b35;
    font-size: 15px;
    font-weight: 800;
    margin-bottom: 12px;
}
.pfo-delivery-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 20px;
    margin-bottom: 9px;
    font-size: 13px;
}
.pfo-delivery-row:last-child {
    margin-bottom: 0;
}
.pfo-delivery-row strong {
    min-width: 115px;
    color: #29432d;
}
.pfo-delivery-address {
    white-space: pre-line;
    overflow-wrap: anywhere;
}
.pfo-payment-summary {
    background: #fafbf8;
    border: 1px solid #e4eadf;
    border-radius: 11px;
    padding: 16px;
    margin-bottom: 18px;
}
.pfo-summary-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 6px 0;
    font-size: 13px;
}
.pfo-summary-total {
    border-top: 1px solid #dfe8d9;
    margin-top: 8px;
    padding-top: 13px;
    color: #527b35;
    font-weight: 800;
    font-size: 17px;
}
.pfo-items {
    background: #f8faf6;
    padding: 14px 17px;
    border-radius: 11px;
    margin-bottom: 18px;
}
.pfo-item {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid #e7ede3;
    font-size: 13px;
}
.pfo-item:last-child {
    border-bottom: none;
}
.pfo-item-note {
    color: #788475;
    font-size: 11px;
    margin-top: 4px;
}
.pfo-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    border-top: 1px solid #ebefe7;
    padding-top: 16px;
}
.pfo-btn {
    display: inline-block;
    padding: 10px 16px;
    background: #527b35;
    color: #fff;
    border: none;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}
.pfo-btn:hover {
    background: #3e6129;
}
.pfo-btn-danger {
    background: #b34d45;
}
.pfo-btn-danger:hover {
    background: #93362f;
}
.pfo-reject-form {
    display: none;
    margin-top: 16px;
    padding: 18px;
    background: #fff8f7;
    border: 1px solid #efd4cf;
    border-radius: 11px;
}
.pfo-reject-form label {
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 7px;
    display: block;
}
.pfo-reject-form select,
.pfo-reject-form textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #dce5d8;
    border-radius: 9px;
    margin-bottom: 13px;
    background: #fff;
}
.pfo-reason {
    background: #fff0ee;
    color: #96332e;
    padding: 12px 15px;
    border-radius: 9px;
    margin-bottom: 15px;
    font-size: 13px;
}
.pfo-alert {
    padding: 13px;
    background: #fff0ee;
    color: #96332e;
    border-radius: 9px;
    margin-bottom: 17px;
}
.pfo-success {
    background: #e9f5e8;
    color: #286f38;
}
@media (max-width: 600px) {
    .pfo-card {
        padding: 16px;
    }
    .pfo-heading h1 {
        font-size: 25px;
    }
}
</style>

<main class="pfo-page">
    <div class="pfo-heading">
        <small>ADMINISTRATION</small>
        <h1>Manage Food Orders</h1>
        <p>Accept, reject and update customer food orders.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="pfo-alert"><?= pch_h($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="pfo-alert pfo-success">
            Order status updated successfully.
        </div>
    <?php endif; ?>

    <div class="pfo-grid">
        <?php if (empty($orders)): ?>
            <div class="pfo-card">No food orders yet.</div>
        <?php endif; ?>

        <?php foreach ($orders as $order): ?>
            <?php
            $id = (int)$order['order_id'];
            $status = (string)$order['order_status'];
            $fulfilment = (string)$order['fulfilment'];
            $area = (string)($order['delivery_area'] ?? '');
            $areaName = pch_delivery_area_name($area);

            if ($areaName === '') {
                $areaName = $area !== '' ? $area : 'Not specified';
            }
            ?>

            <article class="pfo-card">
                <div class="pfo-top">
                    <div>
                        <h2>
                            Order #<?= $id ?> ·
                            <?= pch_h($order['customer_name']) ?>
                        </h2>
                        <div class="pfo-muted">
                            <?= pch_h($order['created_at']) ?> ·
                            <?= pch_h($order['phone']) ?>
                        </div>
                    </div>
                    <span class="pfo-badge <?= pch_h($status) ?>">
                        <?= pch_h($status) ?>
                    </span>
                </div>

                <div class="pfo-info">
                    <span>
                        <strong>Order Type:</strong>
                        <?= pch_h(adminOrderType($fulfilment)) ?>
                    </span>
                    <span>
                        <strong>Payment Method:</strong>
                        <?= pch_h(adminPaymentLabel($order)) ?>
                    </span>

                    <?php if ($order['online_choice'] === 'fpx' && !empty($order['bank'])): ?>
                        <span>
                            <strong>Selected Bank:</strong>
                            <?= pch_h(adminBankLabel($order)) ?>
                        </span>
                    <?php endif; ?>

                    <span>
                        <strong>Payment Status:</strong>
                        <?= pch_h(adminPaymentStatus($order)) ?>
                    </span>

                    <?php if ($fulfilment === 'dine_in'): ?>
                        <span>
                            <strong>Table Number:</strong>
                            <?= !empty($order['table_number'])
                                ? 'Table ' . (int)$order['table_number']
                                : 'Not specified' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($fulfilment === 'delivery'): ?>
                    <div class="pfo-delivery-box">
                        <h3><i class="bi bi-truck me-2"></i>Delivery Details</h3>

                        <div class="pfo-delivery-row">
                            <strong>Delivery Area:</strong>
                            <span><?= pch_h($areaName) ?></span>
                        </div>

                        <div class="pfo-delivery-row">
                            <strong>Delivery Address:</strong>
                            <span class="pfo-delivery-address">
                                <?= pch_h($order['address'] ?: 'Not provided') ?>
                            </span>
                        </div>

                        <div class="pfo-delivery-row">
                            <strong>Delivery Fee:</strong>
                            <span><?= pch_money($order['delivery_fee']) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="pfo-items">
                    <strong>Ordered Food</strong>

                    <?php foreach ($itemsByOrder[$id] ?? [] as $item): ?>
                        <div class="pfo-item">
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
                                        <div class="pfo-item-note">
                                            <?= pch_h($group . ': ' . $choice) ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (trim((string)$item['remark']) !== ''): ?>
                                    <div class="pfo-item-note">
                                        Note: <?= pch_h($item['remark']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <strong><?= pch_money($item['line_total']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="pfo-payment-summary">
                    <strong>Payment Summary</strong>

                    <div class="pfo-summary-row">
                        <span>Subtotal</span>
                        <span><?= pch_money($order['subtotal']) ?></span>
                    </div>

                    <div class="pfo-summary-row">
                        <span>
                            Service Tax
                            (<?= number_format((float)$order['tax_rate'] * 100, 0) ?>%)
                        </span>
                        <span><?= pch_money($order['tax_amount']) ?></span>
                    </div>

                    <div class="pfo-summary-row">
                        <span>Delivery Fee</span>
                        <span><?= pch_money($order['delivery_fee']) ?></span>
                    </div>

                    <div class="pfo-summary-row pfo-summary-total">
                        <span>Total Payment</span>
                        <span><?= pch_money($order['total']) ?></span>
                    </div>
                </div>

                <?php if ($status === 'Rejected'): ?>
                    <div class="pfo-reason">
                        <strong>Order Rejected</strong>

                        <p class="mb-2 mt-2">
                            <strong>Rejection Reason:</strong>
                            <?= pch_h($order['rejection_reason'] ?: 'Not provided') ?>
                        </p>

                        <?php if ($order['payment_status'] === 'demo_refunded'): ?>
                            <div class="pfo-refund-box">
                                <strong>
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                                    Simulated Refund Completed
                                </strong>

                                <p class="mb-1 mt-2">
                                    Refund Amount:
                                    <strong><?= pch_money($order['refund_amount']) ?></strong>
                                </p>

                                <small>No actual money was transferred.</small>
                            </div>
                        <?php else: ?>
                            <p class="mb-0">
                                No refund was required because payment was not collected.
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="pfo-actions">
                    <?php foreach ($nextStatus[$status] ?? [] as $target): ?>
                        <?php if ($target === 'Rejected') continue; ?>

                        <form method="POST">
                            <input type="hidden" name="csrf" value="<?= pch_h(pch_csrf()) ?>">
                            <input type="hidden" name="order_id" value="<?= $id ?>">
                            <input type="hidden" name="status" value="<?= pch_h($target) ?>">

                            <button class="pfo-btn" type="submit">
                                <?= pch_h(match ($target) {
                                    'Confirmed' => 'Accept Order',
                                    'Preparing' => 'Start Preparing',
                                    'Ready' => 'Mark Ready',
                                    'Completed' => 'Complete Order',
                                    default => $target
                                }) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>

                    <?php if ($status === 'Pending'): ?>
                        <button
                            type="button"
                            class="pfo-btn pfo-btn-danger"
                            onclick="toggleReject(<?= $id ?>)"
                        >
                            Reject Order
                        </button>
                    <?php endif; ?>

                    <?php if (!isset($nextStatus[$status])): ?>
                        <span class="pfo-muted">
                            No further status changes.
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($status === 'Pending'): ?>
                    <form
                        method="POST"
                        class="pfo-reject-form"
                        id="reject-form-<?= $id ?>"
                    >
                        <input type="hidden" name="csrf" value="<?= pch_h(pch_csrf()) ?>">
                        <input type="hidden" name="order_id" value="<?= $id ?>">
                        <input type="hidden" name="status" value="Rejected">

                        <label for="reason-<?= $id ?>">
                            Reason for Rejection *
                        </label>

                        <select
                            id="reason-<?= $id ?>"
                            name="reason"
                            required
                            onchange="updateOtherReason(<?= $id ?>)"
                        >
                            <option value="">Select a reason</option>

                            <?php foreach ($reasonChoices as $choice): ?>
                                <option value="<?= pch_h($choice) ?>">
                                    <?= pch_h($choice) ?>
                                </option>
                            <?php endforeach; ?>

                            <option value="Other">Other</option>
                        </select>

                        <div id="other-box-<?= $id ?>" style="display:none">
                            <label for="other-reason-<?= $id ?>">
                                Enter Other Reason *
                            </label>

                            <textarea
                                id="other-reason-<?= $id ?>"
                                name="other_reason"
                                maxlength="500"
                                rows="3"
                                placeholder="Explain why the order cannot be accepted..."
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            class="pfo-btn pfo-btn-danger"
                            onclick="return confirm('Reject this order?')"
                        >
                            Confirm Rejection
                        </button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</main>

<script>
function toggleReject(id) {
    const form = document.getElementById('reject-form-' + id);
    form.style.display = form.style.display === 'block' ? 'none' : 'block';
}

function updateOtherReason(id) {
    const select = document.getElementById('reason-' + id);
    const box = document.getElementById('other-box-' + id);
    const textarea = document.getElementById('other-reason-' + id);
    const isOther = select.value === 'Other';

    box.style.display = isOther ? 'block' : 'none';
    textarea.required = isOther;

    if (!isOther) {
        textarea.value = '';
    }
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>