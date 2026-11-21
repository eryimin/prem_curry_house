<?php

// ============================================
// PREM CURRY HOUSE - PAYMENT CONFIRMATION
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
    exit('Invalid payment result.');
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

    // Support both old and new payment values.

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
        )
    ) {
        throw new RuntimeException(
            'This order is not awaiting payment.'
        );
    }

    $method = (string)(
        $order['online_choice'] ?? ''
    );

    // ========================================
    // 6. VALIDATE PAYMENT METHOD
    // ========================================

    if (!in_array(
        $method,
        ['card', 'fpx', 'tng'],
        true
    )) {
        throw new RuntimeException(
            'Unsupported payment method.'
        );
    }

    // ========================================
    // 7. VALIDATE PRACTICE CARD
    // ========================================

    if (
        $method === 'card' &&
        $result === 'success'
    ) {

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

        // Only accept fictional practice details.
        // Never store card numbers, CVC or expiry.

        if (
            $card !== '4242424242424242' ||
            $expiry !== '12/30' ||
            $cvc !== '123'
        ) {
            throw new RuntimeException(
                'Invalid practice card details. ' .
                'Please use the values shown on the payment page.'
            );
        }
    }

    // ========================================
    // 8. DETERMINE PAYMENT STATUS
    // ========================================

    // Previous orders retain their original status format.
    // New orders use the updated status format.

    $newPaidStatus =
        $order['payment_method'] === 'online_demo'
            ? 'demo_paid'
            : 'paid';

    // ========================================
    // 9. PROCESS PAYMENT RESULT
    // ========================================

    if ($result === 'success') {

        $update = $pdo->prepare("
            UPDATE pch_food_orders
            SET payment_status = ?
            WHERE order_id = ?
              AND user_id = ?
              AND payment_status = ?
        ");

        $update->execute([
            $newPaidStatus,
            $orderId,
            $userId,
            $order['payment_status']
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException(
                'Unable to confirm payment.'
            );
        }

        $pdo->commit();

        // Clear previous failure flag
        unset(
            $_SESSION['pch_demo_payment_failed']
        );

        // Clear cart after payment confirmation
        unset(
            $_SESSION['pch_food_cart'],
            $_SESSION['pch_editing_order_id'],
            $_SESSION['pch_edit_customer_name'],
            $_SESSION['pch_edit_phone']
        );

        header('Location: food_order_success.php');
        exit;
    }

    // ========================================
    // 10. DECLINED PAYMENT
    // ========================================

    // Keep the order unpaid so the customer
    // can retry without creating another order.

    $pdo->commit();

    $_SESSION['pch_demo_payment_failed'] = true;

    header('Location: food_payment_failed.php');
    exit;

// ============================================
// HANDLE VALIDATION ERRORS
// ============================================

} catch (RuntimeException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(422);

    exit(pch_h($e->getMessage()));

// ============================================
// HANDLE DATABASE ERRORS
// ============================================

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'PCH payment confirmation error: ' .
        $e->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to process your payment. ' .
        'Please try again.'
    );
}