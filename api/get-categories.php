<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Fetch categories
    $stmt = $conn->query("SELECT name, description FROM legal_query_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return categories
    echo json_encode($categories);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch categories']);
} 