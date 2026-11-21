
<?php
// PREM CURRY HOUSE - HOMEPAGE

// Featured menu items for Prem Curry House
// Images are temporary and will be replaced later.
$specialties = [
    [
        "name" => "Banana Leaf Set",
        "price" => 11.00,
        "description" => "A traditional banana leaf dining experience.",
        "image" => "https://images.unsplash.com/photo-1546833999-b9f581a1996d?w=900"
    ],
    [
        "name" => "Chicken Briyani",
        "price" => 17.00,
        "description" => "A fragrant rice dish with chicken and spices. Weekends only.",
        "image" => "assets/images/chicken-briyani.png"
    ],
    [
        "name" => "Chicken Bento Box",
        "price" => 19.50,
        "description" => "A convenient meal for busy days.",
        "image" => "assets/images/chicken-bento.png"
    ]
];
?>

<!-- HERO SECTION -->

<section id="home" class="hero-section">
    <div class="container">

        <div class="row align-items-center g-5">

            <div class="col-lg-6">

                <span class="section-label">
                    WELCOME TO PREM CURRY HOUSE
                </span>

                <h1 class="hero-title mt-3">
                    A Taste of Home,
                    <span>Every Day.</span>
                </h1>

                <p class="hero-description">
                    Discover comforting Indian flavours,
                    delicious banana leaf meals and
                    thoughtful monthly tiffin services.
                </p>

                <div class="d-flex flex-wrap gap-3">

                    <a href="#specialties" class="btn btn-pch">
                        Explore Our Food
                    </a>

                    <a href="#tiffin" class="btn btn-pch-outline">
                        Monthly Tiffin
                    </a>

                </div>

            </div>

            <div class="col-lg-6">

                <img
                    src="https://images.unsplash.com/photo-1546833999-b9f581a1996d?w=1200"
                    alt="Temporary Indian food photograph"
                    class="img-fluid hero-image"
                >

            </div>

        </div>

    </div>
</section>


<!-- ============================================
     ABOUT SECTION
     ============================================ -->

<section id="about" class="section-padding">
    <div class="container">

        <div class="row align-items-center g-5">

            <div class="col-lg-6">

                <img
                    src="https://images.unsplash.com/photo-1601050690597-df0568f70950?w=1000"
                    alt="Temporary Indian food photograph"
                    class="img-fluid section-image"
                >

            </div>

            <div class="col-lg-6">

                <span class="section-label">
                    OUR STORY
                </span>

                <h2 class="section-title">
                    Traditional Flavours,
                    Made with Care
                </h2>

                <p>
                    Prem Curry House offers a variety of
                    Indian and Malaysian dishes, including
                    banana leaf meals, curries, rice,
                    breads and beverages.
                </p>

                <p>
                    We also offer monthly lunchbox and
                    tiffin services for people who want
                    convenient meals or wish to arrange
                    food for loved ones living away.
                </p>

                <a
                    href="#specialties"
                    class="btn btn-pch mt-2"
                >
                    Discover Our Specialties
                </a>

            </div>

        </div>

    </div>
</section>


<!-- ============================================
     SPECIALTIES SECTION
     ============================================ -->

<section
    id="specialties"
    class="section-padding specialties-section"
