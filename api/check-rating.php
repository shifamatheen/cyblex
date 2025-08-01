<?php
header('Content-Type: application/json');
require_once '../config/database.php';

// Get the JWT secret from config
$jwt_secret = JWT_SECRET;

// Check if it's a GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Get Authorization header
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No token provided']);
    exit();
}

$token = $matches[1];

try {
    // Decode JWT token
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new Exception('Invalid token format');
    }

    $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0])), true);
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
    $signature = $parts[2];

    if (!$header || !$payload) {
        throw new Exception('Invalid token payload');
    }

    // Check if token is expired
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        throw new Exception('Token expired');
    }

    // Verify signature
    $expectedSignature = hash_hmac('sha256', 
        $parts[0] . "." . $parts[1], 
        $jwt_secret,
        true
    );
    $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));
    
    if (!hash_equals($expectedSignature, $signature)) {
        throw new Exception('Invalid token signature');
    }

    $userId = $payload['user_id'];

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
        $stmt->execute([$queryId, $userId]);
        
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