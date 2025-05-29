<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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

    // Get user type and ID
    $userType = $_SESSION['user_type'];
    $userId = $_SESSION['user_id'];

    // Verify user has access to this query
    $stmt = $conn->prepare("
        SELECT lq.*, l.user_id as lawyer_user_id
        FROM legal_queries lq
        LEFT JOIN lawyers l ON lq.lawyer_id = l.id
        WHERE lq.id = ? AND (
            (lq.client_id = ? AND ? = 'client') OR
            (l.user_id = ? AND ? = 'lawyer')
        )
    ");
    $stmt->execute([$data['queryId'], $userId, $userType, $userId, $userType]);
    $query = $stmt->fetch();

    if (!$query) {
        throw new Exception('Query not found or unauthorized access');
    }

    // Update query status to in_progress if it's assigned
    if ($query['status'] === 'assigned') {
        $stmt = $conn->prepare("
            UPDATE legal_queries 
            SET status = 'in_progress'
            WHERE id = ? AND status = 'assigned'
        ");
        $stmt->execute([$data['queryId']]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Failed to update query status');
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Chat started successfully'
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