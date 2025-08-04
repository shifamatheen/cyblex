<?php
require_once 'cors_config.php';
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    error_log('Submit query - Session check failed. user_id: ' . ($_SESSION['user_id'] ?? 'not set') . ', user_type: ' . ($_SESSION['user_type'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Unauthorized access - please log in again']);
    exit();
}

// Validate input
if (!isset($_POST['title']) || !isset($_POST['category']) || !isset($_POST['description']) || !isset($_POST['urgency_level'])) {
    error_log('Submit query - Missing required fields. POST data: ' . json_encode($_POST));
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Set default language if not provided
$language = isset($_POST['language']) ? $_POST['language'] : 'en';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Start transaction
    $conn->beginTransaction();

    // Insert into legal_queries table
    $stmt = $conn->prepare("
        INSERT INTO legal_queries (client_id, category, title, description, urgency_level, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $_POST['category'],
        $_POST['title'],
        $_POST['description'],
        $_POST['urgency_level']
    ]);

    $queryId = $conn->lastInsertId();

    // Find available lawyer based on category and language
    $stmt = $conn->prepare("
        SELECT l.id 
        FROM lawyers l 
        JOIN users u ON l.user_id = u.id 
        LEFT JOIN lawyer_verifications lv ON l.id = lv.lawyer_id
        WHERE l.specialization = ? 
        AND FIND_IN_SET(?, l.languages) > 0 
        AND (lv.status = 'verified' OR lv.status IS NULL)
        ORDER BY RAND() 
        LIMIT 1
    ");

    $stmt->execute([$_POST['category'], $language]);
    $lawyer = $stmt->fetch();

    if ($lawyer) {
        // Update legal query with lawyer
        $stmt = $conn->prepare("
            UPDATE legal_queries 
            SET lawyer_id = ?,
                status = 'assigned'
            WHERE id = ?
        ");
        $stmt->execute([$lawyer['id'], $queryId]);
        error_log('Submit query - Lawyer assigned: ' . $lawyer['id'] . ' to query: ' . $queryId);
    } else {
        error_log('Submit query - No lawyer found for category: ' . $_POST['category'] . ' and language: ' . $language);
    }

    // Commit transaction
    $conn->commit();

    error_log('Submit query - Success: Query ID ' . $queryId . ' submitted by user ' . $_SESSION['user_id']);
    echo json_encode(['success' => true, 'message' => 'Query submitted successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Submit query - Database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 