
<?php
// ============================================
// PREM CURRY HOUSE - SIMULATED PAYMENT HANDLER
// ============================================

require_once __DIR__ . '/food_order/bootstrap.php';

// ============================================
// 1. VALIDATE REQUEST
// ============================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: menu.php');
    exit;
}

pch_verify();

// ============================================
// 2. CHECK CUSTOMER SESSION
// ============================================

$userId = (int)($_SESSION['user_id'] ?? 0);
$orderId = (int)(
    $_SESSION['pch_last_food_order'] ?? 0
);

if ($userId < 1 || $orderId < 1) {
    http_response_code(403);
    exit('Please log in to continue.');
}

// ============================================
// 3. GET PAYMENT RESULT
// ============================================

$result = (string)(
    $_POST['result'] ?? ''
);

if (!in_array(
    $result,
    ['success', 'failed'],
    true
)) {
    http_response_code(422);
    exit('Invalid demo payment result.');
}

// ============================================
// 4. LOAD ORDER
// ============================================

try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            order_id,
            user_id,
            fulfilment,
            payment_method,
            online_choice,
            payment_status
        FROM pch_food_orders
        WHERE order_id = ?
          AND user_id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $orderId,
        $userId
    ]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new RuntimeException(
            'Order not found or access denied.'
        );
    }

    // ========================================
    // 5. VERIFY PAYMENT ELIGIBILITY
    // ========================================

    if (
        $order['fulfilment'] !== 'delivery' ||
        $order['payment_method'] !== 'online_demo' ||
        $order['payment_status'] !== 'demo_unpaid'
    ) {
        throw new RuntimeException(
            'This order is not awaiting demo payment.'
        );
    }

    $method = $order['online_choice'];

    // ========================================
    // 6. VALIDATE TEST CARD
    // ========================================

    if ($method === 'card') {

        if ($result === 'success') {

            $card = preg_replace(
                '/\s+/',
                '',
                (string)($_POST['test_card'] ?? '')
            );

            $expiry = trim(
                (string)($_POST['test_expiry'] ?? '')
            );

            $cvc = trim(
                (string)($_POST['test_cvc'] ?? '')
            );

            // Accept only fictional test details.
            // Never store or log these fields.

            if (
                $card !== '4242424242424242' ||
                $expiry !== '12/30' ||
                $cvc !== '123'
            ) {
                throw new RuntimeException(
                    'Invalid fictional test card details. ' .
                    'Use the test values shown on the payment page.'
                );
            }

        }

    } elseif (!in_array(
        $method,
        ['fpx', 'tng'],
        true
    )) {

        throw new RuntimeException(
            'Unsupported demo payment method.'
        );
    }

    // ========================================
    // 7. PROCESS DEMO PAYMENT RESULT
    // ========================================

    if ($result === 'success') {

        $update = $pdo->prepare("
            UPDATE pch_food_orders
            SET payment_status = 'demo_paid'
            WHERE order_id = ?
              AND user_id = ?
              AND payment_status = 'demo_unpaid'
        ");

        $update->execute([
            $orderId,
            $userId
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException(
                'Unable to confirm demo payment.'
            );
        }

        $pdo->commit();

        unset($_SESSION['pch_demo_payment_failed']);

        header('Location: food_order_success.php');
        exit;

    }

    // ========================================
    // 8. SIMULATED FAILED PAYMENT
    // ========================================

    // Keep demo_unpaid in MySQL so the customer
    // can retry without creating another order.

    $pdo->commit();

    $_SESSION['pch_demo_payment_failed'] = true;

    header('Location: food_payment_failed.php');
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
        'PCH demo payment error: ' . $e->getMessage()
    );

    http_response_code(500);

    exit('Unable to process the demo payment.');
}
