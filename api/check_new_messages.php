<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate input
if (!isset($_GET['consultation_id']) || !isset($_GET['last_message_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Verify user has access to this consultation
    $stmt = $conn->prepare("
        SELECT * FROM consultations 
        WHERE id = ? AND (client_id = ? OR lawyer_id IN (SELECT id FROM lawyers WHERE user_id = ?))
    ");
    $stmt->execute([$_GET['consultation_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access to this consultation']);
        exit();
    }

    // Get new messages
    $stmt = $conn->prepare("
        SELECT 
            m.*,
            CASE 
                WHEN m.sender_id = ? THEN 'You'
                WHEN u.user_type = 'lawyer' THEN CONCAT('Lawyer: ', u.full_name)
                ELSE u.full_name
            END as sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.consultation_id = ? AND m.id > ?
        ORDER BY m.created_at ASC
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $_GET['consultation_id'],
        $_GET['last_message_id']
    ]);
    
    $messages = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'messages' => array_map(function($msg) {
            return [
                'id' => $msg['id'],
                'sender' => $msg['sender_name'],
                'message' => $msg['message'],
                'time' => $msg['created_at']
            ];
        }, $messages)
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 