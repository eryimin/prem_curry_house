<?php

require_once __DIR__ . '/food_order/bootstrap.php';

// ============================================
// CUSTOMER SESSION
// ============================================

$userId = (int)($_SESSION['user_id'] ?? 0);

$orderId = (int)(
    $_SESSION['pch_last_food_order'] ?? 0
);

if (
    $userId < 1 ||
    $orderId < 1 ||
    ($_SESSION['role'] ?? '') !== 'guest'
) {
    header('Location: menu.php');
    exit;
}

// Customer is editing an unpaid order.
// Require checkout confirmation before payment.

if (
    (int)($_SESSION['pch_editing_order_id'] ?? 0)
    === $orderId
) {
    header('Location: food_checkout.php');
    exit;
}

// ============================================
// PAYMENT METHODS
// ============================================

$paymentLabels = [
    'card' => 'Credit / Debit Card',
    'fpx' => 'FPX Online Banking',
    'tng' => "Touch 'n Go eWallet"
];

$bankNames = [
    'maybank' => 'Maybank',
    'cimb' => 'CIMB Bank',
    'public_bank' => 'Public Bank',
    'rhb' => 'RHB Bank',
    'hong_leong' => 'Hong Leong Bank',
    'bank_islam' => 'Bank Islam'
];

// ============================================
// CHANGE PAYMENT METHOD
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    pch_verify();

    if (
        ($_POST['action'] ?? '') !==
        'change_payment_method'
    ) {
        http_response_code(422);
        exit('Invalid payment action.');
    }

    $newMethod = (string)(
        $_POST['online_choice'] ?? ''
    );

    $newBank = null;

    if (!array_key_exists($newMethod, $paymentLabels)) {
        http_response_code(422);
        exit('Invalid payment method.');
    }

    if ($newMethod === 'fpx') {

        $newBank = (string)(
            $_POST['bank'] ?? ''
        );

        if (!array_key_exists($newBank, $bankNames)) {
            http_response_code(422);
            exit('Please select a valid bank.');
        }
    }

    try {

        // Update only an unpaid order belonging
        // to the currently logged-in customer.

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT order_id
            FROM pch_food_orders
            WHERE order_id = ?
              AND user_id = ?
              AND fulfilment = 'delivery'
              AND payment_method IN (
                  'online_demo',
                  'online'
              )
              AND payment_status IN (
                  'demo_unpaid',
                  'pending'
              )
            FOR UPDATE
        ");

        $stmt->execute([$orderId, $userId]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'This order is no longer awaiting payment.'
            );
        }

        $stmt = $pdo->prepare("
            UPDATE pch_food_orders
            SET online_choice = ?,
                bank = ?
            WHERE order_id = ?
              AND user_id = ?
              AND payment_status IN (
                  'demo_unpaid',
                  'pending'
              )
        ");

        $stmt->execute([
            $newMethod,
            $newBank,
            $orderId,
            $userId
        ]);

        $pdo->commit();

        header('Location: food_demo_payment.php');
        exit;

    } catch (RuntimeException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        http_response_code(409);
        exit(pch_h($e->getMessage()));

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Payment method update: ' .
            $e->getMessage()
        );

        http_response_code(500);
        exit('Unable to update payment method.');
    }
}

// ============================================
// LOAD ORDER
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
    exit('Order not found.');
}

// ============================================
// VERIFY PAYMENT ELIGIBILITY
// ============================================

if (
    $order['fulfilment'] !== 'delivery' ||
    !in_array(
        $order['payment_method'],
        ['online_demo', 'online'],
        true
    )
) {
    header('Location: food_order_success.php');
    exit;
}

// Already paid
if (in_array(
    $order['payment_status'],
    ['demo_paid', 'paid'],
    true
)) {
    header('Location: food_order_success.php');
    exit;
}

// Only unpaid delivery orders can continue
if (!in_array(
    $order['payment_status'],
    ['demo_unpaid', 'pending'],
    true
)) {
    http_response_code(409);
    exit('This order is not awaiting payment.');
}

// ============================================
// SELECTED PAYMENT METHOD
// ============================================

$method = (string)$order['online_choice'];

if (!isset($paymentLabels[$method])) {
    http_response_code(422);
    exit('Invalid payment method.');
}

$bankLabel = $bankNames[
    (string)($order['bank'] ?? '')
] ?? 'Selected Bank';

$pageTitle = 'Complete Payment | Prem Curry House';

require __DIR__ . '/includes/header.php';

?>

<style>

