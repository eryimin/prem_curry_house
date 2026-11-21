<?php

require_once __DIR__ . '/food_order/bootstrap.php';

// ============================================
// GET ORDER DETAILS
// ============================================

$orderType = $_SESSION['order_type'] ?? '';
$tableNumber = $_SESSION['table_number'] ?? null;
$deliveryArea = $_SESSION['delivery_area'] ?? '';

$deliveryAddress = trim(
    (string)($_SESSION['delivery_address'] ?? '')
);

// ============================================
// VALIDATE ORDER TYPE
// ============================================

if (!in_array($orderType, ['dine_in', 'delivery'], true)) {
    header('Location: index.php?choose_order=1');
    exit;
}

// Validate Dine In table
if ($orderType === 'dine_in') {

    $table = filter_var(
        $tableNumber,
        FILTER_VALIDATE_INT
    );

    if (
        $table === false ||
        $table === null ||
        $table < 1 ||
        $table > 10
    ) {
        header('Location: index.php?choose_order=1');
        exit;
    }

    $tableNumber = $table;
}

// Validate Delivery
if ($orderType === 'delivery') {

    if (
        !PCH_DELIVERY_ENABLED ||
        $deliveryAddress === '' ||
        !array_key_exists(
            $deliveryArea,
            pch_delivery_areas()
        )
    ) {
        header('Location: index.php?choose_order=1');
        exit;
    }
}

// ============================================
// CALCULATE CART
// ============================================

$data = pch_calculate(
    $pdo,
    pch_cart()
);

if (!$data['lines']) {
    header('Location: food_cart.php');
    exit;
}

$subtotal = (float)$data['subtotal'];
$tax = round($subtotal * PCH_TAX_RATE, 2);

$deliveryFee = pch_delivery_fee(
    $orderType,
    $orderType === 'delivery'
        ? $deliveryArea
        : null
);

$deliveryAreaName = $orderType === 'delivery'
    ? pch_delivery_area_name($deliveryArea)
    : '';

// Calculate rounding and final total
$totals = pch_order_totals(
    $subtotal,
    $tax,
    $deliveryFee,
    $orderType
);

$originalTotal = $totals['original_total'];
$roundingAdjustment = $totals['rounding_adjustment'];
$total = $totals['total'];

$pageTitle = 'Checkout | Prem Curry House';

require __DIR__ . '/includes/header.php';

?>

<link rel="stylesheet" href="assets/css/food_order.css">

<style>

/* ============================================
   PAYMENT CARDS
   ============================================ */

.pch-payment-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    border: 1px solid #dce6d6;
    border-radius: 10px;
    background: #fff;
    margin-bottom: 11px;
}

.pch-payment-card i {
    font-size: 23px;
    color: #527b35;
}

.pch-payment-card strong {
    display: block;
    color: #263727;
    font-size: 14px;
}

.pch-payment-card small {
    display: block;
    font-size: 12px;
    color: #718071;
    margin-top: 3px;
}

/* ============================================
   ONLINE PAYMENT METHODS
   ============================================ */

.pch-online-methods {
    background: #f7faf4;
    border: 1px solid #e4ebde;
    border-radius: 11px;
    padding: 16px;
    margin-top: 12px;
}

.pch-online-heading {
    font-size: 13px;
    font-weight: 700;
    color: #263727;
    margin-bottom: 13px;
}

.pch-online-method {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: #fff;
    border: 1px solid #e1e8dc;
    border-radius: 9px;
    margin-bottom: 9px;
    cursor: pointer;
    font-size: 13px;
}

.pch-online-method input {
    accent-color: #527b35;
}

.pch-online-method:has(input:checked) {
    border-color: #527b35;
    background: #eff6e9;
}

.pch-online-method i {
    color: #527b35;
    font-size: 19px;
}

.pch-online-methods[hidden],
#pch-bank-section[hidden] {
    display: none !important;
}

