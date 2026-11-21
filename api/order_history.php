<?php
// Prem Curry House - Customer Order History API

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

// Allow GET requests only
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only GET requests are allowed."
    ]);
    exit;
}

try {
    require_once __DIR__ . "/../config/database.php";
    require_once __DIR__ . "/auth.php";

    // Authenticate customer
    $user = authenticateCustomer($pdo);

    $userId = (int) $user["user_id"];

    // Retrieve only this customer's orders
    $stmt = $pdo->prepare(
        "SELECT
            o.order_id,
            o.customer_name,
            o.start_date,
            o.status,
            o.special_requests,
            o.created_at,
            p.plan_name,
            p.price,
            p.duration_days
         FROM tiffin_orders o
         INNER JOIN tiffin_plans p
            ON o.plan_id = p.plan_id
         WHERE o.user_id = ?
         ORDER BY o.created_at DESC, o.order_id DESC"
    );

    $stmt->execute([$userId]);

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);

    echo json_encode([
        "success" => true,
        "message" => "Order history retrieved successfully.",
        "count" => count($orders),
        "data" => $orders
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $exception) {

    error_log($exception->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve order history."
    ]);
}
