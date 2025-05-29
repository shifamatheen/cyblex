<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a lawyer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lawyer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Query ID is required']);
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $stmt = $conn->prepare("
        SELECT lq.*, u.full_name as client_name, u.email as client_email
        FROM legal_queries lq
        JOIN users u ON lq.client_id = u.id
        WHERE lq.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $query = $stmt->fetch();

    if ($query) {
        echo json_encode([
            'success' => true,
            'query' => $query
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Query not found'
        ]);
    }
} catch (PDOException $e) {
    error_log("Error in get_query_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} 