.pch-payment-info {
    color: #718071;
    font-size: 12px;
    margin-top: 13px;
    line-height: 1.6;
}

/* ============================================
   CHECKOUT DETAILS
   ============================================ */

.pch-checkout-detail {
    background: #f7faf4;
    border: 1px solid #e4ebde;
    border-radius: 10px;
    padding: 15px;
    margin-top: 12px;
}

.pch-checkout-detail p {
    margin-bottom: 8px;
    font-size: 14px;
}

.pch-checkout-detail p:last-child {
    margin-bottom: 0;
}

.pch-checkout-detail strong {
    color: #29432d;
}

/* ============================================
   FPX BANK SELECTION
   ============================================ */

#pch-bank-section {
    margin: 12px 0;
    padding: 15px;
    border: 1px solid #dce6d6;
    border-radius: 9px;
    background: #fff;
}

#pch-bank-section label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 700;
}

#pch-bank-section select {
    width: 100%;
    padding: 10px;
    border: 1px solid #dce6d6;
    border-radius: 8px;
}

/* ============================================
   DELIVERY FEE
   ============================================ */

.pch-delivery-highlight {
    background: #eef5e8;
    border: 1px solid #d5e3c8;
    border-radius: 10px;
    padding: 13px 15px;
    margin-top: 13px;
}

.pch-delivery-highlight span {
    color: #527b35;
    font-weight: 700;
}

/* ============================================
   DELIVERY ADDRESS EDIT BUTTON
   ============================================ */

.pch-address-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
}

.pch-address-heading strong {
    color: #29432d;
    font-size: 14px;
}

.pch-address-edit-btn {
    border: 1px solid #527b35;
    border-radius: 8px;
    background: #ffffff;
    color: #527b35;
    font-size: 12px;
    font-weight: 700;
    padding: 6px 12px;
    cursor: pointer;
}

.pch-address-edit-btn:hover {
    background: #eaf0de;
}

.pch-address-preview {
    margin: 0;
    color: #52634f;
    font-size: 13px;
    line-height: 1.7;
    white-space: pre-line;
    overflow-wrap: anywhere;
}

.pch-address-preview[hidden],
#pch-address-editor[hidden] {
    display: none !important;
}

/* ============================================
   EDITABLE ADDRESS FIELD
   ============================================ */

.pch-address-editor textarea {
    display: block;
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #dce6d6;
    border-radius: 9px;
    background: #ffffff;
    font-size: 13px;
    resize: vertical;
}

.pch-address-editor textarea:focus {
    outline: none;
    border-color: #527b35;
    box-shadow: 0 0 0 3px rgba(82,123,53,.12);
}

.pch-address-done-btn {
    background: #527b35;
    border: 0;
    border-radius: 8px;
    color: #ffffff;
    padding: 8px 18px;
    font-size: 12px;
    font-weight: 700;
    margin-top: 10px;
    cursor: pointer;
}

.pch-address-done-btn:hover {
    background: #40652b;
}

/* ============================================
   ORDER SUMMARY
   ============================================ */

.pch-rounding-row {
    color: #62705f;
}

.pch-rounding-row strong {
    color: #527b35;
}

.pch-checkout-grid .pch-summary-row {
    display: grid;
    grid-template-columns:
        minmax(0, 1fr)
        minmax(105px, max-content);
    align-items: center;
    column-gap: 16px;
}

.pch-checkout-grid .pch-summary-row strong {
    display: block;
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
    font-feature-settings: "tnum";
}

.pch-checkout-grid .pch-summary-row span {
    min-width: 0;
}

/* ============================================
   RESPONSIVE
   ============================================ */

@media (max-width: 991px) {

    .pch-checkout-grid .pch-summary-row {
        grid-template-columns: minmax(0, 1fr) 115px;
        column-gap: 12px;
    }
}

</style>

<!-- ============================================
     CHECKOUT PAGE
     ============================================ -->

