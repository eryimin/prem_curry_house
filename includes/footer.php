
<!-- FOOTER -->
<footer class="pch-footer">
    <div class="container">

        <div class="row g-4">

            <!-- Restaurant Information -->
            <div class="col-md-6">

                <h4 class="fw-bold mb-3">
                    Prem Curry House
                </h4>

                <p>
                    Authentic Indian and Malaysian cuisine,
                    traditional banana leaf meals and
                    monthly tiffin services.
                </p>

                <p>
                    A Taste of Home, Every Day.
                </p>

            </div>

            <!-- Quick Links -->
            <div class="col-md-3">

                <h5 class="fw-bold mb-3">
                    Quick Links
                </h5>

                <div class="pch-footer-links">

                    <a href="index.php#home">
                        Home
                    </a>

                    <a href="index.php#about">
                        About Us
                    </a>

                    <a href="menu.php">
                        Our Menu
                    </a>

                    <a href="tiffin.php">
                        Monthly Tiffin
                    </a>

                    <a href="index.php#contact">
                        Contact Us
                    </a>

                </div>

            </div>

            <!-- Restaurant Location -->
            <div class="col-md-3">

                <h5 class="fw-bold mb-3">
                    Visit Us
                </h5>

                <p class="pch-footer-address">
                    <i class="bi bi-geo-alt me-2"></i>
                    <span>
                        20, Jalan Bagor,
                        Taman Petaling Indah,
                        41200 Klang,
                        Selangor, Malaysia
                    </span>
                </p>

                <p>
                    <a
                        href="https://www.google.com/maps/search/?api=1&amp;query=Prem+Curry+House+Jalan+Bagor+Klang"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <i class="bi bi-map me-2"></i>
                        View on Google Maps
                        <i class="bi bi-box-arrow-up-right ms-1"></i>
                    </a>
                </p>

            </div>

        </div>

        <hr class="my-4">

        <!-- Copyright -->
        <p class="text-center mb-0">
            &copy; <?php echo date("Y"); ?>
            Prem Curry House Sdn. Bhd.
            All Rights Reserved.
        </p>

    </div>
</footer>

<!-- Footer Styles -->
<style>
.pch-footer-links {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
}

.pch-footer-links a {
    color: #e8f0e5;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.2s ease;
}

.pch-footer-links a:hover {
    color: #f1c96b;
    text-decoration: underline;
}

.pch-footer-address {
    display: flex;
    align-items: flex-start;
    line-height: 1.7;
}

.pch-footer-address i {
    flex-shrink: 0;
    margin-top: 4px;
}

.pch-footer a:focus-visible {
    outline: 2px solid #f1c96b;
    outline-offset: 4px;
}
</style>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script src="assets/js/script.js"></script>

</body>
</html>
