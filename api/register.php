
<?php
// Prem Curry House - Customer Registration API

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

// Return JSON response
function sendResponse(int $status, array $data): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

// Only POST requests are allowed
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");

    sendResponse(405, [
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);
}

// Accept JSON requests
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

    $fullName = $data["full_name"] ?? "";
    $email = $data["email"] ?? "";
    $password = $data["password"] ?? "";

    if (
        !is_string($fullName) ||
        !is_string($email) ||
        !is_string($password)
    ) {
        sendResponse(400, [
            "success" => false,
            "message" => "Invalid input types."
        ]);
    }

    $fullName = trim($fullName);
    $email = trim($email);

    // Validate full name
    if ($fullName === "" || mb_strlen($fullName) > 100) {
        sendResponse(400, [
            "success" => false,
            "message" => "Enter a valid full name."
        ]);
    }

    // Validate email
    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150
    ) {
        sendResponse(400, [
            "success" => false,
            "message" => "Enter a valid email address."
        ]);
    }

    // Validate password
    if (strlen($password) < 8 || strlen($password) > 72) {
        sendResponse(400, [
            "success" => false,
            "message" => "Password must contain 8 to 72 characters."
        ]);
    }

    // Check whether email already exists
    $check = $pdo->prepare(
        "SELECT user_id FROM users WHERE email = ? LIMIT 1"
    );

    $check->execute([$email]);

    if ($check->fetch()) {
        sendResponse(409, [
            "success" => false,
            "message" => "This email address is already registered."
        ]);
    }

    // Hash password securely
    $hashedPassword = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    // Insert customer account
    // Role is always guest - never accept admin role from input
    $stmt = $pdo->prepare(
        "INSERT INTO users
            (full_name, email, password, role)
         VALUES
            (?, ?, ?, 'guest')"
    );

    $stmt->execute([
        $fullName,
        $email,
        $hashedPassword
    ]);

    sendResponse(201, [
        "success" => true,
        "message" => "Customer registered successfully.",
        "user" => [
            "user_id" => (int) $pdo->lastInsertId(),
            "full_name" => $fullName,
            "email" => $email,
            "role" => "guest"
        ]
    ]);

} catch (PDOException $exception) {

    error_log($exception->getMessage());

    if ($exception->getCode() === "23000") {
        sendResponse(409, [
            "success" => false,
            "message" => "This email address is already registered."
        ]);
    }

    sendResponse(500, [
        "success" => false,
        "message" => "Registration failed."
    ]);

} catch (Throwable $exception) {

    error_log($exception->getMessage());

    sendResponse(500, [
        "success" => false,
        "message" => "An unexpected error occurred."
    ]);
}
