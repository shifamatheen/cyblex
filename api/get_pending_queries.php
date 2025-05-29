<?php
session_start();
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a lawyer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lawyer') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get lawyer's specialization
    $stmt = $conn->prepare("
        SELECT specialization 
        FROM lawyers 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $lawyer = $stmt->fetch();

    if (!$lawyer) {
        throw new Exception('Lawyer profile not found');
    }

    // Get pending queries that match the lawyer's specialization
    $stmt = $conn->prepare("
        SELECT 
            lq.id,
            lq.title,
            lq.description,
            lq.category,
            lq.urgency_level,
            lq.status,
            lq.created_at,
            u.full_name as client_name,
            u.language_preference
        FROM legal_queries lq
        JOIN users u ON lq.client_id = u.id
        WHERE lq.status = 'pending'
        AND lq.category = ?
        ORDER BY 
            CASE lq.urgency_level
                WHEN 'high' THEN 1
                WHEN 'medium' THEN 2
                WHEN 'low' THEN 3
            END,
            lq.created_at ASC
    ");
    
    $stmt->execute([$lawyer['specialization']]);
    $queries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates and prepare response
    foreach ($queries as &$query) {
        $query['created_at'] = date('Y-m-d H:i:s', strtotime($query['created_at']));
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'queries' => $queries
    ]);

} catch (PDOException $e) {
    error_log("Database error in get_pending_queries.php: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in get_pending_queries.php: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 