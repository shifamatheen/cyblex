<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a lawyer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lawyer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['queryId'])) {
    echo json_encode(['success' => false, 'message' => 'Query ID is required']);
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Start transaction
    $conn->beginTransaction();

    // Get lawyer ID
    $stmt = $conn->prepare("SELECT id FROM lawyers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $lawyer = $stmt->fetch();

    if (!$lawyer) {
        throw new Exception('Lawyer profile not found');
    }

    // Update query status
    $stmt = $conn->prepare("
        UPDATE legal_queries 
        SET status = 'completed', 
            completed_at = NOW()
        WHERE id = ? 
        AND lawyer_id = ? 
        AND status = 'in_progress'
    ");
    $stmt->execute([$data['queryId'], $lawyer['id']]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Query not found or cannot be completed');
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Query completed successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 