
<?php
// ============================================
// PREM CURRY HOUSE - SIMULATED PAYMENT PAGE
// ============================================

require_once __DIR__ . '/food_order/bootstrap.php';

// ============================================
// CHECK CUSTOMER SESSION
// ============================================

$userId = (int)($_SESSION['user_id'] ?? 0);
$orderId = (int)($_SESSION['pch_last_food_order'] ?? 0);

if ($userId < 1 || $orderId < 1) {
    header('Location: menu.php');
    exit;
}

// ============================================
// LOAD ORDER FROM MYSQL
// ============================================

$stmt = $pdo->prepare("
    SELECT
        order_id,
        user_id,
        customer_name,
        fulfilment,
        payment_method,
        online_choice,
        bank,
        payment_status,
        total
    FROM pch_food_orders
    WHERE order_id = ?
      AND user_id = ?
    LIMIT 1
");

$stmt->execute([$orderId, $userId]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(403);
    exit('Order not found or access denied.');
}

// Only delivery demo payments use this page
if (
    $order['fulfilment'] !== 'delivery' ||
    $order['payment_method'] !== 'online_demo'
) {
    header('Location: food_order_success.php');
    exit;
}

// Already completed
if ($order['payment_status'] === 'demo_paid') {
    header('Location: food_order_success.php');
    exit;
}

if ($order['payment_status'] !== 'demo_unpaid') {
    http_response_code(409);
    exit('This order is not awaiting demo payment.');
}

// ============================================
// PAYMENT METHOD
// ============================================

$method = (string)$order['online_choice'];

$paymentLabels = [
    'card' => 'Credit / Debit Card',
    'fpx' => 'FPX Online Banking',
    'tng' => "Touch 'n Go eWallet"
];

if (!isset($paymentLabels[$method])) {
    http_response_code(422);
    exit('Invalid payment method.');
}

$bankNames = [
    'maybank' => 'Maybank',
    'cimb' => 'CIMB Bank',
    'public_bank' => 'Public Bank',
    'rhb' => 'RHB Bank',
    'hong_leong' => 'Hong Leong Bank',
    'bank_islam' => 'Bank Islam'
];

$bankLabel = $bankNames[
    (string)($order['bank'] ?? '')
] ?? 'Selected Bank';

// ============================================
// LOAD PAGE
// ============================================

$pageTitle = 'Demo Payment | Prem Curry House';

require __DIR__ . '/includes/header.php';
?>

<style>
.pch-pay-page {
    max-width: 960px;
    margin: 45px auto 75px;
    padding: 0 20px;
}

.pch-pay-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}

.pch-pay-panel {
    background: white;
    border: 1px solid #e2e9dc;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 8px 28px rgba(0,0,0,.05);
}

.pch-pay-logo {
    font-size: 13px;
    font-weight: 800;
    color: #527b35;
    letter-spacing: 1px;
}

.pch-pay-title {
    font-size: 27px;
    font-weight: 800;
    color: #29432d;
    margin: 15px 0;
}

.pch-test-badge {
    display: inline-block;
    background: #e7eefb;
    color: #2758a5;
    border-radius: 20px;
    padding: 6px 12px;
    font-size: 11px;
    font-weight: 800;
}

.pch-pay-muted {
    color: #718071;
    font-size: 13px;
}

.pch-pay-amount {
    font-size: 36px;
    font-weight: 800;
    margin-top: 10px;
    color: #29432d;
}

.pch-pay-method {
    padding: 15px;
    border: 1px solid #e0e8db;
    background: #f7faf4;
    border-radius: 10px;
    margin: 18px 0;
}

.pch-pay-method i {
    color: #527b35;
    margin-right: 8px;
}

.pch-pay-field {
    margin-bottom: 15px;
}

.pch-pay-field label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #29432d;
    margin-bottom: 7px;
}

.pch-pay-field input {
    width: 100%;
    padding: 12px;
    border: 1px solid #dce5d8;
    border-radius: 9px;
    outline: none;
    font-size: 14px;
}

.pch-pay-field input:focus {
    border-color: #527b35;
}

