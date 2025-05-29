<?php
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get authorization header
$headers = getallheaders();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';

// Log incoming request
error_log("get_messages.php called with queryId: " . (isset($_GET['queryId']) ? $_GET['queryId'] : 'not set'));
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

    // Extract and verify token
    $tokenParts = explode('.', $token);
    if (count($tokenParts) !== 3) {
        throw new Exception('Invalid token format');
    }

    $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[0])), true);
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
    $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[2]));

    if (!$header || !$payload || !$signature) {
        throw new Exception('Invalid token parts');
    }

    // Verify signature
    $expectedSignature = hash_hmac('sha256', 
        $tokenParts[0] . "." . $tokenParts[1], 
        JWT_SECRET,
        true
    );

    if (!hash_equals($signature, $expectedSignature)) {
        throw new Exception('Invalid token signature');
    }

    // Check token expiration
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        throw new Exception('Token has expired');
    }

    // Verify token and get user info
    $stmt = $conn->prepare("
        SELECT u.id, u.user_type, u.full_name 
        FROM users u 
        WHERE u.id = ? AND u.status = 'active'
    ");
    
    $stmt->execute([$payload['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("Invalid token or user not found for ID: " . $payload['user_id']);
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid token or user not found']);
        exit();
    }

    error_log("User authenticated: " . print_r($user, true));

    // Get query parameters
    $queryId = isset($_GET['queryId']) ? intval($_GET['queryId']) : null;
    $lastId = isset($_GET['lastId']) ? intval($_GET['lastId']) : 0;

    error_log("Processing request - queryId: $queryId, lastId: $lastId, userId: {$user['id']}, userType: {$user['user_type']}");

    if (!$queryId) {
        error_log("Invalid query ID provided");
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Query ID is required']);
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

    // Get messages for the query
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
        WHERE m.legal_query_id = ? AND m.id > ?
        ORDER BY m.created_at ASC
    ");
    
    error_log("Executing message query for queryId: $queryId, lastId: $lastId");
    $stmt->execute([$user['id'], $queryId, $lastId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Found " . count($messages) . " messages");

    // Mark messages as read for the current user
    if (!empty($messages)) {
        $stmt = $conn->prepare("
            UPDATE messages 
            SET is_read = TRUE 
            WHERE legal_query_id = ? 
            AND sender_id != ? 
            AND is_read = FALSE
        ");
        $stmt->execute([$queryId, $user['id']]);
        error_log("Marked messages as read for query $queryId");
    }

    // Return messages as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'messages' => $messages,
        'last_id' => !empty($messages) ? end($messages)['id'] : $lastId,
        'query_status' => $query['status'] // Include query status in response
    ]);

} catch (PDOException $e) {
    error_log("Database error in get_messages.php: " . $e->getMessage());
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
    error_log("Error in get_messages.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
} 