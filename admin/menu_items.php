
<?php
// ==========================================
// PREM CURRY HOUSE - MANAGE MENU
// Compact design with search and sorting
// ==========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin and staff access
if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true)
) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

// HTML escaping
function e($value): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// CSRF protection for deleting items
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==========================================
// SEARCH AND SORTING
// ==========================================

$search = trim((string)($_GET['search'] ?? ''));
$search = substr($search, 0, 100);

$sort = (string)($_GET['sort'] ?? 'category');

// Only allow predefined sorting options
$sortOptions = [
    'category' => [
        'label' => 'Category',
        'sql' => 'c.category_name ASC, m.item_name ASC'
    ],
    'name_asc' => [
        'label' => 'Item Name (A-Z)',
        'sql' => 'm.item_name ASC'
    ],
    'name_desc' => [
        'label' => 'Item Name (Z-A)',
        'sql' => 'm.item_name DESC'
    ],
    'price_asc' => [
        'label' => 'Price (Low-High)',
        'sql' => 'm.price ASC, m.item_name ASC'
    ],
    'price_desc' => [
        'label' => 'Price (High-Low)',
        'sql' => 'm.price DESC, m.item_name ASC'
    ]
];

if (!isset($sortOptions[$sort])) {
    $sort = 'category';
}

// ==========================================
// FETCH MENU ITEMS
// ==========================================

$sql = "
    SELECT
        m.item_id,
        m.item_name,
        m.price,
        m.is_available,
        c.category_name

    FROM menu_items m

    INNER JOIN categories c
        ON m.category_id = c.category_id
";

$params = [];

if ($search !== '') {
    $sql .= " WHERE m.item_name LIKE :search";
    $params['search'] = '%' . $search . '%';
}

$sql .= " ORDER BY " . $sortOptions[$sort]['sql'];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalItems = count($menuItems);

// Count availability
$availableCount = 0;
$unavailableCount = 0;

foreach ($menuItems as $item) {
    if ((int)$item['is_available'] === 1) {
        $availableCount++;
    } else {
        $unavailableCount++;
    }
}

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Manage Menu | Prem Curry House</title>

<!-- Bootstrap CSS -->
<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
>

<!-- Bootstrap Icons -->
<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

<!-- Existing website stylesheet -->
<link rel="stylesheet" href="../assets/css/style.css">

<style>

/* ==========================================
   MANAGE MENU - COMPACT DESIGN
   ========================================== */

:root {
    --mm-green: #527B35;
    --mm-dark: #233B2A;
    --mm-cream: #FAF8F2;
    --mm-border: #E3E9DF;
    --mm-muted: #70806E;
}

body.admin-body {
    background: var(--mm-cream);
    color: var(--mm-dark);
    font-family: Arial, sans-serif;
    font-size: 14px;
    margin: 0;
}

/* NAVBAR */

.mm-navbar {
    background: #264A35;
    padding: 9px 0;
    position: relative;
}

.mm-navbar .navbar-brand {
    color: white;
    font-size: 18px;
    font-weight: 800;
    text-decoration: none;
}

.mm-navbar .navbar-brand:hover {
    color: white;
}

.mm-navbar .navbar-brand i {
    color: #EFC54B;
}

.mm-nav-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mm-nav-actions .btn {
    font-size: 12px;
    padding: 6px 12px;
    font-weight: 600;
}

/* MAIN PAGE */

.mm-container {
    max-width: 1350px;
    margin: 0 auto;
    padding: 22px 22px 35px;
}

/* HEADING */

.mm-page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 17px;
}

.mm-page-heading h1 {
    color: var(--mm-green);
    font-size: 27px;
    font-weight: 800;
    margin: 0 0 5px;
}

.mm-page-heading p {
    margin: 0;
    color: var(--mm-muted);
    font-size: 13px;
}

.mm-back-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 36px;
    padding: 7px 12px;
    border: 1px solid var(--mm-green);
    border-radius: 8px;
    text-decoration: none;
    color: var(--mm-green);
    background: transparent;
    font-size: 12px;
    font-weight: 700;
}

.mm-back-btn:hover {
    background: #EAF2E5;
    color: #365E2A;
}

/* MAIN PANEL */

.mm-panel {
    background: #FFFFFF;
    border: 1px solid var(--mm-border);
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: 0 3px 12px rgba(20, 50, 20, .035);
}

.mm-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.mm-panel-header h2 {
    color: var(--mm-green);
    font-size: 19px;
    font-weight: 800;
    margin: 0;
}

.mm-panel-header h2 i {
    margin-right: 7px;
}

.mm-add-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #198754;
    color: white;
    text-decoration: none;
    padding: 8px 13px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 12px;
    min-height: 36px;
}

.mm-add-btn:hover {
    background: #146C43;
    color: white;
}

/* SEARCH AND SORT */

.mm-filter-form {
    display: flex;
    align-items: end;
    gap: 10px;
    margin-bottom: 13px;
}

.mm-search-field {
    flex: 2;
    min-width: 180px;
}