.pch-pay-fields-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.pch-pay-button {
    width: 100%;
    padding: 14px;
    background: #527b35;
    border: none;
    border-radius: 9px;
    color: white;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    margin-top: 12px;
}

.pch-pay-button:hover {
    background: #3f632a;
}

.pch-pay-fail {
    background: white;
    color: #b34d45;
    border: 1px solid #b34d45;
}

.pch-pay-fail:hover {
    background: #fff0ee;
}

.pch-pay-warning {
    background: #fff8e7;
    border: 1px solid #f0dfac;
    padding: 13px;
    border-radius: 9px;
    font-size: 12px;
    line-height: 1.7;
    margin: 18px 0;
}

.pch-pay-qr {
    width: 180px;
    height: 180px;
    background: #f0f4ec;
    border: 2px solid #527b35;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 20px auto;
    color: #527b35;
}

.pch-pay-qr i {
    font-size: 65px;
}

.pch-pay-qr span {
    font-size: 12px;
    font-weight: 800;
}

.pch-bank-box {
    background: #f7faf4;
    border: 1px solid #e2e9dc;
    border-radius: 10px;
    padding: 18px;
    text-align: center;
    margin: 20px 0;
}

.pch-bank-box i {
    font-size: 42px;
    color: #527b35;
}

.pch-bank-box h3 {
    font-size: 19px;
    margin-top: 10px;
}

.pch-pay-back {
    display: block;
    text-align: center;
    margin-top: 18px;
    color: #527b35;
    text-decoration: none;
    font-size: 13px;
}

@media (max-width: 767px) {
    .pch-pay-layout {
        grid-template-columns: 1fr;
    }

    .pch-pay-panel {
        padding: 20px;
    }
}
</style>

