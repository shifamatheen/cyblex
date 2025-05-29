<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get all queries with client and lawyer details
    $query = "SELECT q.id, q.title, q.description, q.category, q.status, q.created_at,
                     u.full_name as client_name,
                     l.full_name as lawyer_name,
                     l.specialization as lawyer_specialization
              FROM queries q
              JOIN users u ON q.client_id = u.id
              LEFT JOIN lawyers l ON q.lawyer_id = l.user_id
              ORDER BY q.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $queries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates and add additional information
    foreach ($queries as &$query) {
        $query['created_at'] = date('Y-m-d H:i:s', strtotime($query['created_at']));
        
        // Get message count
        $msgQuery = "SELECT COUNT(*) as message_count FROM chat_messages WHERE query_id = ?";
        $msgStmt = $conn->prepare($msgQuery);
        $msgStmt->execute([$query['id']]);
        $query['message_count'] = $msgStmt->fetch(PDO::FETCH_ASSOC)['message_count'];

        // Get attachment count
        $attQuery = "SELECT COUNT(*) as attachment_count FROM attachments WHERE query_id = ?";
        $attStmt = $conn->prepare($attQuery);
        $attStmt->execute([$query['id']]);
        $query['attachment_count'] = $attStmt->fetch(PDO::FETCH_ASSOC)['attachment_count'];
    }

    echo json_encode([
        'success' => true,
        'queries' => $queries
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching queries'
    ]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred'
    ]);
} 