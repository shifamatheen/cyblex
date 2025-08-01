<?php
header('Content-Type: application/json');
require_once '../config/database.php';

// Log that the API was called
error_log("submit-rating.php called at " . date('Y-m-d H:i:s'));

// Get the JWT secret from config
$jwt_secret = JWT_SECRET;

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

    // Check if user is a client
    if ($userType !== 'client') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only clients can submit ratings']);
        exit();
    }

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired token']);
    exit();
}

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['query_id']) || !isset($input['rating']) || !isset($input['lawyer_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$queryId = intval($input['query_id']);
$rating = intval($input['rating']);
$lawyerId = intval($input['lawyer_id']);
$review = isset($input['review']) ? trim($input['review']) : '';
$clientId = $userId;

// Validate rating range
if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5']);
    exit();
}

try {
    // Start transaction
    $conn->beginTransaction();

    // Check if query exists and belongs to the client
    $stmt = $conn->prepare("
        SELECT 1 FROM legal_queries 
        WHERE id = ? AND client_id = ? AND status = 'completed'
    ");
    $stmt->execute([$queryId, $clientId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Invalid query or query not completed');
    }

    // Check if rating already exists
    $stmt = $conn->prepare("SELECT 1 FROM ratings WHERE query_id = ?");
    $stmt->execute([$queryId]);
    
    if ($stmt->rowCount() > 0) {
        throw new Exception('Rating already submitted for this query');
    }

    // Insert the rating
    $stmt = $conn->prepare("
        INSERT INTO ratings (query_id, client_id, lawyer_id, rating, review)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$queryId, $clientId, $lawyerId, $rating, $review]);

    // Update lawyer's average rating
    $stmt = $conn->prepare("
        UPDATE users u
        SET average_rating = (
            SELECT AVG(rating)
            FROM ratings
            WHERE lawyer_id = ?
        )
        WHERE id = ?
    ");
    $stmt->execute([$lawyerId, $lawyerId]);

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Rating submitted successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Log the error for debugging
    error_log("Rating submission error: " . $e->getMessage());
    error_log("Query ID: " . $queryId . ", Lawyer ID: " . $lawyerId . ", Client ID: " . $clientId);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'query_id' => $queryId,
            'lawyer_id' => $lawyerId,
            'client_id' => $clientId,
            'rating' => $rating
        ]
    ]);
} 