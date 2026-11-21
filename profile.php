
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'guest'
) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$userId = (int)$_SESSION['user_id'];

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$success = isset($_GET['updated']) && $_GET['updated'] === '1'
    ? 'Your name has been updated successfully.'
    : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['profile_csrf'], $token)) {
        http_response_code(403);
        exit('Invalid request. Please refresh the page.');
    }

    $newName = trim((string)($_POST['full_name'] ?? ''));

    if ($newName === '' || mb_strlen($newName, 'UTF-8') > 100) {
        $error = 'Please enter a valid name (maximum 100 characters).';
    } elseif (preg_match('/[\x00-\x1F\x7F]/', $newName)) {
        $error = 'Your name contains invalid characters.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE users
                SET full_name = :full_name
                WHERE user_id = :user_id
                AND role = 'guest'
            ");

            $stmt->execute([
                'full_name' => $newName,
                'user_id' => $userId
            ]);

            $check = $pdo->prepare("
                SELECT full_name
                FROM users
                WHERE user_id = :user_id
                AND role = 'guest'
                LIMIT 1
            ");

            $check->execute(['user_id' => $userId]);
            $updatedUser = $check->fetch(PDO::FETCH_ASSOC);

            if (!$updatedUser) {
                header('Location: logout.php');
                exit;
            }

            $_SESSION['full_name'] = $updatedUser['full_name'];
            $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));

            header('Location: profile.php?updated=1');
            exit;
        } catch (PDOException $ex) {
            error_log('Profile update failed: ' . $ex->getMessage());
            $error = 'Unable to update your name. Please try again.';
        }
    }
}

