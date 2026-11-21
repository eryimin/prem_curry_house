<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check customer login
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'guest'
) {
    header('Location: index.php');
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Verify CSRF token
if (
    empty($_SESSION['csrf_order_type']) ||
    !hash_equals(
        $_SESSION['csrf_order_type'],
        (string)($_POST['csrf_token'] ?? '')
    )
) {
    http_response_code(403);
    exit('Invalid request. Please return to the homepage.');
}

// Get order type
$orderType = (string)($_POST['order_type'] ?? '');

if (!in_array($orderType, ['dine_in', 'delivery'], true)) {
    http_response_code(400);
    exit('Please select a valid order type.');
}

// Available delivery areas and fees
$deliveryAreas = [
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

// Dine In
if ($orderType === 'dine_in') {

    $table = filter_var(
        $_POST['table_number'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        $table === false ||
        $table === null ||
        $table < 1 ||
        $table > 10
    ) {
        http_response_code(400);
        exit('Please select a valid table number.');
    }

    $_SESSION['order_type'] = 'dine_in';
    $_SESSION['table_number'] = $table;

    // Clear previous delivery information
    unset(
        $_SESSION['delivery_area'],
        $_SESSION['delivery_area_name'],
        $_SESSION['delivery_address'],
        $_SESSION['delivery_fee'],
        $_SESSION['delivery_distance_km'],
        $_SESSION['delivery_distance_meters']
    );

} else {

    // Delivery
    $deliveryArea = trim(
        (string)($_POST['delivery_area'] ?? '')
    );

    if (!array_key_exists($deliveryArea, $deliveryAreas)) {
        http_response_code(422);
        exit(
            'Sorry, delivery is currently unavailable in your area. ' .
            'Please select another area or choose Dine In.'
        );
    }

    $address = trim(
        (string)($_POST['delivery_address'] ?? '')
    );

    if (
        $address === '' ||
        mb_strlen($address, 'UTF-8') > 500
    ) {
        http_response_code(400);
        exit('Please enter a valid delivery address.');
    }

    // Get delivery area and fee
    $areaName = $deliveryAreas[$deliveryArea]['name'];
    $deliveryFee = (float)$deliveryAreas[$deliveryArea]['fee'];

    // Save delivery information
    $_SESSION['order_type'] = 'delivery';
    $_SESSION['delivery_area'] = $deliveryArea;
    $_SESSION['delivery_area_name'] = $areaName;
    $_SESSION['delivery_address'] = $address;
    $_SESSION['delivery_fee'] = $deliveryFee;

    // Clear previous dine-in information
    unset(
        $_SESSION['table_number'],
        $_SESSION['delivery_distance_km'],
        $_SESSION['delivery_distance_meters']
    );
}

// Refresh CSRF token
$_SESSION['csrf_order_type'] = bin2hex(random_bytes(32));

// Redirect to menu
header('Location: menu.php');
exit;