<?php
// Prem Curry House - Customer Logout API

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

// Allow POST only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");

    sendResponse(405, [
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);
}

try {
    require_once __DIR__ . "/../config/database.php";
    require_once __DIR__ . "/auth.php";

    // Verify the customer token
    $user = authenticateCustomer($pdo);

    // Read the token from the HTTP header
    $authorization = $_SERVER["HTTP_AUTHORIZATION"]
        ?? $_SERVER["REDIRECT_HTTP_AUTHORIZATION"]
        ?? "";

    if ($authorization === "" && function_exists("getallheaders")) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, "Authorization") === 0) {
                $authorization = $value;
                break;
            }
        }
    }

    if (!preg_match(
        '/^Bearer\s+([a-fA-F0-9]{64})$/',
        trim($authorization),
        $matches
    )) {
        sendResponse(401, [
            "success" => false,
            "message" => "Invalid authentication token."
        ]);
    }

    // Delete this specific token from the database
    $tokenHash = hash("sha256", $matches[1]);

    $stmt = $pdo->prepare(
        "DELETE FROM api_tokens
         WHERE token_hash = ?
           AND user_id = ?"
    );

    $stmt->execute([
        $tokenHash,
        (int) $user["user_id"]
    ]);

    sendResponse(200, [
        "success" => true,
        "message" => "Logout successful."
    ]);

} catch (Throwable $exception) {

    error_log($exception->getMessage());

    sendResponse(500, [
        "success" => false,
        "message" => "Unable to log out."
    ]);
}
