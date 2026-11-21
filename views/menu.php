
<?php
// PREM CURRY HOUSE - MENU VIEW
// Variables supplied by MenuController:
// $categories, $menuItems, $search, $categoryId

require_once __DIR__ . '/../food_order/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

/* Use the database connection from the main menu.php */
$pdo = $GLOBALS['pdo'] ?? null;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$csrf = $_SESSION['pch_food_csrf']
    ??= bin2hex(random_bytes(32));

function foodEsc($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Determine display category without modifying database.
function foodGroup(array $item): string {

    $category = strtolower(
        (string)$item['category_name']
    );

    $name = strtolower(
        (string)$item['item_name']
    );

    if (
        str_contains($category, 'drink') ||
        preg_match(
            '/coffee|kopi|teh|milo|limau|juice|sirap|nescafe|horlicks|latte|chocolate drink/i',
            $name
        )
    ) {
        return 'Drinks';
    }

    if (
        str_contains($category, 'snack') ||
        str_contains($category, 'add on')
    ) {
        return 'Sides & Snacks';
    }

    if (
        str_contains($category, 'naan') ||
        str_contains($category, 'bread')
    ) {
        return 'Indian Breads';
    }

    if (
        str_contains($category, 'bento') ||
        str_contains($category, 'vegetarian') ||
        str_contains($category, 'non-vegetarian')
    ) {
        return 'Main Dishes';
    }

    if (
        preg_match(
            '/noodle|mee|bihun|bee hoon|kuey teow|kuay teow|maggi|pasta|laksa|pan mee/i',
            $name
        )
    ) {
        return 'Noodles';
    }

    if (
        preg_match(
            '/rice|nasi|briyani|biryani|pulao|porridge/i',
            $name
        )
    ) {
        return 'Rice';
    }

    if (
        str_contains($category, 'briyani') ||
        str_contains($category, 'rice')
    ) {
        return 'Rice';
    }

    if (
        str_contains($category, 'noodle') ||
        str_contains($category, 'goreng')
    ) {
        return 'Noodles';
    }

    return 'Main Dishes';
}

// Menu section order.
$names = [
    "Chef's Recommendations",
    "Most Popular",
    "Main Dishes",
    "Rice",
    "Noodles",
    "Indian Breads",
    "Sides & Snacks",
    "Drinks"
];

$grouped = array_fill_keys($names, []);

foreach ($menuItems as $item) {
    $grouped[foodGroup($item)][] = $item;
}

// Chef's Recommendations.
// Enter real menu IDs after restaurant approval.
$chefItemIds = [];

foreach ($menuItems as $item) {

    if (
        in_array(
            (int)$item['item_id'],
            $chefItemIds,
            true
        )
    ) {
        $grouped["Chef's Recommendations"][] = $item;
    }
}

// Most Popular based on completed food orders.
$popularIds = [];

try {

    if (isset($pdo) && $pdo instanceof PDO) {

        $query = $pdo->query("
            SELECT oi.menu_item_id
            FROM pch_food_order_items oi
            JOIN pch_food_orders o
                ON o.order_id = oi.order_id
            WHERE o.order_status = 'Completed'
            GROUP BY oi.menu_item_id
            ORDER BY SUM(oi.quantity) DESC
            LIMIT 6
        ");

        $popularIds = array_map(
            'intval',
            $query->fetchAll(PDO::FETCH_COLUMN)
        );
    }

} catch (Throwable $e) {
    // Menu still works before orders exist.
}

foreach ($menuItems as $item) {

    if (
        in_array(
            (int)$item['item_id'],
            $popularIds,
            true
        )
    ) {
        $grouped['Most Popular'][] = $item;
    }
}

// Hide empty sections.
$activeNames = array_keys(
    array_filter(
        $grouped,
        fn($items) => count($items) > 0
    )
);

// Existing customisation options.
$modifiers = pch_modifiers();

// Suggested add-ons based on the selected product.
$suggestionsById = [];

if (isset($pdo) && $pdo instanceof PDO) {

    foreach ($menuItems as $item) {

        if ((int)$item['is_available'] === 1) {

            $suggestionsById[(int)$item['item_id']] =
                pch_suggestions($pdo, $item);
        }
    }
}

$groupIcons = [
    "Chef's Recommendations" => 'bi-award',
    "Most Popular" => 'bi-fire',
    "Main Dishes" => 'bi-egg-fried',
    "Rice" => 'bi-bowl-hot',
    "Noodles" => 'bi-cup-hot',
    "Indian Breads" => 'bi-circle',
    "Sides & Snacks" => 'bi-basket',
    "Drinks" => 'bi-cup-straw'
];

function foodSlug($name) {

    return trim(
        preg_replace(
            '/[^a-z0-9]+/',
            '-',
            strtolower($name)
        ),
        '-'
    );
}
?>

<link
    rel="stylesheet"
    href="assets/css/food_order.css"
>

<style>

/* ====================================
   MENU HERO
   ==================================== */

.pch-menu-hero {
    background: #eaf0de;
    text-align: center;
    padding: 36px 15px 27px;
}

.pch-menu-hero h1 {
    font-size: 30px;
    font-weight: 800;
    margin: 6px 0;
}

.pch-menu-hero p {
    font-size: 14px;
    margin: 0;
}

.pch-menu-main {
    padding: 28px 0 65px;
}

.pch-blind-hero-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    margin-top: 18px;
    padding: 11px 20px;
    background: #527b35;
    color: #ffffff;
    border-radius: 25px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    transition: background .2s ease, transform .2s ease;
}

.pch-blind-hero-btn:hover {
    background: #3d612a;
    color: #ffffff;
    transform: translateY(-2px);
}

/* ====================================
   SEARCH BAR
   ==================================== */

.pch-menu-search {
    display: flex;
    max-width: 840px;
    gap: 12px;
    margin: 0 auto 27px;
}

.pch-menu-search-box {
    height: 45px;
    border: 1px solid #dae4d5;
    border-radius: 30px;
    background: #ffffff;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 17px;
    flex: 1;
}

.pch-menu-search-box:focus-within {
    border-color: #527b35;
    box-shadow: 0 0 0 3px rgba(82,123,53,.12);
}

.pch-menu-search-box i {
    color: #527b35;
}

.pch-menu-search-box input {
    min-width: 0;
    flex: 1;
    border: 0;
    outline: 0;
    font-size: 14px;
    background: transparent;
}

.pch-menu-search button {
    border: 0;
    border-radius: 30px;
    background: #527b35;
    color: #ffffff;
    font-size: 14px;
    font-weight: 700;
    padding: 0 24px;
    cursor: pointer;
}

.pch-menu-search button:hover {
    background: #3e6329;
}

/* ====================================
   MENU LAYOUT
   ==================================== */

.pch-menu-columns {
    display: grid;
    grid-template-columns: 245px minmax(0, 1fr);
    gap: 24px;
}

.pch-menu-side {
    position: sticky;
    top: 85px;
    align-self: start;
    background: #fff9ed;
    border: 1px solid #e3e2d5;
    border-radius: 13px;
    padding: 15px;
    max-height: calc(100vh - 105px);
    overflow-y: auto;
}

.pch-menu-side h2 {
    font-size: 18px;
    color: #527b35;
    margin: 4px 5px 12px;
    font-weight: 750;
}

.pch-menu-side a {
    display: block;
    text-decoration: none;
    border-radius: 8px;
    padding: 11px 12px;
    margin-bottom: 5px;
    color: #30362e;
    font-size: 13px;
    font-weight: 600;
}

.pch-menu-side a.active {
    background: #e1ecd7;
    color: #345b25;
    border-left: 3px solid #527b35;
}

.pch-menu-side a:hover {
    background: #ecf3e5;
}

/* ====================================
   FOOD SECTIONS
   ==================================== */

.pch-food-section {
    scroll-margin-top: 90px;
    margin-bottom: 33px;
}

.pch-food-section h2 {
    font-weight: 750;
    font-size: 22px;
    margin: 0 0 15px;
    display: flex;
    align-items: center;
    gap: 9px;
}

.pch-food-section h2 i {
    color: #527b35;
}

.pch-food-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

/* ====================================
   FOOD CARDS
   ==================================== */

.pch-food-tile {
    border: 1px solid #eeebe3;
    border-radius: 13px;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 5px 16px rgba(21,41,21,.04);
    display: flex;
    flex-direction: column;
}

.pch-food-image {
    height: 135px;
    width: 100%;
    object-fit: cover;
    background: #527b35;
}

.pch-food-image.is-missing {
    display: none;
}

.pch-food-placeholder {
    display: none;
    align-items: center;
    justify-content: center;
    background: #527b35;
    color: #ffffff;
    font-weight: 700;
    min-height: 135px;
    font-size: 17px;
    text-align: center;
}

.pch-food-placeholder.show {
    display: flex;
}

.pch-food-body {
    padding: 13px;
    display: flex;
    flex: 1;
    flex-direction: column;
}

.pch-food-category {
    color: #527b35;
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 800;
}

.pch-food-body h3 {
    font-size: 16px;
    line-height: 1.3;
    font-weight: 700;
    margin: 8px 0;
}

.pch-food-desc {
    font-size: 12px;
    color: #6b7265;
    margin: 0 0 10px;
}

.pch-food-price {
    font-size: 18px;
    color: #527b35;
    font-weight: 800;
    margin-top: auto;
}

.pch-food-order {
    margin-top: 10px;
    border: 1px solid #527b35;
    border-radius: 8px;
    background: #527b35;
    color: white;
    padding: 9px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.pch-food-order:hover {
    background: #40652b;
}

.pch-food-order:disabled {
    background: #e9ebe7;
    border-color: #e9ebe7;
    color: #8b9088;
    cursor: not-allowed;
}

/* ====================================
   FOOD ORDER POPUP
   ==================================== */

.pch-menu-modal {
    border: 0;
    border-radius: 18px;
    max-width: 620px;
    width: calc(100% - 30px);
    max-height: 88vh;
    padding: 0;
    overflow-y: auto;
    background: #ffffff;
    box-shadow: 0 15px 60px rgba(20,39,20,.34);
}

.pch-menu-modal::backdrop {
    background: rgba(22,36,21,.60);
}

/* POPUP HEADER */

.pch-modal-head {
    padding: 20px 22px;
    border-bottom: 1px solid #eeeeee;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.pch-modal-head h2 {
    font-weight: 800;
    font-size: 23px;
    margin: 0;
}

.pch-modal-close {
    background: none;
    border: 0;
    font-size: 25px;
    cursor: pointer;
}

/* POPUP BODY */

.pch-modal-body {
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 20px 22px;
}

/* FOOD IMAGE AT TOP */

.pch-modal-image-area {
    position: relative;
    width: 100%;
    height: 190px;
    border-radius: 12px;
    overflow: hidden;
    background: #eef2e9;
}

#pch-modal-image {
    display: none;
    width: 100%;
    height: 100%;
    object-fit: cover;
    position: relative;
    z-index: 1;
}

.pch-modal-image-placeholder {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #527b35;
    font-size: 13px;
}

.pch-modal-image-placeholder i {
    font-size: 30px;
}

/* CUSTOMISATION OPTIONS */

#pch-modal-options {
    width: 100%;
}

