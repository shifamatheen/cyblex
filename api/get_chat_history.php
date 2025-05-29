<?php
require_once 'config.php';
require_once 'auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get consultation ID from request
$consultation_id = isset($_GET['consultation_id']) ? intval($_GET['consultation_id']) : 0;

if (!$consultation_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid consultation ID']);
    exit;
}

try {
    // Get user info
    $user = getCurrentUser();
    
    // Verify user has access to this consultation
    $stmt = $pdo->prepare("
        SELECT * FROM consultations 
        WHERE id = ? AND (client_id = ? OR lawyer_id = ?)
    ");
    $stmt->execute([$consultation_id, $user['id'], $user['id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access to consultation']);
        exit;
    }

    // Get chat history
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            CASE 
                WHEN m.sender_id = c.client_id THEN 'client'
                WHEN m.sender_id = c.lawyer_id THEN 'lawyer'
            END as sender_type,
            CASE 
                WHEN m.sender_id = c.client_id THEN cl.name
                WHEN m.sender_id = c.lawyer_id THEN l.name
            END as sender_name
        FROM messages m
        JOIN consultations c ON m.consultation_id = c.id
        LEFT JOIN clients cl ON c.client_id = cl.id
        LEFT JOIN lawyers l ON c.lawyer_id = l.id
        WHERE m.consultation_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$consultation_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 