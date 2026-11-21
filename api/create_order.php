<?php
// Prem Curry House - Create Tiffin Order API

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

// Return JSON responses
function sendResponse(int $status, array $data): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");

    sendResponse(405, [
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);
}

// Require JSON
$contentType = $_SERVER["CONTENT_TYPE"] ?? "";

if (!preg_match('/^application\/json(?:\s*;|$)/i', $contentType)) {
    sendResponse(415, [
        "success" => false,
        "message" => "Content-Type must be application/json."
    ]);
}

// Limit request size
if ((int) ($_SERVER["CONTENT_LENGTH"] ?? 0) > 10000) {
    sendResponse(413, [
        "success" => false,
        "message" => "Request is too large."
    ]);
}

try {
    require_once __DIR__ . "/../config/database.php";
    require_once __DIR__ . "/auth.php";

    // Authenticate customer using Bearer token
    $user = authenticateCustomer($pdo);
    $userId = (int) $user["user_id"];

    // Read JSON input
    $rawInput = file_get_contents(
        "php://input",
        false,
        null,
        0,
        10001
    );

    if (strlen($rawInput) > 10000) {
        sendResponse(413, [
            "success" => false,
            "message" => "Request is too large."
        ]);
    }

    $data = json_decode($rawInput, true);

    if (!is_array($data) || array_is_list($data)) {
        sendResponse(400, [
            "success" => false,
            "message" => "Invalid JSON object."
        ]);
    }

    // Get customer input
    $planId = $data["plan_id"] ?? null;
    $customerName = $data["customer_name"] ?? "";
    $phone = $data["phone"] ?? "";
    $deliveryAddress = $data["delivery_address"] ?? "";
    $startDate = $data["start_date"] ?? "";
    $specialRequests = $data["special_requests"] ?? "";

    // Validate field types
    if (
        (!is_int($planId) && !is_string($planId)) ||
        !is_string($customerName) ||
        !is_string($phone) ||
        !is_string($deliveryAddress) ||
        !is_string($startDate) ||
        !is_string($specialRequests)
    ) {
        sendResponse(400, [
            "success" => false,
            "message" => "Invalid input types."
        ]);
    }

    // Validate plan ID
    $validatedPlanId = filter_var(
        $planId,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if ($validatedPlanId === false) {
        sendResponse(400, [
            "success" => false,
            "message" => "Please select a valid tiffin plan."
        ]);
    }

    // Clean text input
    $customerName = trim($customerName);
    $phone = trim($phone);
    $deliveryAddress = trim($deliveryAddress);
    $startDate = trim($startDate);
    $specialRequests = trim($specialRequests);

    // Validate name
    if ($customerName === "" || mb_strlen($customerName) > 100) {
        sendResponse(400, [
            "success" => false,
            "message" => "Enter a valid customer name."
        ]);
    }

    // Validate Malaysian contact number
    if (!preg_match('/^\+?[0-9][0-9\s-]{7,18}$/', $phone)) {
        sendResponse(400, [
            "success" => false,
            "message" => "Enter a valid phone number."
        ]);
    }

    // Validate address
    if (
        $deliveryAddress === "" ||
        mb_strlen($deliveryAddress) > 500
    ) {
        sendResponse(400, [
            "success" => false,
            "message" => "Enter a valid delivery address."
        ]);
    }

    // Validate special requests
    if (mb_strlen($specialRequests) > 1000) {
        sendResponse(400, [
            "success" => false,
            "message" => "Special requests are too long."
        ]);
    }

    // Validate date in YYYY-MM-DD format
    $date = DateTimeImmutable::createFromFormat(
        "!Y-m-d",
        $startDate
    );

    if (
        !$date ||
        $date->format("Y-m-d") !== $startDate
    ) {
        sendResponse(400, [
            "success" => false,
            "message" => "Start date must be YYYY-MM-DD."
        ]);
    }

    // Use Malaysia's local date
    $today = new DateTimeImmutable(
        "today",
        new DateTimeZone("Asia/Kuala_Lumpur")
    );

    if ($date < $today) {
        sendResponse(400, [
            "success" => false,
            "message" => "Start date cannot be in the past."
        ]);
    }

    // Check whether the selected plan is available
    $planStmt = $pdo->prepare(
        "SELECT plan_id, plan_name, price, duration_days
         FROM tiffin_plans
         WHERE plan_id = ?
           AND is_available = 1
         LIMIT 1"
    );

    $planStmt->execute([$validatedPlanId]);
    $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        sendResponse(404, [
            "success" => false,
            "message" => "Selected tiffin plan is unavailable."
        ]);
    }

    // Insert order into the existing tiffin_orders table
    $insert = $pdo->prepare(
        "INSERT INTO tiffin_orders
         (
            user_id,
            plan_id,
            customer_name,
            phone,
            delivery_address,
            start_date,
            special_requests,
            status
         )
         VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')"
    );

    $insert->execute([
        $userId,
        $validatedPlanId,
        $customerName,
        $phone,
        $deliveryAddress,
        $startDate,
        $specialRequests === "" ? null : $specialRequests
    ]);

    $orderId = (int) $pdo->lastInsertId();

    sendResponse(201, [
        "success" => true,
        "message" => "Tiffin order submitted successfully.",
        "order" => [
            "order_id" => $orderId,
            "plan_id" => (int) $plan["plan_id"],
            "plan_name" => $plan["plan_name"],
            "price" => $plan["price"],
            "duration_days" => (int) $plan["duration_days"],
            "customer_name" => $customerName,
            "start_date" => $startDate,
            "status" => "Pending"
        ]
    ]);

} catch (Throwable $exception) {

    error_log($exception->getMessage());

    sendResponse(500, [
        "success" => false,
        "message" => "Unable to submit tiffin order."
    ]);
}
