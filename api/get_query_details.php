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
    $userType = $payload['user_type'];

    // Get query ID from request
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Query ID is required']);
        exit();
    }

    $queryId = intval($_GET['id']);

    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get query details
    $stmt = $conn->prepare("
        SELECT lq.*, l.user_id as lawyer_user_id
        FROM legal_queries lq
        LEFT JOIN lawyers l ON lq.lawyer_id = l.id
        WHERE lq.id = ? AND lq.client_id = ?
    ");
    $stmt->execute([$queryId, $userId]);
    $query = $stmt->fetch();

    if (!$query) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Query not found']);
        exit();
    }

    // Return query details
    echo json_encode([
        'success' => true,
        'query' => [
            'id' => $query['id'],
            'title' => $query['title'],
            'category' => $query['category'],
            'description' => $query['description'],
            'status' => $query['status'],
            'lawyer_id' => $query['lawyer_user_id'], // This is the user_id of the lawyer
            'created_at' => $query['created_at']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 