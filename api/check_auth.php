<?php
header('Content-Type: application/json');
require_once '../config/database.php';

// Get the JWT secret from config
$jwt_secret = JWT_SECRET;

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

// Get Authorization header
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode(['success' => false, 'message' => 'No token provided']);
    exit;
}

$token = $matches[1];
$payload = verifyToken($token);

if (!$payload) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get user data
    $stmt = $db->query(
        "SELECT id, email, user_type, full_name, language_preference 
         FROM users 
         WHERE id = ?", 
        [$payload['user_id']]
    );
    
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'user' => $user
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
    error_log($e->getMessage());
}
?> 