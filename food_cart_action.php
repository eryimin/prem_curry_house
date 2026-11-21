
<?php
// ============================================
// PREM CURRY HOUSE - FOOD CART ACTIONS
// ============================================

require_once __DIR__ . '/food_order/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: menu.php');
    exit;
}

pch_verify();

$action = (string)($_POST['action'] ?? 'add');

// ============================================
// CLEAR CART
// ============================================

if ($action === 'clear') {

    unset($_SESSION['pch_food_cart']);

    header('Location: food_cart.php');
    exit;
}

// ============================================
// REMOVE MAIN FOOD OR ADD-ON
// ============================================

if ($action === 'remove') {

    $i = filter_var(
        $_POST['index'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        $i !== false &&
        $i !== null &&
        isset($_SESSION['pch_food_cart'][$i])
    ) {

        $cart = &$_SESSION['pch_food_cart'];

        $selected = $cart[$i];

        $bundle = (string)(
            $selected['bundle_id'] ?? ''
        );

        $isAddon =
            ($selected['kind'] ?? 'main') === 'addon';

        if (!$isAddon && $bundle !== '') {

            // Remove main food and linked add-ons
            $cart = array_values(
                array_filter(
                    $cart,
                    static function ($entry) use ($bundle) {
                        return (
                            $entry['bundle_id'] ?? ''
                        ) !== $bundle;
                    }
                )
            );

        } else {

            // Remove one item only
            array_splice($cart, $i, 1);
        }
    }

    header('Location: food_cart.php');
    exit;
}

// ============================================
// UPDATE QUANTITIES
// ============================================

if ($action === 'update') {

    if (!isset($_SESSION['pch_food_cart'])) {
        $_SESSION['pch_food_cart'] = [];
    }

    $cart = &$_SESSION['pch_food_cart'];

    if (
        isset($_POST['qty']) &&
        is_array($_POST['qty'])
    ) {

        foreach ($cart as $i => &$entry) {

            if (isset($_POST['qty'][$i])) {

                $newQty = (int)$_POST['qty'][$i];

                $entry['qty'] = max(
                    1,
                    min(20, $newQty)
                );
            }
        }

        unset($entry);
    }

    header('Location: food_cart.php');
    exit;
}

// ============================================
// ADD RECOMMENDED ADD-ON FROM CART
// ============================================

if ($action === 'add_addon') {

    $mainIndex = filter_var(
        $_POST['main_index'] ?? null,
        FILTER_VALIDATE_INT
    );

    $addonId = filter_var(
        $_POST['addon_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!isset($_SESSION['pch_food_cart'])) {
        http_response_code(400);
        exit('Your cart is empty.');
    }

    $cart = &$_SESSION['pch_food_cart'];

    // Validate main food
    if (
        $mainIndex === false ||
        $mainIndex === null ||
        !isset($cart[$mainIndex]) ||
        ($cart[$mainIndex]['kind'] ?? 'main') !== 'main'
    ) {
        http_response_code(400);
        exit('Invalid main food item.');
    }

    // Validate add-on
    if (
        $addonId === false ||
        $addonId === null ||
        $addonId < 1
    ) {
        http_response_code(400);
        exit('Invalid add-on.');
    }

    $mainEntry = $cart[$mainIndex];

    $bundle = (string)(
        $mainEntry['bundle_id'] ?? ''
    );

    if ($bundle === '') {
        http_response_code(400);
        exit('Invalid food bundle.');
    }

    // ========================================
    // GET MAIN FOOD INFORMATION
    // ========================================

    $stmt = $pdo->prepare("
        SELECT
            m.item_id,
            m.item_name,
            m.price,
            c.category_name
        FROM menu_items m
        JOIN categories c
            ON m.category_id = c.category_id
        WHERE m.item_id = ?
          AND m.is_available = 1
        LIMIT 1
    ");

    $stmt->execute([
        (int)$mainEntry['id']
    ]);

    $main = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$main) {
        http_response_code(400);
        exit('Main food is unavailable.');
    }

    // ========================================
    // VALIDATE RECOMMENDED ADD-ON
    // ========================================

    $suggestions = pch_suggestions(
        $pdo,
        $main
    );

    $allowedIds = array_map(
        'intval',
        array_column($suggestions, 'id')
    );

    if (!in_array($addonId, $allowedIds, true)) {
        http_response_code(400);
        exit('This add-on is not recommended for this food.');
    }

    // ========================================
    // PREVENT DUPLICATES
    // ========================================

    $addonCount = 0;

    foreach ($cart as $entry) {

        if (
            ($entry['kind'] ?? '') === 'addon' &&
            ($entry['bundle_id'] ?? '') === $bundle
        ) {

            $addonCount++;

            if ((int)$entry['id'] === $addonId) {
                header('Location: food_cart.php');
                exit;
            }
        }
    }

    if ($addonCount >= 4) {
        http_response_code(400);
        exit('Maximum 4 add-ons per main food.');
    }

    // ========================================
    // CHECK AVAILABILITY
    // ========================================

    $addon = pch_menu_item(
        $pdo,
        $addonId
    );

    if (
        !$addon ||
        (int)$addon['is_available'] !== 1
    ) {
        http_response_code(400);
        exit('This add-on is unavailable.');
    }

    // ========================================
    // SAVE LINKED ADD-ON
    // ========================================

    $cart[] = [
        'id' => $addonId,
        'qty' => 1,
        'options' => [],
        'remark' => '',
        'kind' => 'addon',
        'bundle_id' => $bundle
    ];

    header('Location: food_cart.php');
    exit;
}

// ============================================
// ADD MAIN FOOD FROM MENU
// ============================================

if ($action !== 'add') {
    http_response_code(400);
    exit('Invalid cart action.');
}

$id = filter_var(
    $_POST['item_id'] ?? null,
    FILTER_VALIDATE_INT
);

$item = $id
    ? pch_menu_item($pdo, $id)
    : null;

if (!$item || !(int)$item['is_available']) {
    http_response_code(400);
    exit('This item is unavailable.');
}

// ============================================
// VALIDATE CUSTOMISATION OPTIONS
// ============================================

$selected = [];

foreach (
    pch_modifiers()[(int)$id] ?? [] as $group => $choices
) {

    $value = (string)(
        $_POST['options'][$group] ?? ''
    );

    if (
        $value !== '' &&
        array_key_exists($value, $choices)
    ) {
        $selected[$group] = $value;
    }
}

// ============================================
// CREATE UNIQUE FOOD BUNDLE
// ============================================

$bundle = bin2hex(random_bytes(12));

// ============================================
// ADD MAIN FOOD
// ============================================

$_SESSION['pch_food_cart'][] = [
    'id' => (int)$id,
    'qty' => max(
        1,
        min(20, (int)($_POST['quantity'] ?? 1))
    ),
    'options' => $selected,
    'remark' => substr(
        trim((string)($_POST['remark'] ?? '')),
        0,
        300
    ),
    'kind' => 'main',
    'bundle_id' => $bundle
];

// ============================================
// GET ALLOWED RECOMMENDATIONS
// ============================================

$mainQuery = $pdo->prepare("
    SELECT
        m.item_id,
        m.item_name,
        m.price,
        c.category_name
    FROM menu_items m
    JOIN categories c
        ON m.category_id = c.category_id
    WHERE m.item_id = ?
");

$mainQuery->execute([(int)$id]);

$main = $mainQuery->fetch(PDO::FETCH_ASSOC);

$allowed = $main
    ? array_map(
        'intval',
        array_column(
            pch_suggestions($pdo, $main),
            'id'
        )
    )
    : [];

$requested = $_POST['suggestions'] ?? [];

// ============================================
// ADD SELECTED RECOMMENDATIONS FROM MENU
// ============================================

if (is_array($requested)) {

    $alreadyAdded = [];

    foreach (
        array_slice($requested, 0, 20) as $rawId
    ) {

        $suggestionId = filter_var(
            $rawId,
            FILTER_VALIDATE_INT
        );

        if (
            $suggestionId === false ||
            $suggestionId === null ||
            isset($alreadyAdded[$suggestionId]) ||
            !in_array($suggestionId, $allowed, true)
        ) {
            continue;
        }

        $suggestion = pch_menu_item(
            $pdo,
            $suggestionId
        );

        if (
            !$suggestion ||
            !(int)$suggestion['is_available']
        ) {
            continue;
        }

        $alreadyAdded[$suggestionId] = true;

        $_SESSION['pch_food_cart'][] = [
            'id' => $suggestionId,
            'qty' => 1,
            'options' => [],
            'remark' => '',
            'kind' => 'addon',
            'bundle_id' => $bundle
        ];

        if (count($alreadyAdded) >= 4) {
            break;
        }
    }
}

// ============================================
// REDIRECT TO CART
// ============================================

header('Location: food_cart.php');
exit;
?>
