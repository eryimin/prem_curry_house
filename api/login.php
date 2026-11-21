<?php
// Prem Curry House - Customer Login REST API

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

// Allow POST requests only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");

    sendResponse(405, [
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);
}

// Check Content-Type
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

    // Read JSON request
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

    $email = $data["email"] ?? "";
    $password = $data["password"] ?? "";

    // Validate input types
    if (!is_string($email) || !is_string($password)) {
        sendResponse(400, [
            "success" => false,
            "message" => "Invalid input."
        ]);
    }

    $email = trim($email);

    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150 ||
        $password === ""
    ) {
        sendResponse(400, [
            "success" => false,
            "message" => "Please enter a valid email and password."
        ]);
    }

    // Find customer account
    $stmt = $pdo->prepare(
        "SELECT
            user_id,
            full_name,
            email,
            password,
            role
         FROM users
         WHERE email = ?
         LIMIT 1"
    );

    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify password and customer role
    if (
        !$user ||
        !password_verify($password, $user["password"]) ||
        $user["role"] !== "guest"
    ) {
        sendResponse(401, [
            "success" => false,
            "message" => "Invalid customer email or password."
        ]);
    }

    // Generate secure random token
    $token = bin2hex(random_bytes(32));

    // Store only the token hash
    $tokenHash = hash("sha256", $token);

    // Token is valid for 7 days
    $expiresAt = gmdate(
        "Y-m-d H:i:s",
        time() + (7 * 24 * 60 * 60)
    );

    // Save token in MySQL
    $insert = $pdo->prepare(
        "INSERT INTO api_tokens
            (user_id, token_hash, expires_at)
         VALUES (?, ?, ?)"
    );

    $insert->execute([
        (int) $user["user_id"],
        $tokenHash,
        $expiresAt
    ]);

    // Return successful login
    sendResponse(200, [
        "success" => true,
        "message" => "Login successful.",
        "token" => $token,
        "token_type" => "Bearer",
        "expires_at" => $expiresAt . " UTC",
        "user" => [
            "user_id" => (int) $user["user_id"],
            "full_name" => $user["full_name"],
            "email" => $user["email"],
            "role" => $user["role"]
        ]
    ]);

} catch (Throwable $exception) {

    error_log($exception->getMessage());

    sendResponse(500, [
        "success" => false,
        "message" => "Unable to log in. Please try again."
    ]);
}
