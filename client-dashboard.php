<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'client') {
    // If session is not set, check for token in localStorage
    if (isset($_GET['token'])) {
        // Verify token and set session
        try {
            $token = $_GET['token'];
            $tokenParts = explode('.', $token);
            
            if (count($tokenParts) !== 3) {
                throw new Exception('Invalid token format');
            }

            $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[0])), true);
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
            $signature = $tokenParts[2];

            if (!$header || !$payload) {
                throw new Exception('Invalid token payload');
            }

            // Check if token is expired
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                throw new Exception('Token expired');
            }

            // Verify signature
            $jwt_secret = JWT_SECRET;
            $expectedSignature = hash_hmac('sha256', 
                $tokenParts[0] . "." . $tokenParts[1], 
                $jwt_secret,
                true
            );
            $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));
            
            if (!hash_equals($expectedSignature, $signature)) {
                throw new Exception('Invalid token signature');
            }
            
            if ($payload && isset($payload['user_id']) && isset($payload['user_type']) && $payload['user_type'] === 'client') {
                $_SESSION['user_id'] = $payload['user_id'];
                $_SESSION['user_type'] = $payload['user_type'];
                $_SESSION['full_name'] = $payload['full_name'] ?? 'User';
            } else {
                throw new Exception('Invalid user type or missing user data');
            }
        } catch (Exception $e) {
            error_log('JWT verification failed: ' . $e->getMessage());
            header('Location: login.html');
            exit();
        }
    } else {
        header('Location: login.html');
        exit();
    }
}

$db = Database::getInstance();
$conn = $db->getConnection();

// --- Handle Legal Query Form Submission ---
// Note: Form submission is now handled via AJAX in client-dashboard.js
// Server-side processing has been removed to prevent conflicts

// --- Fetch categories for the dropdown ---
$categories = [];
$stmt = $conn->query('SELECT name FROM legal_query_categories ORDER BY name');
while ($row = $stmt->fetch()) {
    $categories[] = $row['name'];
}

