<?php
require_once __DIR__ . "/config/database.php";

$stmt = $pdo->query(
    "SELECT COUNT(*) AS total FROM menu_items"
);

$result = $stmt->fetch();

echo "<h2>Database Connected Successfully!</h2>";
echo "<p>Total menu items: "
    . (int)$result["total"] . "</p>";
?>
