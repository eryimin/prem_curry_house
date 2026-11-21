<?php
require_once __DIR__ . '/food_order/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: food_checkout.php');
    exit;
}

pch_verify();

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'guest'
) {
    header('Location: login.php');
    exit;
}

$data = pch_calculate($pdo, pch_cart());

if (!$data['lines']) {
    header('Location: food_cart.php');
    exit;
}

// Customer information
$name = trim((string)($_POST['customer_name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));

if (
    $name === '' ||
    mb_strlen($name, 'UTF-8') > 100 ||
    !preg_match('/^[0-9+() \-]{7,20}$/', $phone)
) {
    http_response_code(422);
    exit('Enter a valid name and contact number.');
}

// Order type
$fulfilment = (string)($_SESSION['order_type'] ?? '');

if (!in_array($fulfilment, ['dine_in', 'delivery'], true)) {
    http_response_code(422);
    exit('Please select Dine In or Delivery before ordering.');
}

$tableNumber = null;
$address = null;
$deliveryArea = null;

// Dine In
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
        exit('Please select a valid table number.');
    }
}

// Delivery
if ($fulfilment === 'delivery') {
    if (!PCH_DELIVERY_ENABLED) {
        http_response_code(422);
        exit('Delivery is currently unavailable.');
    }

    $address = trim((string)($_SESSION['delivery_address'] ?? ''));
    $deliveryArea = (string)($_SESSION['delivery_area'] ?? '');

    if (
        $address === '' ||
        mb_strlen($address, 'UTF-8') > 500
    ) {
        http_response_code(422);
        exit('A valid delivery address is required.');
    }

    if (!array_key_exists($deliveryArea, pch_delivery_areas())) {
        http_response_code(422);
        exit('Please select a valid delivery area.');
    }
}

// Payment method
$payment = (string)($_POST['payment_method'] ?? '');
$choice = null;
$bank = null;

if ($fulfilment === 'dine_in' && $payment !== 'counter') {
    http_response_code(422);
    exit('Dine In orders must be paid at the counter.');
}

if ($fulfilment === 'delivery') {
    if ($payment !== 'online_demo') {
        http_response_code(422);
        exit('Please select an online payment method.');
    }

    $choice = (string)($_POST['online_choice'] ?? '');

    if (!in_array($choice, ['tng', 'fpx', 'card'], true)) {
        http_response_code(422);
        exit('Invalid online payment selection.');
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

// Calculate tax and delivery fee
$subtotal = (float)$data['subtotal'];
$tax = round($subtotal * PCH_TAX_RATE, 2);

$deliveryFee = pch_delivery_fee(
    $fulfilment,
    $deliveryArea
);

// Calculate rounding and final total
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
    : 'demo_unpaid';

try {
    $pdo->beginTransaction();

    // Recheck menu prices and availability
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

        $now = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$now || (int)$now['is_available'] !== 1) {
            throw new RuntimeException('Item became unavailable.');
        }

        $modifier = round(
            $line['unit'] - (float)$line['item']['price'],
            2
        );

        $currentPrice = round(
            (float)$now['price'] + $modifier,
            2
        );

        if ($currentPrice !== $line['unit']) {
            throw new RuntimeException(
                'Menu price changed. Please review your cart.'
            );
        }
    }

    // Save order
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

    $uid = filter_var(
        $_SESSION['user_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$uid || $uid < 1) {
        throw new RuntimeException('Invalid customer session.');
    }

    $stmt->execute([
        $uid,
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

    // Save ordered items
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

    unset($_SESSION['pch_food_cart']);
    $_SESSION['pch_last_food_order'] = $orderId;
    unset($_SESSION['pch_demo_payment_failed']);

    // Delivery goes to simulated payment
    if (
        $fulfilment === 'delivery' &&
        $payment === 'online_demo'
    ) {
        header('Location: food_demo_payment.php');
        exit;
    }

    // Dine In goes to success page
    header('Location: food_order_success.php');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('PCH food ordering: ' . $e->getMessage());

    http_response_code(500);
    exit('Could not place order. Please check the order details and try again.');
}