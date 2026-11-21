
<?php
// Prem Curry House - Menu REST API

// Return JSON data instead of HTML
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

// Only allow GET requests
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
    // Connect to existing database
    require_once __DIR__ . "/../config/database.php";

    // Load existing Menu model
    require_once __DIR__ . "/../models/Menu.php";

    // Get optional category filter
    $categoryId = null;

    if (isset($_GET["category"])) {
        $categoryId = filter_var(
            $_GET["category"],
            FILTER_VALIDATE_INT
        );

        if ($categoryId === false || $categoryId < 1) {
            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "Invalid category ID."
            ]);
            exit;
        }
    }

    // Get optional search keyword
    $search = trim((string) ($_GET["search"] ?? ""));

    if (mb_strlen($search) > 100) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Search keyword is too long."
        ]);
        exit;
    }

    // Retrieve menu items
    $menu = new Menu($pdo);

    $items = $menu->getItems($categoryId, $search);

    // Return JSON response
    http_response_code(200);

    echo json_encode([
        "success" => true,
        "message" => "Menu retrieved successfully.",
        "count" => count($items),
        "data" => $items
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $exception) {

    // Save error details in server logs
    error_log($exception->getMessage());

    // Do not expose technical errors to users
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve menu items."
    ]);
}
