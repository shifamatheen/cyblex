<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['type'])) {
    echo json_encode(['success' => false, 'message' => 'Query ID and type are required']);
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $id = $_GET['id'];
    $type = $_GET['type'];
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];

    if ($type === 'query') {
    $stmt = $conn->prepare("
            SELECT lq.*, 
                   u.full_name as client_name, 
                   u.email as client_email,
                   l.full_name as lawyer_name,
                   lc.name as category_name
        FROM legal_queries lq
        JOIN users u ON lq.client_id = u.id
            LEFT JOIN users l ON lq.lawyer_id = l.id
            LEFT JOIN legal_query_categories lc ON lq.category = lc.name
            WHERE lq.id = ? AND (lq.client_id = ? OR lq.lawyer_id = ? OR ? = 'admin')
    ");
        $stmt->execute([$id, $user_id, $user_id, $user_type]);
    } else if ($type === 'consultation') {
        $stmt = $conn->prepare("
            SELECT c.*, 
                   u1.full_name as client_name, 
                   u2.full_name as lawyer_name,
                   lc.name as category_name
            FROM consultations c
            JOIN users u1 ON c.client_id = u1.id
            JOIN users u2 ON c.lawyer_id = u2.id
            LEFT JOIN legal_query_categories lc ON c.category = lc.name
            WHERE c.id = ? AND (c.client_id = ? OR c.lawyer_id = ? OR ? = 'admin')
        ");
        $stmt->execute([$id, $user_id, $user_id, $user_type]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid type specified']);
        exit();
    }

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Record not found or unauthorized access'
        ]);
    }
} catch (PDOException $e) {
    error_log("Error in get_query_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} 