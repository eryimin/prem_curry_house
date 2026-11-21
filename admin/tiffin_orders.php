
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

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Create CSRF token
if (empty($_SESSION["csrf_manage_tiffin"])) {
    $_SESSION["csrf_manage_tiffin"] = bin2hex(random_bytes(32));
}

$success = "";
$error = "";

// Process order status updates
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $orderId = filter_var(
        $_POST["order_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $newStatus = $_POST["status"] ?? "";
    $token = (string) ($_POST["csrf_token"] ?? "");

    $allowedStatuses = [
        "Pending",
        "Confirmed",
        "Rejected",
        "Completed"
    ];

    if (!hash_equals($_SESSION["csrf_manage_tiffin"], $token)) {
        $error = "Invalid request. Please refresh the page.";
    } elseif (!$orderId || $orderId < 1) {
        $error = "Invalid order ID.";
    } elseif (!in_array($newStatus, $allowedStatuses, true)) {
        $error = "Invalid order status.";
    } else {

        try {

            $update = $pdo->prepare(
                "UPDATE tiffin_orders
                 SET status = ?
                 WHERE order_id = ?"
            );

            $update->execute([$newStatus, $orderId]);

            if ($update->rowCount() > 0) {
                header("Location: tiffin_orders.php?updated=1");
                exit;
            }

            // rowCount can be zero when status has not changed
            $check = $pdo->prepare(
                "SELECT order_id
                 FROM tiffin_orders
                 WHERE order_id = ?"
            );

            $check->execute([$orderId]);

            if ($check->fetch()) {
                header("Location: tiffin_orders.php?updated=1");
                exit;
            }

            $error = "Order not found.";

        } catch (PDOException $exception) {

            error_log($exception->getMessage());
            $error = "Unable to update the order status.";
        }
    }
}

// Retrieve all customer tiffin orders
$stmt = $pdo->query(
    "SELECT
        o.order_id,
        o.customer_name,
        o.phone,
        o.delivery_address,
        o.start_date,
        o.special_requests,
        o.status,
        o.created_at,
        p.plan_name,
        p.price,
        p.duration_days,
        u.email
     FROM tiffin_orders AS o
     INNER JOIN tiffin_plans AS p
         ON o.plan_id = p.plan_id
     INNER JOIN users AS u
         ON o.user_id = u.user_id
     ORDER BY o.created_at DESC, o.order_id DESC"
);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalOrders = count($orders);
$pendingOrders = 0;
$confirmedOrders = 0;

foreach ($orders as $order) {
    if ($order["status"] === "Pending") {
        $pendingOrders++;
    }

    if ($order["status"] === "Confirmed") {
        $confirmedOrders++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Manage Tiffin Orders | Prem Curry House</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

</head>

<body style="background:#FFF9ED;">

<nav class="navbar navbar-expand-lg navbar-dark"
     style="background:#527B35;">

    <div class="container">

        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-flower1 me-2"></i>
            PREM CURRY HOUSE
        </a>

        <div class="d-flex gap-2">

            <a href="dashboard.php"
               class="btn btn-outline-light btn-sm">
                Dashboard
            </a>

            <a href="../logout.php"
               class="btn btn-warning btn-sm">
                Logout
            </a>

        </div>

    </div>

</nav>

<section class="py-5">

    <div class="container">

        <div class="text-center mb-4">

            <i class="bi bi-clipboard-check"
               style="font-size:3rem; color:#527B35;"></i>

            <h1 class="fw-bold mt-3" style="color:#527B35;">
                Manage Tiffin Orders
            </h1>

            <p class="text-muted">
                Review and manage customer monthly tiffin requests.
            </p>

        </div>

        <!-- STATISTICS -->
        <div class="row g-3 mb-4">

            <div class="col-md-4">

                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">

                        <h6>Total Orders</h6>

                        <h2 class="fw-bold">
                            <?php echo $totalOrders; ?>
                        </h2>

                    </div>
                </div>

            </div>

            <div class="col-md-4">

                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">

                        <h6>Pending Orders</h6>

                        <h2 class="fw-bold text-warning">
                            <?php echo $pendingOrders; ?>
                        </h2>

                    </div>
                </div>

            </div>

            <div class="col-md-4">

                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">

                        <h6>Confirmed Orders</h6>

                        <h2 class="fw-bold text-success">
                            <?php echo $confirmedOrders; ?>
                        </h2>

                    </div>
                </div>

            </div>

        </div>

        <?php if (isset($_GET["updated"]) && $_GET["updated"] === "1"): ?>

            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                Order status updated successfully.
            </div>

        <?php endif; ?>

        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">
                <?php echo e($error); ?>
            </div>

        <?php endif; ?>

        <?php if (empty($orders)): ?>

            <div class="alert alert-info text-center">
                No customer tiffin orders have been submitted yet.
            </div>

        <?php else: ?>

            <?php foreach ($orders as $order): ?>

                <div class="card border-0 shadow-sm rounded-4 mb-4">

                    <div class="card-body p-4">

                        <div class="d-flex justify-content-between
                                    align-items-start flex-wrap gap-2">

                            <div>

                                <h4 class="fw-bold" style="color:#527B35;">
                                    Order #<?php echo (int) $order["order_id"]; ?>
                                </h4>

                                <p class="text-muted mb-0">
                                    <?php echo e($order["customer_name"]); ?>
                                    (<?php echo e($order["email"]); ?>)
                                </p>

                            </div>

                            <?php
                                $badgeClasses = [
                                    "Pending" => "bg-warning text-dark",
                                    "Confirmed" => "bg-success",
                                    "Rejected" => "bg-danger",
                                    "Completed" => "bg-primary"
                                ];

                                $badgeClass = $badgeClasses[$order["status"]]
                                    ?? "bg-secondary";
                            ?>

                            <span class="badge <?php echo $badgeClass; ?> fs-6">
                                <?php echo e($order["status"]); ?>
                            </span>

                        </div>

                        <hr>

                        <div class="row g-3">

                            <div class="col-md-6">
                                <strong>Plan</strong>
                                <p><?php echo e($order["plan_name"]); ?></p>
                            </div>

                            <div class="col-md-6">
                                <strong>Price</strong>
                                <p>
                                    RM <?php echo number_format(
                                        (float) $order["price"],
                                        2
                                    ); ?>
                                </p>
                            </div>

                            <div class="col-md-6">
                                <strong>Phone</strong>
                                <p><?php echo e($order["phone"]); ?></p>
                            </div>

                            <div class="col-md-6">
                                <strong>Start Date</strong>
                                <p><?php echo e($order["start_date"]); ?></p>
                            </div>

                            <div class="col-12">
                                <strong>Delivery Address</strong>
                                <p>
                                    <?php echo nl2br(
                                        e($order["delivery_address"])
                                    ); ?>
                                </p>
                            </div>

                            <?php if (!empty($order["special_requests"])): ?>

                                <div class="col-12">
                                    <strong>Special Requests</strong>
                                    <p>
                                        <?php echo nl2br(
                                            e($order["special_requests"])
                                        ); ?>
                                    </p>
                                </div>

                            <?php endif; ?>

                        </div>

                        <hr>

                        <!-- UPDATE STATUS -->
                        <form method="POST"
                              action="tiffin_orders.php"
                              class="row g-3 align-items-end">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo e(
                                    $_SESSION["csrf_manage_tiffin"]
                                ); ?>"
                            >

                            <input
                                type="hidden"
                                name="order_id"
                                value="<?php echo (int) $order["order_id"]; ?>"
                            >

                            <div class="col-md-8">

                                <label class="form-label fw-semibold">
                                    Update Order Status
                                </label>

                                <select
                                    name="status"
                                    class="form-select"
                                    required
                                >

                                    <?php foreach (
                                        ["Pending", "Confirmed", "Rejected", "Completed"]
                                        as $status
                                    ): ?>

                                        <option
                                            value="<?php echo e($status); ?>"
                                            <?php echo
                                                $order["status"] === $status
                                                    ? "selected"
                                                    : "";
                                            ?>
                                        >
                                            <?php echo e($status); ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="col-md-4">

                                <button
                                    type="submit"
                                    class="btn btn-success w-100"
                                >
                                    <i class="bi bi-save me-1"></i>
                                    Save Status
                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
