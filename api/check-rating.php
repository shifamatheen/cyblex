<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Get query ID from request
$queryId = isset($_GET['query_id']) ? intval($_GET['query_id']) : null;

if (!$queryId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Query ID is required']);
    exit();
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check if rating exists and get rating details
    $stmt = $conn->prepare("
        SELECT r.*, u.full_name as client_name
        FROM ratings r
        JOIN users u ON r.client_id = u.id
        WHERE r.query_id = ?
    ");
    $stmt->execute([$queryId]);
    
    if ($stmt->rowCount() > 0) {
        $rating = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true,
            'exists' => true,
            'rating' => $rating
        ]);
    } else {
        // Check if query is completed and belongs to the user
        $stmt = $conn->prepare("
            SELECT 1 FROM legal_queries 
            WHERE id = ? AND client_id = ? AND status = 'completed'
        ");
        $stmt->execute([$queryId, $_SESSION['user_id']]);
        
        echo json_encode([
            'success' => true,
            'exists' => false,
            'can_rate' => $stmt->rowCount() > 0
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to check rating status'
    ]);
} 