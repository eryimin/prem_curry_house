
<?php

session_start();

// Allow only admin and staff
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

// Generate CSRF token
if (empty($_SESSION["csrf_manage_enquiries"])) {
    $_SESSION["csrf_manage_enquiries"] = bin2hex(random_bytes(32));
}

$error = "";

$allowedStatuses = [
    "New",
    "In Progress",
    "Resolved"
];

// Update enquiry status
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $enquiryId = filter_var(
        $_POST["enquiry_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $status = (string) ($_POST["status"] ?? "");
    $token = (string) ($_POST["csrf_token"] ?? "");

    if (!hash_equals($_SESSION["csrf_manage_enquiries"], $token)) {
        $error = "Invalid request. Please refresh the page.";
    } elseif (!$enquiryId || $enquiryId < 1) {
        $error = "Invalid enquiry ID.";
    } elseif (!in_array($status, $allowedStatuses, true)) {
        $error = "Invalid enquiry status.";
    } else {

        try {

            // Check enquiry exists
            $check = $pdo->prepare(
                "SELECT enquiry_id
                 FROM tiffin_enquiries
                 WHERE enquiry_id = ?
                 LIMIT 1"
            );

            $check->execute([$enquiryId]);

            if (!$check->fetch()) {
                $error = "Enquiry not found.";
            } else {

                $update = $pdo->prepare(
                    "UPDATE tiffin_enquiries
                     SET status = ?
                     WHERE enquiry_id = ?"
                );

                $update->execute([$status, $enquiryId]);

                header("Location: tiffin_enquiries.php?updated=1");
                exit;
            }

        } catch (PDOException $exception) {

            error_log($exception->getMessage());
            $error = "Unable to update enquiry status.";
        }
    }
}

// Get all enquiries
$stmt = $pdo->query(
    "SELECT
        enquiry_id,
        customer_name,
        email,
        phone,
        subject,
        message,
        status,
        created_at
     FROM tiffin_enquiries
     ORDER BY created_at DESC, enquiry_id DESC"
);

$enquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count enquiries
$totalEnquiries = count($enquiries);
$newEnquiries = 0;
$inProgressEnquiries = 0;
$resolvedEnquiries = 0;

foreach ($enquiries as $enquiry) {

    if ($enquiry["status"] === "New") {
        $newEnquiries++;
    }

    if ($enquiry["status"] === "In Progress") {
        $inProgressEnquiries++;
    }

    if ($enquiry["status"] === "Resolved") {
        $resolvedEnquiries++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Manage Tiffin Enquiries | Prem Curry House</title>

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

<!-- ADMIN NAVBAR -->
<nav class="navbar navbar-dark"
     style="background:#527B35;">

    <div class="container">

        <a href="dashboard.php" class="navbar-brand fw-bold">
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

            <i class="bi bi-chat-square-text"
               style="font-size:3rem; color:#527B35;"></i>

            <h1 class="fw-bold mt-3" style="color:#527B35;">
                Manage Tiffin Enquiries
            </h1>

            <p class="text-muted">
                View and manage customer questions about monthly tiffin.
            </p>

        </div>

        <!-- STATISTICS -->
        <div class="row g-3 mb-4">

            <div class="col-md-3">

                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">

                        <h6>Total Enquiries</h6>

                        <h2 class="fw-bold">
                            <?php echo $totalEnquiries; ?>
                        </h2>

                    </div>
                </div>

            </div>

            <div class="col-md-3">

                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">

                        <h6>New</h6>

                        <h2 class="fw-bold text-primary">
                            <?php echo $newEnquiries; ?>
                        </h2>

                    </div>
                </div>

            </div>

            <div class="col-md-3">

                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">

                        <h6>In Progress</h6>

                        <h2 class="fw-bold text-warning">
                            <?php echo $inProgressEnquiries; ?>
                        </h2>

                    </div>
                </div>

            </div>

            <div class="col-md-3">

                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">

                        <h6>Resolved</h6>

                        <h2 class="fw-bold text-success">
                            <?php echo $resolvedEnquiries; ?>
                        </h2>

                    </div>
                </div>

            </div>

        </div>

        <!-- SUCCESS MESSAGE -->
        <?php if (
            isset($_GET["updated"]) &&
            $_GET["updated"] === "1"
        ): ?>

            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                Enquiry status updated successfully.
            </div>

        <?php endif; ?>

        <!-- ERROR MESSAGE -->
        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">
                <?php echo e($error); ?>
            </div>

        <?php endif; ?>

        <?php if (empty($enquiries)): ?>

            <div class="alert alert-info text-center">
                No customer enquiries have been submitted yet.
            </div>

        <?php else: ?>

            <?php foreach ($enquiries as $enquiry): ?>

                <?php

                    $badgeClasses = [
                        "New" => "bg-primary",
                        "In Progress" => "bg-warning text-dark",
                        "Resolved" => "bg-success"
                    ];

                    $badgeClass = $badgeClasses[$enquiry["status"]]
                        ?? "bg-secondary";

                ?>

                <div class="card border-0 shadow-sm rounded-4 mb-4">

                    <div class="card-body p-4">

                        <!-- ENQUIRY HEADER -->
                        <div class="d-flex justify-content-between
                                    align-items-start flex-wrap gap-2">

                            <div>

                                <h4 class="fw-bold" style="color:#527B35;">
                                    Enquiry #<?php echo (int) $enquiry["enquiry_id"]; ?>
                                </h4>

                                <p class="text-muted mb-0">
                                    Submitted:
                                    <?php echo e(
                                        date(
                                            "d M Y, h:i A",
                                            strtotime($enquiry["created_at"])
                                        )
                                    ); ?>
                                </p>

                            </div>

                            <span class="badge <?php echo $badgeClass; ?> fs-6">
                                <?php echo e($enquiry["status"]); ?>
                            </span>

                        </div>

                        <hr>

                        <!-- CUSTOMER INFORMATION -->
                        <div class="row g-3">

                            <div class="col-md-6">
                                <strong>Customer Name</strong>
                                <p><?php echo e($enquiry["customer_name"]); ?></p>
                            </div>

                            <div class="col-md-6">
                                <strong>Email Address</strong>
                                <p><?php echo e($enquiry["email"]); ?></p>
                            </div>

                            <div class="col-md-6">
                                <strong>Contact Number</strong>
                                <p>
                                    <?php echo e(
                                        $enquiry["phone"] ?: "Not provided"
                                    ); ?>
                                </p>
                            </div>

                            <div class="col-md-6">
                                <strong>Subject</strong>
                                <p><?php echo e($enquiry["subject"]); ?></p>
                            </div>

                            <div class="col-12">
                                <strong>Customer Message</strong>
                                <p>
                                    <?php echo nl2br(
                                        e($enquiry["message"])
                                    ); ?>
                                </p>
                            </div>

                        </div>

                        <hr>

                        <!-- UPDATE STATUS FORM -->
                        <form
                            method="POST"
                            action="tiffin_enquiries.php"
                            class="row g-3 align-items-end"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo e(
                                    $_SESSION["csrf_manage_enquiries"]
                                ); ?>"
                            >

                            <input
                                type="hidden"
                                name="enquiry_id"
                                value="<?php echo (int) $enquiry["enquiry_id"]; ?>"
                            >

                            <div class="col-md-8">

                                <label class="form-label fw-semibold">
                                    Update Enquiry Status
                                </label>

                                <select
                                    name="status"
                                    class="form-select"
                                    required
                                >

                                    <?php foreach ($allowedStatuses as $status): ?>

                                        <option
                                            value="<?php echo e($status); ?>"
                                            <?php echo
                                                $enquiry["status"] === $status
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