>
    <div class="container">

        <div class="text-center mb-5">

            <span class="section-label">
                OUR FOOD
            </span>

            <h2 class="section-title">
                Discover Our Specialties
            </h2>

            <p>
                Explore a selection of dishes from our menu.
            </p>

        </div>

        <div class="row g-4">

            <?php foreach ($specialties as $food): ?>

                <div class="col-md-6 col-lg-4">

                    <div class="card food-card h-100">

                        <img
                            src="<?php echo htmlspecialchars(
                                $food['image'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            class="card-img-top"
                            alt="<?php echo htmlspecialchars(
                                $food['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                        >

                        <div class="card-body">

                            <h5 class="card-title">

                                <?php echo htmlspecialchars(
                                    $food['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </h5>

                            <p class="card-text">

                                <?php echo htmlspecialchars(
                                    $food['description'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </p>

                            <p class="food-price">

                                RM <?php echo number_format(
                                    $food['price'],
                                    2
                                ); ?>

                            </p>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

        <p class="text-center text-muted small mt-4">
            Menu prices are based on the supplied restaurant listing
            and may be updated.
        </p>

    </div>
</section>


<!-- ============================================
     MONTHLY TIFFIN SERVICE SECTION
     ============================================ -->

<section
    id="tiffin"
    class="section-padding tiffin-section"
>
    <div class="container">

        <!-- SECTION HEADING -->

        <div class="text-center mb-5">

            <span class="section-label">
                MONTHLY MEAL SERVICE
            </span>

            <h2 class="section-title">
                Meals Made with Love
            </h2>

            <p class="mx-auto section-intro tiffin-description">
                Whether you're busy with work or studying away from home, our monthly tiffin service offers a convenient way to arrange meals.
            </p>

        </div>


        <!-- TIFFIN CARDS -->

        <div class="row g-4">

            <!-- ORDER FOR YOURSELF -->

            <div class="col-md-6">

                <div class="tiffin-card h-100">

                    <i class="bi bi-calendar-check tiffin-icon"></i>

                    <h3>
                        Order for Yourself
                    </h3>

                    <p>
                        Arrange monthly lunchbox meals
                        when your schedule leaves little
                        time for cooking.
                    </p>

                    <a
                        href="tiffin.php"
                        class="btn btn-pch mt-3"
                    >
                        <i class="bi bi-bag-check me-2"></i>
                        Explore Tiffin Plans
                    </a>

                </div>

            </div>


            <!-- SEND TO SOMEONE YOU LOVE -->

            <div class="col-md-6">

                <div class="tiffin-card h-100">

                    <i class="bi bi-heart tiffin-icon"></i>

                    <h3>
                        Send to Someone You Love
                    </h3>

                    <p>
                        Arrange meals for family members
                        or loved ones who live far away.
                    </p>

                    
                <a href="tiffin.php" class="btn btn-pch-outline mt-3">
                    <i class="bi bi-heart me-2"></i>
                    View Tiffin Plans
                </a>


                </div>

            </div>

        </div>

    </div>
</section>



<!-- ============================================
     CONTACT US SECTION
     ============================================ -->

<style>
/* CONTACT US SECTION */

.pch-contact-section {
    background: #fff9ef;
    padding: 75px 0;
}

.pch-contact-heading {
    text-align: center;
    margin-bottom: 35px;
}

.pch-contact-heading h2 {
    color: #527b35;
    font-size: 38px;
    font-weight: 800;
    margin: 15px 0;
}

.pch-contact-heading p {
    color: #667061;
    max-width: 750px;
    margin: 0 auto;
}

.pch-contact-card {
    background: #ffffff;
    border: 1px solid #e8ebdf;
    border-radius: 16px;
    padding: 26px;
    height: 100%;
    box-shadow: 0 5px 20px rgba(40, 70, 40, 0.04);
}

.pch-contact-card-icon {
    color: #527b35;
    font-size: 32px;
    margin-bottom: 15px;
}

.pch-contact-card h3 {
    color: #29432d;
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 12px;
}

.pch-contact-card p {
    color: #647063;
    font-size: 14px;
    line-height: 1.8;
    margin-bottom: 0;
}

.pch-contact-map {
    background: #ffffff;
    border: 1px solid #e8ebdf;
    border-radius: 16px;
    padding: 27px;
    margin-top: 24px;
    box-shadow: 0 5px 20px rgba(40, 70, 40, 0.04);
}

.pch-contact-map h3 {
    color: #29432d;
    font-size: 21px;
    font-weight: 700;
    margin-bottom: 16px;
}

.pch-contact-map .btn {
    background: #527b35;
    border: 1px solid #527b35;
    color: #ffffff;
    font-weight: 600;
    padding: 12px 25px;
    border-radius: 8px;
}

.pch-contact-map .btn:hover {
    background: #3c6126;
    color: #ffffff;
}

.pch-contact-form-card {
    background: #ffffff;
    border: 1px solid #e8ebdf;
    border-radius: 16px;
    padding: 35px;
    margin-top: 25px;
    box-shadow: 0 5px 20px rgba(40, 70, 40, 0.04);
}

.pch-contact-form-title {
    color: #29432d;
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 10px;
}

.pch-contact-form-subtitle {
    color: #647063;
    font-size: 14px;
    margin-bottom: 28px;
}

.pch-contact-form-card .form-label {
    font-size: 14px;
    color: #263b28;
    font-weight: 600;
    margin-bottom: 7px;
}

.pch-contact-form-card .form-control,
.pch-contact-form-card .form-select {
    border: 1px solid #dce4da;
    border-radius: 8px;
    padding: 11px 13px;
    font-size: 14px;
}

.pch-contact-form-card .form-control:focus,
.pch-contact-form-card .form-select:focus {
    border-color: #527b35;
    box-shadow: 0 0 0 3px rgba(82, 123, 53, 0.12);
}

.pch-contact-submit {
    background: #198754;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 12px;
    font-weight: 600;
    width: 100%;
}

.pch-contact-submit:hover {
    background: #146c43;
    color: white;
}

.pch-contact-tiffin {
    background: #eef3e7;
    border-radius: 14px;
    padding: 25px;
    margin-top: 25px;
}

.pch-contact-tiffin h3 {
    color: #29432d;
    font-size: 21px;
    font-weight: 700;
}

.pch-contact-tiffin p {
    color: #647063;
    font-size: 14px;
}

@media (max-width: 767px) {
    .pch-contact-section {
        padding: 45px 0;
    }

    .pch-contact-heading h2 {
        font-size: 30px;
    }

    .pch-contact-form-card {
        padding: 22px;
    }

    .pch-contact-card {
        padding: 22px;
    }
}
</style>

<section
    id="contact"
    class="pch-contact-section"
>
    <div class="container">

        <!-- HEADING -->

        <div class="pch-contact-heading">

            <span class="section-label">
                GET IN TOUCH
            </span>

            <div class="mt-3">
                <i
                    class="bi bi-headset"
                    style="font-size:42px;color:#527b35;"
                ></i>
            </div>

            <h2>
                Contact Us
            </h2>

            <p>
                Have questions about our food, orders,
                delivery or monthly tiffin services?
                We're here to help!
            </p>

        </div>


        <!-- RESTAURANT INFORMATION CARDS -->

        <div class="row g-4">

            <!-- VISIT US -->

            <div class="col-md-6">

                <div class="pch-contact-card text-center">

                    <div class="pch-contact-card-icon">
                        <i class="bi bi-geo-alt"></i>
                    </div>

                    <h3>
                        Visit Our Restaurant
                    </h3>

                    <p>
                        20, Jalan Bagor, Taman Petaling Indah,<br>
                        41200 Klang, Selangor, Malaysia
                    </p>

                </div>

            </div>


            <!-- ENQUIRY SUPPORT -->

            <div class="col-md-6">

                <div class="pch-contact-card text-center">

                    <div class="pch-contact-card-icon">
                        <i class="bi bi-chat-square-text"></i>
                    </div>

                    <h3>
                        Enquiry Support
                    </h3>

                    <p>
                        Have a question about our menu,
                        restaurant services, orders,
                        delivery or monthly tiffin plans?
                        Send us an enquiry using the form below.
                    </p>

                </div>

            </div>

        </div>


        <!-- GOOGLE MAPS -->

        <div class="pch-contact-map">

            <div class="d-flex align-items-center gap-2 mb-3">

                <i
                    class="bi bi-map"
                    style="font-size:25px;color:#527b35;"
                ></i>

                <h3 class="mb-0">
                    Find Prem Curry House
                </h3>

            </div>

            <p class="text-muted mb-3">
                Jalan Bagor, Klang, Selangor, Malaysia
            </p>

            <a
                href="https://www.google.com/maps/search/?api=1&amp;query=Prem+Curry+House+Jalan+Bagor+Klang"
                target="_blank"
                rel="noopener noreferrer"
                class="btn"
            >
                <i class="bi bi-geo-alt me-2"></i>
                Find Us on Google Maps
                <i class="bi bi-box-arrow-up-right ms-2"></i>
            </a>

        </div>


        <!-- CONTACT ENQUIRY FORM -->

        <div class="pch-contact-form-card">

            <h3 class="pch-contact-form-title">
                <i
                    class="bi bi-envelope me-2"
                    style="color:#527b35;"
                ></i>
                Send Us an Enquiry
            </h3>

            <p class="pch-contact-form-subtitle">
                Fill in the form below and our team will
                review your enquiry.
            </p>


            <!-- SUCCESS MESSAGE -->

            <?php if (
                isset($_GET['contact_success']) &&
                $_GET['contact_success'] === '1'
            ): ?>

                <div
                    class="alert alert-success"
                    role="status"
                >

                    <h5 class="fw-bold">
                        <i class="bi bi-check-circle me-2"></i>
                        Enquiry Submitted!
                    </h5>

                    <p class="mb-0">
                        Your enquiry has been received.
                        Our team will review your message.
                    </p>

                </div>

            <?php endif; ?>


            <!-- VALIDATION ERRORS -->

            <?php if (!empty($contactErrors)): ?>

                <div
                    class="alert alert-danger"
                    role="alert"
                >

                    <strong>
                        Please correct the following:
                    </strong>

                    <ul class="mb-0 mt-2">

                        <?php foreach ($contactErrors as $error): ?>

                            <li>
                                <?php echo htmlspecialchars(
                                    $error,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action="index.php#contact"
            >

                <!-- FORM IDENTIFIER -->

                <input
                    type="hidden"
                    name="contact_form"
                    value="1"
                >

                <!-- CSRF TOKEN -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars(
                        $_SESSION['csrf_contact'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >


                <!-- FULL NAME -->

                <div class="mb-3">

                    <label
                        for="contact_name"
                        class="form-label"
                    >
                        Full Name *
                    </label>

                    <input
                        type="text"
                        id="contact_name"
                        name="customer_name"
                        class="form-control"
                        maxlength="100"
                        value="<?php echo htmlspecialchars(
                            $contactName,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        required
                    >

                </div>


                <!-- EMAIL ADDRESS -->

                <div class="mb-3">

                    <label
                        for="contact_email"
                        class="form-label"
                    >
                        Email Address *
                    </label>

                    <input
                        type="email"
                        id="contact_email"
                        name="email"
                        class="form-control"
                        maxlength="150"
                        value="<?php echo htmlspecialchars(
                            $contactEmail,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        required
                    >

                </div>


                <!-- CONTACT NUMBER -->

                <div class="mb-3">

                    <label
                        for="contact_phone"
                        class="form-label"
                    >
                        Contact Number (Optional)
                    </label>

                    <input
                        type="tel"
                        id="contact_phone"
                        name="phone"
                        class="form-control"
                        maxlength="20"
                        placeholder="0123456789"
                        value="<?php echo htmlspecialchars(
                            $contactPhone,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                    >

                </div>


                <!-- ENQUIRY SUBJECT -->

                <div class="mb-3">

                    <label
                        for="contact_subject"
                        class="form-label"
                    >
                        Enquiry Subject *
                    </label>

                    <select
                        id="contact_subject"
                        name="subject"
                        class="form-select"
                        required
                    >

                        <option value="">
                            Select a subject
                        </option>

                        <?php foreach ($contactSubjects as $option): ?>

                            <option
                                value="<?php echo htmlspecialchars(
                                    $option,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>"
                                <?php echo
                                    $contactSubject === $option
                                        ? 'selected'
                                        : '';
                                ?>
                            >
                                <?php echo htmlspecialchars(
                                    $option,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- CUSTOMER MESSAGE -->

                <div class="mb-4">

                    <label
                        for="contact_message"
                        class="form-label"
                    >
                        Your Message *
                    </label>

                    <textarea
                        id="contact_message"
                        name="message"
                        class="form-control"
                        rows="5"
                        maxlength="2000"
                        placeholder="Type your question here..."
                        required
                    ><?php echo htmlspecialchars(
                        $contactMessage,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?></textarea>

                </div>


                <!-- SUBMIT BUTTON -->

                <button
                    type="submit"
                    class="pch-contact-submit"
                >
                    <i class="bi bi-send me-2"></i>
                    Submit Enquiry
                </button>

            </form>

        </div>


        <!-- MONTHLY TIFFIN PROMOTION -->

        <div class="pch-contact-tiffin">

            <div class="row align-items-center g-3">

                <div class="col-md-8">

                    <h3>
                        <i class="bi bi-bag-heart me-2"></i>
                        Interested in Monthly Tiffin?
                    </h3>

                    <p class="mb-0">
                        Explore our vegetarian, mixed and
                        non-vegetarian monthly meal plans.
                    </p>

                </div>

                <div class="col-md-4 text-md-end">

                    <a
                        href="tiffin.php"
                        class="btn btn-outline-success"
                    >
                        Explore Monthly Tiffin Plans
                    </a>

                </div>

            </div>

        </div>

    </div>
</section>


<!-- ============================================
     DINE IN / DELIVERY POPUP
     ============================================ -->

<?php if (!empty($showOrderTypePopup)): ?>

<style>
.pch-order-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 99999;
    padding: 20px;
}

.pch-order-popup {
    background: #fffdf7;
    border-radius: 18px;
    padding: 35px;
    width: 100%;
    max-width: 500px;
    text-align: center;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 15px 45px rgba(0,0,0,0.25);
    position: relative;
}

/* Close button at top-right corner */
.pch-order-close {
    position: absolute;
    top: 12px;
    right: 16px;
    background: transparent;
    border: none;
    font-size: 30px;
    font-weight: bold;
    color: #647063;
    cursor: pointer;
    line-height: 1;
    padding: 5px;
}

.pch-order-close:hover {
    color: #dc3545;
}

.pch-order-popup h2 {
    color: #29432d;
    font-weight: 800;
    margin-bottom: 10px;
}

.pch-order-popup p {
    color: #647063;
}

.pch-order-choices {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}

.pch-order-choice {
    flex: 1;
    border: 2px solid #dce4da;
    border-radius: 12px;
    padding: 22px 10px;
    background: white;
    cursor: pointer;
    transition: 0.2s;
}

.pch-order-choice i {
    display: block;
    font-size: 35px;
    color: #527b35;
    margin-bottom: 10px;
}

.pch-order-choice:hover,
.pch-order-choice.selected {
    border-color: #527b35;
    background: #eef3e7;
}

.pch-order-details {
    margin-top: 20px;
    text-align: left;
}

.pch-order-continue {
    background: #527b35;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 13px;
    width: 100%;
    font-weight: 700;
    margin-top: 20px;
}

.pch-order-continue:hover {
    background: #3c6126;
}

@media (max-width: 480px) {
    .pch-order-choices {
        flex-direction: column;
    }
}
</style>

<div class="pch-order-overlay"
     role="dialog"
     aria-modal="true"
     aria-labelledby="pch-order-title">

    <div class="pch-order-popup">

        <!-- CLOSE BUTTON -->
        <button
            type="button"
            class="pch-order-close"
            onclick="closePchOrderPopup()"
            aria-label="Close popup"
        >
            &times;
        </button>

        <h2 id="pch-order-title">
            Welcome to Prem Curry House!
        </h2>

        <p>
            How would you like to enjoy your meal?
        </p>

        <form method="POST" action="save_order_type.php">

            <!-- CSRF TOKEN -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['csrf_order_type'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <!-- ORDER TYPE -->
            <input
                type="hidden"
                name="order_type"
                id="pch-order-type"
                required
            >

            <!-- ORDER TYPE BUTTONS -->
            <div class="pch-order-choices">

                <button
                    type="button"
                    class="pch-order-choice"
                    id="pch-dine-btn"
                    onclick="selectPchOrder('dine_in')"
                >
                    <i class="bi bi-shop"></i>
                    <strong>Dine In</strong>
                </button>

                <button
                    type="button"
                    class="pch-order-choice"
                    id="pch-delivery-btn"
                    onclick="selectPchOrder('delivery')"
                >
                    <i class="bi bi-truck"></i>
                    <strong>Delivery</strong>
                </button>

            </div>

            <!-- ==========================
                 DINE IN DETAILS
                 ========================== -->

            <div
                class="pch-order-details"
                id="pch-dine-details"
                style="display:none;"
            >

                <label
                    for="pch-table-number"
                    class="form-label"
                >
                    Select Table Number
                </label>

                <select
                    name="table_number"
                    id="pch-table-number"
                    class="form-select"
                >

                    <option value="">
                        Choose a table
                    </option>

                    <?php for ($i = 1; $i <= 10; $i++): ?>

                        <option value="<?= $i ?>">
                            Table <?= $i ?>
                        </option>

                    <?php endfor; ?>

                </select>

            </div>

            <!-- ==========================
                 DELIVERY DETAILS
                 ========================== -->

            <div
                class="pch-order-details"
                id="pch-delivery-details"
                style="display:none;"
            >

                <!-- DELIVERY AREA -->

                <label
                    for="pch-delivery-area"
                    class="form-label"
                >
                    Delivery Area
                </label>

                <select
                    name="delivery_area"
                    id="pch-delivery-area"
                    class="form-select"
                    onchange="updatePchDeliveryFee()"
                >

                    <option value="">
                        Select your delivery area
                    </option>

                    <option value="taman_petaling_indah">
                        Taman Petaling Indah
                    </option>

                    <option value="taman_chi_liung">
                        Taman Chi Liung
                    </option>

                    <option value="klang_town">
                        Klang Town
                    </option>

                    <option value="bandar_bukit_tinggi">
                        Bandar Bukit Tinggi
                    </option>

                    <option value="bandar_botanic">
                        Bandar Botanic
                    </option>

                    <option value="taman_sentosa">
                        Taman Sentosa
                    </option>

                    <option value="bandar_puteri_klang">
                        Bandar Puteri Klang
                    </option>

                    <option value="unavailable">
                        My Area Is Not Listed
                    </option>

                </select>

                <div
                    id="pch-area-unavailable"
                    class="alert alert-danger mt-3"
                    style="display:none;"
                >
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Sorry, delivery is currently unavailable in your area.
                    Please choose Dine In or select another supported delivery area.
                </div>

                <!-- DELIVERY ADDRESS -->

                <label
                    for="pch-delivery-address"
                    class="form-label mt-3"
                >
                    Delivery Address
                </label>

                <textarea
                    name="delivery_address"
                    id="pch-delivery-address"
                    class="form-control"
                    rows="3"
                    maxlength="500"
                    placeholder="Enter your complete delivery address"
                ></textarea>

                <small class="text-muted">
                    Please include your house number,
                    street name and postcode.
                </small>

                <!-- DELIVERY FEE PREVIEW -->

                <div
                    id="pch-delivery-fee-box"
                    style="
                        display:none;
                        margin-top:18px;
                        padding:15px;
                        background:#eef3e7;
                        border:1px solid #d5e3c8;
                        border-radius:10px;
                    "
                >

                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            gap:12px;
                        "
                    >

                        <span
                            style="
                                font-weight:600;
                                color:#334638;
                            "
                        >
                            <i class="bi bi-truck me-2"></i>
                            Estimated Delivery Fee
                        </span>

                        <strong
                            id="pch-delivery-fee"
                            style="
                                color:#527b35;
                                font-size:20px;
                                white-space:nowrap;
                            "
                        >
                            RM 0.00
                        </strong>

                    </div>

                    <small
                        style="
                            display:block;
                            margin-top:8px;
                            color:#667560;
                        "
                    >
                        Delivery fee is based on your selected area.
                    </small>

                </div>

            </div>

            <!-- CONTINUE BUTTON -->

            <button
                type="submit"
                class="pch-order-continue"
                id="pch-order-submit"
                disabled
            >
                Continue to Menu
            </button>

        </form>

    </div>

</div>

<script>

// ==========================================
// CLOSE ORDER POPUP
// ==========================================

function closePchOrderPopup() {

    document.querySelector(
        '.pch-order-overlay'
    ).style.display = 'none';

}

// ==========================================
// SELECT ORDER TYPE
// ==========================================

function selectPchOrder(type) {

    document.getElementById(
        'pch-order-type'
    ).value = type;

    const dine = type === 'dine_in';

    const delivery = type === 'delivery';

    // SHOW / HIDE DINE IN DETAILS

    document.getElementById(
        'pch-dine-details'
    ).style.display = dine ? 'block' : 'none';

    // SHOW / HIDE DELIVERY DETAILS

    document.getElementById(
        'pch-delivery-details'
    ).style.display = delivery ? 'block' : 'none';

    // HIGHLIGHT SELECTED BUTTON

    document.getElementById(
        'pch-dine-btn'
    ).classList.toggle('selected', dine);

    document.getElementById(
        'pch-delivery-btn'
    ).classList.toggle('selected', delivery);

    // REQUIRED INPUTS

    document.getElementById(
        'pch-table-number'
    ).required = dine;

    document.getElementById(
        'pch-delivery-area'
    ).required = delivery;

    document.getElementById(
        'pch-delivery-address'
    ).required = delivery;

    // ENABLE CONTINUE BUTTON

    document.getElementById(
        'pch-order-submit'
    ).disabled = false;

}

// ==========================================
// DELIVERY FEE CONFIGURATION
// ==========================================

const pchDeliveryFees = {

    taman_petaling_indah: 3.00,

    taman_chi_liung: 3.00,

    klang_town: 5.00,

    bandar_bukit_tinggi: 7.00,

    bandar_botanic: 7.00,

    taman_sentosa: 9.00,

    bandar_puteri_klang: 9.00

};

// ==========================================
// UPDATE DELIVERY FEE PREVIEW
// ==========================================

function updatePchDeliveryFee() {
    const area = document.getElementById('pch-delivery-area').value;
    const feeBox = document.getElementById('pch-delivery-fee-box');
    const feeText = document.getElementById('pch-delivery-fee');
    const unavailableBox = document.getElementById('pch-area-unavailable');
    const submitButton = document.getElementById('pch-order-submit');

    if (area === 'unavailable') {
        unavailableBox.style.display = 'block';
        feeBox.style.display = 'none';
        submitButton.disabled = true;
        return;
    }

    unavailableBox.style.display = 'none';

    if (
        area === '' ||
        !Object.prototype.hasOwnProperty.call(pchDeliveryFees, area)
    ) {
        feeBox.style.display = 'none';
        feeText.textContent = 'RM 0.00';
        submitButton.disabled = false;
        return;
    }

    const deliveryFee = pchDeliveryFees[area];

    feeText.textContent = 'RM ' + deliveryFee.toFixed(2);
    feeBox.style.display = 'block';
    submitButton.disabled = false;
}

</script>

<?php endif; ?>
