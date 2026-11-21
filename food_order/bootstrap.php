<?php
// Prem Curry House - Food Order Helper
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Food order settings
const PCH_TAX_RATE = 0.06;
const PCH_DELIVERY_ENABLED = true;
const PCH_DELIVERY_FEE = 0.00; // Legacy fallback; use pch_delivery_fee() for delivery orders.
const PCH_ONLINE_MODE = 'demo';

// Delivery areas and fees
function pch_delivery_areas(): array {
    return [
        'taman_petaling_indah' => [
            'name' => 'Taman Petaling Indah',
            'fee' => 3.00
        ],
        'taman_chi_liung' => [
            'name' => 'Taman Chi Liung',
            'fee' => 3.00
        ],
        'klang_town' => [
            'name' => 'Klang Town',
            'fee' => 5.00
        ],
        'bandar_bukit_tinggi' => [
            'name' => 'Bandar Bukit Tinggi',
            'fee' => 7.00
        ],
        'bandar_botanic' => [
            'name' => 'Bandar Botanic',
            'fee' => 7.00
        ],
        'taman_sentosa' => [
            'name' => 'Taman Sentosa',
            'fee' => 9.00
        ],
        'bandar_puteri_klang' => [
            'name' => 'Bandar Puteri Klang',
            'fee' => 9.00
        ]
    ];
}

// Calculate delivery fee using the selected area
function pch_delivery_fee(string $orderType, ?string $area = null): float {
    if ($orderType === 'dine_in') {
        return 0.00;
    }

    if ($orderType !== 'delivery' || !PCH_DELIVERY_ENABLED) {
        throw new InvalidArgumentException('Invalid or unavailable delivery service.');
    }

    $areas = pch_delivery_areas();
    $area = $area ?? (string)($_SESSION['delivery_area'] ?? '');

    if (!array_key_exists($area, $areas)) {
        throw new InvalidArgumentException('Invalid delivery area.');
    }

    return (float)$areas[$area]['fee'];
}

// Get delivery area name
function pch_delivery_area_name(string $area): string {
    $areas = pch_delivery_areas();
    return $areas[$area]['name'] ?? '';
}

// Escape HTML
function pch_h($x): string {
    return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
}

// Format money
function pch_money($n): string {
    return 'RM ' . number_format((float)$n, 2);
}

// Round cash payments to the nearest 5 sen
function pch_round_cash(float $amount): float {
    $cents = (int)round($amount * 100);
    return round(round($cents / 5) * 5 / 100, 2);
}

// Calculate final total and rounding adjustment
function pch_order_totals(
    float $subtotal,
    float $tax,
    float $deliveryFee,
    string $orderType
): array {
    $originalTotal = round($subtotal + $tax + $deliveryFee, 2);

    $finalTotal = $orderType === 'dine_in'
        ? pch_round_cash($originalTotal)
        : $originalTotal;

    return [
        'original_total' => $originalTotal,
        'rounding_adjustment' => round($finalTotal - $originalTotal, 2),
        'total' => $finalTotal
    ];
}

// CSRF token
function pch_csrf(): string {
    return $_SESSION['pch_food_csrf'] ??= bin2hex(random_bytes(32));
}