/* ====================================
   RECOMMENDED ADD-ONS
   ==================================== */

.pch-recommend-section[hidden] {
    display: none !important;
}

.pch-recommend-section {
    border-top: 1px solid #e6e9e2;
    padding-top: 15px;
    margin: 12px 0;
}

.pch-recommend-section h3 {
    font-size: 15px;
    color: #29432d;
    font-weight: 750;
    margin-bottom: 5px;
}

.pch-recommend-section h3 small {
    font-size: 12px;
    color: #718071;
    font-weight: 400;
}

.pch-recommend-section p {
    font-size: 12px;
    color: #718071;
    margin-bottom: 9px;
}

.pch-suggestion-line {
    display: flex !important;
    align-items: center;
    gap: 10px;
    margin: 0 !important;
    padding: 9px 4px !important;
    border-bottom: 1px solid #edf0e9;
    cursor: pointer;
    font-size: 13px !important;
}

.pch-suggestion-line input {
    width: 16px;
    height: 16px;
    accent-color: #527b35;
}

.pch-suggestion-line span {
    flex: 1;
}

.pch-suggestion-line strong {
    color: #527b35;
    white-space: nowrap;
    font-size: 13px;
}

/* ====================================
   INSTRUCTIONS & QUANTITY
   ==================================== */

