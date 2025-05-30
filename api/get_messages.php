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
customErrorLog("=== get_messages.php Request Details ===");
customErrorLog("Request Method: " . $_SERVER['REQUEST_METHOD']);
customErrorLog("ID: " . (isset($_GET['id']) ? $_GET['id'] : 'not set'));
customErrorLog("Type: " . (isset($_GET['type']) ? $_GET['type'] : 'not set'));
customErrorLog("Auth header: " . $auth_header);

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

    // Get request parameters
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    $type = isset($_GET['type']) ? $_GET['type'] : null;
    $lastId = isset($_GET['lastId']) ? intval($_GET['lastId']) : 0;

    if (!$id || !$type) {
        customErrorLog("Invalid request parameters");
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID and type are required']);
        exit();
    }

    // Verify access based on type
    if ($type === 'query') {
        $stmt = $conn->prepare("
            SELECT lq.*, u.user_type as client_type,
                   l.user_id as lawyer_user_id
            FROM legal_queries lq
            JOIN users u ON lq.client_id = u.id
            LEFT JOIN lawyers l ON lq.lawyer_id = l.id
            WHERE lq.id = ?
        ");
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            customErrorLog("Query not found: $id");
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Query not found']);
            exit();
        }

        // Verify user has access to this query
        if ($record['client_id'] != $payload['user_id'] && $record['lawyer_user_id'] != $payload['user_id']) {
            customErrorLog("Unauthorized access to query: $id");
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized access to this query']);
            exit();
        }

        // Get messages for the query
        $stmt = $conn->prepare("
            SELECT 
                m.*,
                u.full_name as sender_name,
                u.user_type as sender_type,
                CASE 
                    WHEN m.sender_id = ? THEN 'You'
                    ELSE u.full_name
                END as display_name,
                CASE 
                    WHEN m.sender_id = ? THEN 'sent'
                    ELSE 'received'
                END as message_type
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.legal_query_id = ? AND m.id > ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$payload['user_id'], $payload['user_id'], $id, $lastId]);

    } else if ($type === 'consultation') {
        $stmt = $conn->prepare("
            SELECT c.* 
            FROM consultations c
            WHERE c.id = ? AND (c.client_id = ? OR c.lawyer_id = ?)
        ");
        $stmt->execute([$id, $payload['user_id'], $payload['user_id']]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            customErrorLog("Consultation not found or unauthorized access");
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Consultation not found or unauthorized access']);
            exit();
        }

        // Get messages for the consultation
        $stmt = $conn->prepare("
            SELECT 
                m.*,
                u.full_name as sender_name,
                u.user_type as sender_type,
                CASE 
                    WHEN m.sender_id = ? THEN 'You'
                    ELSE u.full_name
                END as display_name,
                CASE 
                    WHEN m.sender_id = ? THEN 'sent'
                    ELSE 'received'
                END as message_type
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.consultation_id = ? AND m.id > ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$payload['user_id'], $payload['user_id'], $id, $lastId]);
    } else {
        customErrorLog("Invalid type specified");
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid type specified']);
        exit();
    }

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    customErrorLog("Found " . count($messages) . " messages");

    // Mark messages as read for the current user
    if (!empty($messages)) {
        $stmt = $conn->prepare("
            UPDATE messages 
            SET is_read = TRUE 
            WHERE " . ($type === 'query' ? 'legal_query_id' : 'consultation_id') . " = ? 
            AND sender_id != ? 
            AND is_read = FALSE
        ");
        $stmt->execute([$id, $payload['user_id']]);
        customErrorLog("Marked messages as read for " . ($type === 'query' ? 'query' : 'consultation') . " $id");
    }

    // Return messages as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'messages' => $messages,
        'last_id' => !empty($messages) ? end($messages)['id'] : $lastId,
        'status' => $record['status']
    ]);

} catch (PDOException $e) {
    customErrorLog("Database error in get_messages.php: " . $e->getMessage());
    customErrorLog("SQL State: " . $e->getCode());
    customErrorLog("Error Info: " . print_r($e->errorInfo, true));
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database error occurred',
        'details' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
} catch (Exception $e) {
    customErrorLog("Error in get_messages.php: " . $e->getMessage());
    customErrorLog("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
} 