<main class="container pch-order-page">

    <div class="pch-page-heading">

        <span>SECURE CHECKOUT</span>

        <h1>Checkout</h1>

        <p>
            Review your order and choose how to pay.
        </p>

    </div>

    <form
        class="pch-checkout-grid"
        method="POST"
        action="food_place_order.php"
        id="pch-checkout"
    >

        <input
            type="hidden"
            name="csrf"
            value="<?= pch_h(pch_csrf()) ?>"
        >

        <!-- ====================================
             LEFT: ORDER DETAILS
             ==================================== -->

        <div class="pch-panel">

            <h2>Order Details</h2>

            <!-- CUSTOMER NAME -->

            <label for="customer_name">
                Customer Name *
            </label>

            <input
                id="customer_name"
                type="text"
                name="customer_name"
                required
                maxlength="100"
                value="<?= pch_h(
                    $_SESSION['pch_edit_customer_name']
                    ?? $_SESSION['full_name']
                    ?? ''
                ) ?>"
                placeholder="Your name"
            >

            <!-- CONTACT NUMBER -->

            <label for="phone">

                Contact Number

                <?= $orderType === 'delivery'
                    ? '* (Required)'
                    : '(Optional)'
                ?>

            </label>

            <input
                id="phone"
                type="tel"
                name="phone"
                <?= $orderType === 'delivery'
                    ? 'required'
                    : ''
                ?>
                maxlength="20"
                pattern="[0-9+() -]{7,20}"
                placeholder="0123456789"
                value="<?= pch_h(
                    $_SESSION['pch_edit_phone'] ?? ''
                ) ?>"
            >

            <!-- ====================================
                 ORDER TYPE
                 ==================================== -->

            <h2 class="mt-4">Order Type</h2>

            <div class="pch-checkout-detail">

                <?php if ($orderType === 'dine_in'): ?>

                    <p>
                        <strong>Order Type:</strong>
                        Dine In
                    </p>

                    <p>
                        <strong>Table Number:</strong>
                        Table <?= pch_h($tableNumber) ?>
                    </p>

                <?php else: ?>

                    <p>
                        <strong>Order Type:</strong>
                        Delivery
                    </p>

                    <p>
                        <strong>Delivery Area:</strong>
                        <?= pch_h($deliveryAreaName) ?>
                    </p>

                    <!-- ====================================
                         DELIVERY ADDRESS
                         ==================================== -->

                    <div class="pch-address-heading">

                        <strong>
                            <i class="bi bi-geo-alt"></i>
                            Delivery Address
                        </strong>

                        <button
                            type="button"
                            class="pch-address-edit-btn"
                            id="pch-address-edit-btn"
                            aria-controls="pch-address-editor"
                            aria-expanded="false"
                        >
                            <i class="bi bi-pencil-square"></i>
                            Edit
                        </button>

                    </div>

                    <!-- NORMAL ADDRESS DISPLAY -->

                    <p
                        class="pch-address-preview"
                        id="pch-address-preview"
                    ><?= pch_h($deliveryAddress) ?></p>

                    <!-- EDIT ADDRESS -->

                    <div
                        class="pch-address-editor"
                        id="pch-address-editor"
                        hidden
                    >

                        <label
                            for="delivery_address"
                            class="visually-hidden"
                        >
                            Delivery Address
                        </label>

                        <textarea
                            id="delivery_address"
                            name="delivery_address"
                            rows="3"
                            maxlength="500"
                            required
                            placeholder="Enter your full delivery address"
                        ><?= pch_h($deliveryAddress) ?></textarea>

                        <button
                            type="button"
                            class="pch-address-done-btn"
                            id="pch-address-done-btn"
                        >
                            <i class="bi bi-check-lg"></i>
                            Done
                        </button>

                    </div>

                    <!-- DELIVERY FEE -->

                    <div class="pch-delivery-highlight">

                        <div class="d-flex justify-content-between align-items-center gap-2">

                            <strong>Delivery Fee</strong>

                            <span>
                                <?= pch_money($deliveryFee) ?>
                            </span>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

            <input
                type="hidden"
                name="fulfilment"
                value="<?= pch_h($orderType) ?>"
            >

            <!-- ====================================
                 PAYMENT METHOD
                 ==================================== -->

            <h2 class="mt-4">Payment Method</h2>

            <?php if ($orderType === 'dine_in'): ?>

                <div class="pch-payment-card">

                    <i class="bi bi-wallet2"></i>

                    <span>

                        <strong>Pay at Counter</strong>

                        <small>
                            Please pay at the restaurant counter.
                        </small>

                    </span>

                </div>

                <input
                    type="hidden"
                    name="payment_method"
                    value="counter"
                >

            <?php else: ?>

                <input
                    type="hidden"
                    name="payment_method"
                    value="online"
                >

                <div class="pch-online-methods">

                    <div class="pch-online-heading">
                        Select Online Payment Method
                    </div>

                    <!-- TOUCH N GO -->

                    <label class="pch-online-method">

                        <input
                            type="radio"
                            name="online_choice"
                            value="tng"
                            checked
                        >

                        <i class="bi bi-phone"></i>

                        Touch 'n Go eWallet

                    </label>

                    <!-- FPX -->

                    <label class="pch-online-method">

                        <input
                            type="radio"
                            name="online_choice"
                            value="fpx"
                        >

                        <i class="bi bi-bank"></i>

                        FPX Online Banking

                    </label>

                    <!-- FPX BANK -->

                    <div id="pch-bank-section" hidden>

                        <label for="pch-bank">
                            Select Your Bank *
                        </label>

                        <select
                            name="bank"
                            id="pch-bank"
                            class="form-select"
                            disabled
                        >

                            <option value="">
                                Choose your bank
                            </option>

                            <option value="maybank">Maybank</option>
                            <option value="cimb">CIMB Bank</option>
                            <option value="public_bank">Public Bank</option>
                            <option value="rhb">RHB Bank</option>
                            <option value="hong_leong">Hong Leong Bank</option>
                            <option value="bank_islam">Bank Islam</option>

                        </select>

                    </div>

                    <!-- CARD -->

                    <label class="pch-online-method">

                        <input
                            type="radio"
                            name="online_choice"
                            value="card"
                        >

                        <i class="bi bi-credit-card"></i>

                        Credit / Debit Card

                    </label>

                    <p class="pch-payment-info">
                        This checkout uses practice payments.
                        No real money is collected.
                        Do not enter real card or banking details.
                    </p>

                </div>

            <?php endif; ?>

        </div>

        <!-- ====================================
             RIGHT: ORDER SUMMARY
             ==================================== -->

        <div class="pch-panel">

            <h2>Order Summary</h2>

            <!-- ORDER ITEMS -->

            <?php foreach ($data['lines'] as $line): ?>

                <div class="pch-summary-row">

                    <span>
                        <?= pch_h(
                            $line['item']['item_name']
                        ) ?>

                        × <?= (int)$line['qty'] ?>
                    </span>

                    <strong>
                        <?= pch_money($line['total']) ?>
                    </strong>

                </div>

            <?php endforeach; ?>

            <hr>

            <!-- SUBTOTAL -->

            <div class="pch-summary-row">

                <span>Subtotal</span>

                <strong>
                    <?= pch_money($subtotal) ?>
                </strong>

            </div>

            <!-- TAX -->

            <div class="pch-summary-row">

                <span>
                    Service Tax
                    (<?= number_format(
                        PCH_TAX_RATE * 100,
                        0
                    ) ?>%)
                </span>

                <strong>
                    <?= pch_money($tax) ?>
                </strong>

            </div>

            <!-- DELIVERY FEE -->

            <div class="pch-summary-row">

                <span>Delivery Fee</span>

                <strong>
                    <?= pch_money($deliveryFee) ?>
                </strong>

            </div>

            <!-- ROUNDING ADJUSTMENT -->

            <?php if ($orderType === 'dine_in'): ?>

                <div class="pch-summary-row pch-rounding-row">

                    <span>Rounding Adjustment</span>

                    <strong>

                        <?= $roundingAdjustment > 0
                            ? '+'
                            : (
                                $roundingAdjustment < 0
                                    ? '-'
                                    : ''
                            )
                        ?>

                        <?= pch_money(
                            abs($roundingAdjustment)
                        ) ?>

                    </strong>

                </div>

            <?php endif; ?>

            <hr>

            <!-- TOTAL -->

            <div class="pch-summary-row pch-total">

                <span>Total</span>

                <strong>
                    <?= pch_money($total) ?>
                </strong>

            </div>

            <!-- PAYMENT NOTE -->

            <p class="pch-note">

                <?php if ($orderType === 'dine_in'): ?>

                    Cash payments are rounded to the nearest 5 sen.
                    Please pay at the counter.

                <?php else: ?>

                    Online payment uses the exact total amount.

                <?php endif; ?>

            </p>

            <!-- SUBMIT -->

            <button
                class="pch-primary w-100 mt-3"
                type="submit"
                id="pch-submit"
            >

                <?= $orderType === 'delivery'
                    ? 'Continue to Payment'
                    : 'Place Order'
                ?>

            </button>

        </div>

    </form>

