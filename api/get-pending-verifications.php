<?php
require_once '../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log session data for debugging
error_log("Session data: " . print_r($_SESSION, true));

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        error_log("User not logged in");
        http_response_code(401);
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }

    // Check if user is admin
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        error_log("User is not an admin. User type: " . $_SESSION['user_type']);
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized']);
        exit;
    }

    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Test the connection
    if (!$conn) {
        error_log("Database connection failed");
        throw new Exception("Database connection failed");
    }
    
    // Log the SQL query for debugging
    $sql = "
        SELECT 
            lv.lawyer_id,
            u.full_name,
            u.email,
            lv.specialization,
            lv.submitted_at,
            lv.status
        FROM lawyer_verifications lv
        JOIN users u ON lv.lawyer_id = u.id
        WHERE lv.status = 'pending'
    ";
    error_log("Executing SQL query: " . $sql);
    
    // Execute query
    $result = $conn->query($sql);
    
    if ($result === false) {
        error_log("Query failed: " . print_r($conn->errorInfo(), true));
        throw new Exception("Query failed: " . implode(" ", $conn->errorInfo()));
    }
    
    // Fetch results
    $verifications = $result->fetchAll(PDO::FETCH_ASSOC);
    error_log("Query results: " . print_r($verifications, true));
    
    // Return results
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'verifications' => $verifications
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    error_log("Error info: " . print_r($e->errorInfo, true));
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?> 