<?php
// Prem Curry House - Customer Enquiry API

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

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

    // Verify customer login
    $user = authenticateCustomer($pdo);

    // Read JSON
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

    // Read enquiry details
    $phone = $data["phone"] ?? "";
    $subject = $data["subject"] ?? "";
    $message = $data["message"] ?? "";

    // Validate input types
    if (
        !is_string($phone) ||
        !is_string($subject) ||
        !is_string($message)
    ) {
        sendResponse(400, [
            "success" => false,
            "message" => "Invalid input types."
        ]);
    }

    $phone = trim($phone);
    $subject = trim($subject);
    $message = trim($message);

    // Validate phone
    if (!preg_match('/^\+?[0-9][0-9\s-]{7,18}$/', $phone)) {
        sendResponse(400, [
            "success" => false,
            "message" => "Enter a valid phone number."
        ]);
    }

    // Validate subject
    if ($subject === "" || mb_strlen($subject) > 150) {
        sendResponse(400, [
            "success" => false,
            "message" => "Enter a valid enquiry subject."
        ]);
    }

    // Validate message
    if ($message === "" || mb_strlen($message) > 2000) {
        sendResponse(400, [
            "success" => false,
            "message" => "Enter a valid enquiry message."
        ]);
    }

    // Customer details come from verified login token
    $userId = (int) $user["user_id"];
    $customerName = $user["full_name"];
    $email = $user["email"];

    // Save enquiry to MySQL
    $stmt = $pdo->prepare(
        "INSERT INTO tiffin_enquiries
        (
            user_id,
            customer_name,
            email,
            phone,
            subject,
            message,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, 'New')"
    );

    $stmt->execute([
        $userId,
        $customerName,
        $email,
        $phone,
        $subject,
        $message
    ]);

    $enquiryId = (int) $pdo->lastInsertId();

    
    sendResponse(201, [
        "success" => true,
        "message" => "Enquiry submitted successfully.",
        "enquiry" => [
            "enquiry_id" => $enquiryId,
            "subject" => $subject,
            "status" => "New"
        ]
    ]);


} catch (Throwable $exception) {

    error_log($exception->getMessage());

    sendResponse(500, [
        "success" => false,
        "message" => "Unable to submit enquiry."
    ]);
}
