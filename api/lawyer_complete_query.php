<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Check if user is logged in and is a lawyer
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lawyer') {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit();
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['queryId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Query ID is required']);
        exit();
    }
    
    $queryId = intval($input['queryId']);
    $lawyerUserId = $_SESSION['user_id'];
    
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // First, get the lawyer's ID from the lawyers table
    $stmt = $conn->prepare("SELECT id FROM lawyers WHERE user_id = ?");
    $stmt->execute([$lawyerUserId]);
    $lawyer = $stmt->fetch();
    
    if (!$lawyer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Lawyer profile not found']);
        exit();
    }
    
    $lawyerId = $lawyer['id'];
    
    // Verify the query belongs to the current lawyer and is in a completable state
    $stmt = $conn->prepare("
        SELECT id, status, lawyer_id, payment_status
        FROM legal_queries 
        WHERE id = ? AND lawyer_id = ? AND status IN ('assigned', 'in_progress')
    ");
    $stmt->execute([$queryId, $lawyerId]);
    $query = $stmt->fetch();
    
    if (!$query) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Query not found or cannot be completed']);
        exit();
    }

    // Check payment status
    if (isset($query['payment_status']) && $query['payment_status'] !== 'completed') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Payment must be completed before completing the query']);
        exit();
    }
    
    // Update the query status to completed
    $stmt = $conn->prepare("
        UPDATE legal_queries 
        SET status = 'completed', updated_at = NOW() 
        WHERE id = ?
    ");
    
    if ($stmt->execute([$queryId])) {
        echo json_encode([
            'success' => true, 
            'message' => 'Query completed successfully',
            'query_id' => $queryId
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to complete query']);
    }
    
} catch (Exception $e) {
    error_log('Lawyer complete query error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?> 