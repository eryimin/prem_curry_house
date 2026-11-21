
<?php

session_start();

require_once __DIR__ . "/config/database.php";

// Redirect users who are already logged in
if (isset($_SESSION["user_id"])) {
    if (in_array($_SESSION["role"] ?? "", ["admin", "staff"], true)) {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$fullName = "";
$email = "";
$errors = [];

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    // Check CSRF token
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {
        $errors[] = "Invalid form request. Please refresh the page.";
    }

    // Validate full name
    if ($fullName === "") {
        $errors[] = "Full name is required.";
    } elseif (mb_strlen($fullName) > 100) {
        $errors[] = "Full name cannot exceed 100 characters.";
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } elseif (strlen($email) > 150) {
        $errors[] = "Email address is too long.";
    }

    // Validate password
    if (strlen($password) < 8) {
        $errors[] = "Password must contain at least 8 characters.";
    } elseif (strlen($password) > 72) {
        $errors[] = "Password must not exceed 72 characters.";
    }

    // Confirm passwords match
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    // Check whether email already exists
    if (empty($errors)) {

        $stmt = $pdo->prepare(
            "SELECT user_id FROM users WHERE email = :email"
        );

        $stmt->execute(["email" => $email]);

        if ($stmt->fetch()) {
            $errors[] = "This email address is already registered.";
        }
    }

    // Register customer
    if (empty($errors)) {

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare(
            "INSERT INTO users
                (full_name, email, password, role)
             VALUES
                (:full_name, :email, :password, 'guest')"
        );

        try {
            $stmt->execute([
                "full_name" => $fullName,
                "email" => $email,
                "password" => $hashedPassword
            ]);

            header("Location: login.php?registered=1");
            exit;

        } catch (PDOException $exception) {

            // Handle duplicate email safely
            if ($exception->getCode() === "23000") {
                $errors[] = "This email address is already registered.";
            } else {
                error_log($exception->getMessage());
                $errors[] = "Registration failed. Please try again.";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Register | Prem Curry House</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body style="background-color:#FFF9ED;">

<nav class="navbar navbar-light bg-white shadow-sm">
    <div class="container">

        <a href="index.php"
           class="navbar-brand fw-bold"
           style="color:#527B35;">

            <i class="bi bi-flower1 me-2"></i>
            PREM CURRY HOUSE

        </a>

        <a href="login.php"
           class="btn btn-outline-success btn-sm">
            Login
        </a>

    </div>
</nav>

<main class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-8 col-lg-6">

            <div class="card border-0 shadow-sm rounded-4">

                <div class="card-body p-4 p-md-5">

                    <div class="text-center mb-4">

                        <i class="bi bi-person-plus fs-1"
                           style="color:#527B35;"></i>

                        <h2 class="fw-bold mt-2"
                            style="color:#527B35;">
                            Create Your Account
                        </h2>

                        <p class="text-muted">
                            Join Prem Curry House as a customer.
                        </p>

                    </div>

                    <?php if (!empty($errors)): ?>

                        <div class="alert alert-danger">

                            <strong>Please correct these errors:</strong>

                            <ul class="mb-0 mt-2">

                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo e($error); ?></li>
                                <?php endforeach; ?>

                            </ul>

                        </div>

                    <?php endif; ?>

                    <form action="register.php" method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo e($_SESSION["csrf_token"]); ?>">

                        <!-- FULL NAME -->
                        <div class="mb-3">

                            <label for="full_name"
                                   class="form-label fw-semibold">
                                Full Name *
                            </label>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                class="form-control"
                                maxlength="100"
                                autocomplete="name"
                                value="<?php echo e($fullName); ?>"
                                placeholder="Enter your full name"
                                required>

                        </div>

                        <!-- EMAIL -->
                        <div class="mb-3">

                            <label for="email"
                                   class="form-label fw-semibold">
                                Email Address *
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control"
                                maxlength="150"
                                autocomplete="email"
                                value="<?php echo e($email); ?>"
                                placeholder="example@email.com"
                                required>

                        </div>

                        <!-- PASSWORD -->
                        <div class="mb-3">

                            <label for="password"
                                   class="form-label fw-semibold">
                                Password *
                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                placeholder="Minimum 8 characters"
                                required>

                        </div>

                        <!-- CONFIRM PASSWORD -->
                        <div class="mb-4">

                            <label for="confirm_password"
                                   class="form-label fw-semibold">
                                Confirm Password *
                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-control"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                placeholder="Re-enter your password"
                                required>

                        </div>

                        <!-- REGISTER BUTTON -->
                        <button
                            type="submit"
                            class="btn btn-success w-100 py-2">

                            <i class="bi bi-person-check me-2"></i>
                            Create Account

                        </button>

                    </form>

                    <p class="text-center text-muted mt-4 mb-0">

                        Already have an account?

                        <a href="login.php"
                           class="text-success fw-semibold">
                            Login here
                        </a>

                    </p>

                </div>

            </div>

        </div>

    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
