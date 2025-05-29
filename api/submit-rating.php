<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'client') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['query_id']) || !isset($input['rating']) || !isset($input['lawyer_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$queryId = intval($input['query_id']);
$rating = intval($input['rating']);
$lawyerId = intval($input['lawyer_id']);
$review = isset($input['review']) ? trim($input['review']) : '';
$clientId = $_SESSION['user_id'];

// Validate rating range
if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5']);
    exit();
}

try {
    // Start transaction
    $conn->beginTransaction();

    // Check if query exists and belongs to the client
    $stmt = $conn->prepare("
        SELECT 1 FROM legal_queries 
        WHERE id = ? AND client_id = ? AND status = 'completed'
    ");
    $stmt->execute([$queryId, $clientId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Invalid query or query not completed');
    }

    // Check if rating already exists
    $stmt = $conn->prepare("SELECT 1 FROM ratings WHERE query_id = ?");
    $stmt->execute([$queryId]);
    
    if ($stmt->rowCount() > 0) {
        throw new Exception('Rating already submitted for this query');
    }

    // Insert the rating
    $stmt = $conn->prepare("
        INSERT INTO ratings (query_id, client_id, lawyer_id, rating, review)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$queryId, $clientId, $lawyerId, $rating, $review]);

    // Update lawyer's average rating
    $stmt = $conn->prepare("
        UPDATE users u
        SET average_rating = (
            SELECT AVG(rating)
            FROM ratings
            WHERE lawyer_id = ?
        )
        WHERE id = ?
    ");
    $stmt->execute([$lawyerId, $lawyerId]);

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Rating submitted successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 