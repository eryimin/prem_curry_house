
<?php
// ============================================
// PREM CURRY HOUSE - HOMEPAGE
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

$pageTitle = "Prem Curry House | Home";

// ============================================
// CUSTOMER ORDER TYPE POPUP
// ============================================

$showOrderTypePopup = false;

// Only show popup when customer clicks Our Menu
if (
    isset($_SESSION['user_id']) &&
    ($_SESSION['role'] ?? '') === 'guest' &&
    ($_GET['choose_order'] ?? '') === '1'
) {
    $showOrderTypePopup = true;
}

// Security token
if (empty($_SESSION['csrf_order_type'])) {
    $_SESSION['csrf_order_type'] = bin2hex(random_bytes(32));
}


// ============================================
// CONTACT FORM CONFIGURATION
// ============================================

if (empty($_SESSION['csrf_contact'])) {
    $_SESSION['csrf_contact'] = bin2hex(random_bytes(32));
}

$contactName = '';
$contactEmail = '';
$contactPhone = '';
$contactSubject = '';
$contactMessage = '';
$contactErrors = [];

$contactSubjects = [
    'General Enquiry',
    'Food and Menu Enquiry',
    'Order Enquiry',
    'Delivery Enquiry',
    'Monthly Tiffin Enquiry',
    'Feedback and Suggestions',
    'Other Enquiry'
];

// ============================================
// LOAD CUSTOMER INFORMATION
// ============================================

$contactUserId = null;

if (
    isset($_SESSION['user_id']) &&
    ($_SESSION['role'] ?? '') === 'guest'
) {
    $contactUserId = (int) $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT full_name, email
            FROM users
            WHERE user_id = ?
              AND role = 'guest'
            LIMIT 1
        ");

        $stmt->execute([$contactUserId]);

        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($customer) {
            $contactName = $customer['full_name'];
            $contactEmail = $customer['email'];
        } else {
            $contactUserId = null;
        }

    } catch (PDOException $ex) {
        error_log('Customer lookup error: ' . $ex->getMessage());
        $contactUserId = null;
    }
}

// ============================================
// PROCESS CONTACT US FORM
// ============================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['contact_form'] ?? '') === '1'
) {
    $contactName = trim((string) ($_POST['customer_name'] ?? ''));
    $contactEmail = trim((string) ($_POST['email'] ?? ''));
    $contactPhone = trim((string) ($_POST['phone'] ?? ''));
    $contactSubject = trim((string) ($_POST['subject'] ?? ''));
    $contactMessage = trim((string) ($_POST['message'] ?? ''));

    $token = (string) ($_POST['csrf_token'] ?? '');

    // Check form security token
    if (!hash_equals($_SESSION['csrf_contact'], $token)) {
        $contactErrors[] =
            'Invalid form request. Please refresh the page.';
    }

    // Validate customer name
    if (
        $contactName === '' ||
        strlen($contactName) > 100
    ) {
        $contactErrors[] =
            'Enter a valid full name (maximum 100 characters).';
    }

    // Validate email address
    if (
        !filter_var($contactEmail, FILTER_VALIDATE_EMAIL) ||
        strlen($contactEmail) > 150
    ) {
        $contactErrors[] = 'Enter a valid email address.';
    }

    // Validate phone number
    if (
        $contactPhone !== '' &&
        !preg_match('/^[0-9+\-\s()]{7,20}$/', $contactPhone)
    ) {
        $contactErrors[] =
            'Enter a valid contact number or leave it blank.';
    }

    // Validate enquiry subject
    if (!in_array($contactSubject, $contactSubjects, true)) {
        $contactErrors[] = 'Please select a valid enquiry subject.';
    }

    // Validate customer message
    if (
        $contactMessage === '' ||
        strlen($contactMessage) > 2000
    ) {
        $contactErrors[] =
            'Enter your message (maximum 2000 characters).';
    }

    // ========================================
    // SAVE ENQUIRY TO DATABASE
    // ========================================

    if (empty($contactErrors)) {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO tiffin_enquiries
                (
                    user_id,
                    customer_name,
                    email,
                    phone,
                    subject,
                    message,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, 'New')
            ");

            $stmt->execute([
                $contactUserId,
                $contactName,
                $contactEmail,
                $contactPhone === '' ? null : $contactPhone,
                $contactSubject,
                $contactMessage
            ]);

            // Create a fresh security token
            $_SESSION['csrf_contact'] = bin2hex(random_bytes(32));

            // Redirect after submission
            header('Location: index.php?contact_success=1#contact');
            exit;

        } catch (PDOException $ex) {

            error_log(
                'Contact form error: ' . $ex->getMessage()
            );

            $contactErrors[] =
                'Unable to send your enquiry. Please try again.';
        }
    }
}

// ============================================
// LOAD WEBSITE LAYOUT
// ============================================

require_once __DIR__ . '/includes/header.php';

require_once __DIR__ . '/views/home.php';

require_once __DIR__ . '/includes/footer.php';
?>
