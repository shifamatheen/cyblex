<?php
header('Content-Type: application/json');
require_once '../config/database.php';

// Get the JWT secret from config
$jwt_secret = JWT_SECRET;

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

function verifyToken($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }

    $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0])), true);
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
    $signature = $parts[2];

    if (!$header || !$payload) {
        return false;
    }

    // Verify expiration
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }

    // Verify signature
    $expectedSignature = hash_hmac('sha256', 
        $parts[0] . "." . $parts[1], 
        $jwt_secret,
        true
    );
    $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));

    return hash_equals($expectedSignature, $signature) ? $payload : false;
}

try {
    // Get Authorization header
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No token provided']);
        exit();
    }

    $token = $matches[1];
    $payload = verifyToken($token);

    if (!$payload) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired token']);
        exit();
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['query_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Query ID is required']);
        exit();
    }
    
    $queryId = intval($input['query_id']);
    $userId = $payload['user_id'];
    
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verify the query belongs to the current user and is in a completable state
    $stmt = $conn->prepare("
        SELECT id, status, client_id, payment_status
        FROM legal_queries 
        WHERE id = ? AND client_id = ? AND status IN ('assigned', 'in_progress')
    ");
    $stmt->execute([$queryId, $userId]);
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
    error_log('Complete query error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?> 