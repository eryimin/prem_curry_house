<?php
require_once __DIR__ . '/food_order/bootstrap.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'guest'
) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!hash_equals(pch_csrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid request. Please refresh.']);
        exit;
    }

    if (($_POST['action'] ?? '') !== 'draw') {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action.']);
        exit;
    }

    $budget = filter_var(
        $_POST['budget'] ?? 0,
        FILTER_VALIDATE_INT
    );

    if (!in_array($budget, [0, 10, 20, 30, 50], true)) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid budget selection.']);
        exit;
    }

    $previousId = filter_var(
        $_POST['previous_id'] ?? 0,
        FILTER_VALIDATE_INT
    );

    $previousId = $previousId === false ? 0 : max(0, $previousId);

    $sql = "
        SELECT item_id, item_name, price, image
        FROM menu_items
        WHERE is_available = 1
    ";

    $params = [];

    if ($budget > 0) {
        $sql .= " AND price <= :budget";
        $params['budget'] = $budget;
    }

    $sql .= " ORDER BY RAND() LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $food = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$food) {
        echo json_encode([
            'error' => 'No available food within this budget. Try another budget.'
        ]);
        exit;
    }

    if ((int)$food['item_id'] === $previousId) {
        $alternateSql = "
            SELECT item_id, item_name, price, image
            FROM menu_items
            WHERE is_available = 1
              AND item_id <> :previous_id
        ";

        $alternateParams = ['previous_id' => $previousId];

        if ($budget > 0) {
            $alternateSql .= " AND price <= :budget";
            $alternateParams['budget'] = $budget;
        }

        $alternateSql .= " ORDER BY RAND() LIMIT 1";

        $alternateStmt = $pdo->prepare($alternateSql);
        $alternateStmt->execute($alternateParams);
        $alternate = $alternateStmt->fetch(PDO::FETCH_ASSOC);

        if ($alternate) {
            $food = $alternate;
        }
    }

    echo json_encode([
        'success' => true,
        'food' => [
            'id' => (int)$food['item_id'],
            'name' => $food['item_name'],
            'price' => number_format((float)$food['price'], 2),
            'image' => trim((string)($food['image'] ?? ''))
        ]
    ]);
    exit;
}

$pageTitle = 'Mystery Food Box | Prem Curry House';
require __DIR__ . '/includes/header.php';
?>

<style>
.pch-blind-page {
    min-height: 75vh;
    padding: 45px 16px 70px;
    background: #fff9ed;
}
.pch-blind-wrap {
    max-width: 620px;
    margin: 0 auto;
    text-align: center;
}
.pch-blind-label {
    color: #527b35;
    font-size: 12px;
    font-weight: 750;
    letter-spacing: 2px;
}
.pch-blind-title {
    color: #263727;
    font-size: clamp(28px, 5vw, 38px);
    font-weight: 800;
    margin: 10px 0;
}
.pch-blind-desc {
    color: #718071;
    font-size: 14px;
}
.pch-blind-panel {
    background: #ffffff;
    border: 1px solid #e3e9df;
    border-radius: 20px;
    padding: 32px 24px;
    margin-top: 28px;
    box-shadow: 0 8px 24px rgba(38, 55, 39, .05);
}
.pch-blind-icon {
    width: 76px;
    height: 76px;
    display: grid;
    place-items: center;
    background: #edf3e9;
    color: #527b35;
    font-size: 35px;
    border-radius: 20px;
    margin: 0 auto 20px;
}
.pch-blind-panel h2 {
    color: #263727;
    font-size: 23px;
    font-weight: 800;
}

