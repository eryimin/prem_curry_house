
<?php

session_start();

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "guest"
) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/config/database.php";

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Get selected plan
$planId = filter_input(INPUT_GET, "plan_id", FILTER_VALIDATE_INT);

if (!$planId) {
    header("Location: tiffin.php");
    exit;
}

$stmt = $pdo->prepare(
    "SELECT plan_id, plan_name, price, duration_days
     FROM tiffin_plans
     WHERE plan_id = ? AND is_available = 1
     LIMIT 1"
);

$stmt->execute([$planId]);
$plan = $stmt->fetch();

if (!$plan) {
    header("Location: tiffin.php");
    exit;
}

// Get customer details
$userStmt = $pdo->prepare(
    "SELECT full_name
     FROM users
     WHERE user_id = ? AND role = 'guest'
     LIMIT 1"
);

$userStmt->execute([(int) $_SESSION["user_id"]]);
$customer = $userStmt->fetch();

if (!$customer) {
    header("Location: logout.php");
    exit;
}

if (empty($_SESSION["csrf_tiffin_order"])) {
    $_SESSION["csrf_tiffin_order"] = bin2hex(random_bytes(32));
}

$customerName = $customer["full_name"];
$phone = "";
$deliveryAddress = "";
$startDate = "";
$specialRequests = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customerName = trim((string) ($_POST["customer_name"] ?? ""));
    $phone = trim((string) ($_POST["phone"] ?? ""));
    $deliveryAddress = trim((string) ($_POST["delivery_address"] ?? ""));
    $startDate = trim((string) ($_POST["start_date"] ?? ""));
    $specialRequests = trim((string) ($_POST["special_requests"] ?? ""));
    $token = (string) ($_POST["csrf_token"] ?? "");

    if (!hash_equals($_SESSION["csrf_tiffin_order"], $token)) {
        $errors[] = "Invalid form request. Please refresh the page.";
    }

    if ($customerName === "" || strlen($customerName) > 100) {
        $errors[] = "Enter a valid customer name (maximum 100 characters).";
    }

    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $errors[] = "Enter a valid contact number.";
    }

    if ($deliveryAddress === "" || strlen($deliveryAddress) > 1000) {
        $errors[] = "Enter a delivery address (maximum 1000 characters).";
    }

    $date = DateTimeImmutable::createFromFormat("!Y-m-d", $startDate);
    $today = new DateTimeImmutable("today");

    if (
        !$date ||
        $date->format("Y-m-d") !== $startDate ||
        $date < $today
    ) {
        $errors[] = "Choose a valid start date from today onwards.";
    }

    if (strlen($specialRequests) > 1000) {
        $errors[] = "Special requests must not exceed 1000 characters.";
    }

    if (empty($errors)) {

        try {

            $insert = $pdo->prepare(
                "INSERT INTO tiffin_orders
                (
                    user_id,
                    plan_id,
                    customer_name,
                    phone,
                    delivery_address,
                    start_date,
                    special_requests,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')"
            );

            $insert->execute([
                (int) $_SESSION["user_id"],
                (int) $plan["plan_id"],
                $customerName,
                $phone,
                $deliveryAddress,
                $startDate,
                $specialRequests
            ]);

            // Prevent accidental duplicate submission on refresh
            unset($_SESSION["csrf_tiffin_order"]);

            header("Location: order_tiffin.php?plan_id=" . $planId . "&success=1");
            exit;

        } catch (PDOException $exception) {

            error_log($exception->getMessage());
            $errors[] = "Unable to submit your order. Please try again.";
        }
    }
}

$pageTitle = "Order Monthly Tiffin | Prem Curry House";

require __DIR__ . "/includes/header.php";

?>

