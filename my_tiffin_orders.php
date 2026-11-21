<?php

session_start();

// Only logged-in customers can access this page
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "guest"
) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/config/database.php";

// Escape output safely
function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Get the current customer's orders only
$userId = (int) $_SESSION["user_id"];

$stmt = $pdo->prepare(
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
        p.duration_days
     FROM tiffin_orders AS o
     INNER JOIN tiffin_plans AS p
         ON o.plan_id = p.plan_id
     WHERE o.user_id = ?
     ORDER BY o.created_at DESC, o.order_id DESC"
);

$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "My Tiffin Orders | Prem Curry House";

require __DIR__ . "/includes/header.php";

?>


<style>
.order-track {background:#f7faf5;border:1px solid #e2eadb;border-radius:16px;padding:20px;margin-top:22px}
.order-track-title {color:#355a37;font-weight:750;font-size:.97rem;margin-bottom:17px}
.order-track-steps {display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;position:relative}
.order-track-step {position:relative;text-align:center;z-index:1}
.order-track-circle {height:37px;width:37px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;background:#e5eae2;color:#7d8b7f;font-size:1.1rem;border:2px solid #d8e1d4}
.order-track-step.done .order-track-circle {background:#527B35;color:#fff;border-color:#527B35}
.order-track-step.current .order-track-circle {box-shadow:0 0 0 4px #527b3524}
.order-track-step:not(:last-child)::after {content:'';position:absolute;top:18px;left:calc(50% + 23px);width:calc(100% - 46px);height:3px;background:#dfe7db;z-index:-1}
.order-track-step.done:not(:last-child)::after {background:#8eb17b}
.order-track-name {display:block;font-size:.8rem;font-weight:700;margin-top:8px;color:#68756a}
.order-track-step.done .order-track-name {color:#355a37}
.order-track-help {color:#728173;font-size:.8rem;margin-top:16px}
.order-track-rejected {display:flex;align-items:center;gap:12px;background:#fff0ee;border:1px solid #f2ceca;border-radius:12px;padding:15px;color:#9e433d}
@media(max-width:450px){.order-track{padding:14px}.order-track-name{font-size:.69rem}}
</style>

<section class="py-5" style="background:#FFF9ED; min-height:80vh;">

    <div class="container">

        <div class="text-center mb-5">

            <i class="bi bi-bag-check"
               style="font-size:3rem; color:#527B35;"></i>

            <h1 class="fw-bold mt-3" style="color:#527B35;">
                My Tiffin Orders
            </h1>

            <p class="text-muted">
                View your monthly meal requests and their status.
            </p>

        </div>

        <div class="row justify-content-center">

            <div class="col-lg-10">

                <?php if (empty($orders)): ?>

                    <!-- NO ORDERS -->
                    <div class="card border-0 shadow-sm rounded-4">

                        <div class="card-body p-5 text-center">

                            <i class="bi bi-inbox"
                               style="font-size:3rem; color:#527B35;"></i>

                            <h4 class="mt-3">
                                No Orders Yet
                            </h4>

                            <p class="text-muted">
                                You haven't submitted any monthly tiffin requests.
                            </p>

                            <a href="tiffin.php" class="btn btn-success">
                                Browse Tiffin Plans
                            </a>

                        </div>

                    </div>

                <?php else: ?>

                    <!-- ORDER COUNT -->
                    <div class="alert alert-success mb-4">

                        <i class="bi bi-info-circle me-2"></i>

                        You have submitted
                        <strong><?php echo count($orders); ?></strong>
                        tiffin order request(s).

                    </div>

                    <?php foreach ($orders as $order): ?>

                        <?php
                            // Status badge colour
                            $statusClasses = [
                                "Pending" => "bg-warning text-dark",
                                "Confirmed" => "bg-success",
                                "Rejected" => "bg-danger",
                                "Completed" => "bg-primary"
                            ];

                            $badgeClass = $statusClasses[$order["status"]]
                                ?? "bg-secondary";
                        ?>

                        <div class="card border-0 shadow-sm rounded-4 mb-4">

                            <div class="card-body p-4">

                                <div class="d-flex justify-content-between
                                            align-items-start flex-wrap gap-2 mb-3">

                                    <div>

                                        <h4 class="fw-bold mb-1"
                                            style="color:#527B35;">

                                            Order #<?php echo (int) $order["order_id"]; ?>

                                        </h4>

                                        <small class="text-muted">
                                            Submitted:
                                            <?php echo e(
                                                date(
                                                    "d M Y, h:i A",
                                                    strtotime($order["created_at"])
                                                )
                                            ); ?>
                                        </small>

                                    </div>

                                    <span class="badge <?php echo $badgeClass; ?> fs-6">

                                        <?php echo e($order["status"]); ?>

                                    </span>

                                </div>

                                <hr>

                                <div class="row g-3">

                                    <div class="col-md-6">

                                        <strong>Selected Plan</strong>

                                        <p class="mb-0">
                                            <?php echo e($order["plan_name"]); ?>
                                        </p>

                                    </div>

                                    <div class="col-md-6">

                                        <strong>Plan Price</strong>

                                        <p class="mb-0">
                                            RM <?php echo number_format(
                                                (float) $order["price"],
                                                2
                                            ); ?>
                                        </p>

                                    </div>

                                    <div class="col-md-6">

                                        <strong>Duration</strong>

                                        <p class="mb-0">
                                            <?php echo (int) $order["duration_days"]; ?>
                                            days
                                        </p>

                                    </div>

                                    <div class="col-md-6">

                                        <strong>Preferred Start Date</strong>

                                        <p class="mb-0">
                                            <?php echo e($order["start_date"]); ?>
                                        </p>

                                    </div>

                                    <div class="col-md-6">

                                        <strong>Contact Number</strong>

                                        <p class="mb-0">
                                            <?php echo e($order["phone"]); ?>
                                        </p>

                                    </div>

                                    <div class="col-12">

                                        <strong>Delivery Address</strong>

                                        <p class="mb-0">
                                            <?php echo nl2br(
                                                e($order["delivery_address"])
                                            ); ?>
                                        </p>

                                    </div>

                                    <?php if (!empty($order["special_requests"])): ?>

                                        <div class="col-12">

                                            <strong>Special Requests</strong>

                                            <p class="mb-0">
                                                <?php echo nl2br(
                                                    e($order["special_requests"])
                                                ); ?>
                                            </p>

                                        </div>

                                    <?php endif; ?>

                                </div>

                                                                 <!-- CUSTOMER ORDER STATUS TRACKER: read-only, based on database status -->
                                 <?php
                                     $currentStatus = (string) $order["status"];
                                     $progress = ["Pending" => 1, "Confirmed" => 2, "Completed" => 3];
                                     $stepReached = $progress[$currentStatus] ?? 0;
                                     $steps = [
                                         ["Order Placed", "bi-bag-check"],
                                         ["Confirmed", "bi-check-circle"],
                                         ["Completed", "bi-stars"]
                                     ];
                                 ?>
                                 <div class="order-track" aria-label="Order progress">
                                     <div class="order-track-title"><i class="bi bi-truck me-2"></i>Order Status Tracker</div>
                                     <?php if ($currentStatus === "Rejected"): ?>
                                         <div class="order-track-rejected">
                                             <i class="bi bi-x-circle-fill fs-4"></i>
                                             <div><strong>Order Rejected</strong><div class="small">The restaurant could not accept this order request.</div></div>
                                         </div>
                                     <?php else: ?>
                                         <div class="order-track-steps">
                                             <?php foreach ($steps as $i => $step): ?>
                                                 <?php $reached = ($i + 1) <= $stepReached; ?>
                                                 <div class="order-track-step <?php echo $reached ? 'done' : ''; ?> <?php echo ($i + 1) === $stepReached ? 'current' : ''; ?>">
                                                     <span class="order-track-circle"><i class="bi <?php echo $reached ? 'bi-check-lg' : $step[1]; ?>"></i></span>
                                                     <span class="order-track-name"><?php echo e($step[0]); ?></span>
                                                 </div>
                                             <?php endforeach; ?>
                                         </div>
                                         <p class="order-track-help mb-0">
                                             <?php if ($currentStatus === "Pending"): ?>Your order request has been received and is awaiting restaurant confirmation.
                                             <?php elseif ($currentStatus === "Confirmed"): ?>Your order is confirmed. We will update its status once completed.
                                             <?php elseif ($currentStatus === "Completed"): ?>Your order is marked as completed. Thank you for choosing Prem Curry House!
                                             <?php else: ?>Please contact the restaurant if you need a status update.
                                             <?php endif; ?>
                                         </p>
                                     <?php endif; ?>
                                 </div>

<?php if ($order["status"] === "Pending"): ?>

                                    <div class="alert alert-warning mt-4 mb-0">

                                        <i class="bi bi-clock-history me-2"></i>

                                        Your request is awaiting restaurant confirmation.

                                    </div>

                                <?php elseif ($order["status"] === "Confirmed"): ?>

                                    <div class="alert alert-success mt-4 mb-0">

                                        <i class="bi bi-check-circle me-2"></i>

                                        Your order has been confirmed by the restaurant.

                                    </div>

                                <?php elseif ($order["status"] === "Rejected"): ?>

                                    <div class="alert alert-danger mt-4 mb-0">

                                        The restaurant could not accept this request.

                                    </div>

                                <?php elseif ($order["status"] === "Completed"): ?>

                                    <div class="alert alert-info mt-4 mb-0">

                                        This tiffin order has been completed.

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

                <!-- NAVIGATION -->
                <div class="text-center mt-4">

                    <a href="tiffin.php"
                       class="btn btn-success me-2 mb-2">

                        <i class="bi bi-plus-circle me-1"></i>
                        Browse Tiffin Plans

                    </a>
                    
                    <a href="profile.php"
                       class="btn btn-outline-secondary mb-2">

                        <i class="bi bi-person-circle me-1"></i>
                        My Profile

                    </a>

                </div>

            </div>

        </div>

    </div>

</section>

<?php require __DIR__ . "/includes/footer.php"; ?>
