<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in via session or token
$isLoggedIn = false;
$userType = null;
$userName = null;

if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    $isLoggedIn = true;
    $userType = $_SESSION['user_type'];
    $userName = $_SESSION['full_name'] ?? 'User';
} elseif (isset($_GET['token'])) {
    // Verify JWT token
    try {
        $token = $_GET['token'];
        $tokenParts = explode('.', $token);
        
        if (count($tokenParts) === 3) {
            $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[0])), true);
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
            $signature = $tokenParts[2];

            if ($header && $payload) {
                // Check if token is expired
                if (!isset($payload['exp']) || $payload['exp'] >= time()) {
                    // Verify signature
                    $jwt_secret = JWT_SECRET;
                    $expectedSignature = hash_hmac('sha256', 
                        $tokenParts[0] . "." . $tokenParts[1], 
                        $jwt_secret,
                        true
                    );
                    $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));
                    
                    if (hash_equals($expectedSignature, $signature)) {
                        $isLoggedIn = true;
                        $userType = $payload['user_type'];
                        $userName = $payload['full_name'] ?? 'User';
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Token verification failed, user not logged in
    }
}

// Determine dashboard URL based on user type
$dashboardUrl = 'login.html';
if ($isLoggedIn) {
    $tokenParam = isset($_GET['token']) ? '?token=' . $_GET['token'] : '';
    switch ($userType) {
        case 'client':
            $dashboardUrl = 'client-dashboard.php' . $tokenParam;
            break;
        case 'lawyer':
            $dashboardUrl = 'lawyer-dashboard.php' . $tokenParam;
            break;
        case 'admin':
            $dashboardUrl = 'admin-dashboard.php' . $tokenParam;
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cyblex - Real-time Digital Legal Advisory Platform</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/style.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/images/logo.svg" alt="Cyblex Logo" height="40">
                <span>Cyblex</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $isLoggedIn ? $dashboardUrl : 'login.html' ?>">Find A Lawyer</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $isLoggedIn ? $dashboardUrl : 'login.html' ?>">Legal Advice</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="language-selector me-3">
                        <select class="form-select form-select-sm">
                            <option value="en">English</option>
                            <option value="ta">தமிழ்</option>
                            <option value="si">සිංහල</option>
                        </select>
                    </div>
                    <?php if ($isLoggedIn): ?>
                        <!-- User is logged in - show dashboard link and logout -->
                        <div class="dropdown me-2">
                            <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i>
                                <?= htmlspecialchars($userName) ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= $dashboardUrl ?>">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- User is not logged in - show login/register buttons -->
                        <a href="login.html" class="btn btn-outline-primary me-2">Login</a>
                        <a href="register.html" class="btn btn-primary">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Connect Instantly with Verified Legal Experts</h1>
                    <p class="lead mb-4">Get real-time legal help from trusted professionals — in your language, on your terms.</p>
                    <h3 class="h4 mb-4">Legal support, made fast, fair, and secure.</h3>
                    <div class="d-flex gap-3">
                        <?php if ($isLoggedIn): ?>
                            <a href="<?= $dashboardUrl ?>" class="btn btn-primary btn-lg">Go to Dashboard</a>
                        <?php else: ?>
                            <a href="client-dashboard.php" class="btn btn-primary btn-lg">Get Legal Advice Now</a>
                        <?php endif; ?>
                        <a href="#how-it-works" class="btn btn-outline-primary btn-lg">How It Works</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <img src="assets/images/lawelement.svg" alt="Legal Consultation" class="img-fluid">
                </div>
            </div>
        </div>
    </section>

    <!-- Core Features Section -->
    <section class="features-section py-5">
        <div class="container">
            <h2 class="text-center mb-5">Our Core Features</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card text-center p-4">
                        <i class="fas fa-comments fa-3x mb-3 text-primary"></i>
                        <h3>Real-time Chat</h3>
                        <p>Connect instantly with legal experts through our secure chat platform</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card text-center p-4">
                        <i class="fas fa-language fa-3x mb-3 text-primary"></i>
                        <h3>Multilingual Support</h3>
                        <p>Access legal advice in Sinhala, Tamil, and English</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card text-center p-4">
                        <i class="fas fa-user-shield fa-3x mb-3 text-primary"></i>
                        <h3>Verified Experts</h3>
                        <p>All our legal professionals are thoroughly verified</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="how-it-works-section py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">How Cyblex Works</h2>
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="step-card text-center p-4">
                        <div class="step-number mb-3">1</div>
                        <h4>Submit Your Query</h4>
                        <p>Describe your legal issue and select the appropriate category</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="step-card text-center p-4">
                        <div class="step-number mb-3">2</div>
                        <h4>Get Matched</h4>
                        <p>Our system matches you with the best legal expert for your case</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="step-card text-center p-4">
                        <div class="step-number mb-3">3</div>
                        <h4>Chat & Consult</h4>
                        <p>Connect with your lawyer through our secure chat platform</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="step-card text-center p-4">
                        <div class="step-number mb-3">4</div>
                        <h4>Get Resolution</h4>
                        <p>Receive expert legal advice and guidance for your situation</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="cta-section py-5">
        <div class="container text-center">
            <h2 class="mb-4">Ready to Get Started?</h2>
            <p class="lead mb-4">Join thousands of users who trust Cyblex for their legal needs</p>
            <div class="d-flex gap-3 justify-content-center">
                <?php if ($isLoggedIn): ?>
                    <a href="<?= $dashboardUrl ?>" class="btn btn-primary btn-lg">Go to Dashboard</a>
                <?php else: ?>
                    <a href="client-dashboard.php" class="btn btn-primary btn-lg">Get Legal Advice Now</a>
                    <a href="lawyer-dashboard.php" class="btn btn-outline-primary btn-lg">Become a Legal Expert</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials-section py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Our Clients Say</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="testimonial-card p-4">
                        <div class="stars mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p class="mb-3">"Cyblex helped me resolve my property dispute quickly and efficiently. The lawyer was professional and knowledgeable."</p>
                        <div class="client-info">
                            <strong>Ravi Kumar</strong>
                            <small class="text-muted d-block">Property Law</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card p-4">
                        <div class="stars mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p class="mb-3">"Excellent service! I got immediate help with my employment contract. The platform is user-friendly and secure."</p>
                        <div class="client-info">
                            <strong>Sarah Johnson</strong>
                            <small class="text-muted d-block">Employment Law</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card p-4">
                        <div class="stars mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p class="mb-3">"The real-time chat feature is amazing. I could get instant answers to my legal questions without leaving my home."</p>
                        <div class="client-info">
                            <strong>Mohammed Ali</strong>
                            <small class="text-muted d-block">Family Law</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>About Cyblex</h5>
                    <p>Real-time digital legal advisory platform providing instant legal consultations via secure online communications.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white-50">How It Works</a></li>
                        <li><a href="#" class="text-white-50">Legal Categories</a></li>
                        <li><a href="#" class="text-white-50">Expert Verification</a></li>
                        <li><a href="#" class="text-white-50">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact Us</h5>
                    <p>
                        <i class="fas fa-envelope me-2"></i> support@cyblex.com<br>
                        <i class="fas fa-phone me-2"></i> +94 11 234 5678
                    </p>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2024 Cyblex. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">Made with ❤️ for Sri Lanka</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 