
<?php
// =============================================
// PREM CURRY HOUSE - MY ORDERS
// =============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only logged-in customers can access their orders.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'My Orders | Prem Curry House';

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* =============================================
   MY ORDERS PAGE
   ============================================= */

.pch-orders-page {
    max-width: 1100px;
    margin: 0 auto;
    padding: 42px 20px 75px;
    min-height: 65vh;
}

/* PAGE TITLE */

.pch-orders-heading {
    text-align: center;
    margin-bottom: 32px;
}

.pch-orders-heading .pch-label {
    display: block;
    color: #527b35;
    font-size: 11px;
    font-weight: 750;
    letter-spacing: 2px;
    margin-bottom: 8px;
}

.pch-orders-heading h1 {
    color: #263727;
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 8px;
}

.pch-orders-heading p {
    color: #778374;
    font-size: 14px;
    margin: 0;
}

/* TWO-COLUMN CARDS */

.pch-orders-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
}

.pch-orders-card {
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border: 1px solid #e3e9df;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 5px 20px rgba(30,55,30,.04);
    transition: transform .2s ease, box-shadow .2s ease;
}

.pch-orders-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 9px 24px rgba(30,55,30,.09);
}

/* ICON */

.pch-orders-icon {
    width: 54px;
    height: 54px;
    display: flex;
    justify-content: center;
    align-items: center;
    background: #edf3e9;
    color: #527b35;
    border-radius: 13px;
    font-size: 25px;
    margin-bottom: 20px;
}

/* CONTENT */

.pch-orders-card h2 {
    font-size: 20px;
    font-weight: 750;
    color: #263727;
    margin-bottom: 10px;
}

.pch-orders-card p {
    color: #738071;
    font-size: 13px;
    line-height: 1.7;
    margin-bottom: 22px;
    flex: 1;
}

/* VIEW BUTTON */

.pch-orders-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: fit-content;
    padding: 11px 19px;
    background: #527b35;
    color: white;
    text-decoration: none;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 700;
    transition: background .2s ease;
}

.pch-orders-button:hover {
    background: #3d612a;
    color: white;
}

/* BACK TO MENU */

.pch-orders-bottom {
    display: flex;
    justify-content: center;
    margin-top: 30px;
}

.pch-orders-back {
    color: #527b35;
    text-decoration: none;
    font-size: 13px;
    font-weight: 650;
}

.pch-orders-back:hover {
    text-decoration: underline;
}

/* RESPONSIVE */

@media (max-width: 700px) {
    .pch-orders-grid {
        grid-template-columns: 1fr;
    }

    .pch-orders-page {
        padding: 30px 15px 55px;
    }

    .pch-orders-card {
        padding: 23px;
    }

    .pch-orders-heading h1 {
        font-size: 28px;
    }
}
</style>

<main class="pch-orders-page">

    <!-- PAGE TITLE -->

    <div class="pch-orders-heading">

        <span class="pch-label">
            ORDER HISTORY
        </span>

        <h1>My Orders</h1>

        <p>
            View and track all your orders from Prem Curry House.
        </p>

    </div>

    <!-- ORDER TYPES -->

    <div class="pch-orders-grid">

        <!-- =====================================
             FOOD & BEVERAGE ORDERS
             ===================================== -->

        <div class="pch-orders-card">

            <div class="pch-orders-icon">
                <i class="bi bi-egg-fried"></i>
            </div>

            <h2>Food & Beverage Orders</h2>

            <p>
                View your food and beverage orders,
                including the items purchased,
                total amount, payment information
                and current order status.
            </p>

            <a
                href="food_my_orders.php"
                class="pch-orders-button"
            >
                View Food Orders
                <i class="bi bi-arrow-right"></i>
            </a>

        </div>

        <!-- =====================================
             MONTHLY TIFFIN ORDERS
             ===================================== -->

        <div class="pch-orders-card">

            <div class="pch-orders-icon">
                <i class="bi bi-calendar-check"></i>
            </div>

            <h2>Monthly Tiffin Orders</h2>

            <p>
                View your monthly tiffin subscriptions,
                selected meal plans, subscription
                details and order status.
            </p>

            <a
                href="my_tiffin_orders.php"
                class="pch-orders-button"
            >
                View Tiffin Orders
                <i class="bi bi-arrow-right"></i>
            </a>

        </div>

    </div>

    <!-- RETURN TO MENU -->

    <div class="pch-orders-bottom">

        <a href="menu.php" class="pch-orders-back">
            <i class="bi bi-arrow-left me-1"></i>
            Continue Browsing Menu
        </a>

    </div>

</main>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
