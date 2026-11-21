<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/controllers/MenuController.php";

$controller = new MenuController($pdo);
$controller->index();