/* ============================================
   PAYMENT PAGE
   ============================================ */

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
    background: #ffffff;
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

.pch-pay-muted {
    color: #718071;
    font-size: 13px;
    line-height: 1.7;
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

.pch-change-payment {
    margin-top: 15px;
    border-top: 1px solid #dce6d6;
    padding-top: 15px;
}

.pch-change-payment label {
    display: block;
    color: #29432d;
    font-size: 13px;
    font-weight: 700;
    margin: 9px 0 6px;
}

.pch-change-payment select {
    display: block;
    width: 100%;
    padding: 11px;
    border: 1px solid #dce6d6;
    border-radius: 9px;
    background: #fff;
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
    color: #ffffff;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    margin-top: 10px;
}

.pch-pay-button:hover {
    background: #3e6329;
}

.pch-bank-box {
    background: #f7faf4;
    border: 1px solid #e0e8db;
    border-radius: 12px;
    padding: 28px 15px;
    text-align: center;
    margin-bottom: 20px;
}

.pch-bank-box i {
    display: block;
    font-size: 42px;
    color: #527b35;
    margin-bottom: 12px;
}

.pch-bank-box h3 {
    color: #29432d;
    font-size: 20px;
    font-weight: 700;
}

.pch-pay-qr {
    width: 180px;
    height: 180px;
    border: 2px solid #527b35;
    border-radius: 13px;
    background: #f7faf4;
    margin: 15px auto 25px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #527b35;
}

.pch-pay-qr i {
    font-size: 70px;
}

.pch-pay-qr span {
    font-size: 11px;
    font-weight: 800;
}

.pch-pay-warning {
    margin-top: 18px;
    padding: 13px;
    border: 1px solid #f0dca9;
    border-radius: 10px;
    background: #fff8e5;
    font-size: 12px;
    line-height: 1.7;
    color: #594b2d;
}

@media (max-width: 767px) {

    .pch-pay-layout {
        grid-template-columns: 1fr;
    }

    .pch-pay-panel {
        padding: 20px;
    }
}

/* ============================================
   PAYMENT BACK BUTTONS
   ============================================ */

.pch-payment-back-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 24px;
}

.pch-payment-back-actions form {
    margin: 0;
}

.pch-payment-back-btn {
    width: 100%;
    padding: 12px 10px;
    border: 1px solid #527b35;
    border-radius: 9px;
    background: #ffffff;
    color: #527b35;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.pch-payment-back-btn:hover {
    background: #eaf0de;
}

.pch-payment-back-btn i {
    margin-right: 5px;
}

