<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get all users with their details
    $query = "SELECT u.id, u.full_name, u.email, u.user_type, u.status, u.created_at,
                     l.specialization, l.verification_status
              FROM users u
              LEFT JOIN lawyers l ON u.id = l.user_id
              ORDER BY u.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates and add additional information
    foreach ($users as &$user) {
        $user['created_at'] = date('Y-m-d H:i:s', strtotime($user['created_at']));
        if ($user['user_type'] === 'lawyer') {
            $user['verification_status'] = $user['verification_status'] ?? 'pending';
        }
    }

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching users'
    ]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred'
    ]);
} 