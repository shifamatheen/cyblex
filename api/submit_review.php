<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate input
if (!isset($_POST['consultation_id']) || !isset($_POST['rating']) || !isset($_POST['comment'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Validate rating
if (!is_numeric($_POST['rating']) || $_POST['rating'] < 1 || $_POST['rating'] > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating value']);
    exit();
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Start transaction
    $conn->beginTransaction();

    // Verify consultation exists and belongs to user
    $stmt = $conn->prepare("
        SELECT c.*, l.id as lawyer_id 
        FROM consultations c
        JOIN lawyers l ON c.lawyer_id = l.id
        WHERE c.id = ? AND c.client_id = ? AND c.status = 'completed'
    ");
    $stmt->execute([$_POST['consultation_id'], $_SESSION['user_id']]);
    $consultation = $stmt->fetch();

    if (!$consultation) {
        echo json_encode(['success' => false, 'message' => 'Invalid consultation or unauthorized access']);
        exit();
    }

    // Check if review already exists
    $stmt = $conn->prepare("
        SELECT id FROM reviews 
        WHERE consultation_id = ?
    ");
    $stmt->execute([$_POST['consultation_id']]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Review already submitted for this consultation']);
        exit();
    }

    // Insert review
    $stmt = $conn->prepare("
        INSERT INTO reviews (consultation_id, client_id, lawyer_id, rating, comment, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $_POST['consultation_id'],
        $_SESSION['user_id'],
        $consultation['lawyer_id'],
        $_POST['rating'],
        $_POST['comment']
    ]);

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 