</main>

<script>

// ============================================
// CHECKOUT PAYMENT METHOD
// ============================================

const form = document.getElementById('pch-checkout');

const orderType = <?= json_encode($orderType) ?>;

const bankSection = document.getElementById(
    'pch-bank-section'
);

const bankSelect = document.getElementById(
    'pch-bank'
);

function syncCheckout() {

    if (!bankSection || !bankSelect) {
        return;
    }

    const selected = form.querySelector(
        'input[name="online_choice"]:checked'
    );

    const isBank =
        orderType === 'delivery' &&
        selected !== null &&
        selected.value === 'fpx';

    bankSection.hidden = !isBank;

    bankSelect.required = isBank;
    bankSelect.disabled = !isBank;

    if (!isBank) {
        bankSelect.value = '';
    }
}

form.addEventListener('change', syncCheckout);

syncCheckout();

// ============================================
// EDIT DELIVERY ADDRESS
// ============================================

const addressEditBtn = document.getElementById(
    'pch-address-edit-btn'
);

const addressEditor = document.getElementById(
    'pch-address-editor'
);

const addressPreview = document.getElementById(
    'pch-address-preview'
);

const addressInput = document.getElementById(
    'delivery_address'
);

const addressDoneBtn = document.getElementById(
    'pch-address-done-btn'
);

if (
    addressEditBtn &&
    addressEditor &&
    addressPreview &&
    addressInput &&
    addressDoneBtn
) {

    // Open address editor
    addressEditBtn.addEventListener('click', function () {

        addressEditor.hidden = false;
        addressPreview.hidden = true;

        addressEditBtn.setAttribute(
            'aria-expanded',
            'true'
        );

        addressInput.focus();
    });

    // Save edited address to the checkout form
    addressDoneBtn.addEventListener('click', function () {

        const updatedAddress = addressInput.value.trim();

        if (!updatedAddress) {

            addressInput.setCustomValidity(
                'Please enter your delivery address.'
            );

            addressInput.reportValidity();
            addressInput.focus();

            return;
        }

        addressInput.setCustomValidity('');

        addressInput.value = updatedAddress;

        // Show updated address normally
        addressPreview.textContent = updatedAddress;

        addressEditor.hidden = true;
        addressPreview.hidden = false;

        addressEditBtn.setAttribute(
            'aria-expanded',
            'false'
        );
    });

    addressInput.addEventListener('input', function () {
        addressInput.setCustomValidity('');
    });
}

</script>

<?php require __DIR__ . '/includes/footer.php'; ?>