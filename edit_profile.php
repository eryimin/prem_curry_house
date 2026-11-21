
<?php

session_start();

// Only customers can edit their profiles
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "guest"
) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/config/database.php";

$userId = (int) $_SESSION["user_id"];

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Get current customer information
$stmt = $pdo->prepare(
    "SELECT user_id, full_name, email
     FROM users
     WHERE user_id = ? AND role = 'guest'
     LIMIT 1"
);

$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: logout.php");
    exit;
}

// Generate CSRF token
if (empty($_SESSION["csrf_edit_profile"])) {
    $_SESSION["csrf_edit_profile"] = bin2hex(random_bytes(32));
}

$fullName = $user["full_name"];
$email = $user["email"];
$errors = [];

// Process form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullName = trim((string) ($_POST["full_name"] ?? ""));
    $email = trim((string) ($_POST["email"] ?? ""));
    $token = (string) ($_POST["csrf_token"] ?? "");

    // Verify CSRF token
    if (!hash_equals($_SESSION["csrf_edit_profile"], $token)) {
        $errors[] = "Invalid request. Please refresh the page.";
    }

    // Validate full name
    if ($fullName === "") {
        $errors[] = "Full name is required.";
    } elseif (mb_strlen($fullName) > 100) {
        $errors[] = "Full name must not exceed 100 characters.";
    }

    // Validate email
    if ($email === "") {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } elseif (strlen($email) > 150) {
        $errors[] = "Email address is too long.";
    }

    if (empty($errors)) {

        // Check whether another account uses this email
        $check = $pdo->prepare(
            "SELECT user_id
             FROM users
             WHERE email = ? AND user_id != ?
             LIMIT 1"
        );

        $check->execute([$email, $userId]);

        if ($check->fetch()) {
            $errors[] = "This email address is already registered.";
        }
    }

    // Update customer profile
    if (empty($errors)) {

        try {

            $update = $pdo->prepare(
                "UPDATE users
                 SET full_name = ?, email = ?
                 WHERE user_id = ? AND role = 'guest'"
            );

            $update->execute([
                $fullName,
                $email,
                $userId
            ]);

            // Update session name
            $_SESSION["full_name"] = $fullName;

            // Redirect after successful update
            header("Location: profile.php?updated=1");
            exit;

        } catch (PDOException $exception) {

            // Handle duplicate email if another request used it
            if ($exception->getCode() === "23000") {
                $errors[] = "This email address is already registered.";
            } else {
                error_log($exception->getMessage());
                $errors[] = "Unable to update your profile right now.";
            }
        }
    }
}

$pageTitle = "Edit Profile | Prem Curry House";

require __DIR__ . "/includes/header.php";

?>

<section class="py-5" style="background:#FFF9ED; min-height:80vh;">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-lg-7">

                <div class="text-center mb-4">

                    <i class="bi bi-pencil-square"
                       style="font-size:3rem; color:#527B35;"></i>

                    <h1 class="fw-bold mt-3" style="color:#527B35;">
                        Edit My Profile
                    </h1>

                    <p class="text-muted">
                        Update your customer account information.
                    </p>

                </div>

                <div class="card border-0 shadow-sm rounded-4">

                    <div class="card-body p-4 p-md-5">

                        <?php if (!empty($errors)): ?>

                            <div class="alert alert-danger">

                                <strong>Please correct the following:</strong>

                                <ul class="mb-0 mt-2">

                                    <?php foreach ($errors as $error): ?>

                                        <li><?php echo e($error); ?></li>

                                    <?php endforeach; ?>

                                </ul>

                            </div>

                        <?php endif; ?>

                        <form method="POST" action="edit_profile.php">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo e($_SESSION["csrf_edit_profile"]); ?>"
                            >

                            <!-- FULL NAME -->
                            <div class="mb-3">

                                <label for="full_name"
                                       class="form-label fw-semibold">
                                    Full Name
                                </label>

                                <input
                                    type="text"
                                    id="full_name"
                                    name="full_name"
                                    class="form-control"
                                    maxlength="100"
                                    value="<?php echo e($fullName); ?>"
                                    required
                                >

                            </div>

                            <!-- EMAIL -->
                            <div class="mb-4">

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

                            <div class="d-flex flex-wrap gap-3">

                                <button type="submit"
                                        class="btn btn-success">

                                    <i class="bi bi-check-circle me-2"></i>
                                    Save Changes

                                </button>

                                <a href="profile.php"
                                   class="btn btn-outline-secondary">

                                    Cancel

                                </a>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<?php require __DIR__ . "/includes/footer.php"; ?>
