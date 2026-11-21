
<?php
// Prem Curry House - Tiffin Plans REST API

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

    // Retrieve available monthly tiffin plans
    $stmt = $pdo->prepare(
        "SELECT
            plan_id,
            plan_name,
            description,
            price,
            duration_days
         FROM tiffin_plans
         WHERE is_available = 1
         ORDER BY price ASC"
    );

    $stmt->execute();

    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);

    echo json_encode([
        "success" => true,
        "message" => "Tiffin plans retrieved successfully.",
        "count" => count($plans),
        "data" => $plans
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $exception) {

    error_log($exception->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve tiffin plans."
    ]);
}
