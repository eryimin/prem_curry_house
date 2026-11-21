<?php

require_once __DIR__ . '/food_order/bootstrap.php';

// ============================================
// ONLY ACCEPT POST REQUESTS
// ============================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: menu.php');
    exit;
}

// Verify CSRF security token
pch_verify();

// ============================================
// VERIFY CUSTOMER LOGIN
// ============================================

$userId = (int)(
    $_SESSION['user_id'] ?? 0
);

$orderId = (int)(
    $_SESSION['pch_last_food_order'] ?? 0
);

if (
    $userId < 1 ||
    $orderId < 1 ||
    ($_SESSION['role'] ?? '') !== 'guest'
) {
    header('Location: login.php');
    exit;
}

// ============================================
// GET RETURN DESTINATION
// ============================================

// Customer can return to either:
// 1. Checkout
// 2. Shopping Cart

$destination = (string)(
    $_POST['destination'] ?? 'checkout'
);

if (!in_array(
    $destination,
    ['checkout', 'cart'],
    true
)) {
    http_response_code(422);
    exit('Invalid return destination.');
}

// ============================================
// LOAD EXISTING ORDER
// ============================================

$stmt = $pdo->prepare("
    SELECT
        order_id,
        user_id,
        customer_name,
        phone,
        fulfilment,
        payment_method,
        payment_status,
        order_status
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
    http_response_code(404);
    exit('Order not found.');
}

// ============================================
// VERIFY ORDER CAN BE EDITED
// ============================================

// Only unpaid Delivery orders can be edited.

if (
    $order['fulfilment'] !== 'delivery' ||
    !in_array(
        $order['payment_method'],
        ['online_demo', 'online'],
        true
    ) ||
    !in_array(
        $order['payment_status'],
        ['demo_unpaid', 'pending'],
        true
    ) ||
    in_array(
        $order['order_status'],
        ['Cancelled', 'Rejected', 'Completed'],
        true
    )
) {
    http_response_code(409);

    exit(
        'This order can no longer be edited.'
    );
}

// ============================================
// RESTORE SHOPPING CART IF NECESSARY
// ============================================

// New Delivery orders normally keep the cart.
//
// For older orders, restore the cart from MySQL
// if the cart session was previously cleared.

if (empty($_SESSION['pch_food_cart'])) {

    $stmt = $pdo->prepare("
        SELECT
            menu_item_id,
            quantity,
            options_json,
            remark
        FROM pch_food_order_items
        WHERE order_id = ?
        ORDER BY menu_item_id
    ");

    $stmt->execute([$orderId]);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) {
        http_response_code(409);

        exit(
            'This order has no items to restore.'
        );
    }

    $restoredCart = [];

    foreach ($items as $item) {

        $options = json_decode(
            (string)(
                $item['options_json'] ?? '{}'
            ),
            true
        );

        if (!is_array($options)) {
            $options = [];
        }

        $restoredCart[] = [
            'id' => (int)$item['menu_item_id'],
            'qty' => (int)$item['quantity'],
            'options' => $options,
            'remark' => (string)(
                $item['remark'] ?? ''
            )
        ];
    }

    $_SESSION['pch_food_cart'] = $restoredCart;
}

// ============================================
// REMEMBER THE ORDER BEING EDITED
// ============================================

// When customer checks out again,
// food_place_order.php will update this order
// instead of creating a duplicate order.

$_SESSION['pch_editing_order_id'] = $orderId;

// ============================================
// REMEMBER CUSTOMER INFORMATION
// ============================================

$_SESSION['pch_edit_customer_name'] =
    (string)$order['customer_name'];

$_SESSION['pch_edit_phone'] =
    (string)$order['phone'];

// ============================================
// REDIRECT CUSTOMER
// ============================================

// Return to shopping cart
if ($destination === 'cart') {

    header('Location: food_cart.php');
    exit;
}

// Return to checkout
header('Location: food_checkout.php');
exit;