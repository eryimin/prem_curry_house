
<?php
session_start();

// Only admin can delete menu items
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

// Only accept POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}

// Check CSRF token
if (
    !isset($_POST["csrf_token"], $_SESSION["csrf_token"]) ||
    !hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"]
    )
) {
    http_response_code(403);
    exit("Invalid form request.");
}

// Validate item ID
$itemId = filter_var(
    $_POST["item_id"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$itemId || $itemId < 1) {
    http_response_code(400);
    exit("Invalid menu item ID.");
}

// Delete selected item
$stmt = $pdo->prepare(
    "DELETE FROM menu_items WHERE item_id = :item_id"
);

$stmt->execute([
    "item_id" => $itemId
]);

// Redirect with success message
if ($stmt->rowCount() > 0) {
    header("Location: menu_items.php?deleted=1");
} else {
    header("Location: menu_items.php?notfound=1");
}

exit;
?>
