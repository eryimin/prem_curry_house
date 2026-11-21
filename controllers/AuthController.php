<?php

class AuthController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function login(string $email, string $password): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT user_id, full_name, email, password, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user["password"])) {
            return false;
        }

        if (!in_array($user["role"], ["admin", "staff", "guest"], true)) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION["user_id"] = (int) $user["user_id"];
        $_SESSION["full_name"] = $user["full_name"];
        $_SESSION["role"] = $user["role"];

        return true;
    }
}
