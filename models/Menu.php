<?php

class Menu
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Retrieve all menu categories
    public function getCategories(): array
    {
        $stmt = $this->pdo->query(
            "SELECT category_id, category_name
             FROM categories
             ORDER BY category_id"
        );

        return $stmt->fetchAll();
    }

    // Retrieve menu items with optional filters
    public function getItems(
        ?int $categoryId = null,
        string $search = ""
    ): array {
        $sql = "
            SELECT
                m.item_id,
                m.item_name,
                m.price,
                m.description,
                m.image,
                m.is_available,
                c.category_name
            FROM menu_items m
            INNER JOIN categories c
                ON m.category_id = c.category_id
            WHERE 1 = 1
        ";

        $params = [];

        if ($categoryId !== null) {
            $sql .= " AND m.category_id = :category_id";
            $params["category_id"] = $categoryId;
        }

        if ($search !== "") {
            $sql .= " AND m.item_name LIKE :search";
            $params["search"] = "%" . $search . "%";
        }

        $sql .= " ORDER BY c.category_id, m.item_name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
