<?php

require_once __DIR__ . "/../models/Menu.php";

class MenuController
{
    private Menu $menuModel;

    public function __construct(PDO $pdo)
    {
        $this->menuModel = new Menu($pdo);
    }

    public function index(): void
    {
        $categories = $this->menuModel->getCategories();

        // Read the selected category safely
        $categoryId = filter_input(
            INPUT_GET,
            "category",
            FILTER_VALIDATE_INT
        );

        if ($categoryId === false || $categoryId === null) {
            $categoryId = null;
        }

        // Read the search keyword
        $search = trim((string) ($_GET["search"] ?? ""));

        // Limit search length
        $search = substr($search, 0, 100);

        // Fetch matching menu items
        $menuItems = $this->menuModel->getItems(
            $categoryId,
            $search
        );

        $pageTitle = "Our Menu | Prem Curry House";

        require __DIR__ . "/../includes/header.php";
        require __DIR__ . "/../views/menu.php";
        require __DIR__ . "/../includes/footer.php";
    }
}
