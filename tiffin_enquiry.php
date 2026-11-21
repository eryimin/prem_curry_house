
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Customers may enquire without logging in.
// Logged-in customers will have their account linked.
$userId = null;
$customerName = "";
$email = "";

if (
    isset($_SESSION["user_id"]) &&
    ($_SESSION["role"] ?? "") === "guest"
) {
    $userId = (int) $_SESSION["user_id"];

    $userStmt = $pdo->prepare(
        "SELECT full_name, email
         FROM users
         WHERE user_id = ? AND role = 'guest'
         LIMIT 1"
    );

    $userStmt->execute([$userId]);
    $customer = $userStmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        $customerName = $customer["full_name"];
        $email = $customer["email"];
    } else {
        $userId = null;
    }
}

if (empty($_SESSION["csrf_tiffin_enquiry"])) {
    $_SESSION["csrf_tiffin_enquiry"] = bin2hex(random_bytes(32));
}

$phone = "";
$subject = "";
$message = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customerName = trim((string) ($_POST["customer_name"] ?? ""));
    $email = trim((string) ($_POST["email"] ?? ""));
    $phone = trim((string) ($_POST["phone"] ?? ""));
    $subject = trim((string) ($_POST["subject"] ?? ""));
    $message = trim((string) ($_POST["message"] ?? ""));
    $token = (string) ($_POST["csrf_token"] ?? "");

    if (!hash_equals($_SESSION["csrf_tiffin_enquiry"], $token)) {
        $errors[] = "Invalid form request. Please refresh the page.";
    }

    if ($customerName === "" || strlen($customerName) > 100) {
        $errors[] = "Enter a valid name (maximum 100 characters).";
    }

    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150
    ) {
        $errors[] = "Enter a valid email address.";
    }

    if (
        $phone !== "" &&
        !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)
    ) {
        $errors[] = "Enter a valid phone number or leave it blank.";
    }

    if ($subject === "" || strlen($subject) > 150) {
        $errors[] = "Please select an enquiry subject.";
    }

    $allowedSubjects = [
        "Tiffin Plans and Pricing",
        "Delivery Areas",
        "Vegetarian and Non-Vegetarian Options",
        "Meal Preferences",
        "Other Enquiry"
    ];

    if (!in_array($subject, $allowedSubjects, true)) {
        $errors[] = "Please choose a valid enquiry subject.";
    }

    if ($message === "" || strlen($message) > 2000) {
        $errors[] = "Enter your enquiry (maximum 2000 characters).";
    }

    if (empty($errors)) {

        try {

            $stmt = $pdo->prepare(
                "INSERT INTO tiffin_enquiries
                (
                    user_id,
                    customer_name,
                    email,
                    phone,
                    subject,
                    message,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, 'New')"
            );

            $stmt->execute([
                $userId,
                $customerName,
                $email,
                $phone === "" ? null : $phone,
                $subject,
                $message
            ]);

            // Rotate token after successful submission
            $_SESSION["csrf_tiffin_enquiry"] = bin2hex(random_bytes(32));

            header("Location: tiffin_enquiry.php?success=1");
            exit;

        } catch (PDOException $exception) {

            error_log($exception->getMessage());
            $errors[] = "Unable to send your enquiry. Please try again.";
        }
    }
}

$pageTitle = "Monthly Tiffin Enquiry | Prem Curry House";

require __DIR__ . "/includes/header.php";

?>

<section class="py-5" style="background:#FFF9ED; min-height:80vh;">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-lg-8">

                <div class="text-center mb-4">

                    <i class="bi bi-chat-dots"
                       style="font-size:3rem; color:#527B35;"></i>

                    <h1 class="fw-bold mt-3" style="color:#527B35;">
                        Monthly Tiffin Enquiry
                    </h1>

                    <p class="text-muted">
                        Have questions about our monthly meal plans?
                        Send us an enquiry.
                    </p>

                </div>

                <?php if (
                    isset($_GET["success"]) &&
                    $_GET["success"] === "1"
                ): ?>

                    <div class="alert alert-success text-center">

                        <h5 class="fw-bold">
                            <i class="bi bi-check-circle me-2"></i>
                            Enquiry Submitted!
                        </h5>

                        <p class="mb-0">
                            Your enquiry has been received.
                            Our team will review your message.
                        </p>

                    </div>

                <?php endif; ?>

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

                        <form method="POST" action="tiffin_enquiry.php">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo e(
                                    $_SESSION["csrf_tiffin_enquiry"]
                                ); ?>"
                            >

                            <!-- NAME -->
                            <div class="mb-3">

                                <label for="customer_name"
                                       class="form-label fw-semibold">
                                    Full Name
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

                            <!-- EMAIL -->
                            <div class="mb-3">

                                <label for="email"
                                       class="form-label fw-semibold">
                                    Email Address
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control"
                                    maxlength="150"
                                    value="<?php echo e($email); ?>"
                                    required
                                >

                            </div>

                            <!-- PHONE -->
                            <div class="mb-3">

                                <label for="phone"
                                       class="form-label fw-semibold">
                                    Contact Number (Optional)
                                </label>

                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    class="form-control"
                                    maxlength="20"
                                    placeholder="0123456789"
                                    value="<?php echo e($phone); ?>"
                                >

                            </div>

                            <!-- SUBJECT -->
                            <div class="mb-3">

                                <label for="subject"
                                       class="form-label fw-semibold">
                                    Enquiry Subject
                                </label>

                                <select
                                    id="subject"
                                    name="subject"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        Select a subject
                                    </option>

                                    <?php
                                        $subjects = [
                                            "Tiffin Plans and Pricing",
                                            "Delivery Areas",
                                            "Vegetarian and Non-Vegetarian Options",
                                            "Meal Preferences",
                                            "Other Enquiry"
                                        ];
                                    ?>

                                    <?php foreach ($subjects as $option): ?>

                                        <option
                                            value="<?php echo e($option); ?>"
                                            <?php echo
                                                $subject === $option
                                                    ? "selected"
                                                    : "";
                                            ?>
                                        >
                                            <?php echo e($option); ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <!-- MESSAGE -->
                            <div class="mb-4">

                                <label for="message"
                                       class="form-label fw-semibold">
                                    Your Message
                                </label>

                                <textarea
                                    id="message"
                                    name="message"
                                    class="form-control"
                                    rows="5"
                                    maxlength="2000"
                                    placeholder="Type your question here..."
                                    required
                                ><?php echo e($message); ?></textarea>

                            </div>

                            <!-- SUBMIT -->
                            <button
                                type="submit"
                                class="btn btn-success w-100 py-2"
                            >
                                <i class="bi bi-send me-2"></i>
                                Submit Enquiry
                            </button>

                            <a
                                href="tiffin.php"
                                class="btn btn-outline-secondary w-100 mt-2"
                            >
                                Back to Tiffin Plans
                            </a>

                        </form>

                    </div>

                </div>

                <p class="text-muted text-center small mt-4">
                    Submitting an enquiry does not place an order.
                    Our team will review your question.
                </p>

            </div>

        </div>

    </div>

</section>

<?php require __DIR__ . "/includes/footer.php"; ?>
