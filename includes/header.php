
<?php
// ==========================================
// PREM CURRY HOUSE - SHARED HEADER
// ==========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? '';
$fullName = $_SESSION['full_name'] ?? '';
$baseUrl = '/prem_curry_house/';

if (!function_exists('headerEscape')) {
    function headerEscape($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// ==========================================
// CUSTOMER DISPLAY NAME
// ==========================================

$displayName = trim((string)$fullName);

if ($displayName !== '') {
    $nameParts = preg_split('/\s+/u', $displayName);
    $displayName = $nameParts[0] ?? $displayName;
}

// ==========================================
// CUSTOMER CART ITEM COUNT
// ==========================================

$cartCount = 0;

if ($isLoggedIn && $userRole === 'guest') {
    $cartItems = $_SESSION['pch_food_cart'] ?? [];

    if (is_array($cartItems)) {
        foreach ($cartItems as $cartItem) {
            if (is_array($cartItem)) {
                $cartCount += max(0, (int)($cartItem['qty'] ?? 0));
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= headerEscape($pageTitle ?? 'Prem Curry House') ?></title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Restaurant CSS -->
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/style.css">

    
    <style>
    /* ==========================================
    PREM CURRY HOUSE - NAVIGATION BAR
    ========================================== */

    .pch-navbar {
        position: sticky !important;
        top: 0 !important;
        z-index: 1050;
        background: #ffffff;
        border-bottom: 1px solid #e9eee5;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    }

    /* ==========================================
    RESTAURANT LOGO
    ========================================== */

    .pch-navbar .navbar-brand {
        font-size: 19px;
        font-weight: 800;
        color: #142f22;
        text-decoration: none;
        white-space: nowrap;
        margin-right: 30px;
        flex-shrink: 0;
    }

    .pch-navbar .navbar-brand i {
        color: #527b35;
    }

    /* ==========================================
    NAVIGATION LINKS
    ========================================== */

    .pch-navbar .nav-link {
        color: #334638;
        font-size: 13px;
        font-weight: 500;
        padding: 8px 9px;
        margin-left: 0;
        white-space: nowrap;
    }

    .pch-navbar .nav-link:hover {
        color: #527b35;
    }

    .pch-navbar .btn {
        font-size: 12px;
        white-space: nowrap;
        padding: 6px 9px;
    }

    /* ==========================================
    DESKTOP NAVIGATION LAYOUT
    ========================================== */

    @media (min-width: 1200px) {

        /* Keep navigation beside logo on large screens */
        @media (min-width: 1200px) {
            .pch-navbar #mainNavbar {
                flex: 0 0 auto;
                width: auto;
                margin: 0;
                justify-content: flex-start;
            }
        }

        /* Allow Bootstrap mobile menu to work */
        @media (max-width: 1199.98px) {
            .pch-navbar #mainNavbar {
                flex-basis: 100%;
                width: 100%;
                margin: 0;
            }
        }

        /* Fixed gap between navigation buttons */
        .pch-navbar .pch-nav-links {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            width: auto;
            margin: 0 !important;
            padding: 0;
        }

        /* Do not stretch navigation items */
        .pch-navbar .pch-nav-links .nav-item {
            flex: 0 0 auto;
        }

    }

    /* ==========================================
    SHOPPING CART
    ========================================== */

    .pch-cart-link {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: #527b35 !important;
        font-weight: 700 !important;
    }

    .pch-cart-link i {
        font-size: 16px;
    }

    .pch-cart-link:hover {
        color: #3d6129 !important;
    }

    .pch-cart-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        margin-left: 2px;
        border-radius: 20px;
        background: #527b35;
        color: white;
        font-size: 11px;
        font-weight: 700;
    }

    /* ==========================================
    CUSTOMER GREETING
    ========================================== */

    .pch-customer-greeting {
        color: #527b35 !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        white-space: nowrap;
    }

    /* ==========================================
    SECTION SCROLLING
    ========================================== */

    html {
        scroll-padding-top: 90px;
    }

    /* ==========================================
    TABLET / MOBILE NAVIGATION
    ========================================== */

    @media (max-width: 1199px) {

        .pch-navbar .navbar-collapse {
            flex-grow: 1;
            width: 100%;
            max-height: calc(100vh - 75px);
            overflow-y: auto;
        }

        .pch-navbar .pch-nav-links {
            display: flex;
            flex-direction: column;
            align-items: flex-start !important;
            gap: 4px;
            width: 100%;
            margin: 0 !important;
            padding: 12px 0;
        }

        .pch-navbar .nav-link {
            font-size: 14px;
            padding: 10px 12px;
        }

        .pch-navbar .btn {
            font-size: 13px;
            margin: 4px 12px;
        }
    }

    @media (max-width: 576px) {

        .pch-navbar .navbar-brand {
            font-size: 16px;
            margin-right: 10px;
        }

    }
    </style>
<head>

<body>

<!-- ==========================================
     NAVIGATION BAR
     ========================================== -->

<nav class="navbar navbar-expand-xl navbar-light pch-navbar sticky-top">
    <div class="container-fluid px-4">

        <!-- RESTAURANT NAME -->
        <a class="navbar-brand fw-bold" href="<?= $baseUrl ?>index.php">
            <i class="bi bi-flower1 me-2"></i>PREM CURRY HOUSE
        </a>

        <!-- MOBILE MENU BUTTON -->
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                aria-controls="mainNavbar" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- NAVIGATION LINKS -->
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav align-items-lg-center pch-nav-links">

                <!-- HOME -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>index.php#home">Home</a>
                </li>

                <!-- ABOUT US -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>index.php#about">About Us</a>
                </li>

                <!-- OUR MENU -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>index.php?choose_order=1">Our Menu</a>
                </li>

                <!-- MONTHLY TIFFIN -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>tiffin.php">Monthly Tiffin</a>
                </li>

                <!-- CONTACT -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>index.php#contact">Contact</a>
                </li>

                <?php if (!$isLoggedIn): ?>

                    <!-- LOGIN -->
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-outline-success btn-sm mt-2 mt-lg-0"
                           href="<?= $baseUrl ?>login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Login
                        </a>
                    </li>

                    <!-- REGISTER -->
                    <li class="nav-item">
                        <a class="btn btn-success btn-sm mt-2 mt-lg-0"
                           href="<?= $baseUrl ?>register.php">
                            <i class="bi bi-person-plus me-1"></i>Register
                        </a>
                    </li>

                <?php else: ?>

                    <?php if ($userRole === 'guest'): ?>

                        <!-- SHOPPING CART -->
                        <li class="nav-item ms-lg-2">
                            <a class="nav-link pch-cart-link"
                               href="<?= $baseUrl ?>food_cart.php">
                                <i class="bi bi-cart3"></i>
                                Cart
                                <?php if ($cartCount > 0): ?>
                                    <span class="pch-cart-count"><?= $cartCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>

                        <!-- CUSTOMER PROFILE -->
                        <li class="nav-item">
                            <a class="btn btn-outline-success btn-sm mt-2 mt-lg-0"
                               href="<?= $baseUrl ?>profile.php">
                                <i class="bi bi-person-circle me-1"></i>My Profile
                            </a>
                        </li>

                        <!-- CUSTOMER ORDERS -->
                        <li class="nav-item">
                            <a class="btn btn-outline-success btn-sm mt-2 mt-lg-0"
                               href="<?= $baseUrl ?>my_orders.php">
                                <i class="bi bi-bag-check me-1"></i>My Orders
                            </a>
                        </li>

                        <!-- CUSTOMER NAME -->
                        <li class="nav-item">
                            <span class="nav-link pch-customer-greeting">
                                Hi, <?= headerEscape($displayName) ?>!
                            </span>
                        </li>

                    <?php elseif ($userRole === 'admin' || $userRole === 'staff'): ?>

                        <!-- ADMIN DASHBOARD -->
                        <li class="nav-item ms-lg-2">
                            <a class="btn btn-outline-success btn-sm mt-2 mt-lg-0"
                               href="<?= $baseUrl ?>admin/dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                        </li>

                    <?php endif; ?>

                    <!-- LOGOUT -->
                    <li class="nav-item">
                        <a class="btn btn-outline-danger btn-sm mt-2 mt-lg-0"
                           href="<?= $baseUrl ?>logout.php">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </a>
                    </li>

                <?php endif; ?>

            </ul>
        </div>
    </div>
</nav>