$stmt = $pdo->prepare("
    SELECT user_id, full_name, email, role, created_at
    FROM users
    WHERE user_id = :user_id
    AND role = 'guest'
    LIMIT 1
");

$stmt->execute(['user_id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: logout.php');
    exit;
}

$_SESSION['full_name'] = $user['full_name'];

$joinedDate = date('d F Y', strtotime($user['created_at']));
$pageTitle = 'My Profile | Prem Curry House';

require __DIR__ . '/includes/header.php';
?>

<style>
.pch-profile-page {
    background: #fff9ed;
    min-height: 80vh;
}
.pch-profile-title {
    color: #527b35;
}
.pch-profile-card {
    border: 0;
    border-radius: 16px;
}
.pch-profile-edit {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.pch-profile-edit-name {
    color: #263727;
    overflow-wrap: anywhere;
}
.pch-edit-btn {
    border: 1px solid #527b35;
    background: white;
    color: #527b35;
    border-radius: 7px;
    padding: 5px 13px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
}
.pch-edit-btn:hover {
    background: #527b35;
    color: white;
}
.pch-edit-form {
    margin-top: 15px;
    padding: 18px;
    border: 1px solid #dce6d6;
    background: #f7faf4;
    border-radius: 10px;
}
.pch-edit-form label {
    display: block;
    color: #263727;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 8px;
}
.pch-edit-form input {
    width: 100%;
    max-width: 420px;
    border: 1px solid #cbd8c5;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 14px;
}
.pch-edit-form input:focus {
    border-color: #527b35;
    outline: none;
    box-shadow: 0 0 0 3px rgba(82,123,53,.12);
}
.pch-edit-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 14px;
}
.pch-save-btn {
    border: none;
    background: #527b35;
    color: white;
    border-radius: 7px;
    padding: 9px 17px;
    font-size: 13px;
    font-weight: 700;
}
.pch-save-btn:hover {
    background: #3e602b;
}
.pch-cancel-btn {
    border: 1px solid #cbd8c5;
    background: white;
    color: #576653;
    border-radius: 7px;
    padding: 9px 17px;
    font-size: 13px;
    font-weight: 600;
}
.pch-cancel-btn:hover {
    background: #edf2e9;
}
.pch-profile-message {
    border-radius: 9px;
    padding: 12px 15px;
    margin-bottom: 20px;
    font-size: 13px;
}
.pch-profile-message.success {
    color: #23693a;
    background: #e8f5e9;
    border: 1px solid #cce7d0;
}
.pch-profile-message.error {
    color: #a1332e;
    background: #fff0ef;
    border: 1px solid #f0cecb;
}
@media (max-width: 575px) {
    .pch-profile-edit {
        align-items: flex-start;
    }
}
</style>

<section class="pch-profile-page py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <div class="text-center mb-4">
                    <i class="bi bi-person-circle"
                       style="font-size:4rem;color:#527b35;"></i>

                    <h1 class="fw-bold mt-2 pch-profile-title">
                        My Profile
                    </h1>

                    <p class="text-muted">
                        View and manage your Prem Curry House account details.
                    </p>
                </div>

                <div class="card pch-profile-card shadow-sm">
                    <div class="card-body p-4 p-md-5">

                        <h3 class="fw-bold mb-2 pch-profile-title">
                            Welcome, <?= e($user['full_name']) ?>!
                        </h3>

                        <p class="text-muted mb-4">
                            Here is your account information.
                        </p>

                        <?php if ($success !== ''): ?>
                            <div class="pch-profile-message success" role="status">
                                <i class="bi bi-check-circle me-2"></i>
                                <?= e($success) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error !== ''): ?>
                            <div class="pch-profile-message error" role="alert">
                                <i class="bi bi-exclamation-circle me-2"></i>
                                <?= e($error) ?>
                            </div>
                        <?php endif; ?>

                        <hr>

                        <div class="row py-3">
                            <div class="col-sm-4 fw-semibold mb-2 mb-sm-0">
                                <i class="bi bi-person me-2"></i>
                                Full Name
                            </div>

                            <div class="col-sm-8">
                                <div class="pch-profile-edit">
                                    <span class="pch-profile-edit-name">
                                        <?= e($user['full_name']) ?>
                                    </span>

                                    <button
                                        type="button"
                                        class="pch-edit-btn"
                                        id="pch-edit-name-btn"
                                        aria-expanded="false"
                                        aria-controls="pch-edit-name-form"
                                    >
                                        <i class="bi bi-pencil-square me-1"></i>
                                        Edit Name
                                    </button>
                                </div>

                                <form
                                    action="profile.php"
                                    method="POST"
                                    id="pch-edit-name-form"
                                    class="pch-edit-form"
                                    <?= $error === '' ? 'hidden' : '' ?>
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION['profile_csrf']) ?>"
                                    >

                                    <label for="pch-full-name">
                                        Enter Your New Name
                                    </label>

                                    <input
                                        id="pch-full-name"
                                        type="text"
                                        name="full_name"
                                        required
                                        maxlength="100"
                                        autocomplete="name"
                                        value="<?= e(
                                            $error !== ''
                                                ? ($_POST['full_name'] ?? '')
                                                : $user['full_name']
                                        ) ?>"
                                    >

                                    <div class="pch-edit-actions">
                                        <button
                                            type="submit"
                                            class="pch-save-btn"
                                        >
                                            <i class="bi bi-check-lg me-1"></i>
                                            Save Changes
                                        </button>

                                        <button
                                            type="button"
                                            class="pch-cancel-btn"
                                            id="pch-cancel-name-btn"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <hr class="my-0">

                        <div class="row py-3">
                            <div class="col-sm-4 fw-semibold mb-2 mb-sm-0">
                                <i class="bi bi-envelope me-2"></i>
                                Email Address
                            </div>

                            <div class="col-sm-8">
                                <?= e($user['email']) ?>
                            </div>
                        </div>

                        <hr class="my-0">

                        <div class="row py-3">
                            <div class="col-sm-4 fw-semibold mb-2 mb-sm-0">
                                <i class="bi bi-person-badge me-2"></i>
                                Account Type
                            </div>

                            <div class="col-sm-8">
                                <span class="badge bg-success">
                                    Customer
                                </span>
                            </div>
                        </div>

                        <hr class="my-0">

                        <div class="row py-3">
                            <div class="col-sm-4 fw-semibold mb-2 mb-sm-0">
                                <i class="bi bi-calendar-check me-2"></i>
                                Member Since
                            </div>

                            <div class="col-sm-8">
                                <?= e($joinedDate) ?>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex flex-wrap gap-3 mt-4">
                            <a href="index.php" class="btn btn-success">
                                <i class="bi bi-house me-2"></i>
                                Back to Home
                            </a>

                            <a href="logout.php" class="btn btn-outline-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                Logout
                            </a>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editBtn = document.getElementById('pch-edit-name-btn');
    const cancelBtn = document.getElementById('pch-cancel-name-btn');
    const editForm = document.getElementById('pch-edit-name-form');
    const nameInput = document.getElementById('pch-full-name');

    if (!editBtn || !cancelBtn || !editForm || !nameInput) {
        return;
    }

    function showEditForm() {
        editForm.hidden = false;
        editBtn.setAttribute('aria-expanded', 'true');
        nameInput.focus();
        nameInput.select();
    }

    function hideEditForm() {
        editForm.hidden = true;
        editBtn.setAttribute('aria-expanded', 'false');
        nameInput.value = <?= json_encode(
            (string)$user['full_name'],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;
    }

    editBtn.addEventListener('click', function () {
        if (editForm.hidden) {
            showEditForm();
        } else {
            hideEditForm();
        }
    });

    cancelBtn.addEventListener('click', hideEditForm);

    if (!editForm.hidden) {
        editBtn.setAttribute('aria-expanded', 'true');
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
