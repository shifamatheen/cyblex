<?php
require_once '../config/database.php';
require_once 'cors_config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set up custom error logging
function customErrorLog($message) {
    $logFile = __DIR__ . '/../logs/api_errors.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Get authorization header
$headers = getallheaders();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';

// Log incoming request details
customErrorLog("=== send_message.php Request Details ===");
customErrorLog("Request Method: " . $_SERVER['REQUEST_METHOD']);
customErrorLog("Auth header: " . $auth_header);
customErrorLog("Request Headers: " . print_r($headers, true));

// Check if token exists
if (!$auth_header || !preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
    customErrorLog("No valid authorization token provided");
    customErrorLog("Auth header format check failed");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access - please log in']);
    exit();
}

$token = $matches[1];
customErrorLog("Token extracted: " . substr($token, 0, 20) . "..."); // Log first 20 chars of token for security

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    customErrorLog("Database connection successful");

    // Extract and verify token
    $tokenParts = explode('.', $token);
    if (count($tokenParts) !== 3) {
        customErrorLog("Invalid token format - parts count: " . count($tokenParts));
        throw new Exception('Invalid token format');
    }

    $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[0])), true);
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
    $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[2]));

    if (!$header || !$payload || !$signature) {
        customErrorLog("Token parts validation failed");
        throw new Exception('Invalid token parts');
    }

    // Verify signature
    $expectedSignature = hash_hmac('sha256', 
        $tokenParts[0] . "." . $tokenParts[1], 
        JWT_SECRET,
        true
    );

    if (!hash_equals($signature, $expectedSignature)) {
        customErrorLog("Token signature verification failed");
        throw new Exception('Invalid token signature');
    }

    // Check token expiration
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        customErrorLog("Token expired. Exp: " . $payload['exp'] . ", Current time: " . time());
        throw new Exception('Token has expired');
    }

    // Get request body
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);

    if (!$data || !isset($data['query_id']) || !isset($data['message'])) {
        customErrorLog("Invalid request body: " . $requestBody);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        exit();
    }

    // Insert message
    $stmt = $conn->prepare("
        INSERT INTO messages (legal_query_id, sender_id, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $data['query_id'],
        $payload['user_id'],
        $data['message']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'message_id' => $conn->lastInsertId()
    ]);

} catch (Exception $e) {
    customErrorLog("Error in send_message.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 