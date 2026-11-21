
<?php
// ============================================
// PREM CURRY HOUSE - SHOPPING CART
// ============================================

require_once __DIR__ . '/food_order/bootstrap.php';

$cart = pch_cart();
$data = pch_calculate($pdo, $cart);
$subtotal = $data['subtotal'];
$pageTitle = 'My Cart | Prem Curry House';

// ============================================
// LOAD VISIBLE CART ITEMS
// ============================================

$visible = [];

foreach ($cart as $index => $entry) {
    $result = pch_calculate($pdo, [$entry]);

    if (!empty($result['lines'])) {
        $visible[] = [
            'index' => $index,
            'entry' => $entry,
            'line' => $result['lines'][0]
        ];
    }
}

// ============================================
// SEPARATE MAIN FOOD AND ADD-ONS
// ============================================

$mainRows = [];
$childRows = [];

foreach ($visible as $row) {
    $bundle = (string)(
        $row['entry']['bundle_id'] ?? ''
    );

    if (
        ($row['entry']['kind'] ?? 'main') === 'addon' &&
        $bundle !== ''
    ) {
        $childRows[$bundle][] = $row;
    } else {
        $mainRows[] = $row;
    }
}

// Display orphaned items
$mainBundles = array_map(
    static fn($row) =>
        (string)($row['entry']['bundle_id'] ?? ''),
    $mainRows
);

foreach ($childRows as $bundle => $children) {
    if (!in_array($bundle, $mainBundles, true)) {
        foreach ($children as $row) {
            $mainRows[] = $row;
        }
        unset($childRows[$bundle]);
    }
}

// ============================================
// LOAD RECOMMENDED ADD-ONS
// ============================================

$availableAddons = [];

$mainInfoStmt = $pdo->prepare("
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

foreach ($mainRows as $row) {

    if (($row['entry']['kind'] ?? 'main') !== 'main') {
        continue;
    }

    $mainIndex = (int)$row['index'];

    $bundle = (string)(
        $row['entry']['bundle_id'] ?? ''
    );

    if ($bundle === '') {
        continue;
    }

    $mainInfoStmt->execute([
        (int)$row['entry']['id']
    ]);

    $mainInfo = $mainInfoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$mainInfo) {
        continue;
    }

    $suggestions = pch_suggestions($pdo, $mainInfo);

    $selectedIds = [];

    foreach ($childRows[$bundle] ?? [] as $child) {
        $selectedIds[] = (int)$child['entry']['id'];
    }

    // Hide already selected add-ons
    $availableAddons[$mainIndex] = array_values(
        array_filter(
            $suggestions,
            static fn($suggestion) =>
                !in_array(
                    (int)$suggestion['id'],
                    $selectedIds,
                    true
                )
        )
    );

    if (count($selectedIds) >= 4) {
        $availableAddons[$mainIndex] = [];
    }
}

// ============================================
// CART TOTALS
// ============================================

$totalQuantity = array_sum(
    array_map(
        static fn($row) => (int)$row['line']['qty'],
        $visible
    )
);

$tax = round($subtotal * PCH_TAX_RATE, 2);
$total = round($subtotal + $tax, 2);

require __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="assets/css/food_order.css">

<style>
/* ============================================
   SHOPPING CART LAYOUT
   ============================================ */

.pch-cart-page {
    max-width: 1140px;
    margin: auto;
    padding: 36px 18px 65px;
}

.pch-cart-header {
    text-align: center;
    margin-bottom: 28px;
}

.pch-cart-header h1 {
    font-size: 31px;
    font-weight: 800;
    color: #263727;
    margin: 5px 0;
}

.pch-cart-header p {
    font-size: 13px;
    color: #718071;
}

.pch-cart-layout {
    display: grid;
    grid-template-columns: minmax(0,1.65fr) minmax(280px,1fr);
    gap: 22px;
    align-items: start;
}

.pch-cart-box {
    background: white;
    border: 1px solid #e6e9df;
    border-radius: 15px;
    padding: 22px;
    box-shadow: 0 5px 18px rgba(30,55,30,.04);
}

.pch-cart-box-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 15px;
}

.pch-cart-box h2 {
    font-size: 18px;
    font-weight: 750;
    color: #263727;
    margin: 0;
}

.pch-cart-count {
    background: #edf3e9;
    color: #527b35;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
}

/* ============================================
   MAIN FOOD
   ============================================ */

.pch-cart-product {
    display: grid;
    grid-template-columns: 29px 46px minmax(0,1fr) auto;
    align-items: center;
    gap: 11px;
    padding: 17px 0;
    border-bottom: 1px solid #edf0e9;
}

