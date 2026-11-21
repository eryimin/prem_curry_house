<?php
// Prem Curry House - API Authentication Helper

function authenticateCustomer(PDO $pdo): array
{
    // Read the Authorization header
    $headers = function_exists("getallheaders")
        ? getallheaders()
        : [];

    $authorization = $_SERVER["HTTP_AUTHORIZATION"]
        ?? $_SERVER["REDIRECT_HTTP_AUTHORIZATION"]
        ?? "";

    if ($authorization === "") {
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, "Authorization") === 0) {
                $authorization = $value;
                break;
            }
        }
    }

    // Check Bearer token format
    if (!preg_match(
        '/^Bearer\s+([a-fA-F0-9]{64})$/',
        trim($authorization),
        $matches
    )) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Authentication token required."
        ]);
        exit;
    }

    // Hash the provided token
    $tokenHash = hash("sha256", $matches[1]);

    // Check token and expiry in MySQL
    $stmt = $pdo->prepare(
        "SELECT
            u.user_id,
            u.full_name,
            u.email,
            u.role
         FROM api_tokens t
         INNER JOIN users u
            ON t.user_id = u.user_id
         WHERE t.token_hash = ?
           AND t.expires_at > UTC_TIMESTAMP()
           AND u.role = 'guest'
         LIMIT 1"
    );

    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Invalid or expired token."
        ]);
        exit;
    }

    return $user;
}