@media (max-width: 480px) {
    .pch-payment-back-actions {
        grid-template-columns: 1fr;
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

            <h1 class="pch-pay-title">
                Complete Your Payment
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

            <!-- ====================================
                 CHANGE PAYMENT METHOD
                 ==================================== -->

            <form
                class="pch-change-payment"
                method="POST"
                action="food_demo_payment.php"
                id="pch-change-payment-form"
            >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= pch_h(pch_csrf()) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="change_payment_method"
                >

                <label for="pch-change-method">
                    Change Payment Method
                </label>

                <select
                    id="pch-change-method"
                    name="online_choice"
                    required
                >

                    <?php foreach ($paymentLabels as $key => $label): ?>

                        <option
                            value="<?= pch_h($key) ?>"
                            <?= $method === $key ? 'selected' : '' ?>
                        >
                            <?= pch_h($label) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <div
                    id="pch-change-bank-wrap"
                    <?= $method !== 'fpx' ? 'hidden' : '' ?>
                >

                    <label for="pch-change-bank">
                        Select Bank
                    </label>

                    <select
                        id="pch-change-bank"
                        name="bank"
                    >

                        <option value="">
                            Choose your bank
                        </option>

                        <?php foreach ($bankNames as $key => $label): ?>

                            <option
                                value="<?= pch_h($key) ?>"
                                <?= ($order['bank'] ?? '') === $key
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= pch_h($label) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <p
                    class="pch-pay-muted mt-2 mb-0"
                    id="pch-change-hint"
                    aria-live="polite"
                >
                    Your payment screen updates automatically.
                </p>

            </form>

            <div class="pch-pay-warning">

                <strong>Test checkout:</strong>

                This is a practice payment page.
                No real payment is processed.

                Never enter real card numbers,
                banking passwords, PINs or OTPs.

            </div>

        <!-- ============================================
            RETURN TO CHECKOUT OR CART
            ============================================ -->

        <div class="pch-payment-back-actions">

            <!-- BACK TO CHECKOUT -->

            <form
                method="POST"
                action="food_return_to_edit.php"
            >
                <input
                    type="hidden"
                    name="csrf"
                    value="<?= pch_h(pch_csrf()) ?>"
                >

                <input
                    type="hidden"
                    name="destination"
                    value="checkout"
                >

                <button
                    type="submit"
                    class="pch-payment-back-btn"
                >
                    <i class="bi bi-arrow-left"></i>
                    Back to Checkout
                </button>
            </form>

            <!-- EDIT SHOPPING CART -->

            <form
                method="POST"
                action="food_return_to_edit.php"
            >
                <input
                    type="hidden"
                    name="csrf"
                    value="<?= pch_h(pch_csrf()) ?>"
                >

                <input
                    type="hidden"
                    name="destination"
                    value="cart"
                >

                <button
                    type="submit"
                    class="pch-payment-back-btn"
                >
                    <i class="bi bi-cart3"></i>
                    Edit My Cart
                </button>
            </form>

        </div>

        </section>

        <!-- ====================================
             RIGHT: PAYMENT INTERFACE
             ==================================== -->

        <section class="pch-pay-panel">

            <h2 class="pch-pay-title">

                <?php if ($method === 'card'): ?>

                    Credit / Debit Card

                <?php elseif ($method === 'fpx'): ?>

                    FPX Online Banking

                <?php else: ?>

                    Touch 'n Go eWallet

                <?php endif; ?>

            </h2>

            <!-- ====================================
                 CREDIT / DEBIT CARD
                 ==================================== -->

            <?php if ($method === 'card'): ?>

                <p class="pch-pay-muted">
                    Use the practice card details below.
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
                            Card Number
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
                                Expiry Date
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
                                CVC
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
                        Practice details:
                        4242 4242 4242 4242,
                        expiry 12/30,
                        CVC 123.
                    </p>

                    <button
                        type="submit"
                        class="pch-pay-button"
                    >
                        Pay <?= pch_money($order['total']) ?>
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
                        Payment Authorization
                    </p>

                    <p>
                        Amount:
                        <strong>
                            <?= pch_money($order['total']) ?>
                        </strong>
                    </p>

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
                        Confirm Payment
                    </button>

                </form>

            <!-- ====================================
                 TOUCH N GO EWALLET
                 ==================================== -->

            <?php else: ?>

                <p class="pch-pay-muted">
                    Review your payment amount below.
                    The QR icon is illustrative and cannot be scanned.
                </p>

                <div class="pch-pay-qr">

                    <i class="bi bi-qr-code"></i>

                    <span>QR PREVIEW</span>

                </div>

                <div class="text-center">

                    <strong>
                        Touch 'n Go eWallet
                    </strong>

                    <p class="pch-pay-muted mt-2">
                        Payment Amount:
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
                        Confirm Payment
                    </button>

                </form>

            <?php endif; ?>

            <p class="pch-pay-muted text-center mt-3">
                Complete payment to confirm your delivery order.
            </p>

        </section>

    </div>

</main>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const changeForm = document.getElementById(
        'pch-change-payment-form'
    );

    const method = document.getElementById(
        'pch-change-method'
    );

    const bankWrap = document.getElementById(
        'pch-change-bank-wrap'
    );

    const bank = document.getElementById(
        'pch-change-bank'
    );

    const hint = document.getElementById(
        'pch-change-hint'
    );

    if (!changeForm || !method || !bankWrap || !bank) {
        return;
    }

    let saving = false;

    function updateBankField() {

        const isFpx = method.value === 'fpx';

        bankWrap.hidden = !isFpx;

        bank.required = isFpx;
        bank.disabled = !isFpx;
    }

    function saveChoice() {

        if (saving) {
            return;
        }

        if (
            method.value === 'fpx' &&
            !bank.value
        ) {
            return;
        }

        saving = true;

        if (hint) {
            hint.textContent = 'Updating payment method...';
        }

        changeForm.submit();
    }

    method.addEventListener('change', function () {

        updateBankField();

        if (
            method.value === 'fpx' &&
            !bank.value
        ) {

            if (hint) {
                hint.textContent = 'Choose a bank to continue.';
            }

            bank.focus();
            return;
        }

        saveChoice();
    });

    bank.addEventListener('change', function () {

        if (method.value === 'fpx') {
            saveChoice();
        }
    });

    updateBankField();

});

</script>

<?php require __DIR__ . '/includes/footer.php'; ?>