.mm-sort-field {
    flex: 1;
    min-width: 180px;
}

.mm-filter-label {
    display: block;
    color: #61745B;
    font-size: 11px;
    font-weight: 750;
    margin-bottom: 5px;
}

.mm-filter-form input,
.mm-filter-form select {
    width: 100%;
    height: 37px;
    border: 1px solid #D8E2D4;
    border-radius: 8px;
    padding: 0 11px;
    color: var(--mm-dark);
    font-size: 13px;
    background: white;
}

.mm-filter-form input:focus,
.mm-filter-form select:focus {
    outline: none;
    border-color: var(--mm-green);
    box-shadow: 0 0 0 3px rgba(82,123,53,.1);
}

.mm-filter-button {
    height: 37px;
    min-width: 88px;
    padding: 0 13px;
    background: var(--mm-green);
    color: white;
    border: 0;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.mm-filter-button:hover {
    background: #42652B;
}

.mm-reset {
    height: 37px;
    min-width: 70px;
    padding: 0 12px;
    border: 1px solid #CED8CA;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #596957;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
}

.mm-reset:hover {
    background: #F1F5EF;
    color: var(--mm-dark);
}

/* RESULTS SUMMARY */

.mm-results {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 12px;
    color: var(--mm-muted);
    font-size: 12px;
}

.mm-results strong {
    color: var(--mm-dark);
}

.mm-results .mm-separator {
    color: #CBD4C7;
}

/* COMPACT TABLE */

.mm-table-wrap {
    overflow-x: auto;
    border: 1px solid #E5EBE2;
    border-radius: 10px;
}

.mm-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
    font-size: 13px;
}

.mm-table thead {
    background: #EFF4EA;
}

.mm-table th {
    color: #4D7537;
    font-weight: 750;
    font-size: 12px;
    text-align: left;
    white-space: nowrap;
    padding: 11px 12px;
    border-bottom: 1px solid #DCE7D7;
}

.mm-table td {
    padding: 8px 12px;
    border-bottom: 1px solid #EBEFEB;
    vertical-align: middle;
    color: #253629;
    line-height: 1.35;
}

.mm-table tbody tr {
    height: 48px;
}

.mm-table tbody tr:hover {
    background: #FAFCF8;
}

.mm-table tbody tr:last-child td {
    border-bottom: none;
}

.mm-item-name {
    font-weight: 700;
    color: #243A2A;
}

