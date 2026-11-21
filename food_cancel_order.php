<?php
require_once __DIR__ . '/food_order/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: food_my_orders.php');
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

$userId = (int)$_SESSION['user_id'];

$orderId = filter_var(
    $_POST['order_id'] ?? null,
    FILTER_VALIDATE_INT
);

$reason = trim((string)($_POST['cancel_reason'] ?? ''));

$allowedReasons = [
    'Ordered by mistake',
    'Wrong food items selected',
    'Wrong delivery address',
    'Changed my mind',
    'Delivery taking too long',
    'Other'
];

if (!$orderId || $orderId < 1) {
    http_response_code(422);
    exit('Invalid order ID.');
}

if (!in_array($reason, $allowedReasons, true)) {
    http_response_code(422);
    exit('Please select a valid cancellation reason.');
}

if ($reason === 'Other') {
    $reason = trim((string)($_POST['other_reason'] ?? ''));

    if ($reason === '' || mb_strlen($reason, 'UTF-8') > 500) {
        http_response_code(422);
        exit('Please enter a valid cancellation reason.');
    }
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            order_id,
            user_id,
            order_status,
            payment_status,
            total
        FROM pch_food_orders
        WHERE order_id = ?
          AND user_id = ?
        FOR UPDATE
    ");

    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new RuntimeException(
            'Order not found or access denied.'
        );
    }

    if (!in_array(
        $order['order_status'],
        ['Pending', 'Confirmed'],
        true
    )) {
        throw new RuntimeException(
            'This order can no longer be cancelled.'
        );
    }

    $paymentStatus = $order['payment_status'];
    $refundAmount = 0.00;

    if ($paymentStatus === 'demo_paid') {
        $paymentStatus = 'demo_refunded';
        $refundAmount = (float)$order['total'];
    } elseif (in_array(
        $paymentStatus,
        ['demo_unpaid', 'unpaid'],
        true
    )) {
        $refundAmount = 0.00;
    } else {
        throw new RuntimeException(
            'This order requires restaurant assistance for cancellation.'
        );
    }

    $update = $pdo->prepare("
        UPDATE pch_food_orders
        SET
            order_status = 'Cancelled',
            payment_status = ?,
            cancel_reason = ?,
            refund_amount = ?,
            cancelled_at = NOW()
        WHERE order_id = ?
          AND user_id = ?
    ");

    $update->execute([
        $paymentStatus,
        $reason,
        $refundAmount,
        $orderId,
        $userId
    ]);

    $pdo->commit();

    header('Location: food_my_orders.php?cancelled=1');
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

    error_log('Food cancellation: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to cancel order. Please try again.');
}