.pch-cart-number {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #edf3e9;
    color: #527b35;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-size: 12px;
    font-weight: 800;
}

.pch-cart-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #eef3e8;
    color: #527b35;
    border-radius: 10px;
    width: 46px;
    height: 46px;
    font-size: 20px;
}

.pch-cart-item-info h3 {
    font-size: 14px;
    font-weight: 750;
    color: #263727;
    margin: 0 0 5px;
}

.pch-cart-item-info small {
    color: #7b8579;
    font-size: 11px;
}

.pch-cart-item-price {
    font-size: 13px;
    color: #527b35;
    font-weight: 750;
}

/* ============================================
   EXISTING ADD-ONS
   ============================================ */

.pch-cart-addon {
    margin-left: 39px;
    border-left: 2px solid #d8e7cf;
    padding: 12px 0 12px 14px;
    display: grid;
    grid-template-columns: minmax(0,1fr) auto;
    gap: 12px;
    align-items: center;
    background: #fcfdfb;
}

.pch-cart-addon-label {
    display: inline-block;
    font-size: 10px;
    background: #edf3e9;
    color: #527b35;
    padding: 3px 8px;
    border-radius: 12px;
    margin-bottom: 5px;
}

.pch-cart-addon-title {
    font-size: 13px;
    font-weight: 700;
    color: #263727;
}

/* ============================================
   QUANTITY CONTROLS
   ============================================ */

.pch-cart-controls {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 7px;
}

.pch-cart-stepper {
    display: flex;
    align-items: center;
    border: 1px solid #dce7d5;
    border-radius: 30px;
    overflow: hidden;
}

.pch-cart-stepper button {
    border: none;
    background: white;
    color: #527b35;
    font-size: 20px;
    font-weight: 800;
    width: 32px;
    height: 34px;
    cursor: pointer;
}

.pch-cart-stepper button:disabled {
    color: #c8d0c3;
    cursor: not-allowed;
}

.pch-cart-stepper input {
    width: 43px;
    border: none;
    text-align: center;
    font-size: 13px;
    font-weight: 700;
    background: white;
    appearance: textfield;
}

.pch-cart-stepper input::-webkit-inner-spin-button,
.pch-cart-stepper input::-webkit-outer-spin-button {
    appearance: none;
}

.pch-cart-line-total {
    font-size: 13px;
    font-weight: 750;
}

.pch-cart-remove {
    border: none;
    background: transparent;
    color: #d9534f;
    font-size: 11px;
    cursor: pointer;
}

/* ============================================
   CHOOSE ADD-ONS
   ============================================ */

.pch-addon-picker {
    margin: 12px 0 18px 39px;
    border: 1px solid #dce8d5;
    border-radius: 11px;
    overflow: hidden;
}

.pch-addon-toggle {
    width: 100%;
    background: #f1f7ec;
    border: none;
    padding: 12px 15px;
    color: #527b35;
    font-size: 13px;
    font-weight: 750;
    text-align: left;
    cursor: pointer;
}

.pch-addon-toggle:hover {
    background: #e7f0de;
}

.pch-addon-list {
    padding: 12px 15px;
    background: #fcfdfb;
}

.pch-addon-list[hidden] {
    display: none !important;
}

.pch-addon-list-heading {
    font-size: 12px;
    color: #718071;
    margin-bottom: 10px;
}

.pch-addon-option {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 11px 0;
    border-bottom: 1px solid #edf0e9;
}

.pch-addon-option:last-child {
    border-bottom: none;
}

.pch-addon-option strong {
    display: block;
    font-size: 13px;
    color: #263727;
}

.pch-addon-option-price {
    font-size: 12px;
    font-weight: 700;
    color: #527b35;
}

.pch-addon-add {
    background: #527b35;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 13px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.pch-addon-add:hover {
    background: #3e6129;
}

/* ============================================
   CART ACTIONS
   ============================================ */

.pch-cart-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 20px;
}

.pch-cart-update {
    background: white;
    border: 1px solid #527b35;
    color: #527b35;
    border-radius: 9px;
    padding: 11px 16px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.pch-cart-update:hover {
    background: #f1f7ec;
}

.pch-cart-add-more {
    color: #527b35;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
}

.pch-cart-add-more:hover {
    text-decoration: underline;
}

/* ============================================
   ORDER SUMMARY
   ============================================ */

.pch-cart-summary {
    position: sticky;
    top: 100px;
}

.pch-cart-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 12px 0;
    border-bottom: 1px solid #edf0e9;
    font-size: 13px;
}

