<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a lawyer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lawyer') {
    // If session is not set, check for token in URL
    if (isset($_GET['token'])) {
        try {
            $token = $_GET['token'];
            $tokenParts = explode('.', $token);
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
            if ($payload && isset($payload['user_id']) && isset($payload['user_type']) && $payload['user_type'] === 'lawyer') {
                $_SESSION['user_id'] = $payload['user_id'];
                $_SESSION['user_type'] = $payload['user_type'];
                $_SESSION['full_name'] = $payload['full_name'] ?? 'User';
            } else {
                header('Location: login.html');
                exit();
            }
        } catch (Exception $e) {
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

// Validate user_id
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    error_log("Lawyer dashboard: Invalid user_id in session: " . ($_SESSION['user_id'] ?? 'not set'));
    header('Location: login.html');
    exit();
}

// Get lawyer information
try {
    // First check if the status column exists in lawyers table
    $checkColumn = $conn->query("SHOW COLUMNS FROM lawyers LIKE 'status'");
    if ($checkColumn->rowCount() == 0) {
        // Add status column if it doesn't exist
        $conn->exec("ALTER TABLE lawyers ADD COLUMN status ENUM('pending', 'active', 'suspended') DEFAULT 'pending'");
    }

    $stmt = $conn->prepare("
        SELECT 
            u.*, 
            l.specialization,
            l.experience_years,
            l.hourly_rate,
            l.languages,
            l.verification_status,
            u.status
        FROM users u 
        LEFT JOIN lawyers l ON u.id = l.user_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $lawyer = $stmt->fetch();

    // Debug logging
    if (!$lawyer) {
        error_log("Lawyer dashboard: No lawyer record found for user_id: " . $_SESSION['user_id']);
    }

    // If lawyer profile doesn't exist or query returned false, create one
    if (!$lawyer || !isset($lawyer['specialization'])) {
        $stmt = $conn->prepare("
            INSERT INTO lawyers (
                user_id, 
                specialization, 
                experience_years, 
                bar_council_number, 
                hourly_rate, 
                languages,
                status
            ) VALUES (?, 'General', 0, 'PENDING', 0.00, 'en', 'active')
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Fetch the updated lawyer information
        $stmt = $conn->prepare("
            SELECT 
                u.*, 
                l.specialization,
                l.experience_years,
                l.hourly_rate,
                l.languages,
                l.verification_status,
                u.status
            FROM users u 
            LEFT JOIN lawyers l ON u.id = l.user_id 
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $lawyer = $stmt->fetch();
    }
} catch (PDOException $e) {
    error_log("Error in lawyer-dashboard.php: " . $e->getMessage());
    // If table doesn't exist, create it
    if ($e->getCode() == '42S02') { // Table doesn't exist error code
        $sql = "CREATE TABLE IF NOT EXISTS lawyers (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            specialization VARCHAR(100) NOT NULL,
            experience_years INT(11) NOT NULL DEFAULT 0,
            bar_council_number VARCHAR(50) NOT NULL,
            verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
            hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            languages VARCHAR(100) NOT NULL DEFAULT 'en',
            bio TEXT,
            status ENUM('pending', 'active', 'suspended') DEFAULT 'pending',
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $conn->exec($sql);
        
        // Insert default lawyer record
        $stmt = $conn->prepare("
            INSERT INTO lawyers (
                user_id, 
                specialization, 
                experience_years, 
                bar_council_number, 
                hourly_rate, 
                languages,
                status
            ) VALUES (?, 'General', 0, 'PENDING', 0.00, 'en', 'active')
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Try the query again
        $stmt = $conn->prepare("
            SELECT 
                u.*, 
                l.specialization,
                l.experience_years,
                l.hourly_rate,
                l.languages,
                l.verification_status,
                u.status
            FROM users u 
            LEFT JOIN lawyers l ON u.id = l.user_id 
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $lawyer = $stmt->fetch();
    } else {
        throw $e; // Re-throw if it's a different error
    }
}

// Ensure we have valid lawyer data with fallback values
if (!$lawyer || !is_array($lawyer)) {
    $lawyer = [
        'id' => $_SESSION['user_id'] ?? 0,
        'full_name' => $_SESSION['full_name'] ?? 'User',
        'specialization' => 'General',
        'experience_years' => 0,
        'hourly_rate' => 0.00,
        'languages' => 'en',
        'verification_status' => 'pending',
        'status' => 'active' // Default to active for existing users
    ];
}

// Get lawyer statistics
try {
    // Get pending queries count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM legal_queries 
        WHERE status = 'pending'
    ");
    $stmt->execute();
    $pending_count = $stmt->fetch()['count'];

    // Get active chats count
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT lq.id) as count 
        FROM legal_queries lq
        JOIN lawyers l ON lq.lawyer_id = l.id
        WHERE l.user_id = ? AND lq.status IN ('assigned', 'in_progress')
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $active_chats = $stmt->fetch()['count'];

    // Get average rating
    $stmt = $conn->prepare("
        SELECT COALESCE(AVG(r.rating), 0) as avg_rating
        FROM reviews r
        JOIN legal_queries lq ON r.query_id = lq.id
        JOIN lawyers l ON lq.lawyer_id = l.id
        WHERE l.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $avg_rating = round($stmt->fetch()['avg_rating'], 1);

    // Get average response time (in hours)
    $stmt = $conn->prepare("
        SELECT COALESCE(AVG(TIMESTAMPDIFF(HOUR, lq.created_at, m.created_at)), 0) as avg_response_time
        FROM legal_queries lq
        JOIN messages m ON lq.id = m.query_id
        JOIN lawyers l ON lq.lawyer_id = l.id
        WHERE l.user_id = ? AND m.sender_id = l.user_id
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $avg_response_time = round($stmt->fetch()['avg_response_time'], 1);

} catch (PDOException $e) {
    error_log("Error fetching lawyer statistics: " . $e->getMessage());
    $pending_count = 0;
    $active_chats = 0;
    $avg_rating = 0;
    $avg_response_time = 0;
}

// Get pending legal queries
try {
    // First check if the legal_queries table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'legal_queries'");
    if ($tableCheck->rowCount() == 0) {
        throw new PDOException("legal_queries table does not exist");
    }

    $stmt = $conn->prepare("
        SELECT lq.*, u.full_name as client_name
        FROM legal_queries lq
        JOIN users u ON lq.client_id = u.id
        WHERE lq.status = 'pending'
        ORDER BY 
            CASE lq.urgency_level
                WHEN 'high' THEN 1
                WHEN 'medium' THEN 2
                WHEN 'low' THEN 3
            END,
            lq.created_at ASC
    ");
    $stmt->execute();
    $pending_queries = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in lawyer-dashboard.php: " . $e->getMessage());
    $pending_queries = [];
}

// Get accepted queries
try {
    // Debug: Log the lawyer's user ID
    error_log("Fetching accepted queries for lawyer user_id: " . $_SESSION['user_id']);
    
    $stmt = $conn->prepare("
        SELECT lq.*, u.full_name as client_name
        FROM legal_queries lq
        JOIN users u ON lq.client_id = u.id
        JOIN lawyers l ON lq.lawyer_id = l.id
        WHERE l.user_id = ? AND lq.status IN ('assigned', 'in_progress')
        ORDER BY 
            CASE lq.urgency_level
                WHEN 'high' THEN 1
                WHEN 'medium' THEN 2
                WHEN 'low' THEN 3
            END,
            lq.created_at ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $accepted_queries = $stmt->fetchAll();
    
    // Debug: Log the number of queries found
    error_log("Found " . count($accepted_queries) . " accepted queries");
    
    // Debug: Log details of each query
    foreach ($accepted_queries as $query) {
        error_log("Query ID: " . $query['id'] . ", Status: " . $query['status'] . ", Title: " . $query['title']);
    }
} catch (PDOException $e) {
    error_log("Error fetching accepted queries: " . $e->getMessage());
    $accepted_queries = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lawyer Dashboard - Cyblex</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/style.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
            --light-text: #ffffff;
            --border-color: #e9ecef;
        }

        body {
            background-color: var(--light-bg);
            color: var(--dark-text);
        }

        .navbar {
            background-color: var(--light-text) !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand span {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--dark-text);
        }

        .nav-link {
            color: var(--dark-text) !important;
            transition: color 0.2s;
            padding: 0.5rem 1rem;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--secondary-color) !important;
            background-color: rgba(52, 152, 219, 0.1);
            border-radius: 4px;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .dropdown-item {
            padding: 0.5rem 1rem;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }

        .section-card {
            background: var(--light-text);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .section-card h3 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 600;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0.5rem;
        }

        .section-card h3 i {
            margin-right: 0.5rem;
            color: var(--secondary-color);
        }

        .main-content {
            margin-top: 80px;
            padding: 20px;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-pending {
            background-color: var(--warning-color);
            color: var(--dark-text);
        }

        .status-active {
            background-color: var(--success-color);
            color: var(--light-text);
        }

        .status-suspended {
            background-color: var(--danger-color);
            color: var(--light-text);
        }

        .action-button {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .action-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .action-button-primary {
            background-color: var(--secondary-color);
            color: var(--light-text);
            border: none;
        }

        .action-button-secondary {
            background-color: var(--light-text);
            color: var(--secondary-color);
            border: 1px solid var(--secondary-color);
        }

        .stats-card {
            background: var(--light-text);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
            transition: transform 0.2s;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .stats-card h4 {
            color: var(--primary-color);
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .stats-card .value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--secondary-color);
        }

        .chat-messages {
            background-color: #f8f9fa;
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

        .alert {
            margin-bottom: 1.5rem;
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffeeba;
        }

        .alert p {
            margin-top: 0.5rem;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/images/logo.svg" alt="Cyblex Logo" height="40">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link active" href="#dashboard">Dashboard</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i>
                            <span id="userName"><?= htmlspecialchars($lawyer['full_name'] ?? 'User') ?></span>
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

    <div class="main-content">
        <?php if (isset($lawyer['status']) && $lawyer['status'] !== 'active'): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Account Pending Approval</strong>
            <p class="mb-0">Your account is currently pending approval. You will be able to access chat and other features once your account is approved by our admin team.</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <h4><i class="fas fa-clipboard-list"></i> Pending Queries</h4>
                    <div class="value"><?= $pending_count ?></div>
                </div>
            </div>
            <?php if (isset($lawyer['status']) && $lawyer['status'] === 'active'): ?>
            <div class="col-md-3">
                <div class="stats-card">
                    <h4><i class="fas fa-comments"></i> Active Chats</h4>
                    <div class="value"><?= $active_chats ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h4><i class="fas fa-star"></i> Average Rating</h4>
                    <div class="value">
                        <?php if ($avg_rating > 0): ?>
                            <div class="d-flex align-items-center justify-content-center">
                                <span class="h2 mb-0"><?= number_format($avg_rating, 1) ?></span>
                                <div class="ms-2">
                                    <?php
                                    $fullStars = floor($avg_rating);
                                    $halfStar = $avg_rating - $fullStars >= 0.5;
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $fullStars) {
                                            echo '<i class="fas fa-star text-warning"></i>';
                                        } elseif ($i == $fullStars + 1 && $halfStar) {
                                            echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                        } else {
                                            echo '<i class="far fa-star text-warning"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <small class="text-muted">
                                <?php
                                $stmt = $conn->prepare("
                                    SELECT COUNT(*) as count 
                                    FROM ratings 
                                    WHERE lawyer_id = ?
                                ");
                                $stmt->execute([$_SESSION['user_id']]);
                                $rating_count = $stmt->fetch()['count'];
                                echo $rating_count . ' ' . ($rating_count == 1 ? 'rating' : 'ratings');
                                ?>
                            </small>
                        <?php else: ?>
                            <span class="text-muted">No ratings yet</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h4><i class="fas fa-clock"></i> Response Time</h4>
                    <div class="value"><?= $avg_response_time ?>h</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Main Sections -->
        <div class="row">
            <div class="col-md-8">
                <!-- Pending Legal Queries -->
                <section id="pending" class="section-card">
                    <h3><i class="fas fa-clock"></i> Pending Queries</h3>
                    <?php if (empty($pending_queries)): ?>
                        <div class="alert alert-info">No pending queries at the moment.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Category</th>
                                        <th>Title</th>
                                        <th>Urgency</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_queries as $query): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($query['client_name']) ?></td>
                                        <td><?= htmlspecialchars($query['category']) ?></td>
                                        <td><?= htmlspecialchars($query['title']) ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($query['urgency_level']) {
                                                    'high' => 'danger',
                                                    'medium' => 'warning',
                                                    'low' => 'success',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?= ucfirst($query['urgency_level']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('Y-m-d', strtotime($query['created_at'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary view-query" data-id="<?= $query['id'] ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <?php if (isset($lawyer['status']) && $lawyer['status'] === 'active'): ?>
                                            <button class="btn btn-sm btn-success accept-query" data-id="<?= $query['id'] ?>">
                                                <i class="fas fa-check"></i> Accept
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if (isset($lawyer['status']) && $lawyer['status'] === 'active'): ?>
                <!-- Accepted Legal Queries -->
                <section id="accepted" class="section-card">
                    <h3><i class="fas fa-check-circle"></i> Accepted Queries</h3>
                    <?php if (empty($accepted_queries)): ?>
                        <div class="alert alert-info">No accepted queries at the moment.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Category</th>
                                        <th>Title</th>
                                        <th>Urgency</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($accepted_queries as $query): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($query['client_name']) ?></td>
                                        <td><?= htmlspecialchars($query['category']) ?></td>
                                        <td><?= htmlspecialchars($query['title']) ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($query['urgency_level']) {
                                                    'high' => 'danger',
                                                    'medium' => 'warning',
                                                    'low' => 'success',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?= ucfirst($query['urgency_level']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($query['status']) {
                                                    'assigned' => 'info',
                                                    'in_progress' => 'primary',
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
                                            <?php if (isset($query['payment_status']) && $query['payment_status'] === 'pending'): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock"></i> Awaiting Payment
                                            </span>
                                            <?php else: ?>
                                            <button class="btn btn-sm btn-primary view-query" data-id="<?= $query['id'] ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-sm btn-success start-chat" data-id="<?= $query['id'] ?>">
                                                <i class="fas fa-comments"></i> Chat
                                            </button>
                                            <?php if ($query['status'] === 'in_progress'): ?>
                                            <button class="btn btn-sm btn-info complete-query" data-id="<?= $query['id'] ?>">
                                                <i class="fas fa-check-circle"></i> Complete
                                            </button>
                                            <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Real-Time Chat Interface -->
                <section id="chat" class="section-card">
                    <h3><i class="fas fa-comments"></i>Real-Time Chat</h3>
                    <div class="chat-container">
                        <!-- Chat interface will be loaded here -->
                    </div>
                </section>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <!-- Profile & Verification Status -->
                <section id="profile" class="section-card">
                    <h3><i class="fas fa-user-cog"></i>Profile Status</h3>
                    
                    <!-- Account Status -->
                    <div class="status-section mb-3">
                        <h6 class="text-muted mb-2">Account Status</h6>
                        <span class="status-badge status-<?= $lawyer['status'] ?? 'pending' ?>">
                            <i class="fas fa-<?= ($lawyer['status'] === 'active') ? 'check-circle' : (($lawyer['status'] === 'suspended') ? 'ban' : 'clock') ?>"></i>
                            <?= ucfirst($lawyer['status'] ?? 'pending') ?>
                        </span>
                    </div>
                    
                    <!-- Verification Status -->
                    <div class="status-section mb-3">
                        <h6 class="text-muted mb-2">Verification Status</h6>
                        <span class="status-badge status-<?= $lawyer['verification_status'] ?? 'pending' ?>">
                            <i class="fas fa-<?= ($lawyer['verification_status'] === 'verified') ? 'shield-check' : (($lawyer['verification_status'] === 'rejected') ? 'times-circle' : 'hourglass-half') ?>"></i>
                            <?= ucfirst($lawyer['verification_status'] ?? 'pending') ?> Verification
                        </span>
                    </div>
                    
                    <!-- Profile Information -->
                    <div class="profile-info">
                        <h6 class="text-muted mb-2">Profile Details</h6>
                        <div class="profile-detail">
                            <i class="fas fa-gavel text-primary"></i>
                            <span><strong>Specialization:</strong> <?= htmlspecialchars($lawyer['specialization'] ?? 'General') ?></span>
                        </div>
                        <div class="profile-detail">
                            <i class="fas fa-clock text-info"></i>
                            <span><strong>Experience:</strong> <?= htmlspecialchars($lawyer['experience_years'] ?? '0') ?> years</span>
                        </div>
                        <div class="profile-detail">
                            <i class="fas fa-dollar-sign text-success"></i>
                            <span><strong>Hourly Rate:</strong> $<?= number_format($lawyer['hourly_rate'] ?? 0, 2) ?></span>
                        </div>
                        <div class="profile-detail">
                            <i class="fas fa-language text-warning"></i>
                            <span><strong>Languages:</strong> <?= htmlspecialchars($lawyer['languages'] ?? 'English') ?></span>
                        </div>
                        <?php if (isset($lawyer['status']) && $lawyer['status'] === 'active'): ?>
                        <div class="profile-detail">
                            <i class="fas fa-star text-warning"></i>
                            <span><strong>Rating:</strong> <?= number_format($avg_rating, 1) ?>/5.0</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="profile-actions mt-3">
                        <button class="btn btn-outline-primary btn-sm w-100 mb-2" onclick="editProfile()">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                        <?php if ($lawyer['verification_status'] === 'pending'): ?>
                        <button class="btn btn-outline-warning btn-sm w-100" onclick="submitVerification()">
                            <i class="fas fa-upload"></i> Submit Verification
                        </button>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if (isset($lawyer['status']) && $lawyer['status'] === 'active'): ?>
                <!-- Quick Actions -->
                <section class="section-card">
                    <h3><i class="fas fa-bolt"></i>Quick Actions</h3>
                    <div class="d-grid gap-2">
                        <button class="action-button action-button-primary">
                            <i class="fas fa-plus"></i> New Template
                        </button>
                        <button class="action-button action-button-secondary">
                            <i class="fas fa-file-export"></i> Export Reports
                        </button>
                    </div>
                </section>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Query Details Modal -->
    <div class="modal fade" id="queryDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="queryTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6>Description</h6>
                        <p id="queryDescription"></p>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Category:</strong> <span id="queryCategory"></span></p>
                            <p><strong>Urgency:</strong> <span id="queryUrgency"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Client:</strong> <span id="queryClient"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
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
    <!-- Custom JS (to be created: js/lawyer-dashboard.js) -->
    <script src="js/lawyer-dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html> 