.pch-modal-bottom-row {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 18px;
    align-items: start;
}

.pch-modal-body label {
    display: block;
    font-size: 13px;
    margin: 0 0 8px;
    font-weight: 700;
}

.pch-optional {
    font-weight: 400;
    font-size: 12px;
    color: #7b8579;
}

.pch-modal-body textarea,
.pch-modal-body select,
.pch-modal-body input[type=number] {
    width: 100%;
    border: 1px solid #dce4d8;
    border-radius: 9px;
    padding: 11px 12px;
    font-size: 13px;
    background: #ffffff;
}

.pch-modal-instructions textarea {
    min-height: 92px;
    resize: vertical;
}

.pch-modal-quantity input {
    height: 45px;
}

.pch-modal-body textarea:focus,
.pch-modal-body select:focus,
.pch-modal-body input:focus {
    outline: none;
    border-color: #527b35;
    box-shadow: 0 0 0 3px rgba(82,123,53,.12);
}

/* ====================================
   POPUP FOOTER
   ==================================== */

.pch-modal-footer {
    border-top: 1px solid #eeeeee;
    padding: 17px 22px;
    background: #fafbf8;
}

.pch-modal-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 800;
    color: #527b35;
    margin-bottom: 12px;
}

#pch-modal-total {
    font-size: 21px;
    font-weight: 800;
    color: #527b35;
}

