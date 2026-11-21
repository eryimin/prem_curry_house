
<?php

session_start();

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/controllers/AuthController.php";

// Redirect logged-in users to the correct page
function redirectByRole(): void
{
    $role = $_SESSION["role"] ?? "";

    if ($role === "admin" || $role === "staff") {
        header("Location: admin/dashboard.php");
    } elseif ($role === "guest") {
        header("Location: index.php");
    } else {
        header("Location: logout.php");
    }

    exit;
}

if (isset($_SESSION["user_id"])) {
    redirectByRole();
}

$error = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim((string) ($_POST["email"] ?? ""));
    $password = (string) ($_POST["password"] ?? "");

    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {

        $auth = new AuthController($pdo);

        if ($auth->login($email, $password)) {
            redirectByRole();
        }

        $error = "Invalid email address or password.";
    }
}

function escapeHtml($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Account Login | Prem Curry House</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body style="background-color:#FFF9ED;">

    <nav class="navbar navbar-expand-lg bg-white shadow-sm">
        <div class="container">

            <a href="index.php"
               class="navbar-brand fw-bold"
               style="color:#527B35;">
                <i class="bi bi-flower1 me-2"></i>
                PREM CURRY HOUSE
            </a>

            <a href="index.php" class="btn btn-outline-success btn-sm">
                Back to Home
            </a>

        </div>
    </nav>

    <?php require __DIR__ . "/views/login.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