/* REVEALED DISH: SHOW IMAGE, NAME AND PRICE TOGETHER */
.pch-food-result {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 9px;
    margin-bottom: 22px;
}
.pch-result-image-wrap {
    width: 100%;
    max-width: 440px;
    aspect-ratio: 16 / 9;
    margin: 0 auto 10px;
    overflow: hidden;
    border-radius: 14px;
    background: #edf3e9;
    border: 1px solid #e3e9df;
}
.pch-result-image-wrap[hidden] {
    display: none;
}
.pch-result-image {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}
.pch-result-image-fallback {
    display: none;
    width: 100%;
    height: 100%;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 8px;
    color: #527b35;
    font-size: 13px;
}
.pch-result-image-fallback i {
    font-size: 32px;
}
.pch-result-image-wrap.is-fallback .pch-result-image {
    display: none;
}
.pch-result-image-wrap.is-fallback .pch-result-image-fallback {
    display: flex;
}
.pch-food-result .pch-food-name {
    margin: 0;
    color: #263727;
    font-size: 24px;
    font-weight: 800;
}
.pch-food-result .pch-food-price {
    margin: 0;
    color: #527b35;
    font-size: 20px;
    font-weight: 800;
}
.pch-blind-btn {
    width: 100%;
    border: none;
    border-radius: 10px;
    padding: 13px 20px;
    background: #527b35;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}
.pch-blind-btn:hover {
    background: #3d612a;
}
.pch-blind-btn:disabled {
    opacity: .6;
    cursor: not-allowed;
}
.pch-blind-outline {
    background: #fff;
    color: #527b35;
    border: 1px solid #527b35;
}
.pch-blind-outline:hover {
    background: #edf3e9;
}
.pch-blind-options {
    display: grid;
    gap: 12px;
    margin-top: 24px;
}
.pch-blind-budget {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin: 20px 0;
}
.pch-budget-btn {
    border: 1px solid #dce6d6;
    background: #fff;
    color: #29432d;
    border-radius: 10px;
    padding: 13px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}
.pch-budget-btn:hover,
.pch-budget-btn.active {
    border-color: #527b35;
    background: #edf3e9;
    color: #527b35;
}
.pch-mystery-cards {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    max-width: 540px;
    min-height: 280px;
    margin: 25px auto;
    padding: 20px 0;
}

.pch-mystery-card {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    flex: 0 0 112px;
    width: 112px;
    height: 175px;
    border: 2px solid #78985f;
    border-radius: 15px;
    background: linear-gradient(145deg, #263727, #527b35);
    color: #ffffff;
    box-shadow: 0 12px 25px rgba(38, 55, 39, .18);
    cursor: pointer;
    margin-left: -16px;
    transition: transform .25s ease, box-shadow .25s ease;
}

.pch-mystery-card:first-child {
    margin-left: 0;
}

.pch-mystery-card:nth-child(1) {
    transform: translateY(20px) rotate(-14deg);
    z-index: 1;
}

.pch-mystery-card:nth-child(2) {
    transform: translateY(6px) rotate(-7deg);
    z-index: 2;
}

.pch-mystery-card:nth-child(3) {
    transform: translateY(0) rotate(0deg);
    z-index: 3;
}

.pch-mystery-card:nth-child(4) {
    transform: translateY(6px) rotate(7deg);
    z-index: 2;
}

.pch-mystery-card:nth-child(5) {
    transform: translateY(20px) rotate(14deg);
    z-index: 1;
}

.pch-mystery-card:hover,
.pch-mystery-card:focus-visible {
    transform: translateY(-20px) rotate(0deg);
    z-index: 10;
    box-shadow: 0 20px 35px rgba(38, 55, 39, .28);
}

.pch-mystery-card i {
    font-size: 36px;
}

.pch-mystery-card span {
    margin-top: 12px;
    font-size: 10px;
    letter-spacing: .5px;
    font-weight: 700;
}

.pch-mystery-card.revealing {
    animation: pch-flip .7s ease;
    z-index: 15;
}

@keyframes pch-flip {
    0% { transform: rotateY(0deg) scale(1); }
    50% { transform: rotateY(90deg) scale(.9); }
    100% { transform: rotateY(0deg) scale(1); }
}

@media (max-width: 480px) {
    .pch-mystery-cards {
        min-height: 220px;
    }

    .pch-mystery-card {
        flex-basis: 75px;
        width: 75px;
        height: 130px;
        margin-left: -10px;
        border-radius: 11px;
    }

    .pch-mystery-card i {
        font-size: 27px;
    }

    .pch-mystery-card span {
        font-size: 8px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .pch-mystery-card,
    .pch-mystery-card.revealing {
        animation: none;
        transition: none;
    }
}
</style>

<main class="pch-blind-page">
    <div class="pch-blind-wrap">

        <span class="pch-blind-label">LET YOUR LUCK DECIDE</span>

        <h1 class="pch-blind-title">
            Mystery Food Box
        </h1>

        <p class="pch-blind-desc">
            Can't decide what to eat?
            Let us pick something delicious for you!
        </p>

        <div class="pch-blind-panel">

            <section id="pch-start-screen">
                <div class="pch-blind-icon">
                    <i class="bi bi-gift"></i>
                </div>

                <h2>How do you want to play?</h2>

                <p class="pch-blind-desc">
                    Discover a random dish from our menu.
                </p>

                <div class="pch-blind-options">
                    <button
                        type="button"
                        class="pch-blind-btn"
                        id="pch-surprise-btn"
                    >
                        <i class="bi bi-stars me-2"></i>
                        Surprise Me
                    </button>

                    <button
                        type="button"
                        class="pch-blind-btn pch-blind-outline"
                        id="pch-budget-screen-btn"
                    >
                        <i class="bi bi-wallet2 me-2"></i>
                        Set a Budget
                    </button>
                </div>
            </section>

            <section id="pch-budget-screen" hidden>
                <h2>What's your budget?</h2>

                <p class="pch-blind-desc">
                    Choose the maximum price for one dish.
                </p>

                <div class="pch-blind-budget">
                    <button type="button" class="pch-budget-btn" data-budget="10">
                        Below RM 10
                    </button>
                    <button type="button" class="pch-budget-btn" data-budget="20">
                        Below RM 20
                    </button>
                    <button type="button" class="pch-budget-btn" data-budget="30">
                        Below RM 30
                    </button>
                    <button type="button" class="pch-budget-btn" data-budget="50">
                        Below RM 50
                    </button>
                </div>

                <button
                    type="button"
                    class="pch-blind-btn"
                    id="pch-start-budget-btn"
                    disabled
                >
                    Start Mystery Box
                </button>

                <button
                    type="button"
                    class="pch-blind-btn pch-blind-outline mt-2"
                    id="pch-budget-back-btn"
                >
                    Back
                </button>
            </section>

            <section id="pch-draw-screen" hidden>

                <h2>Choose Your Mystery Card!</h2>

                <p class="pch-blind-desc">
                    Tap any card to reveal your surprise.
                </p>

                <div class="pch-mystery-cards">

                    <button
                        type="button"
                        class="pch-mystery-card"
                        aria-label="Reveal mystery card 1"
                    >
                        <i class="bi bi-question-diamond"></i>
                        <span>CARD 1</span>
                    </button>

                    <button
                        type="button"
                        class="pch-mystery-card"
                        aria-label="Reveal mystery card 2"
                    >
                        <i class="bi bi-question-diamond"></i>
                        <span>CARD 2</span>
                    </button>

                    <button
                        type="button"
                        class="pch-mystery-card"
                        aria-label="Reveal mystery card 3"
                    >
                        <i class="bi bi-question-diamond"></i>
                        <span>CARD 3</span>
                    </button>

                    <button
                        type="button"
                        class="pch-mystery-card"
                        aria-label="Reveal mystery card 4"
                    >
                        <i class="bi bi-question-diamond"></i>
                        <span>CARD 4</span>
                    </button>

                    <button
                        type="button"
                        class="pch-mystery-card"
                        aria-label="Reveal mystery card 5"
                    >
                        <i class="bi bi-question-diamond"></i>
                        <span>CARD 5</span>
                    </button>

                </div>

            </section>

            <section id="pch-result-screen" hidden>
                <div class="pch-food-result">
                    <div class="pch-result-image-wrap" id="pch-result-image-wrap" hidden>
                        <img
                            id="pch-food-image"
                            class="pch-result-image"
                            alt=""
                        >
                        <div class="pch-result-image-fallback" aria-hidden="true">
                            <i class="bi bi-egg-fried"></i>
                            <span>Food image unavailable</span>
                        </div>
                    </div>

                    <small>YOU GOT!</small>

                    <h2 class="pch-food-name" id="pch-food-name"></h2>

                    <div class="pch-food-price" id="pch-food-price"></div>
                </div>

                <div class="pch-result-actions">
                    <button
                        type="button"
                        class="pch-blind-btn pch-blind-outline"
                        id="pch-again-btn"
                    >
                        <i class="bi bi-arrow-repeat me-1"></i>
                        Draw Again
                    </button>

                    <form method="POST" action="food_cart_action.php">
                        <input type="hidden" name="csrf"
                               value="<?= pch_h(pch_csrf()) ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="item_id" id="pch-food-id">
                        <input type="hidden" name="quantity" value="1">

                        <button type="submit" class="pch-blind-btn">
                            <i class="bi bi-cart-plus me-1"></i>
                            Add to Cart
                        </button>
                    </form>
                </div>
            </section>

            <p class="pch-blind-error" id="pch-blind-error" role="alert" hidden></p>

        </div>

        <a href="menu.php" class="pch-back-menu">
            <i class="bi bi-arrow-left me-1"></i>
            Browse Menu
        </a>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const screens = {
        start: document.getElementById('pch-start-screen'),
        budget: document.getElementById('pch-budget-screen'),
        draw: document.getElementById('pch-draw-screen'),
        result: document.getElementById('pch-result-screen')
    };

    const errorBox = document.getElementById('pch-blind-error');
    const revealBtns = document.querySelectorAll('.pch-mystery-card');
    const startBudgetBtn = document.getElementById('pch-start-budget-btn');
    const resultImageWrap = document.getElementById('pch-result-image-wrap');
    const resultImage = document.getElementById('pch-food-image');

    resultImage.addEventListener('error', function () {
        resultImageWrap.classList.add('is-fallback');
    });

    function showFoodImage(food) {
        const path = String(food.image || '').trim();
        resultImageWrap.hidden = false;
        resultImageWrap.classList.remove('is-fallback');
        resultImage.alt = 'Photo of ' + food.name;

        if (!path) {
            resultImage.removeAttribute('src');
            resultImageWrap.classList.add('is-fallback');
            return;
        }

        // Image paths in menu_items are relative to the project root.
        // Reject unsafe URLs and use the same path as the menu page.
        if (
            !path.startsWith('assets/images/') ||
            path.includes('..') ||
            path.includes('\\')
        ) {
            resultImage.removeAttribute('src');
            resultImageWrap.classList.add('is-fallback');
            return;
        }

        resultImage.src = path;
    }

    let selectedBudget = 0;
    let previousId = 0;
    let drawing = false;

    const csrf = <?= json_encode(pch_csrf()) ?>;

    function showScreen(name) {
        Object.keys(screens).forEach(function (key) {
            screens[key].hidden = key !== name;
        });

        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function showError(message) {
        errorBox.textContent = message;
        errorBox.hidden = false;
    }

    document.getElementById('pch-surprise-btn').addEventListener('click', function () {
        selectedBudget = 0;
        previousId = 0;
        showScreen('draw');
    });

    document.getElementById('pch-budget-screen-btn').addEventListener('click', function () {
        showScreen('budget');
    });

    document.getElementById('pch-budget-back-btn').addEventListener('click', function () {
        showScreen('start');
    });

    document.querySelectorAll('.pch-budget-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('.pch-budget-btn').forEach(function (btn) {
                btn.classList.remove('active');
            });

            button.classList.add('active');
            selectedBudget = Number(button.dataset.budget);
            previousId = 0;
            startBudgetBtn.disabled = false;
        });
    });

    startBudgetBtn.addEventListener('click', function () {
        showScreen('draw');
    });

    revealBtns.forEach(function (button) {
        button.addEventListener('click', async function () {
            if (drawing) return;

            drawing = true;
            revealBtns.forEach(btn => btn.disabled = true);
            errorBox.hidden = true;
            button.classList.add('revealing');

            try {
                const body = new URLSearchParams({
                    csrf: csrf,
                    action: 'draw',
                    budget: String(selectedBudget),
                    previous_id: String(previousId)
                });

                const response = await fetch('food_blind_box.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: body
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(
                        data.error || 'Unable to draw a food.'
                    );
                }

                await new Promise(function (resolve) {
                    setTimeout(resolve, 650);
                });

                previousId = data.food.id;

                document.getElementById('pch-food-id').value =
                    data.food.id;

                document.getElementById('pch-food-name').textContent =
                    data.food.name;

                document.getElementById('pch-food-price').textContent =
                    'RM ' + data.food.price;

                showFoodImage(data.food);

                showScreen('result');

            } catch (error) {
                showError(
                    error.message || 'Something went wrong. Please try again.'
                );
            } finally {
                drawing = false;
                revealBtns.forEach(btn => btn.disabled = false);
                button.classList.remove('revealing');
            }
        });
    });

    document.getElementById('pch-again-btn').addEventListener('click', function () {
        showScreen('draw');
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