/* ====================================
   RESPONSIVE DESIGN
   ==================================== */

@media (max-width: 1000px) {
    .pch-food-grid {
        grid-template-columns: repeat(2, minmax(0,1fr));
    }
}

@media (max-width: 767px) {

    .pch-menu-columns {
        grid-template-columns: 1fr;
    }

    .pch-menu-side {
        position: static;
        max-height: none;
        display: flex;
        overflow-x: auto;
        gap: 5px;
        align-items: center;
    }

    .pch-menu-side h2 {
        display: none;
    }

    .pch-menu-side a {
        white-space: nowrap;
        margin: 0;
    }

    .pch-menu-hero h1 {
        font-size: 25px;
    }
}

@media (max-width: 480px) {

    .pch-food-grid {
        grid-template-columns: repeat(2,minmax(0,1fr));
        gap: 9px;
    }

    .pch-food-image,
    .pch-food-placeholder {
        height: 110px;
        min-height: 110px;
    }

    .pch-food-body {
        padding: 10px;
    }

    .pch-food-body h3 {
        font-size: 14px;
    }

    .pch-menu-search button {
        padding: 0 13px;
    }

    .pch-menu-search-box {
        padding: 0 12px;
    }

    .pch-menu-modal {
        max-width: 440px;
    }

    .pch-modal-head {
        padding: 16px;
    }

    .pch-modal-body {
        padding: 16px;
        gap: 15px;
    }

    .pch-modal-image-area {
        height: 155px;
    }

    .pch-modal-bottom-row {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .pch-modal-footer {
        padding: 16px;
    }
}

</style>

<!-- ====================================
     MENU HERO
     ==================================== -->

<section class="pch-menu-hero">

    <span class="section-label">
        PREM CURRY HOUSE
    </span>

    <h1>Explore Our Menu</h1>

    <p>
        Discover Indian and Malaysian flavours.
    </p>

    <?php if (
        isset($_SESSION['user_id']) &&
        ($_SESSION['role'] ?? '') === 'guest'
    ): ?>
        <a href="food_blind_box.php" class="pch-blind-hero-btn">
            <i class="bi bi-gift me-2"></i>
            Can't Decide? Try Mystery Food Box!
        </a>
    <?php endif; ?>

</section>

<!-- ====================================
     MAIN MENU
     ==================================== -->


<main class="container pch-menu-main">

    <div class="pch-menu-toolbar">

        <?php
        $currentOrderType = $_SESSION['order_type'] ?? '';
        $currentTable = $_SESSION['table_number'] ?? '';
        $currentDeliveryArea = $_SESSION['delivery_area_name'] ?? '';
        ?>

        <?php if ($currentOrderType === 'dine_in'): ?>
            <div class="pch-current-order">
                <i class="bi bi-shop"></i>
                <span>Dine In · Table <?= foodEsc($currentTable) ?></span>
            </div>
        <?php elseif ($currentOrderType === 'delivery'): ?>
            <div class="pch-current-order">
                <i class="bi bi-truck"></i>
                <span>
                    Delivery
                    <?php if ($currentDeliveryArea !== ''): ?>
                        · <?= foodEsc($currentDeliveryArea) ?>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <form action="menu.php" method="GET" class="pch-menu-search">
            <div class="pch-menu-search-box">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input
                    type="search"
                    name="search"
                    placeholder="Search for your favourite food..."
                    aria-label="Search food"
                    maxlength="100"
                    value="<?= foodEsc($search) ?>"
                >
            </div>
            <button type="submit">Search</button>
        </form>

    </div>

    <div class="pch-menu-columns">

        <aside class="pch-menu-side">
            <h2>Menu Categories</h2>

            <?php foreach ($activeNames as $label): ?>
                <a
                    href="#cat-<?= foodEsc(foodSlug($label)) ?>"
                    data-category="<?= foodEsc($label) ?>"
                >
                    <?= foodEsc($label) ?>
                </a>
            <?php endforeach; ?>
        </aside>

        <div class="pch-menu-content">

            <?php if (empty($menuItems)): ?>
                <div class="pch-panel text-center">
                    <h2>No dishes found</h2>
                    <p>Try another search term.</p>
                    <a href="menu.php" class="pch-primary">
                        View All Menu Items
                    </a>
                </div>
            <?php endif; ?>

            <?php foreach ($activeNames as $label): ?>

                <section
                    class="pch-food-section"
                    id="cat-<?= foodEsc(foodSlug($label)) ?>"
                    data-category="<?= foodEsc($label) ?>"
                >

                    <h2>
                        <i class="bi <?= foodEsc($groupIcons[$label]) ?>"></i>
                        <?= foodEsc($label) ?>
                    </h2>

                    <div class="pch-food-grid">

                        <?php foreach ($grouped[$label] as $food): ?>

                            <article class="pch-food-tile">

                                <img
                                    class="pch-food-image"
                                    src="<?= foodEsc($food['image'] ?? '') ?>"
                                    alt="<?= foodEsc($food['item_name']) ?>"
                                    onerror="
                                        this.classList.add('is-missing');
                                        this.nextElementSibling.classList.add('show');
                                    "
                                >

                                <div class="pch-food-placeholder">
                                    Prem Curry House
                                </div>

                                <div class="pch-food-body">

                                    <span class="pch-food-category">
                                        <?= foodEsc($food['category_name']) ?>
                                    </span>

                                    <h3>
                                        <?= foodEsc($food['item_name']) ?>
                                    </h3>

                                    <?php if (!empty($food['description'])): ?>
                                        <p class="pch-food-desc">
                                            <?= foodEsc($food['description']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <div class="pch-food-price">
                                        RM <?= number_format((float)$food['price'], 2) ?>
                                    </div>

                                    <button
                                        class="pch-food-order"
                                        type="button"
                                        <?= (int)$food['is_available'] !== 1 ? 'disabled' : '' ?>
                                        data-food='<?= foodEsc(
                                            json_encode(
                                                [
                                                    'id' => (int)$food['item_id'],
                                                    'name' => $food['item_name'],
                                                    'price' => (float)$food['price'],
                                                    'image' => $food['image'] ?? '',
                                                    'options' => $modifiers[(int)$food['item_id']] ?? [],
                                                    'suggestions' => $suggestionsById[(int)$food['item_id']] ?? []
                                                ],
                                                JSON_UNESCAPED_UNICODE |
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_AMP |
                                                JSON_HEX_QUOT
                                            )
                                        ) ?>'
                                    >
                                        <?= (int)$food['is_available'] === 1
                                            ? 'View & Add +'
                                            : 'Unavailable'
                                        ?>
                                    </button>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endforeach; ?>

        </div>

    </div>

</main>


<!-- ====================================
     FOOD CUSTOMISATION POPUP
     ==================================== -->

<dialog
    class="pch-menu-modal"
    id="food-modal"
    aria-labelledby="pch-modal-title"
>

    <form
        method="POST"
        action="food_cart_action.php"
        id="food-modal-form"
    >

        <!-- CSRF -->

        <input
            type="hidden"
            name="csrf"
            value="<?= foodEsc($csrf) ?>"
        >

        <!-- ADD ACTION -->

        <input
            type="hidden"
            name="action"
            value="add"
        >

        <!-- SELECTED ITEM -->

        <input
            type="hidden"
            name="item_id"
            id="pch-modal-id"
        >

        <!-- POPUP HEADER -->

        <div class="pch-modal-head">

            <div>

                <span class="pch-food-category">
                    CUSTOMISE YOUR ORDER
                </span>

                <h2 id="pch-modal-title"></h2>

                <p id="pch-modal-base"></p>

            </div>

            <button
                type="button"
                class="pch-modal-close"
                id="pch-modal-close"
                aria-label="Close"
            >
                &times;
            </button>

        </div>

        <!-- POPUP BODY -->

        <div class="pch-modal-body">

            <!-- FOOD IMAGE AT TOP -->

            <div class="pch-modal-image-area">

                <img
                    id="pch-modal-image"
                    src=""
                    alt="Selected food"
                >

                <div
                    class="pch-modal-image-placeholder"
                    id="pch-modal-placeholder"
                >

                    <i class="bi bi-image"></i>

                    <span>Food Image</span>

                </div>

            </div>

            <!-- PRODUCT CUSTOMISATIONS -->

            <div id="pch-modal-options"></div>

            <!-- ====================================
                 RECOMMENDED ADD-ONS
                 ==================================== -->

            <div
                id="pch-recommend-section"
                class="pch-recommend-section"
                hidden
            >

                <h3>

                    Recommended with Your Meal

                    <small>(Optional)</small>

                </h3>

                <p>
                    Choose something that pairs well
                    with your order.
                </p>

                <div id="pch-modal-suggestions"></div>

            </div>

            <!-- ====================================
                 INSTRUCTIONS AND QUANTITY
                 ==================================== -->

            <div class="pch-modal-bottom-row">

                <!-- LEFT: INSTRUCTIONS -->

                <div class="pch-modal-instructions">

                    <label for="pch-remark">

                        Special Instructions

                        <span class="pch-optional">
                            (Optional)
                        </span>

                    </label>

                    <textarea
                        name="remark"
                        id="pch-remark"
                        maxlength="300"
                        rows="3"
                        placeholder="Less spicy, no onions..."
                    ></textarea>

                </div>

                <!-- RIGHT: QUANTITY -->

                <div class="pch-modal-quantity">

                    <label for="pch-modal-qty">
                        Quantity
                    </label>

                    <input
                        id="pch-modal-qty"
                        name="quantity"
                        type="number"
                        min="1"
                        max="20"
                        value="1"
                        required
                    >

                </div>

            </div>

        </div>

        <!-- POPUP FOOTER -->

        <div class="pch-modal-footer">

            <div class="pch-modal-total">

                <span>Total</span>

                <span id="pch-modal-total"></span>

            </div>

            <button
                class="pch-primary w-100"
                type="submit"
            >
                Add to Cart
            </button>

        </div>

    </form>

</dialog>

<!-- MENU JAVASCRIPT -->

<script src="assets/js/food_menu.js"></script>

<?php if (
    isset($_SESSION['user_id']) &&
    ($_SESSION['role'] ?? '') === 'guest'
): ?>

<style>
.pch-blind-invite[hidden] {
    display: none !important;
}

.pch-blind-invite {
    position: fixed;
    inset: 0;
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(20, 47, 34, .55);
}

.pch-blind-invite-card {
    position: relative;
    width: 100%;
    max-width: 420px;
    padding: 35px 28px;
    border: 1px solid #e3e9df;
    border-radius: 18px;
    background: #fff9ed;
    text-align: center;
    box-shadow: 0 15px 45px rgba(0, 0, 0, .18);
}

.pch-blind-invite-icon {
    width: 78px;
    height: 78px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    border-radius: 18px;
    background: #edf3e9;
    color: #527b35;
    font-size: 35px;
}

.pch-blind-invite-card h2 {
    color: #263727;
    font-size: 25px;
    font-weight: 800;
    margin-bottom: 12px;
}

.pch-blind-invite-card p {
    color: #718071;
    font-size: 14px;
    line-height: 1.7;
    margin-bottom: 23px;
}

.pch-blind-invite-play {
    display: block;
    width: 100%;
    padding: 13px 18px;
    border-radius: 10px;
    background: #527b35;
    color: white;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
}

.pch-blind-invite-play:hover {
    background: #3d612a;
    color: white;
}

.pch-blind-invite-later {
    margin-top: 15px;
    padding: 5px 12px;
    border: 0;
    background: transparent;
    color: #718071;
    font-size: 13px;
    cursor: pointer;
}

.pch-blind-invite-close {
    position: absolute;
    top: 14px;
    right: 17px;
    border: 0;
    background: transparent;
    color: #718071;
    font-size: 21px;
    cursor: pointer;
}

@media (max-width: 480px) {
    .pch-blind-invite-card {
        padding: 30px 20px;
    }

    .pch-blind-invite-card h2 {
        font-size: 22px;
    }
}


.pch-menu-toolbar {
    position: relative;
    width: 100%;
    margin-bottom: 27px;
}

.pch-menu-toolbar .pch-menu-search {
    display: flex;
    width: 100%;
    max-width: 840px;
    gap: 12px;
    margin: 0 auto;
}

.pch-menu-toolbar .pch-current-order {
    position: absolute;
    top: 0;
    left: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    max-width: 220px;
    min-height: 45px;
    padding: 10px 15px;
    margin: 0;
    background: #edf3e9;
    border: 1px solid #dce6d6;
    border-radius: 25px;
    color: #527b35;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.pch-menu-toolbar .pch-current-order i {
    font-size: 16px;
    flex-shrink: 0;
}

.pch-menu-toolbar .pch-current-order span {
    overflow: hidden;
    text-overflow: ellipsis;
}

@media (max-width: 1250px) {
    .pch-menu-toolbar .pch-current-order {
        position: static;
        width: fit-content;
        max-width: 100%;
        margin-bottom: 12px;
    }

    .pch-menu-toolbar .pch-menu-search {
        width: 100%;
        max-width: 840px;
        margin: 0 auto;
    }
}
</style>

<div
    class="pch-blind-invite"
    id="pch-blind-invite"
    role="dialog"
    aria-modal="true"
    aria-labelledby="pch-blind-invite-title"
    aria-describedby="pch-blind-invite-desc"
    hidden
>
    <div class="pch-blind-invite-card">

        <button
            type="button"
            class="pch-blind-invite-close"
            id="pch-blind-invite-close"
            aria-label="Close"
        >
            <i class="bi bi-x-lg"></i>
        </button>

        <div class="pch-blind-invite-icon">
            <i class="bi bi-gift"></i>
        </div>

        <h2 id="pch-blind-invite-title">
            Can't Decide What to Eat?
        </h2>

        <p id="pch-blind-invite-desc">
            Let our Mystery Food Box surprise you!
            <br>
            Your next favourite dish might be one click away.
        </p>

        <a href="food_blind_box.php" class="pch-blind-invite-play">
            <i class="bi bi-stars me-2"></i>
            Let's Play!
        </a>

        <button
            type="button"
            class="pch-blind-invite-later"
            id="pch-blind-invite-later"
        >
            Maybe Later
        </button>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const popup = document.getElementById('pch-blind-invite');
    const closeBtn = document.getElementById('pch-blind-invite-close');
    const laterBtn = document.getElementById('pch-blind-invite-later');
    const foodModal = document.getElementById('food-modal');

    if (!popup || !closeBtn || !laterBtn) {
        return;
    }

    // POPUP SETTINGS
    const firstPopupDelay = 10000;    // 10 seconds
    const repeatPopupDelay = 120000;  // 2 minutes

    const dismissedKey = 'pch_blind_popup_dismissed';
    const snoozeKey = 'pch_blind_popup_snooze_until';

    let popupTimer = null;
    let lastFocused = null;

    // Check whether customer has permanently closed the popup
    function isDismissed() {
        return localStorage.getItem(dismissedKey) === 'true';
    }

    function schedulePopup(delay) {

        clearTimeout(popupTimer);

        if (isDismissed()) {
            return;
        }

        popupTimer = setTimeout(showPopup, delay);
    }

    function showPopup() {

        if (isDismissed() || !popup.hidden) {
            return;
        }

        const snoozeUntil = Number(
            localStorage.getItem(snoozeKey) || 0
        );

        // Customer previously clicked Maybe Later
        if (Date.now() < snoozeUntil) {

            schedulePopup(snoozeUntil - Date.now());
            return;
        }

        // Do not interrupt food customisation
        if (foodModal && foodModal.open) {

            schedulePopup(1000);
            return;
        }

        lastFocused = document.activeElement;

        popup.hidden = false;

        closeBtn.focus();
    }

    function hidePopup() {

        popup.hidden = true;

        if (
            lastFocused &&
            typeof lastFocused.focus === 'function'
        ) {
            lastFocused.focus();
        }
    }

    // MAYBE LATER: show again after 2 minutes
    laterBtn.addEventListener('click', function () {

        hidePopup();

        const nextPopupTime =
            Date.now() + repeatPopupDelay;

        localStorage.setItem(
            snoozeKey,
            String(nextPopupTime)
        );

        schedulePopup(repeatPopupDelay);
    });

    // X BUTTON: permanently disable automatic popup
    closeBtn.addEventListener('click', function () {

        hidePopup();

        clearTimeout(popupTimer);

        localStorage.setItem(
            dismissedKey,
            'true'
        );

        localStorage.removeItem(snoozeKey);
    });

    // Clicking outside works like Maybe Later
    popup.addEventListener('click', function (event) {

        if (event.target === popup) {
            laterBtn.click();
        }
    });

    // Escape key works like Maybe Later
    document.addEventListener('keydown', function (event) {

        if (event.key === 'Escape' && !popup.hidden) {
            laterBtn.click();
        }
    });

    // Start timer when customer opens the menu
    if (!isDismissed()) {

        const snoozeUntil = Number(
            localStorage.getItem(snoozeKey) || 0
        );

        if (Date.now() < snoozeUntil) {

            schedulePopup(snoozeUntil - Date.now());

        } else {

            localStorage.removeItem(snoozeKey);

            schedulePopup(firstPopupDelay);
        }
    }

});
</script>

<?php endif; ?>