<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.html');
    exit();
}

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get platform statistics
try {
    $stats = [
        'total_users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_lawyers' => $conn->query("SELECT COUNT(*) FROM users WHERE user_type = 'lawyer'")->fetchColumn(),
        'total_clients' => $conn->query("SELECT COUNT(*) FROM users WHERE user_type = 'client'")->fetchColumn(),
        'total_queries' => $conn->query("SELECT COUNT(*) FROM queries")->fetchColumn(),
        'active_queries' => $conn->query("SELECT COUNT(*) FROM queries WHERE status = 'active'")->fetchColumn(),
        'pending_verifications' => $conn->query("SELECT COUNT(*) FROM lawyer_verifications WHERE status = 'pending'")->fetchColumn()
    ];
} catch (PDOException $e) {
    // If lawyer_verifications table doesn't exist, set pending_verifications to 0
    if ($e->getCode() == '42S02') {
        $stats = [
            'total_users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'total_lawyers' => $conn->query("SELECT COUNT(*) FROM users WHERE user_type = 'lawyer'")->fetchColumn(),
            'total_clients' => $conn->query("SELECT COUNT(*) FROM users WHERE user_type = 'client'")->fetchColumn(),
            'total_queries' => $conn->query("SELECT COUNT(*) FROM queries")->fetchColumn(),
            'active_queries' => $conn->query("SELECT COUNT(*) FROM queries WHERE status = 'active'")->fetchColumn(),
            'pending_verifications' => 0
        ];
    } else {
        throw $e; // Re-throw if it's a different error
    }
}

