
<?php

require_once __DIR__ . "/config/database.php";

// Retrieve available monthly tiffin plans
$stmt = $pdo->prepare(
    "SELECT plan_id, plan_name, description, price, duration_days
     FROM tiffin_plans
     WHERE is_available = 1
     ORDER BY price ASC"
);

$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Monthly Tiffin Plans | Prem Curry House";

require __DIR__ . "/includes/header.php";

?>

<section class="py-5" style="background:#FFF9ED; min-height:80vh;">

    <div class="container">

        <div class="text-center mb-5">

            <i class="bi bi-bag-heart"
               style="font-size:3rem; color:#527B35;"></i>

            <h1 class="fw-bold mt-3" style="color:#527B35;">
                Monthly Tiffin Plans
            </h1>

            <p class="text-muted">
                Explore our convenient monthly lunch delivery plans.
            </p>

        </div>

<div class="row g-4 justify-content-center">


        <div class="row g-4 justify-content-center">

            <?php if (empty($plans)): ?>

                <div class="col-12">
                    <div class="alert alert-info text-center">
                        No tiffin plans are currently available.
                    </div>
                </div>

            <?php else: ?>

                <?php foreach ($plans as $plan): ?>

                    <div class="col-md-6 col-lg-4">

                        <div class="card h-100 border-0 shadow-sm rounded-4">

                            <div class="card-body p-4 d-flex flex-column">

                                <div class="text-center mb-3">
                                    <i class="bi bi-box2-heart"
                                       style="font-size:2.5rem; color:#527B35;">
                                    </i>
                                </div>

                                <h4 class="fw-bold text-center"
                                    style="color:#527B35;">

                                    <?php echo htmlspecialchars(
                                        $plan["plan_name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>

                                </h4>

                                <p class="text-muted text-center">

                                    <?php echo htmlspecialchars(
                                        $plan["description"] ?? "",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>

                                </p>

                                <div class="text-center my-3">

                                    <h2 class="fw-bold" style="color:#527B35;">

                                        RM <?php echo number_format(
                                            (float) $plan["price"],
                                            2
                                        ); ?>

                                    </h2>

                                    <span class="text-muted">
                                        <?php echo (int) $plan["duration_days"]; ?>
                                        days
                                    </span>

                                </div>

                                <div class="mt-auto">

                                    <a
                                        href="order_tiffin.php?plan_id=<?php echo (int) $plan["plan_id"]; ?>"
                                        class="btn btn-success w-100 mb-2"
                                    >
                                        <i class="bi bi-cart-plus me-2"></i>
                                        Order This Plan
                                    </a>

                                    <a
                                        href="tiffin_enquiry.php"
                                        class="btn btn-outline-success w-100"
                                    >
                                        <i class="bi bi-chat-dots me-2"></i>
                                        Ask a Question
                                    </a>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

        <div class="text-center mt-5">

            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>
                Back to Homepage
            </a>

        </div>

    </div>

</section>

<?php require __DIR__ . "/includes/footer.php"; ?>
