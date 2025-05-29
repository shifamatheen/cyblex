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

    // Get all reviews with client and lawyer details
    $query = "SELECT r.id, r.rating, r.comment, r.status, r.created_at,
                     u.full_name as client_name,
                     l.full_name as lawyer_name,
                     l.specialization as lawyer_specialization,
                     q.title as query_title
              FROM reviews r
              JOIN users u ON r.client_id = u.id
              JOIN lawyers l ON r.lawyer_id = l.user_id
              JOIN queries q ON r.query_id = q.id
              ORDER BY r.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates and add additional information
    foreach ($reviews as &$review) {
        $review['created_at'] = date('Y-m-d H:i:s', strtotime($review['created_at']));
        
        // Calculate average rating for the lawyer
        $avgQuery = "SELECT AVG(rating) as avg_rating FROM reviews WHERE lawyer_id = ?";
        $avgStmt = $conn->prepare($avgQuery);
        $avgStmt->execute([$review['lawyer_id']]);
        $review['lawyer_avg_rating'] = round($avgStmt->fetch(PDO::FETCH_ASSOC)['avg_rating'], 1);
    }

    echo json_encode([
        'success' => true,
        'reviews' => $reviews
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching reviews'
    ]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred'
    ]);
} 