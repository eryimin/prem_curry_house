
<?php

require_once __DIR__ . "/config/database.php";

// Admin account details
$fullName = "Prem Curry House Admin";
$email = "admin@premcurryhouse.test";
$password = "ChangeMeBeforeLogin2026!";

// Hash the password securely
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Check whether admin account already exists
$check = $pdo->prepare(
    "SELECT user_id FROM users WHERE email = ?"
);

$check->execute([$email]);

$user = $check->fetch();

if ($user) {

    // Update existing admin account
    $stmt = $pdo->prepare(
        "UPDATE users
         SET full_name = ?, password = ?, role = 'admin'
         WHERE email = ?"
    );

    $stmt->execute([
        $fullName,
        $hashedPassword,
        $email
    ]);

    echo "<h2>Admin Account Updated Successfully!</h2>";
    echo "<p>You can now log in with your new password.</p>";

} else {

    // Create new admin account
    $stmt = $pdo->prepare(
        "INSERT INTO users
         (full_name, email, password, role)
         VALUES (?, ?, ?, 'admin')"
    );

    $stmt->execute([
        $fullName,
        $email,
        $hashedPassword
    ]);

    echo "<h2>Admin Account Created Successfully!</h2>";
    echo "<p>You can now log in.</p>";
}

echo '<p><a href="login.php">Go to Login Page</a></p>';

?>
