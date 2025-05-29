<?php
// Start output buffering
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type
header('Content-Type: application/json');

try {
    // Test database connection
    require_once '../config/database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Test basic query
    $stmt = $conn->query("SELECT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // If we get here, connection is working
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'result' => $result
    ]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} 