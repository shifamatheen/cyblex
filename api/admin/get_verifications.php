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

    // Get pending verifications with lawyer details
    // Using the correct column names from the lawyers table
    $query = "SELECT l.id, u.full_name as lawyer_name, u.email, l.specialization, 
                     l.experience_years, l.bar_council_number, l.verification_status,
                     l.created_at as submitted_at
              FROM lawyers l
              JOIN users u ON l.user_id = u.id
              WHERE l.verification_status = 'pending'
              ORDER BY l.created_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates
    foreach ($verifications as &$verification) {
        $verification['submitted_at'] = date('Y-m-d H:i:s', strtotime($verification['submitted_at']));
    }

    echo json_encode([
        'success' => true,
        'verifications' => $verifications
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching verifications'
    ]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred'
    ]);
} 