<section class="py-5" style="background:#FFF9ED; min-height:80vh;">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-lg-8">

                <div class="text-center mb-4">

                    <i class="bi bi-bag-check"
                       style="font-size:3rem; color:#527B35;"></i>

                    <h1 class="fw-bold mt-3" style="color:#527B35;">
                        Order Monthly Tiffin
                    </h1>

                    <p class="text-muted">
                        Submit your monthly meal delivery request.
                    </p>

                </div>

                <?php if (isset($_GET["success"])): ?>

                    <div class="alert alert-success text-center">

                        <h5 class="fw-bold">
                            <i class="bi bi-check-circle me-2"></i>
                            Order Request Submitted!
                        </h5>

                        <p class="mb-0">
                            Your request has been received.
                            The restaurant will need to confirm your order.
                        </p>

                    </div>

                    <div class="text-center mb-4">
                        <a href="tiffin.php" class="btn btn-success">
                            Back to Tiffin Plans
                        </a>
                    </div>

                <?php else: ?>

                    <!-- SELECTED PLAN -->
                    <div class="card border-0 shadow-sm mb-4">

                        <div class="card-body p-4">

                            <h4 class="fw-bold" style="color:#527B35;">
                                <?php echo e($plan["plan_name"]); ?>
                            </h4>

                            <p class="mb-1">
                                <strong>Price:</strong>
                                RM <?php echo number_format((float) $plan["price"], 2); ?>
                            </p>

                            <p class="mb-0">
                                <strong>Duration:</strong>
                                <?php echo (int) $plan["duration_days"]; ?> days
                            </p>

                            
                            <p class="text-muted small mt-2 mb-0">
                                Plan availability and pricing are subject to
                                confirmation by Prem Curry House.
                            </p>


                        </div>

                    </div>

                    <!-- ORDER FORM -->
                    <div class="card border-0 shadow-sm rounded-4">

                        <div class="card-body p-4 p-md-5">

                            <?php if (!empty($errors)): ?>

                                <div class="alert alert-danger">
                                    <strong>Please correct:</strong>
                                    <ul class="mb-0 mt-2">

                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo e($error); ?></li>
                                        <?php endforeach; ?>

                                    </ul>
                                </div>

                            <?php endif; ?>

                            <form
                                method="POST"
                                action="order_tiffin.php?plan_id=<?php echo (int) $planId; ?>"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo e($_SESSION["csrf_tiffin_order"]); ?>"
                                >

                                <div class="mb-3">

                                    <label for="customer_name"
                                           class="form-label fw-semibold">
                                        Customer Name
                                    </label>

                                    <input
                                        type="text"
                                        id="customer_name"
                                        name="customer_name"
                                        class="form-control"
                                        maxlength="100"
                                        value="<?php echo e($customerName); ?>"
                                        required
                                    >

                                </div>

                                <div class="mb-3">

                                    <label for="phone"
                                           class="form-label fw-semibold">
                                        Contact Number
                                    </label>

                                    <input
                                        type="tel"
                                        id="phone"
                                        name="phone"
                                        class="form-control"
                                        maxlength="20"
                                        placeholder="0123456789"
                                        value="<?php echo e($phone); ?>"
                                        required
                                    >

                                </div>

                                <div class="mb-3">

                                    <label for="delivery_address"
                                           class="form-label fw-semibold">
                                        Delivery Address
                                    </label>

                                    <textarea
                                        id="delivery_address"
                                        name="delivery_address"
                                        class="form-control"
                                        rows="3"
                                        maxlength="1000"
                                        required
                                    ><?php echo e($deliveryAddress); ?></textarea>

                                </div>

                                <div class="mb-3">

                                    <label for="start_date"
                                           class="form-label fw-semibold">
                                        Preferred Start Date
                                    </label>

                                    <input
                                        type="date"
                                        id="start_date"
                                        name="start_date"
                                        class="form-control"
                                        min="<?php echo date("Y-m-d"); ?>"
                                        value="<?php echo e($startDate); ?>"
                                        required
                                    >

                                </div>

                                <div class="mb-4">

                                    <label for="special_requests"
                                           class="form-label fw-semibold">
                                        Special Requests (Optional)
                                    </label>

                                    <textarea
                                        id="special_requests"
                                        name="special_requests"
                                        class="form-control"
                                        rows="3"
                                        maxlength="1000"
                                        placeholder="Meal preferences or delivery instructions"
                                    ><?php echo e($specialRequests); ?></textarea>

                                </div>

                                <button
                                    type="submit"
                                    class="btn btn-success w-100 py-2"
                                >
                                    <i class="bi bi-check-circle me-2"></i>
                                    Submit Order Request
                                </button>

                                <a
                                    href="tiffin.php"
                                    class="btn btn-outline-secondary w-100 mt-2"
                                >
                                    Cancel
                                </a>

                            </form>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</section>

<?php require __DIR__ . "/includes/footer.php"; ?>