.pch-cart-row strong {
    color: #263727;
}

.pch-cart-total {
    font-size: 18px;
    font-weight: 800;
    color: #527b35;
}

.pch-cart-checkout {
    display: block;
    background: #527b35;
    color: white;
    border-radius: 9px;
    padding: 13px;
    text-align: center;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    margin-top: 20px;
}

.pch-cart-checkout:hover {
    background: #3e6129;
    color: white;
}

/* ============================================
   RESPONSIVE
   ============================================ */

@media (max-width: 900px) {
    .pch-cart-layout {
        grid-template-columns: 1fr;
    }

    .pch-cart-summary {
        position: static;
    }
}

@media (max-width: 576px) {
    .pch-cart-product {
        grid-template-columns: 26px 40px minmax(0,1fr);
    }

    .pch-cart-product .pch-cart-controls {
        grid-column: 2 / -1;
        width: 100%;
        align-items: flex-end;
    }

    .pch-cart-addon {
        margin-left: 20px;
    }

    .pch-addon-picker {
        margin-left: 0;
    }

    .pch-cart-actions {
        flex-wrap: wrap;
    }

    .pch-cart-box {
        padding: 16px;
    }
}
</style>

<main class="pch-cart-page">

    <!-- CART HEADER -->
    <div class="pch-cart-header">
        <h1>My Cart</h1>
        <p>Review your food and extras before checkout.</p>
    </div>

    <?php if (empty($visible)): ?>

        <section class="pch-cart-box text-center">

            <i
                class="bi bi-cart-x"
                style="font-size:45px;color:#527b35"
            ></i>

            <h2 class="mt-3">Your cart is empty</h2>

            <p>Add some delicious food to get started.</p>

            <a
                href="menu.php"
                class="pch-cart-checkout"
            >
                Explore Our Menu
            </a>

        </section>

    <?php else: ?>

        <div class="pch-cart-layout">

            <!-- ====================================
                 LEFT: ORDER ITEMS
                 ==================================== -->

            <section class="pch-cart-box">

                <div class="pch-cart-box-heading">

                    <h2>Order Items</h2>

                    <span class="pch-cart-count">
                        <?= count($mainRows) ?> products ·
                        <?= $totalQuantity ?> units
                    </span>

                </div>

                <!-- UPDATE QUANTITIES FORM -->

                <form
                    id="pch-cart-update-form"
                    method="POST"
                    action="food_cart_action.php"
                >

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= pch_h(pch_csrf()) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="update"
                    >

                    <?php foreach ($mainRows as $position => $row): ?>

                        <?php
                        $i = (int)$row['index'];
                        $line = $row['line'];

                        $bundle = (string)(
                            $row['entry']['bundle_id'] ?? ''
                        );
                        ?>

                        <!-- MAIN FOOD -->

                        <div class="pch-cart-product">

                            <span class="pch-cart-number">
                                <?= $position + 1 ?>
                            </span>

                            <span class="pch-cart-icon">
                                <i class="bi bi-egg-fried"></i>
                            </span>

                            <div class="pch-cart-item-info">

                                <h3>
                                    <?= pch_h(
                                        $line['item']['item_name']
                                    ) ?>
                                </h3>

                                <?php foreach (
                                    $line['options'] as $group => $choice
                                ): ?>

                                    <div>
                                        <small>
                                            <?= pch_h(
                                                $group . ': ' . $choice
                                            ) ?>
                                        </small>
                                    </div>

                                <?php endforeach; ?>

                                <?php if ($line['remark'] !== ''): ?>

                                    <small>
                                        <?= pch_h($line['remark']) ?>
                                    </small>

                                <?php endif; ?>

                                <div class="pch-cart-item-price">

                                    <?= pch_money($line['unit']) ?>

                                    <small>/ each</small>

                                </div>

                            </div>

                            <div class="pch-cart-controls">

                                <div class="pch-cart-stepper">

                                    <button
                                        type="button"
                                        data-step="-1"
                                        data-target="qty-<?= $i ?>"
                                        <?= $line['qty'] <= 1 ? 'disabled' : '' ?>
                                    >−</button>

                                    <input
                                        id="qty-<?= $i ?>"
                                        name="qty[<?= $i ?>]"
                                        type="number"
                                        min="1"
                                        max="20"
                                        value="<?= (int)$line['qty'] ?>"
                                    >

                                    <button
                                        type="button"
                                        data-step="1"
                                        data-target="qty-<?= $i ?>"
                                        <?= $line['qty'] >= 20 ? 'disabled' : '' ?>
                                    >+</button>

                                </div>

                                <div class="pch-cart-line-total">
                                    <?= pch_money($line['total']) ?>
                                </div>

                                <button
                                    type="submit"
                                    form="pch-remove-<?= $i ?>"
                                    class="pch-cart-remove"
                                >
                                    Remove
                                </button>

                            </div>

                        </div>

                        <!-- EXISTING LINKED ADD-ONS -->

                        <?php foreach (
                            $childRows[$bundle] ?? [] as $child
                        ): ?>

                            <?php
                            $j = (int)$child['index'];
                            $extra = $child['line'];
                            ?>

                            <div class="pch-cart-addon">

                                <div>

                                    <span class="pch-cart-addon-label">
                                        <i class="bi bi-arrow-return-right"></i>
                                        Add-on for
                                        <?= pch_h(
                                            $line['item']['item_name']
                                        ) ?>
                                    </span>

                                    <div class="pch-cart-addon-title">
                                        <?= pch_h(
                                            $extra['item']['item_name']
                                        ) ?>
                                    </div>

                                    <div class="pch-cart-item-price">
                                        <?= pch_money($extra['unit']) ?>
                                        <small>/ each</small>
                                    </div>

                                </div>

                                <div class="pch-cart-controls">

                                    <div class="pch-cart-stepper">

                                        <button
                                            type="button"
                                            data-step="-1"
                                            data-target="qty-<?= $j ?>"
                                            <?= $extra['qty'] <= 1 ? 'disabled' : '' ?>
                                        >−</button>

                                        <input
                                            id="qty-<?= $j ?>"
                                            name="qty[<?= $j ?>]"
                                            type="number"
                                            min="1"
                                            max="20"
                                            value="<?= (int)$extra['qty'] ?>"
                                        >

                                        <button
                                            type="button"
                                            data-step="1"
                                            data-target="qty-<?= $j ?>"
                                            <?= $extra['qty'] >= 20 ? 'disabled' : '' ?>
                                        >+</button>

                                    </div>

                                    <div class="pch-cart-line-total">
                                        <?= pch_money($extra['total']) ?>
                                    </div>

                                    <button
                                        type="submit"
                                        form="pch-remove-<?= $j ?>"
                                        class="pch-cart-remove"
                                    >
                                        Remove
                                    </button>

                                </div>

                            </div>

                        <?php endforeach; ?>

                        <!-- CHOOSE MORE ADD-ONS -->

                        <?php if (!empty($availableAddons[$i])): ?>

                            <div class="pch-addon-picker">

                                <button
                                    type="button"
                                    class="pch-addon-toggle"
                                    aria-expanded="false"
                                    aria-controls="pch-addon-list-<?= $i ?>"
                                    onclick="toggleCartAddons(<?= $i ?>, this)"
                                >
                                    <i class="bi bi-plus-circle me-1"></i>
                                    Choose Add-ons
                                    <i class="bi bi-chevron-down ms-1"></i>
                                </button>

                                <div
                                    class="pch-addon-list"
                                    id="pch-addon-list-<?= $i ?>"
                                    hidden
                                >

                                    <p class="pch-addon-list-heading">
                                        Recommended for
                                        <?= pch_h(
                                            $line['item']['item_name']
                                        ) ?>
                                    </p>

                                    <?php foreach (
                                        $availableAddons[$i] as $suggestion
                                    ): ?>

                                        <?php
                                        $addonId = (int)$suggestion['id'];
                                        $addonFormId =
                                            'pch-add-addon-' . $i . '-' . $addonId;
                                        ?>

                                        <div class="pch-addon-option">

                                            <div>
                                                <strong>
                                                    <?= pch_h(
                                                        $suggestion['name']
                                                    ) ?>
                                                </strong>

                                                <span class="pch-addon-option-price">
                                                    <?= pch_money(
                                                        $suggestion['price']
                                                    ) ?>
                                                </span>
                                            </div>

                                            <button
                                                type="submit"
                                                class="pch-addon-add"
                                                form="<?= pch_h($addonFormId) ?>"
                                            >
                                                <i class="bi bi-plus"></i>
                                                Add
                                            </button>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            </div>

                        <?php endif; ?>

                    <?php endforeach; ?>

                    <!-- CART ACTIONS -->

                    <div class="pch-cart-actions">

                        <button
                            type="submit"
                            class="pch-cart-update"
                        >
                            Update Quantities
                        </button>

                        <a
                            href="menu.php"
                            class="pch-cart-add-more"
                        >
                            + Add More Food
                        </a>

                    </div>

                </form>

                <!-- ====================================
                     SEPARATE ADD-ON FORMS
                     ==================================== -->

                <?php foreach (
                    $availableAddons as $mainIndex => $suggestions
                ): ?>

                    <?php foreach ($suggestions as $suggestion): ?>

                        <?php
                        $addonId = (int)$suggestion['id'];
                        $addonFormId =
                            'pch-add-addon-' . $mainIndex . '-' . $addonId;
                        ?>

                        <form
                            id="<?= pch_h($addonFormId) ?>"
                            method="POST"
                            action="food_cart_action.php"
                        >

                            <input
                                type="hidden"
                                name="csrf"
                                value="<?= pch_h(pch_csrf()) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="add_addon"
                            >

                            <input
                                type="hidden"
                                name="main_index"
                                value="<?= (int)$mainIndex ?>"
                            >

                            <input
                                type="hidden"
                                name="addon_id"
                                value="<?= $addonId ?>"
                            >

                        </form>

                    <?php endforeach; ?>

                <?php endforeach; ?>

            </section>

            <!-- ====================================
                 RIGHT: ORDER SUMMARY
                 ==================================== -->

            <aside class="pch-cart-box pch-cart-summary">

                <h2>Order Summary</h2>

                <div class="pch-cart-row">
                    <span>Total units</span>
                    <strong><?= $totalQuantity ?></strong>
                </div>

                <div class="pch-cart-row">
                    <span>Subtotal</span>
                    <strong><?= pch_money($subtotal) ?></strong>
                </div>

                <div class="pch-cart-row">
                    <span>
                        Service Tax
                        (<?= number_format(PCH_TAX_RATE * 100, 0) ?>%)
                    </span>

                    <strong>
                        <?= pch_money($tax) ?>
                    </strong>
                </div>

                <div class="pch-cart-row">
                    <span>Delivery Fee</span>
                    <strong>Calculated at checkout</strong>
                </div>

                <div class="pch-cart-row">
                    <strong>Total before delivery fee</strong>
                    <strong class="pch-cart-total">
                        <?= pch_money($total) ?>
                    </strong>
                </div>

                <p class="text-muted mt-3" style="font-size:12px;">
                    Final delivery charges will be confirmed
                    during checkout.
                </p>

                <a
                    href="food_checkout.php"
                    class="pch-cart-checkout"
                >
                    Proceed to Checkout
                </a>

            </aside>

        </div>

        <!-- ====================================
             REMOVE ITEM FORMS
             ==================================== -->

        <?php foreach ($visible as $row): ?>

            <form
                id="pch-remove-<?= (int)$row['index'] ?>"
                method="POST"
                action="food_cart_action.php"
            >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= pch_h(pch_csrf()) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="remove"
                >

                <input
                    type="hidden"
                    name="index"
                    value="<?= (int)$row['index'] ?>"
                >

            </form>

        <?php endforeach; ?>

    <?php endif; ?>

