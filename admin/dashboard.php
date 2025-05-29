<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cyblex</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Cyblex Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#verifications">Verifications</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Pending Verifications</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Specialization</th>
                                        <th>Submitted Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="verificationsTableBody">
                                    <!-- Data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Make sure the functions are available globally
        window.loadPendingVerifications = async function() {
            try {
                const response = await fetch('../api/get-pending-verifications.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                console.log('Response data:', data); // Debug log
                
                const tableBody = document.getElementById('verificationsTableBody');
                if (!tableBody) {
                    console.error('Table body element not found');
                    return;
                }
                tableBody.innerHTML = '';
                
                // Handle different response formats
                let verifications = [];
                if (Array.isArray(data)) {
                    verifications = data;
                } else if (data && typeof data === 'object') {
                    if (data.verifications && Array.isArray(data.verifications)) {
                        verifications = data.verifications;
                    } else if (data.success && data.verifications) {
                        verifications = Array.isArray(data.verifications) ? data.verifications : [];
                    }
                }
                
                console.log('Processed verifications:', verifications); // Debug log
                
                if (!verifications || verifications.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center">No pending verifications found</td>
                        </tr>
                    `;
                    return;
                }
                
                verifications.forEach(verification => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${verification.full_name || ''}</td>
                        <td>${verification.email || ''}</td>
                        <td>${verification.specialization || ''}</td>
                        <td>${verification.submitted_at ? new Date(verification.submitted_at).toLocaleDateString() : ''}</td>
                        <td>
                            <button class="btn btn-success btn-sm" onclick="handleVerification(${verification.lawyer_id}, 'approve')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="handleVerification(${verification.lawyer_id}, 'reject')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </td>
                    `;
                    tableBody.appendChild(row);
                });
            } catch (error) {
                console.error('Error loading verifications:', error);
                const tableBody = document.getElementById('verificationsTableBody');
                if (tableBody) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center text-danger">
                                Error loading verifications: ${error.message}
                            </td>
                        </tr>
                    `;
                }
            }
        };

        window.handleVerification = async function(lawyerId, action) {
            try {
                const response = await fetch('../api/verify-advisor.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        lawyer_id: lawyerId,
                        action: action
                    })
                });

                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to process verification');
                }

                // Show success message
                alert(data.message || 'Verification processed successfully');
                
                // Reload the verifications list
                loadPendingVerifications();
            } catch (error) {
                console.error('Error handling verification:', error);
                alert('Error: ' + error.message);
            }
        };

        // Load verifications when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadPendingVerifications();
        });
    </script>
</body>
</html> 