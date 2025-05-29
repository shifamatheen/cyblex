<?php
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get authorization header
$headers = getallheaders();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';

// Log incoming request
error_log("send_message.php called with data: " . file_get_contents('php://input'));
error_log("Auth header: " . $auth_header);

// Check if token exists
if (!$auth_header || !preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
    error_log("No valid authorization token provided");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access - please log in']);
    exit();
}

$token = $matches[1];

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Verify token and get user info
    $stmt = $conn->prepare("
        SELECT u.id, u.user_type, u.full_name 
        FROM users u 
        WHERE u.token = ?
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("Invalid token provided");
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit();
    }

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    $queryId = isset($data['queryId']) ? intval($data['queryId']) : null;
    $message = isset($data['message']) ? trim($data['message']) : null;

    error_log("Processing request - queryId: $queryId, message length: " . strlen($message));

    // Validate input
    if (!$queryId || !$message) {
        error_log("Invalid input - queryId: $queryId, message: " . substr($message, 0, 50));
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Query ID and message are required']);
        exit();
    }

    // First verify the query exists and get its details
    $stmt = $conn->prepare("
        SELECT lq.*, u.user_type as client_type 
        FROM legal_queries lq
        JOIN users u ON lq.client_id = u.id
        WHERE lq.id = ?
    ");
    $stmt->execute([$queryId]);
    $query = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$query) {
        error_log("Query not found: $queryId");
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Query not found']);
        exit();
    }

    error_log("Query found: " . print_r($query, true));

    // Check if user has access to this query
    $hasAccess = false;
    if ($user['user_type'] === 'client') {
        // Client can only access their own queries
        $hasAccess = ($query['client_id'] === $user['id']);
        error_log("Client access check - client_id: {$query['client_id']}, user_id: {$user['id']}, hasAccess: " . ($hasAccess ? 'true' : 'false'));
    } else if ($user['user_type'] === 'lawyer') {
        // Lawyer can access if they are assigned to the query
        $stmt = $conn->prepare("
            SELECT 1 
            FROM lawyers l 
            WHERE l.id = ? AND l.user_id = ?
        ");
        $stmt->execute([$query['lawyer_id'], $user['id']]);
        $hasAccess = ($stmt->rowCount() > 0);
        error_log("Lawyer access check - lawyer_id: {$query['lawyer_id']}, user_id: {$user['id']}, hasAccess: " . ($hasAccess ? 'true' : 'false'));
    } else if ($user['user_type'] === 'admin') {
        // Admins have access to all queries
        $hasAccess = true;
        error_log("Admin access granted");
    }

    if (!$hasAccess) {
        error_log("Access denied to query $queryId for user {$user['id']} (type: {$user['user_type']})");
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied to this query']);
        exit();
    }

    // Insert the message
    $stmt = $conn->prepare("
        INSERT INTO messages (legal_query_id, sender_id, message, is_read, created_at)
        VALUES (?, ?, ?, FALSE, CURRENT_TIMESTAMP)
    ");
    
    error_log("Inserting message for queryId: $queryId");
    $stmt->execute([$queryId, $user['id'], $message]);
    $messageId = $conn->lastInsertId();
    error_log("Message inserted with ID: $messageId");

    // Get the inserted message with sender details
    $stmt = $conn->prepare("
        SELECT 
            m.*,
            u.full_name as sender_name,
            u.user_type as sender_type,
            CASE 
                WHEN m.sender_id = ? THEN 'You'
                WHEN u.user_type = 'lawyer' THEN CONCAT('Lawyer: ', u.full_name)
                ELSE u.full_name
            END as display_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$user['id'], $messageId]);
    $insertedMessage = $stmt->fetch(PDO::FETCH_ASSOC);

    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $insertedMessage,
        'query_status' => $query['status']
    ]);

} catch (PDOException $e) {
    error_log("Database error in send_message.php: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("Error Info: " . print_r($e->errorInfo, true));
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'details' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
} catch (Exception $e) {
    error_log("Error in send_message.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
}
?> 