function pch_verify(): void {
    if (!hash_equals(pch_csrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid form token. Please refresh.');
    }
}

// Get food cart
function pch_cart(): array {
    return $_SESSION['pch_food_cart'] ?? [];
}

// Get menu item
function pch_menu_item(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT item_id, item_name, price, is_available
        FROM menu_items
        WHERE item_id = ?
    ");
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

// Food customisation options
function pch_modifiers(): array {
    return [
        // 123 => [
        //     'Portion' => ['Regular' => 0, 'Large' => 3.00],
        //     'Extras' => ['Extra egg' => 2.00]
        // ],
    ];
}

// Calculate food cart
function pch_calculate(PDO $pdo, array $cart): array {
    $lines = [];
    $subtotal = 0;

    foreach ($cart as $entry) {
        $item = pch_menu_item($pdo, (int)($entry['id'] ?? 0));

        if (!$item || !(int)$item['is_available']) {
            continue;
        }

        $qty = max(1, min(20, (int)($entry['qty'] ?? 1)));
        $options = [];
        $extra = 0;
        $modifiers = pch_modifiers();

        foreach (($entry['options'] ?? []) as $group => $choice) {
            $choices = $modifiers[(int)$item['item_id']][$group] ?? [];

            if (isset($choices[$choice])) {
                $extra += (float)$choices[$choice];
                $options[$group] = $choice;
            }
        }

        $unit = round((float)$item['price'] + $extra, 2);
        $line = round($unit * $qty, 2);
        $subtotal += $line;

        $lines[] = [
            'item' => $item,
            'qty' => $qty,
            'options' => $options,
            'remark' => substr((string)($entry['remark'] ?? ''), 0, 300),
            'unit' => $unit,
            'total' => $line
        ];
    }

    return [
        'lines' => $lines,
        'subtotal' => round($subtotal, 2)
    ];
}

// Automatic food recommendations
function pch_suggestions(PDO $pdo, array $item): array {
    static $availableItems = null;

    if ($availableItems === null) {
        $stmt = $pdo->query("
            SELECT item_id, item_name, price
            FROM menu_items
            WHERE is_available = 1
            ORDER BY item_name ASC
        ");
        $availableItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $name = strtolower((string)($item['item_name'] ?? ''));
    $category = strtolower((string)($item['category_name'] ?? ''));
    $keywords = [];

    // Rice and briyani
    if (preg_match('/rice|nasi|briyani|biryani/i', $name)) {
        $keywords = [
            'papadum', 'papadam', 'egg',
            'raita', 'limau', 'teh'
        ];
    }

    // Noodles
    elseif (preg_match('/noodle|mee|maggi|bihun|bee hoon|kuey teow/i', $name)) {
        $keywords = [
            'egg', 'chicken', 'limau',
            'teh', 'milo'
        ];
    }

    // Bento box
    elseif (preg_match('/bento/i', $name)) {
        $keywords = [
            'papadum', 'egg', 'fries',
            'limau', 'juice'
        ];
    }

    // Naan and Indian breads
    elseif (preg_match('/naan|roti|chapati|paratha|tosai|dosa/i', $name)) {
        $keywords = [
            'dhal', 'curry', 'gravy',
            'chutney', 'teh'
        ];
    }

    // Chicken cutlet and fried chicken
    elseif (preg_match('/cutlet|fried chicken/i', $name)) {
        $keywords = [
            'fries', 'sauce', 'limau',
            'juice', 'milo'
        ];
    }

    // Other main dishes
    elseif (
        preg_match('/chicken|mutton|fish|vegetarian/i', $name) ||
        preg_match('/non-vegetarian|vegetarian/i', $category)
    ) {
        $keywords = [
            'rice', 'papadum', 'naan',
            'limau', 'teh'
        ];
    }

    // Drinks do not receive food recommendations
    elseif (
        preg_match('/coffee|kopi|teh|milo|juice|limau|drink/i', $name) ||
        str_contains($category, 'drink')
    ) {
        return [];
    }

    // Sides and snacks
    else {
        $keywords = [
            'limau', 'teh', 'coffee', 'juice'
        ];
    }

    $suggestions = [];
    $currentId = (int)($item['item_id'] ?? 0);

    foreach ($keywords as $keyword) {
        foreach ($availableItems as $extra) {
            $extraId = (int)$extra['item_id'];
            $extraName = strtolower((string)$extra['item_name']);

            if ($extraId === $currentId || isset($suggestions[$extraId])) {
                continue;
            }

            if (stripos($extraName, $keyword) !== false) {
                $suggestions[$extraId] = [
                    'id' => $extraId,
                    'name' => $extra['item_name'],
                    'price' => (float)$extra['price']
                ];
                break;
            }
        }

        if (count($suggestions) >= 4) {
            break;
        }
    }

    return array_values($suggestions);
}