</main>

<script>
// ============================================
// CART QUANTITY BUTTONS
// ============================================

document.querySelectorAll('[data-step]').forEach(button => {

    button.addEventListener('click', function () {

        const input = document.getElementById(
            this.dataset.target
        );

        if (!input) return;

        const step = Number(this.dataset.step);
        const min = Number(input.min) || 1;
        const max = Number(input.max) || 20;
        const current = Number(input.value) || min;

        input.value = Math.min(
            max,
            Math.max(min, current + step)
        );

        // Update disabled states
        const stepper = input.closest('.pch-cart-stepper');

        if (stepper) {
            stepper.querySelectorAll('[data-step]').forEach(btn => {
                const next = Number(input.value) +
                    Number(btn.dataset.step);

                btn.disabled = next < min || next > max;
            });
        }
    });

});

// ============================================
// CHOOSE ADD-ONS DROPDOWN
// ============================================

function toggleCartAddons(index, button) {

    const list = document.getElementById(
        'pch-addon-list-' + index
    );

    if (!list) return;

    list.hidden = !list.hidden;

    button.setAttribute(
        'aria-expanded',
        list.hidden ? 'false' : 'true'
    );

    const icon = button.querySelector('.bi-chevron-down, .bi-chevron-up');

    if (icon) {
        icon.classList.toggle('bi-chevron-down', list.hidden);
        icon.classList.toggle('bi-chevron-up', !list.hidden);
    }
}
</script>

<?php
require __DIR__ . '/includes/footer.php';
?>
