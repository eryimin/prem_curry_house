<?php

require_once __DIR__ . '/food_order/bootstrap.php';

// ============================================
// REQUEST AND LOGIN
// ============================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: food_checkout.php');
    exit;
}

pch_verify();

$userId = (int)($_SESSION['user_id'] ?? 0);

if (
    $userId < 1 ||
    ($_SESSION['role'] ?? '') !== 'guest'
) {
    header('Location: login.php');
    exit;
}

// ============================================
// CART
// ============================================

$data = pch_calculate($pdo, pch_cart());

if (!$data['lines']) {
    header('Location: food_cart.php');
    exit;
}

// ============================================
// ORDER TYPE
// ============================================

$fulfilment = (string)($_SESSION['order_type'] ?? '');

if (!in_array($fulfilment, ['dine_in', 'delivery'], true)) {
    http_response_code(422);
    exit('Please select Dine In or Delivery.');
}

// ============================================
// CUSTOMER DETAILS
// ============================================

$name = trim((string)($_POST['customer_name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));

if (
    $name === '' ||
    mb_strlen($name, 'UTF-8') > 100
) {
    http_response_code(422);
    exit('Please enter a valid customer name.');
}

if ($fulfilment === 'delivery' && $phone === '') {
    http_response_code(422);
    exit('Contact number is required for delivery.');
}

if (
    $phone !== '' &&
    !preg_match('/^[0-9+() \-]{7,20}$/', $phone)
) {
    http_response_code(422);
    exit('Please enter a valid contact number.');
}

// ============================================
// TABLE AND DELIVERY INFORMATION
// ============================================

$tableNumber = null;
$address = null;
$deliveryArea = null;

if ($fulfilment === 'dine_in') {

    $tableNumber = filter_var(
        $_SESSION['table_number'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        $tableNumber === false ||
        $tableNumber === null ||
        $tableNumber < 1 ||
        $tableNumber > 10
    ) {
        http_response_code(422);
        exit('Invalid table number.');
    }

} else {

    if (!PCH_DELIVERY_ENABLED) {
        http_response_code(422);
        exit('Delivery is unavailable.');
    }

    // Get the updated address from Checkout
    $address = trim(
        (string)($_POST['delivery_address'] ?? '')
    );

    $deliveryArea = (string)(
        $_SESSION['delivery_area'] ?? ''
    );

    if (
        $address === '' ||
        mb_strlen($address, 'UTF-8') > 500
    ) {
        http_response_code(422);
        exit('A valid delivery address is required.');
    }

    // Remember the updated address
    $_SESSION['delivery_address'] = $address;

    if (!array_key_exists(
        $deliveryArea,
        pch_delivery_areas()
    )) {
        http_response_code(422);
        exit('Invalid delivery area.');
    }
}

// ============================================
// PAYMENT METHOD
// ============================================

$payment = (string)($_POST['payment_method'] ?? '');

$choice = null;
$bank = null;

if ($fulfilment === 'dine_in') {

    if ($payment !== 'counter') {
        http_response_code(422);
        exit('Dine In must be paid at the counter.');
    }

} else {

    if ($payment !== 'online') {
        http_response_code(422);
        exit('Please select online payment.');
    }

    $choice = (string)(
        $_POST['online_choice'] ?? ''
    );

    if (!in_array(
        $choice,
        ['tng', 'fpx', 'card'],
        true
    )) {
        http_response_code(422);
        exit('Invalid online payment method.');
    }

    if ($choice === 'fpx') {

        $bank = (string)($_POST['bank'] ?? '');

        $allowedBanks = [
            'maybank',
            'cimb',
            'public_bank',
            'rhb',
            'hong_leong',
            'bank_islam'
        ];

        if (!in_array($bank, $allowedBanks, true)) {
            http_response_code(422);
            exit('Please select a valid bank.');
        }
    }
}

// ============================================
// CALCULATE TOTAL
// ============================================

$subtotal = (float)$data['subtotal'];

$tax = round(
    $subtotal * PCH_TAX_RATE,
    2
);

$deliveryFee = pch_delivery_fee(
    $fulfilment,
    $deliveryArea
);

$totals = pch_order_totals(
    $subtotal,
    $tax,
    $deliveryFee,
    $fulfilment
);

$roundingAdjustment = $totals['rounding_adjustment'];
$total = $totals['total'];

$paymentStatus = $payment === 'counter'
    ? 'unpaid'
    : 'pending';

// ============================================
// CHECK IF EDITING AN EXISTING ORDER
// ============================================

$editingOrderId = (int)(
    $_SESSION['pch_editing_order_id'] ?? 0
);

try {

    $pdo->beginTransaction();

    // ========================================
    // CHECK CURRENT MENU PRICES
    // ========================================

    foreach ($data['lines'] as $line) {

        $stmt = $pdo->prepare("
            SELECT price, is_available
            FROM menu_items
            WHERE item_id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            (int)$line['item']['item_id']
        ]);

        $currentItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$currentItem ||
            (int)$currentItem['is_available'] !== 1
        ) {
            throw new RuntimeException(
                'An item is no longer available.'
            );
        }

        $modifier = round(
            $line['unit'] -
            (float)$line['item']['price'],
            2
        );

        $currentPrice = round(
            (float)$currentItem['price'] + $modifier,
            2
        );

        if ($currentPrice !== $line['unit']) {
            throw new RuntimeException(
                'Menu prices changed. Please review your cart.'
            );
        }
    }

    // ========================================
    // REUSE EXISTING UNPAID ORDER
    // ========================================

    if ($editingOrderId > 0) {

        if ($fulfilment !== 'delivery') {
            throw new RuntimeException(
                'Cannot change this order to Dine In.'
            );
        }

        $stmt = $pdo->prepare("
            SELECT
                order_id,
                payment_status,
                payment_method,
                order_status
            FROM pch_food_orders
            WHERE order_id = ?
              AND user_id = ?
              AND fulfilment = 'delivery'
            FOR UPDATE
        ");

        $stmt->execute([
            $editingOrderId,
            $userId
        ]);

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$existing ||
            !in_array(
                $existing['payment_status'],
                ['demo_unpaid', 'pending'],
                true
            ) ||
            !in_array(
                $existing['payment_method'],
                ['online_demo', 'online'],
                true
            ) ||
            in_array(
                $existing['order_status'],
                ['Cancelled', 'Rejected', 'Completed'],
                true
            )
        ) {
            throw new RuntimeException(
                'This order can no longer be edited.'
            );
        }

        $orderId = $editingOrderId;

        $stmt = $pdo->prepare("
            UPDATE pch_food_orders
            SET
                customer_name = ?,
                phone = ?,
                address = ?,
                delivery_area = ?,
                table_number = NULL,
                payment_method = ?,
                online_choice = ?,
                bank = ?,
                payment_status = ?,
                subtotal = ?,
                tax_rate = ?,
                tax_amount = ?,
                delivery_fee = ?,
                rounding_adjustment = ?,
                total = ?
            WHERE order_id = ?
              AND user_id = ?
        ");

        $stmt->execute([
            $name,
            $phone,
            $address,
            $deliveryArea,
            $payment,
            $choice,
            $bank,
            $paymentStatus,
            $subtotal,
            PCH_TAX_RATE,
            $tax,
            $deliveryFee,
            $roundingAdjustment,
            $total,
            $orderId,
            $userId
        ]);

        // Remove previous items from this unpaid order.
        // We will insert its updated cart below.

        $stmt = $pdo->prepare("
            DELETE FROM pch_food_order_items
            WHERE order_id = ?
        ");

        $stmt->execute([$orderId]);

    } else {

        // ====================================
        // CREATE NEW ORDER
        // ====================================

        $stmt = $pdo->prepare("
            INSERT INTO pch_food_orders (
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
                subtotal,
                tax_rate,
                tax_amount,
                delivery_fee,
                rounding_adjustment,
                total
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $name,
            $phone,
            $fulfilment,
            $address,
            $deliveryArea,
            $tableNumber,
            $payment,
            $choice,
            $bank,
            $paymentStatus,
            $subtotal,
            PCH_TAX_RATE,
            $tax,
            $deliveryFee,
            $roundingAdjustment,
            $total
        ]);

        $orderId = (int)$pdo->lastInsertId();
    }

    // ========================================
    // SAVE UPDATED ORDER ITEMS
    // ========================================

    $ins = $pdo->prepare("
        INSERT INTO pch_food_order_items (
            order_id,
            menu_item_id,
            item_name,
            quantity,
            unit_price,
            line_total,
            options_json,
            remark
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($data['lines'] as $line) {

        $ins->execute([
            $orderId,
            (int)$line['item']['item_id'],
            $line['item']['item_name'],
            $line['qty'],
            $line['unit'],
            $line['total'],
            json_encode(
                $line['options'],
                JSON_UNESCAPED_UNICODE
            ),
            $line['remark']
        ]);
    }

    $pdo->commit();

    // ========================================
    // UPDATE SESSION
    // ========================================

    $_SESSION['pch_last_food_order'] = $orderId;

    unset(
        $_SESSION['pch_editing_order_id'],
        $_SESSION['pch_edit_customer_name'],
        $_SESSION['pch_edit_phone'],
        $_SESSION['pch_demo_payment_failed']
    );

    // ========================================
    // REDIRECT CUSTOMER
    // ========================================

    if ($fulfilment === 'delivery') {

        // Keep cart until payment is completed.
        header('Location: food_demo_payment.php');
        exit;
    }

    // Dine In
    unset($_SESSION['pch_food_cart']);

    header('Location: food_order_success.php');
    exit;

} catch (RuntimeException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(422);
    exit(pch_h($e->getMessage()));

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'PCH food order error: ' . $e->getMessage()
    );

    http_response_code(500);
    exit(
        'Could not place order. Please try again.'
    );
}