// Get user information
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user's legal queries
$stmt = $conn->prepare("
    SELECT lq.* 
    FROM legal_queries lq 
    WHERE lq.client_id = ? 
    ORDER BY 
        CASE lq.status 
            WHEN 'in_progress' THEN 1
            WHEN 'assigned' THEN 2
            WHEN 'completed' THEN 3
            WHEN 'pending' THEN 4
            ELSE 5
        END,
        lq.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$legal_queries = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - Cyblex</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/style.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/formik@2.4.5/dist/formik.umd.production.min.js"></script>
    <script src="https://unpkg.com/yup@1.3.3/dist/yup.umd.min.js"></script>
    <style>
        .query-form {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .form-label {
            font-weight: 500;
            color: var(--dark-text);
        }
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .error-message {
            color: var(--danger-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .success-message {
            color: var(--success-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .rating-stars {
            cursor: pointer;
        }
        .rating-stars .fa-star {
            color: #ddd;
            margin: 0 2px;
            transition: color 0.2s;
        }
        .rating-stars .fa-star.active {
            color: #ffc107;
        }
        .rating-stars:hover .fa-star {
            color: #ffc107;
        }
        .rating-stars .fa-star:hover ~ .fa-star {
            color: #ddd;
        }
        .message {
            margin-bottom: 1rem;
            max-width: 80%;
        }
        .message.sent {
            margin-left: auto;
        }
        .message.received {
            margin-right: auto;
        }
        .message-content {
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            position: relative;
        }
        .message.sent .message-content {
            background-color: #007bff;
            color: white;
            border-bottom-right-radius: 0.25rem;
        }
        .message.received .message-content {
            background-color: #e9ecef;
            color: #212529;
            border-bottom-left-radius: 0.25rem;
        }
        .message-header {
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        .message.sent .message-header {
            color: #e9ecef;
        }
        .message.received .message-header {
            color: #6c757d;
        }
        .message-time {
            font-size: 0.75rem;
            opacity: 0.8;
        }
        .message-text {
            word-wrap: break-word;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/images/logo.svg" alt="Cyblex Logo" height="40">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                                 <ul class="navbar-nav me-auto">
                     <li class="nav-item">
                         <a class="nav-link active" href="#dashboard">Dashboard</a>
                     </li>
                 </ul>
                <div class="d-flex align-items-center">
                    <!-- Language Selector -->
                    <div class="language-selector me-3">
                        <select class="form-select form-select-sm" id="languagePreference">
                            <option value="en">English</option>
                            <option value="ta">தமிழ்</option>
                            <option value="si">සිංහල</option>
                        </select>
                    </div>
                                         <!-- Notifications Dropdown -->
                     <div class="dropdown me-3">
                         <button class="btn btn-outline-primary dropdown-toggle" type="button" id="notificationsDropdown" data-bs-toggle="dropdown">
                             <i class="fas fa-bell"></i>
                             <span class="badge bg-danger" id="notificationCount" style="display: none;">0</span>
                         </button>
                         <div class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                             <h6 class="dropdown-header">Notifications</h6>
                             <div id="notificationsList">
                                 <div class="dropdown-item text-muted">No new notifications</div>
                             </div>
                         </div>
                     </div>
                    <!-- User Profile Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i>
                            <span id="userName">Loading...</span>
                        </a>
                                                 <div class="dropdown-menu dropdown-menu-end">
                             <a class="dropdown-item text-danger" href="logout.php">
                                 <i class="fas fa-sign-out-alt"></i> Logout
                             </a>
                         </div>
                    </li>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-5 pt-4">
        <!-- Legal Query Form -->
        <section class="query-form">
            <h3 class="mb-4"><i class="fas fa-question-circle"></i> Submit Legal Query</h3>
            <form id="legalQueryForm" method="POST" action="javascript:void(0);">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="category" class="form-label">Legal Category</label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= (isset($_POST['category']) && $_POST['category'] === $cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="urgency" class="form-label">Urgency Level</label>
                        <select class="form-select" id="urgency" name="urgency_level" required>
                            <option value="">Select urgency level</option>
                            <option value="low" <?= (isset($_POST['urgency_level']) && $_POST['urgency_level'] === 'low') ? 'selected' : '' ?>>Low</option>
                            <option value="medium" <?= (isset($_POST['urgency_level']) && $_POST['urgency_level'] === 'medium') ? 'selected' : '' ?>>Medium</option>
                            <option value="high" <?= (isset($_POST['urgency_level']) && $_POST['urgency_level'] === 'high') ? 'selected' : '' ?>>High</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="title" class="form-label">Query Title</label>
                    <input type="text" class="form-control" id="title" name="title" required
                           placeholder="Brief description of your legal issue" value="<?= isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '' ?>">
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Detailed Description</label>
                    <textarea class="form-control" id="description" name="description" rows="5" required
                              placeholder="Please provide detailed information about your legal issue"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Submit Query
                    </button>
                </div>
            </form>
        </section>

        <!-- Query History Section -->
        <section class="query-history mb-4">
            <h3>Your Legal Queries</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                                                 <?php foreach ($legal_queries as $query): ?>
                         <?php
                         // Check if rating already exists for this query
                         $ratingExists = false;
                         if ($query['status'] === 'completed') {
                             $ratingStmt = $conn->prepare("SELECT 1 FROM ratings WHERE query_id = ?");
                             $ratingStmt->execute([$query['id']]);
                             $ratingExists = $ratingStmt->rowCount() > 0;
                         }
                         ?>
                         <tr>
                             <td><?= htmlspecialchars($query['title']) ?></td>
                             <td><?= htmlspecialchars($query['category']) ?></td>
                             <td>
                                 <span class="badge bg-<?php 
                                     echo match($query['urgency_level']) {
                                         'low' => 'success',
                                         'medium' => 'warning',
                                         'high' => 'danger',
                                         default => 'secondary'
                                     };
                                 ?>">
                                     <?= ucfirst($query['urgency_level']) ?>
                                 </span>
                             </td>
                             <td>
                                 <span class="badge bg-<?php 
                                     echo match($query['status']) {
                                         'pending' => 'warning',
                                         'assigned' => 'info',
                                         'in_progress' => 'primary',
                                         'completed' => 'success',
                                         'cancelled' => 'danger',
                                         default => 'secondary'
                                     };
                                 ?>">
                                     <?= ucfirst(str_replace('_', ' ', $query['status'])) ?>
                                 </span>
                             </td>
                             <td>
                                 <?php if (isset($query['payment_amount']) && $query['payment_amount'] > 0): ?>
                                     <?php if (isset($query['payment_status']) && $query['payment_status'] === 'completed'): ?>
                                         <span class="badge bg-success">
                                             <i class="fas fa-check"></i> Paid LKR <?= number_format($query['payment_amount'], 2) ?>
                                         </span>
                                     <?php elseif (isset($query['payment_status']) && $query['payment_status'] === 'pending'): ?>
                                         <span class="badge bg-warning">
                                             <i class="fas fa-clock"></i> Pending LKR <?= number_format($query['payment_amount'], 2) ?>
                                         </span>
                                     <?php else: ?>
                                         <span class="text-muted">LKR <?= number_format($query['payment_amount'], 2) ?></span>
                                     <?php endif; ?>
                                 <?php else: ?>
                                     <span class="text-muted">-</span>
                                 <?php endif; ?>
                             </td>
                             <td><?= date('Y-m-d', strtotime($query['created_at'])) ?></td>
                             <td>
                                 <?php if (isset($query['payment_status']) && $query['payment_status'] === 'pending' && in_array($query['status'], ['assigned'])): ?>
                                 <button class="btn btn-sm btn-warning pay-query" data-id="<?= $query['id'] ?>" data-amount="<?= $query['payment_amount'] ?>">
                                     <i class="fas fa-credit-card"></i> Pay LKR <?= number_format($query['payment_amount'], 2) ?>
                                 </button>
                                 <?php endif; ?>
                                 <?php if (isset($query['payment_status']) && $query['payment_status'] === 'completed' && in_array($query['status'], ['assigned', 'in_progress'])): ?>
                                 <button class="btn btn-sm btn-outline-primary start-chat" data-id="<?= $query['id'] ?>">
                                     <i class="fas fa-comments"></i> Chat
                                 </button>
                                 <button class="btn btn-sm btn-outline-success complete-query" data-id="<?= $query['id'] ?>">
                                     <i class="fas fa-check"></i> Complete
                                 </button>
                                 <?php endif; ?>
                                 <?php if ($query['status'] === 'completed' && !$ratingExists): ?>
                                 <button class="btn btn-sm btn-outline-success rate-lawyer" data-id="<?= $query['id'] ?>">
                                     <i class="fas fa-star"></i> Rate
                                 </button>
                                 <?php endif; ?>
                                 <?php if ($query['status'] === 'completed' && $ratingExists): ?>
                                 <span class="badge bg-success">
                                     <i class="fas fa-star"></i> Rated
                                 </span>
                                 <?php endif; ?>
                             </td>
                         </tr>
                         <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Chat and Consultation Details Section -->
        <div class="row">
            <!-- Chat Window -->
            <div class="col-md-8">
                <div class="card shadow-sm chat-container" style="display: none;">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Live Chat</h5>
                        <span class="consultation-status badge bg-primary"></span>
                    </div>
                    <div class="card-body">
                        <div class="chat-messages" style="height: 300px; overflow-y: auto;">
                            <!-- Chat messages will be loaded here -->
                        </div>
                                                 <div class="chat-input mt-3">
                             <form id="inlineChatForm" class="d-flex">
                                 <input type="text" class="form-control me-2" placeholder="Type your message...">
                                 <button type="submit" class="btn btn-primary">Send</button>
                             </form>
                         </div>
                    </div>
                </div>
            </div>

            <!-- Consultation Details -->
            <div class="col-md-4">
                <div class="card shadow-sm query-details" style="display: none;">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Consultation Details</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="consultation-title"></h6>
                        <p class="consultation-category mb-2"></p>
                        <p class="consultation-description"></p>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary rate-lawyer" style="display: none;">
                                Rate Lawyer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rating Modal -->
        <div class="modal fade" id="ratingModal" tabindex="-1" aria-labelledby="ratingModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ratingModalLabel">Rate Your Experience</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="ratingForm">
                            <input type="hidden" id="ratingQueryId" name="query_id">
                            <input type="hidden" id="ratingLawyerId" name="lawyer_id">
                            
                            <div class="mb-4 text-center">
                                <div class="rating-stars mb-2">
                                    <i class="fas fa-star fa-2x" data-rating="1"></i>
                                    <i class="fas fa-star fa-2x" data-rating="2"></i>
                                    <i class="fas fa-star fa-2x" data-rating="3"></i>
                                    <i class="fas fa-star fa-2x" data-rating="4"></i>
                                    <i class="fas fa-star fa-2x" data-rating="5"></i>
                                </div>
                                <input type="hidden" id="ratingValue" name="rating" required>
                                <div id="ratingText" class="text-muted">Select your rating</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="reviewText" class="form-label">Your Review (Optional)</label>
                                <textarea class="form-control" id="reviewText" name="review" rows="3" 
                                    placeholder="Share your experience with this lawyer..."></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="submitRatingBtn">Submit Rating</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rating History Section -->
        <section class="rating-history mb-4">
            <h3>Your Ratings</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Query Title</th>
                            <th>Lawyer</th>
                            <th>Rating</th>
                            <th>Review</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                                                 $stmt = $conn->prepare("
                             SELECT r.*, lq.title as query_title, u.full_name as lawyer_name
                             FROM ratings r
                             JOIN legal_queries lq ON r.query_id = lq.id
                             JOIN users u ON r.lawyer_id = u.id
                             WHERE lq.client_id = ?
                             ORDER BY r.created_at DESC
                         ");
                        $stmt->execute([$_SESSION['user_id']]);
                        $ratings = $stmt->fetchAll();
                        
                        foreach ($ratings as $rating):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($rating['query_title']) ?></td>
                            <td><?= htmlspecialchars($rating['lawyer_name']) ?></td>
                            <td>
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    echo '<i class="fas fa-star ' . ($i <= $rating['rating'] ? 'text-warning' : 'text-muted') . '"></i>';
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($rating['review'] ?? 'No review') ?></td>
                            <td><?= date('Y-m-d', strtotime($rating['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Chat Modal -->
    <div class="modal fade" id="chatModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Chat</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="chat-messages p-3" style="height: 400px; overflow-y: auto;">
                        <!-- Messages will be loaded here -->
                    </div>
                    <div class="chat-input p-3 border-top">
                        <form id="chatForm" class="d-flex gap-2">
                            <input type="text" id="messageInput" name="message" class="form-control" placeholder="Type your message..." required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>About Cyblex</h5>
                    <p>Real-time digital legal advisory platform providing instant legal consultations via secure online communications.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h5>Contact Us</h5>
                    <p>
                        <i class="fas fa-envelope me-2"></i> shifa@trexsolutions.co<br>
                        <i class="fas fa-phone me-2"></i> 70 217 0512
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="js/client-dashboard.js?v=<?php echo time(); ?>"></script>
         <script>
     // Load notifications
     function loadNotifications() {
         const token = localStorage.getItem('token');
         if (!token) return;
         
         // For now, we'll just hide the notification count
         // In a real implementation, you would fetch notifications from an API
         document.getElementById('notificationCount').style.display = 'none';
     }
     
     // Rating form functionality
     document.addEventListener('DOMContentLoaded', function() {
         // Load notifications on page load
         loadNotifications();
        const ratingStars = document.querySelectorAll('.rating-stars .fa-star');
        const ratingValue = document.getElementById('ratingValue');
        const ratingText = document.getElementById('ratingText');
        const ratingForm = document.getElementById('ratingForm');
        const submitRatingBtn = document.getElementById('submitRatingBtn');
        
        // Star rating interaction
        ratingStars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.dataset.rating;
                ratingValue.value = rating;
                
                // Update stars
                ratingStars.forEach(s => {
                    s.classList.toggle('active', s.dataset.rating <= rating);
                });
                
                // Update text
                const texts = ['Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                ratingText.textContent = texts[rating - 1];
            });
            
            star.addEventListener('mouseover', function() {
                const rating = this.dataset.rating;
                ratingStars.forEach(s => {
                    s.classList.toggle('active', s.dataset.rating <= rating);
                });
            });
        });
        
        // Reset stars on mouse leave
        document.querySelector('.rating-stars').addEventListener('mouseleave', function() {
            const rating = ratingValue.value;
            ratingStars.forEach(s => {
                s.classList.toggle('active', s.dataset.rating <= rating);
            });
        });
        
        // Submit rating
        submitRatingBtn.addEventListener('click', function() {
            if (!ratingValue.value) {
                alert('Please select a rating');
                return;
            }
            
            const formData = new FormData(ratingForm);
            const data = Object.fromEntries(formData.entries());
            
            console.log('Rating data being sent:', data);
            
            const token = localStorage.getItem('token');
            if (!token) {
                window.location.href = 'login.html';
                return;
            }
            
            fetch('api/submit-rating.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                console.log('Rating submission result:', result);
                if (result.success) {
                    alert('Thank you for your feedback!');
                    $('#ratingModal').modal('hide');
                    // Refresh the page to show updated data
                    location.reload();
                } else {
                    console.error('Rating submission failed:', result);
                    alert(result.error || 'Failed to submit rating');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to submit rating');
            });
        });
    });

    // Function to show rating modal
    function showRatingModal(queryId, lawyerId) {
        const token = localStorage.getItem('token');
        if (!token) {
            window.location.href = 'login.html';
            return;
        }
        
        // Check if rating already exists
        fetch(`api/check-rating.php?query_id=${queryId}`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.exists) {
                        alert('You have already rated this query');
                    } else if (data.can_rate) {
                        document.getElementById('ratingQueryId').value = queryId;
                        document.getElementById('ratingLawyerId').value = lawyerId;
                        document.getElementById('ratingValue').value = '';
                        document.getElementById('reviewText').value = '';
                        document.querySelectorAll('.rating-stars .fa-star').forEach(s => s.classList.remove('active'));
                        document.getElementById('ratingText').textContent = 'Select your rating';
                        $('#ratingModal').modal('show');
                    } else {
                        alert('This query cannot be rated yet');
                    }
                } else {
                    alert(data.error || 'Failed to check rating status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to check rating status');
            });
    }
    </script>
</body>
</html> 