// Get average response time (in hours)
$avgResponseTime = $conn->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, assigned_at)) 
    FROM queries 
    WHERE status != 'pending' AND assigned_at IS NOT NULL
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cyblex</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link href="css/style.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .nav-pills .nav-link {
            color: #2c3e50;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }
        .nav-pills .nav-link:hover {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }
        .nav-pills .nav-link.active {
            background-color: #3498db;
            color: white;
        }
        .nav-pills .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .table-responsive {
            max-height: 600px;
        }
        .navbar {
            background-color: white !important;
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
            color: #2c3e50;
        }
        .nav-link {
            color: #2c3e50 !important;
            transition: color 0.2s;
        }
        .nav-link:hover {
            color: #3498db !important;
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
        .badge {
            position: absolute;
            top: 0;
            right: 0;
            transform: translate(50%, -50%);
        }
        .notification-icon {
            position: relative;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="assets/images/logo.svg" alt="Cyblex Logo" height="40">
                <span>Admin Dashboard</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link notification-icon" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="badge bg-danger">3</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <h6 class="dropdown-header">Notifications</h6>
                            <a class="dropdown-item" href="#">
                                <i class="fas fa-user-plus text-success"></i> New lawyer registration
                            </a>
                            <a class="dropdown-item" href="#">
                                <i class="fas fa-flag text-warning"></i> Flagged review reported
                            </a>
                            <a class="dropdown-item" href="#">
                                <i class="fas fa-exclamation-triangle text-danger"></i> System alert
                            </a>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i>
                            <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="#"><i class="fas fa-cog"></i> Settings</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid py-4" style="margin-top: 70px;">
        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4 col-lg-2">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="card-title">Total Users</h6>
                        <h2 class="mb-0"><?php echo number_format($stats['total_users']); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <h6 class="card-title">Lawyers</h6>
                        <h2 class="mb-0"><?php echo number_format($stats['total_lawyers']); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <h6 class="card-title">Clients</h6>
                        <h2 class="mb-0"><?php echo number_format($stats['total_clients']); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <h6 class="card-title">Total Queries</h6>
                        <h2 class="mb-0"><?php echo number_format($stats['total_queries']); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body">
                        <h6 class="card-title">Active Queries</h6>
                        <h2 class="mb-0"><?php echo number_format($stats['active_queries']); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="card stat-card bg-secondary text-white">
                    <div class="card-body">
                        <h6 class="card-title">Pending Verifications</h6>
                        <h2 class="mb-0"><?php echo number_format($stats['pending_verifications']); ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Dashboard Content -->
        <div class="row">
            <!-- Left Sidebar -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="nav flex-column nav-pills" role="tablist">
                            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#users">
                                <i class="fas fa-users"></i> User Management
                            </button>
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#verifications">
                                <i class="fas fa-user-check"></i> Advisor Verification
                            </button>
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#queries">
                                <i class="fas fa-question-circle"></i> Query Oversight
                            </button>
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#reviews">
                                <i class="fas fa-star"></i> Ratings & Reviews
                            </button>
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#notifications">
                                <i class="fas fa-bullhorn"></i> System Notifications
                            </button>
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#analytics">
                                <i class="fas fa-chart-line"></i> Analytics
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="col-md-9">
                <div class="tab-content">
                    <!-- User Management -->
                    <div class="tab-pane fade show active" id="users">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">User Management</h5>
                                <div class="input-group" style="width: 300px;">
                                    <input type="text" class="form-control" placeholder="Search users...">
                                    <button class="btn btn-outline-secondary" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Joined</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="usersTableBody">
                                            <!-- Users will be loaded here via JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Advisor Verification -->
                    <div class="tab-pane fade" id="verifications">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Pending Verifications</h5>
                                <div class="input-group" style="width: 300px;">
                                    <input type="text" class="form-control" id="verificationSearch" placeholder="Search verifications...">
                                    <button class="btn btn-outline-secondary" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Lawyer</th>
                                                <th>Specialization</th>
                                                <th>Submitted</th>
                                                <th>Documents</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="verificationsTableBody">
                                            <!-- Verifications will be loaded here via JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Query Oversight -->
                    <div class="tab-pane fade" id="queries">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Query Management</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Client</th>
                                                <th>Category</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="queriesTableBody">
                                            <!-- Queries will be loaded here via JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ratings & Reviews -->
                    <div class="tab-pane fade" id="reviews">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Review Moderation</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Lawyer</th>
                                                <th>Client</th>
                                                <th>Rating</th>
                                                <th>Review</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="reviewsTableBody">
                                            <!-- Reviews will be loaded here via JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Notifications -->
                    <div class="tab-pane fade" id="notifications">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Send System Notification</h5>
                            </div>
                            <div class="card-body">
                                <form id="notificationForm">
                                    <div class="mb-3">
                                        <label class="form-label">Recipients</label>
                                        <select class="form-select" multiple>
                                            <option value="all">All Users</option>
                                            <option value="lawyers">All Lawyers</option>
                                            <option value="clients">All Clients</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Title</label>
                                        <input type="text" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Message</label>
                                        <textarea class="form-control" rows="4" required></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Priority</label>
                                        <select class="form-select">
                                            <option value="low">Low</option>
                                            <option value="medium">Medium</option>
                                            <option value="high">High</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Send Notification</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics -->
                    <div class="tab-pane fade" id="analytics">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Platform Analytics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <canvas id="userGrowthChart"></canvas>
                                    </div>
                                    <div class="col-md-6">
                                        <canvas id="queryCategoriesChart"></canvas>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <canvas id="responseTimeChart"></canvas>
                                    </div>
                                    <div class="col-md-6">
                                        <canvas id="satisfactionChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Modal -->
    <div class="modal fade" id="chatModal" tabindex="-1" aria-labelledby="chatModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="chatModalLabel">Chat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="chat-messages" style="height: 400px; overflow-y: auto; margin-bottom: 1rem;">
                        <!-- Messages will be loaded here -->
                    </div>
                    <form id="chatForm" class="d-flex">
                        <input type="text" class="form-control me-2" placeholder="Type your message...">
                        <button type="submit" class="btn btn-primary">Send</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom JS -->
    <script>
        // Function to load pending verifications
        function loadPendingVerifications() {
            fetch('api/get-pending-verifications.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    const tbody = document.getElementById('verificationsTableBody');
                    tbody.innerHTML = '';
                    
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    const verifications = Array.isArray(data) ? data : (data.verifications || []);
                    
                    if (verifications.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <i class="fas fa-info-circle text-info me-2"></i>
                                    No pending verifications found
                                </td>
                            </tr>
                        `;
                        return;
                    }
                    
                    verifications.forEach(verification => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="${verification.profile_image || 'assets/images/default-avatar.png'}" 
                                         class="rounded-circle me-2" 
                                         width="40" 
                                         height="40" 
                                         alt="${verification.full_name}">
                                    <div>
                                        <div class="fw-bold">${verification.full_name}</div>
                                        <small class="text-muted">${verification.email}</small>
                                    </div>
                                </div>
                            </td>
                            <td>${verification.specialization}</td>
                            <td>${new Date(verification.submitted_at).toLocaleDateString()}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="viewDocuments(${verification.lawyer_id})">
                                    <i class="fas fa-file-alt"></i> View Documents
                                </button>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-success" 
                                            onclick="handleVerification(${verification.lawyer_id}, 'approve')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn btn-sm btn-danger" 
                                            onclick="handleVerification(${verification.lawyer_id}, 'reject')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                })
                .catch(error => {
                    console.error('Error loading verifications:', error);
                    const tbody = document.getElementById('verificationsTableBody');
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center py-4 text-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                ${error.message || 'Failed to load pending verifications'}
                            </td>
                        </tr>
                    `;
                });
        }

        // Function to handle verification actions
        function handleVerification(lawyerId, action) {
            if (!confirm(`Are you sure you want to ${action} this verification?`)) {
                return;
            }

            fetch('api/verify-advisor.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lawyer_id: lawyerId,
                    action: action
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                if (data.success) {
                    alert(data.message);
                    loadPendingVerifications(); // Reload the table
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to process verification: ' + error.message);
            });
        }

        // Function to view documents
        function viewDocuments(lawyerId) {
            // Implement document viewer modal
            alert('Document viewer functionality to be implemented');
        }

        // Load verifications when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadPendingVerifications();
        });
    </script>
</body>
</html> 