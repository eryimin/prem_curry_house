
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

// Create a CSRF token to protect the form
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Get all categories from MySQL
$stmt = $pdo->query(
    "SELECT category_id, category_name
     FROM categories
     ORDER BY category_id"
);

$categories = $stmt->fetchAll();

$errors = [];

$itemName = "";
$categoryId = "";
$price = "";
$description = "";
$isAvailable = "1";

// Process the form when submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $itemName = trim($_POST["item_name"] ?? "");
    $categoryId = $_POST["category_id"] ?? "";
    $price = trim($_POST["price"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $isAvailable = $_POST["is_available"] ?? "1";

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

    // Validate food name
    if ($itemName === "") {
        $errors[] = "Please enter the menu item name.";
    } elseif (mb_strlen($itemName) > 150) {
        $errors[] = "Item name cannot exceed 150 characters.";
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

    // Insert into database if validation passes
    if (empty($errors)) {

        $sql = "
            INSERT INTO menu_items
                (category_id, item_name, price,
                 description, image, is_available)
            VALUES
                (:category_id, :item_name, :price,
                 :description, NULL, :is_available)
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            "category_id" => (int) $categoryId,
            "item_name" => $itemName,
            "price" => $price,
            "description" => $description,
            "is_available" => (int) $isAvailable
        ]);

        // Redirect to prevent duplicate submission
        header("Location: menu_items.php?added=1");
        exit;
    }
}

// Escape output
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

    <title>Add Menu Item | Prem Curry House</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="admin-body">

<nav class="navbar admin-navbar">
    <div class="container-fluid px-4">

        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-flower1 me-2"></i>
            PREM CURRY HOUSE
        </a>

        <a href="menu_items.php" class="btn btn-outline-success btn-sm">
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
                    <i class="bi bi-plus-circle me-2"></i>
                    Add New Menu Item
                </h2>

                <p class="text-muted mb-4">
                    Enter the details of the new food or drink.
                </p>

                <?php if (!empty($errors)): ?>

                    <div class="alert alert-danger">
                        <strong>Please correct these errors:</strong>

                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo e($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                <?php endif; ?>

                <form action="add_item.php" method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo e($_SESSION["csrf_token"]); ?>">

                    <!-- ITEM NAME -->
                    <div class="mb-3">
                        <label for="item_name" class="form-label fw-semibold">
                            Menu Item Name *
                        </label>

                        <input
                            type="text"
                            id="item_name"
                            name="item_name"
                            class="form-control"
                            maxlength="150"
                            value="<?php echo e($itemName); ?>"
                            placeholder="e.g. Chicken Curry"
                            required>
                    </div>

                    <!-- CATEGORY -->
                    <div class="mb-3">
                        <label for="category_id" class="form-label fw-semibold">
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
                        <label for="price" class="form-label fw-semibold">
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
                            placeholder="e.g. 12.50"
                            required>
                    </div>

                    <!-- DESCRIPTION -->
                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">
                            Description
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            class="form-control"
                            rows="4"
                            maxlength="1000"
                            placeholder="Describe the dish..."><?php echo e($description); ?></textarea>
                    </div>

                    <!-- AVAILABILITY -->
                    <div class="mb-4">
                        <label for="is_available" class="form-label fw-semibold">
                            Availability *
                        </label>

                        <select
                            id="is_available"
                            name="is_available"
                            class="form-select">

                            <option value="1" <?php echo $isAvailable === "1" ? "selected" : ""; ?>>
                                Available
                            </option>

                            <option value="0" <?php echo $isAvailable === "0" ? "selected" : ""; ?>>
                                Unavailable
                            </option>

                        </select>
                    </div>

                    <!-- BUTTONS -->
                    <div class="d-flex flex-wrap gap-3">

                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-2"></i>
                            Save Menu Item
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