<main class="pch-pay-page">

    <div class="pch-pay-layout">

        <!-- ====================================
             LEFT: ORDER SUMMARY
             ==================================== -->

        <section class="pch-pay-panel">

            <div class="pch-pay-logo">
                PREM CURRY HOUSE
            </div>

            <span class="pch-test-badge">
                SANDBOX SIMULATION
            </span>

            <h1 class="pch-pay-title">
                Complete Your Demo Payment
            </h1>

            <p class="pch-pay-muted">
                Order #<?= (int)$order['order_id'] ?>
            </p>

            <p class="pch-pay-muted">
                Customer:
                <?= pch_h($order['customer_name']) ?>
            </p>

            <hr>

            <p class="pch-pay-muted">
                Total Payment Amount
            </p>

            <div class="pch-pay-amount">
                <?= pch_money($order['total']) ?>
            </div>

            <div class="pch-pay-method">

                <i class="bi bi-wallet2"></i>

                <strong>
                    <?= pch_h($paymentLabels[$method]) ?>
                </strong>

                <?php if ($method === 'fpx'): ?>

                    <p class="pch-pay-muted mb-0 mt-2">
                        Bank:
                        <?= pch_h($bankLabel) ?>
                    </p>

                <?php endif; ?>

            </div>

            <div class="pch-pay-warning">

                <strong>Important:</strong>

                This page is only a payment simulation
                for an academic project.

                No real money will be transferred.

                Do not enter real card information,
                banking passwords, PINs or OTPs.

            </div>

        </section>

        <!-- ====================================
             RIGHT: PAYMENT INTERFACE
             ==================================== -->

        <section class="pch-pay-panel">

            <h2 class="pch-pay-title">
                <?php if ($method === 'card'): ?>
                    Test Card Payment
                <?php elseif ($method === 'fpx'): ?>
                    Mock FPX Banking
                <?php else: ?>
                    Mock TNG Payment
                <?php endif; ?>
            </h2>

            <!-- ====================================
                 CREDIT / DEBIT CARD
                 ==================================== -->

            <?php if ($method === 'card'): ?>

                <p class="pch-pay-muted">
                    Enter the fictional test card
                    details shown below.
                </p>

                <form
                    method="POST"
                    action="food_confirm_payment.php"
                    autocomplete="off"
                >

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= pch_h(pch_csrf()) ?>"
                    >

                    <input
                        type="hidden"
                        name="result"
                        value="success"
                    >

                    <div class="pch-pay-field">

                        <label for="test-card">
                            Test Card Number
                        </label>

                        <input
                            type="text"
                            id="test-card"
                            name="test_card"
                            inputmode="numeric"
                            maxlength="19"
                            placeholder="4242 4242 4242 4242"
                            autocomplete="off"
                            required
                        >

                    </div>

                    <div class="pch-pay-fields-row">

                        <div class="pch-pay-field">

                            <label for="test-expiry">
                                Test Expiry
                            </label>

                            <input
                                type="text"
                                id="test-expiry"
                                name="test_expiry"
                                placeholder="12/30"
                                maxlength="5"
                                autocomplete="off"
                                required
                            >

                        </div>

                        <div class="pch-pay-field">

                            <label for="test-cvc">
                                Test CVC
                            </label>

                            <input
                                type="text"
                                id="test-cvc"
                                name="test_cvc"
                                placeholder="123"
                                maxlength="3"
                                autocomplete="off"
                                required
                            >

                        </div>

                    </div>

                    <p class="pch-pay-muted">
                        Accepted fictional details:
                        4242 4242 4242 4242,
                        expiry 12/30,
                        CVC 123.
                    </p>

                    <button
                        type="submit"
                        class="pch-pay-button"
                    >
                        Simulate Card Payment
                    </button>

                </form>

            <!-- ====================================
                 FPX ONLINE BANKING
                 ==================================== -->

            <?php elseif ($method === 'fpx'): ?>

                <div class="pch-bank-box">

                    <i class="bi bi-bank"></i>

                    <h3>
                        <?= pch_h($bankLabel) ?>
                    </h3>

                    <p class="pch-pay-muted">
                        Simulated Bank Authorization
                    </p>

                    <p>
                        Amount:
                        <strong>
                            <?= pch_money($order['total']) ?>
                        </strong>
                    </p>

                </div>

                <p class="pch-pay-muted">
                    Choose whether your mock
                    bank transaction should succeed
                    or fail.
                </p>

                <form
                    method="POST"
                    action="food_confirm_payment.php"
                >

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= pch_h(pch_csrf()) ?>"
                    >

                    <button
                        type="submit"
                        name="result"
                        value="success"
                        class="pch-pay-button"
                    >
                        Approve Demo Bank Payment
                    </button>

                    <button
                        type="submit"
                        name="result"
                        value="failed"
                        class="pch-pay-button pch-pay-fail"
                    >
                        Simulate Bank Payment Failure
                    </button>

                </form>

            <!-- ====================================
                 TOUCH N GO EWALLET
                 ==================================== -->

            <?php else: ?>

                <p class="pch-pay-muted">
                    This is a mock eWallet payment
                    screen. The image below is not
                    a scannable payment QR code.
                </p>

                <div class="pch-pay-qr">

                    <i class="bi bi-qr-code"></i>

                    <span>DEMO ONLY</span>

                </div>

                <div class="text-center">

                    <strong>
                        Touch 'n Go eWallet
                    </strong>

                    <p class="pch-pay-muted mt-2">
                        Simulated payment amount:
                    </p>

                    <h3>
                        <?= pch_money($order['total']) ?>
                    </h3>

                </div>

                <form
                    method="POST"
                    action="food_confirm_payment.php"
                >

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= pch_h(pch_csrf()) ?>"
                    >

                    <button
                        type="submit"
                        name="result"
                        value="success"
                        class="pch-pay-button"
                    >
                        Simulate TNG Payment Success
                    </button>

                    <button
                        type="submit"
                        name="result"
                        value="failed"
                        class="pch-pay-button pch-pay-fail"
                    >
                        Simulate TNG Payment Failure
                    </button>

                </form>

            <?php endif; ?>

            <a
                href="food_order_success.php"
                class="pch-pay-back"
            >
                Return to Order Summary
            </a>

        </section>

    </div>

</main>

<?php
require __DIR__ . '/includes/footer.php';
?>
