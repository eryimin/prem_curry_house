
<?php

session_start();

// Only admin and staff can access this page
if (
    !isset($_SESSION["user_id"]) ||
    !in_array($_SESSION["role"] ?? "", ["admin", "staff"], true)
) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

// Generate a CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Check the item ID from the URL
$itemId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$itemId || $itemId < 1) {
    http_response_code(400);
    exit("Invalid menu item ID.");
}

// Retrieve the existing menu item
$stmt = $pdo->prepare(
    "SELECT * FROM menu_items WHERE item_id = :id"
);

$stmt->execute(["id" => $itemId]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    exit("Menu item not found.");
}

// Retrieve categories
$stmt = $pdo->query(
    "SELECT category_id, category_name
     FROM categories
     ORDER BY category_id"
);

$categories = $stmt->fetchAll();

$errors = [];

// Pre-fill the form with existing values
$itemName = $item["item_name"];
$categoryId = $item["category_id"];
$price = $item["price"];
$description = $item["description"] ?? "";
$isAvailable = (string) $item["is_available"];

// Process form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $itemName = trim($_POST["item_name"] ?? "");
    $categoryId = $_POST["category_id"] ?? "";
    $price = trim($_POST["price"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $isAvailable = $_POST["is_available"] ?? "";

    // Check CSRF token
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {
        $errors[] = "Invalid form request. Please refresh the page.";
    }

    // Validate item name
    if ($itemName === "") {
        $errors[] = "Menu item name is required.";
    } elseif (mb_strlen($itemName) > 150) {
        $errors[] = "Menu item name is too long.";
    }

    // Validate category
    $validCategoryIds = array_map(
        "intval",
        array_column($categories, "category_id")
    );

    if (
        filter_var($categoryId, FILTER_VALIDATE_INT) === false ||
        !in_array((int) $categoryId, $validCategoryIds, true)
    ) {
        $errors[] = "Please select a valid category.";
    }

    // Validate price
    if (
        !preg_match('/^\d{1,6}(\.\d{1,2})?$/', $price) ||
        (float) $price <= 0 ||
        (float) $price > 999999.99
    ) {
        $errors[] = "Please enter a valid price greater than RM 0.";
    }

    // Validate description
    if (mb_strlen($description) > 1000) {
        $errors[] = "Description cannot exceed 1000 characters.";
    }

    // Validate availability
    if (!in_array($isAvailable, ["0", "1"], true)) {
        $errors[] = "Please select a valid availability status.";
    }

    // Update the database
    if (empty($errors)) {

        $sql = "
            UPDATE menu_items
            SET
                item_name = :item_name,
                category_id = :category_id,
                price = :price,
                description = :description,
                is_available = :is_available
            WHERE item_id = :item_id
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            "item_name" => $itemName,
            "category_id" => (int) $categoryId,
            "price" => $price,
            "description" => $description,
            "is_available" => (int) $isAvailable,
            "item_id" => $itemId
        ]);

        // Return to Manage Menu
        header("Location: menu_items.php?updated=1");
        exit;
    }
}

// Escape text for safe HTML output
function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit Menu Item | Prem Curry House</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="admin-body">

<!-- ADMIN NAVBAR -->
<nav class="navbar admin-navbar">
    <div class="container-fluid px-4">

        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-flower1 me-2"></i>
            PREM CURRY HOUSE
        </a>

        <a href="menu_items.php"
           class="btn btn-outline-success btn-sm">
            <i class="bi bi-arrow-left me-1"></i>
            Back to Menu
        </a>

    </div>
</nav>

<main class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-8">

            <div class="dashboard-panel">

                <h2 class="fw-bold mb-2" style="color:#527B35;">
                    <i class="bi bi-pencil-square me-2"></i>
                    Edit Menu Item
                </h2>

                <p class="text-muted mb-4">
                    Update the details of this dish or drink.
                </p>

                <!-- VALIDATION ERRORS -->
                <?php if (!empty($errors)): ?>

                    <div class="alert alert-danger">

                        <strong>Please correct the following:</strong>

                        <ul class="mb-0 mt-2">

                            <?php foreach ($errors as $error): ?>

                                <li><?php echo e($error); ?></li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                <?php endif; ?>

                <!-- EDIT FORM -->
                <form
                    action="edit_item.php?id=<?php echo $itemId; ?>"
                    method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo e($_SESSION["csrf_token"]); ?>">

                    <!-- ITEM NAME -->
                    <div class="mb-3">

                        <label for="item_name"
                               class="form-label fw-semibold">
                            Menu Item Name *
                        </label>

                        <input
                            type="text"
                            id="item_name"
                            name="item_name"
                            class="form-control"
                            maxlength="150"
                            value="<?php echo e($itemName); ?>"
                            required>

                    </div>

                    <!-- CATEGORY -->
                    <div class="mb-3">

                        <label for="category_id"
                               class="form-label fw-semibold">
                            Category *
                        </label>

                        <select
                            id="category_id"
                            name="category_id"
                            class="form-select"
                            required>

                            <option value="">Select a category</option>

                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?php echo (int) $category["category_id"]; ?>"
                                    <?php echo (string) $categoryId === (string) $category["category_id"] ? "selected" : ""; ?>>

                                    <?php echo e($category["category_name"]); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- PRICE -->
                    <div class="mb-3">

                        <label for="price"
                               class="form-label fw-semibold">
                            Price (RM) *
                        </label>

                        <input
                            type="number"
                            id="price"
                            name="price"
                            class="form-control"
                            min="0.01"
                            max="999999.99"
                            step="0.01"
                            value="<?php echo e($price); ?>"
                            required>

                    </div>

                    <!-- DESCRIPTION -->
                    <div class="mb-3">

                        <label for="description"
                               class="form-label fw-semibold">
                            Description
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            class="form-control"
                            rows="4"
                            maxlength="1000"><?php echo e($description); ?></textarea>

                    </div>

                    <!-- AVAILABILITY -->
                    <div class="mb-4">

                        <label for="is_available"
                               class="form-label fw-semibold">
                            Availability *
                        </label>

                        <select
                            id="is_available"
                            name="is_available"
                            class="form-select"
                            required>

                            <option
                                value="1"
                                <?php echo $isAvailable === "1" ? "selected" : ""; ?>>
                                Available
                            </option>

                            <option
                                value="0"
                                <?php echo $isAvailable === "0" ? "selected" : ""; ?>>
                                Unavailable
                            </option>

                        </select>

                    </div>

                    <!-- BUTTONS -->
                    <div class="d-flex flex-wrap gap-3">

                        <button
                            type="submit"
                            class="btn btn-success">

                            <i class="bi bi-check-circle me-2"></i>
                            Save Changes

                        </button>

                        <a href="menu_items.php"
                           class="btn btn-outline-secondary">
                            Cancel
                        </a>

                    </div>

                </form>

            </div>

        </div>

    </div>

</main>

</body>
</html>
