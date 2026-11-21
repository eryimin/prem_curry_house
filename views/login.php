
<section class="py-5" style="min-height:80vh;">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-md-8 col-lg-5">

                <div class="card border-0 shadow-sm rounded-4">

                    <div class="card-body p-4 p-md-5">

                        <div class="text-center mb-4">

                            <i class="bi bi-shield-lock"
                               style="font-size:3rem; color:#527B35;"></i>

                            <h2 class="fw-bold mt-3"
                                style="color:#527B35;">
                                Account Login
                            </h2>

                            <p class="text-muted">
                                Welcome to Prem Curry House
                            </p>

                        </div>

                        <?php if (isset($_GET["registered"]) && $_GET["registered"] === "1"): ?>

                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                Registration successful! Please log in.
                            </div>

                        <?php endif; ?>

                        <?php if (!empty($error)): ?>

                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-circle me-2"></i>
                                <?php echo escapeHtml($error); ?>
                            </div>

                        <?php endif; ?>

                        <form action="login.php" method="POST">

                            <div class="mb-3">

                                <label for="email"
                                       class="form-label fw-semibold">
                                    Email Address
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control"
                                    maxlength="150"
                                    autocomplete="email"
                                    value="<?php echo escapeHtml($email ?? ""); ?>"
                                    placeholder="Enter your email"
                                    required>

                            </div>

                            <div class="mb-4">

                                <label for="password"
                                       class="form-label fw-semibold">
                                    Password
                                </label>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control"
                                    autocomplete="current-password"
                                    placeholder="Enter your password"
                                    required>

                            </div>

                            <button type="submit"
                                    class="btn btn-success w-100 py-2">

                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                Login

                            </button>

                        </form>

                        <p class="text-center text-muted mt-4 mb-0">

                            Don't have an account?

                            <a href="register.php"
                               class="fw-semibold text-success">
                                Register here
                            </a>

                        </p>

                        <p class="text-center text-muted small mt-3 mb-0">
                            Staff and administrators can also log in here.
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>