.mm-price {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.mm-status {
    display: inline-block;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 11px;
    font-weight: 750;
    white-space: nowrap;
}

.mm-available {
    background: #E1F1E6;
    color: #198754;
}

.mm-unavailable {
    background: #FBE8E7;
    color: #BF4A47;
}

/* EDIT AND DELETE */

.mm-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.mm-actions form {
    margin: 0;
}

.mm-action {
    min-height: 31px;
    padding: 5px 9px;
    border: 1px solid;
    border-radius: 6px;
    background: white;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    cursor: pointer;
}

.mm-edit {
    color: #198754;
    border-color: #198754;
}

.mm-edit:hover {
    background: #E9F6EE;
    color: #146C43;
}

.mm-delete {
    color: #C94B4B;
    border-color: #E2A6A6;
}

.mm-delete:hover {
    background: #FFF1F1;
}

/* ALERTS */

.mm-alert {
    font-size: 13px;
    border-radius: 9px;
    padding: 10px 14px;
    margin-bottom: 13px;
}

/* RESPONSIVE */

@media (max-width: 850px) {
    .mm-filter-form {
        flex-wrap: wrap;
    }

    .mm-search-field,
    .mm-sort-field {
        flex: 1 1 45%;
    }
}

@media (max-width: 600px) {
    .mm-container {
        padding: 15px 12px 25px;
    }

    .mm-panel {
        padding: 14px 12px;
    }

    .mm-page-heading h1 {
        font-size: 24px;
    }

    .mm-search-field,
    .mm-sort-field {
        flex: 1 1 100%;
    }

    .mm-filter-button,
    .mm-reset {
        flex: 1;
    }
}

</style>
</head>

<body class="admin-body">

<!-- ==========================================
     ADMIN NAVBAR
     ========================================== -->

<nav class="navbar mm-navbar">

    <div class="container-fluid px-3 px-lg-4">

        <a
            class="navbar-brand"
            href="dashboard.php"
        >
            <i class="bi bi-flower1 me-2"></i>
            PREM CURRY HOUSE
        </a>

        <div class="mm-nav-actions">

            <a
                href="dashboard.php"
                class="btn btn-outline-light btn-sm"
            >
                <i class="bi bi-speedometer2 me-1"></i>
                Dashboard
            </a>

            <a
                href="../logout.php"
                class="btn btn-outline-warning btn-sm"
            >
                Logout
            </a>

        </div>

    </div>

</nav>

<!-- ==========================================
     MAIN CONTENT
     ========================================== -->

<main class="mm-container">

    <!-- PAGE HEADING -->

    <div class="mm-page-heading">

        <div>

            <h1>Manage Menu</h1>

            <p>
                View and manage restaurant dishes and drinks.
            </p>

        </div>

        <a
            href="dashboard.php"
            class="mm-back-btn"
        >
            <i class="bi bi-arrow-left"></i>
            Back to Dashboard
        </a>

    </div>

    <!-- ==================================
         SUCCESS MESSAGES
         ================================== -->

    <?php if (isset($_GET['added'])): ?>

        <div class="alert alert-success mm-alert">
            <i class="bi bi-check-circle me-2"></i>
            New menu item added successfully!
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>

        <div class="alert alert-success mm-alert">
            <i class="bi bi-check-circle me-2"></i>
            Menu item updated successfully!
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>

        <div class="alert alert-success mm-alert">
            <i class="bi bi-check-circle me-2"></i>
            Menu item deleted successfully!
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['notfound'])): ?>

        <div class="alert alert-warning mm-alert">
            Menu item was not found.
        </div>

    <?php endif; ?>

    <!-- ==================================
         MENU PANEL
         ================================== -->

    <section class="mm-panel">

        <div class="mm-panel-header">

            <h2>
                <i class="bi bi-list-ul"></i>
                Menu Items
            </h2>

            <a
                href="add_item.php"
                class="mm-add-btn"
            >
                <i class="bi bi-plus-circle"></i>
                Add New Item
            </a>

        </div>

        <!-- ==================================
             SEARCH AND SORT
             ================================== -->

        <form
            method="GET"
            action="menu_items.php"
            class="mm-filter-form"
        >

            <div class="mm-search-field">

                <label
                    for="mm-search"
                    class="mm-filter-label"
                >
                    Search Menu
                </label>

                <input
                    type="search"
                    id="mm-search"
                    name="search"
                    placeholder="Search food or drinks..."
                    maxlength="100"
                    value="<?= e($search) ?>"
                >

            </div>

            <div class="mm-sort-field">

                <label
                    for="mm-sort"
                    class="mm-filter-label"
                >
                    Sort By
                </label>

                <select
                    name="sort"
                    id="mm-sort"
                >

                    <?php foreach (
                        $sortOptions as $value => $option
                    ): ?>

                        <option
                            value="<?= e($value) ?>"
                            <?= $sort === $value
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= e($option['label']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <button
                class="mm-filter-button"
                type="submit"
            >
                <i class="bi bi-search me-1"></i>
                Search
            </button>

            <a
                class="mm-reset"
                href="menu_items.php"
            >
                Reset
            </a>

        </form>

        <!-- RESULTS SUMMARY -->

        <div class="mm-results">

            <span>
                Showing
                <strong><?= number_format($totalItems) ?></strong>
                menu items
            </span>

            <span class="mm-separator">|</span>

            <span>
                <strong><?= $availableCount ?></strong>
                Available
            </span>

            <span class="mm-separator">|</span>

            <span>
                <strong><?= $unavailableCount ?></strong>
                Unavailable
            </span>

        </div>

        <!-- ==================================
             MENU TABLE
             ================================== -->

        <div class="mm-table-wrap">

            <table class="mm-table">

                <thead>
                    <tr>
                        <th style="width:65px">ID</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Price (RM)</th>
                        <th>Availability</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($menuItems)): ?>

                    <tr>
                        <td
                            colspan="6"
                            class="text-center py-4"
                        >
                            No menu items found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($menuItems as $item): ?>

                        <tr>

                            <!-- ID -->

                            <td>
                                <?= (int)$item['item_id'] ?>
                            </td>

                            <!-- ITEM NAME -->

                            <td class="mm-item-name">
                                <?= e($item['item_name']) ?>
                            </td>

                            <!-- CATEGORY -->

                            <td>
                                <?= e($item['category_name']) ?>
                            </td>

                            <!-- PRICE -->

                            <td class="mm-price">
                                RM <?= number_format(
                                    (float)$item['price'],
                                    2
                                ) ?>
                            </td>

                            <!-- AVAILABILITY -->

                            <td>

                                <?php if (
                                    (int)$item['is_available'] === 1
                                ): ?>

                                    <span class="mm-status mm-available">
                                        Available
                                    </span>

                                <?php else: ?>

                                    <span class="mm-status mm-unavailable">
                                        Unavailable
                                    </span>

                                <?php endif; ?>

                            </td>

                            <!-- ACTIONS -->

                            <td>

                                <div class="mm-actions">

                                    <!-- EDIT -->

                                    <a
                                        class="mm-action mm-edit"
                                        href="edit_item.php?id=<?= (int)$item['item_id'] ?>"
                                    >
                                        <i class="bi bi-pencil-square"></i>
                                        Edit
                                    </a>

                                    <!-- DELETE (ADMIN ONLY) -->

                                    <?php if ($isAdmin): ?>

                                        <form
                                            action="delete_item.php"
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to permanently delete this menu item?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e(
                                                    $_SESSION['csrf_token']
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="item_id"
                                                value="<?= (int)$item['item_id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="mm-action mm-delete"
                                            >
                                                <i class="bi bi-trash"></i>
                                                Delete
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>
</html>
