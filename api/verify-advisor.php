<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['lawyer_id']) || !isset($data['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

$lawyer_id = $data['lawyer_id'];
$action = $data['action'];

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Start transaction
    $conn->beginTransaction();

    if ($action === 'approve') {
        // Update lawyer verification status in lawyers table
        $stmt = $conn->prepare("UPDATE lawyers SET verification_status = 'verified', updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$lawyer_id]);

        // Update user status
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND user_type = 'lawyer'");
        $stmt->execute([$lawyer_id]);

        // Log the verification
        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, target_id, details) VALUES (?, 'verify_advisor', ?, 'Advisor verification approved')");
        $stmt->execute([$_SESSION['user_id'], $lawyer_id]);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Advisor verification approved successfully']);
    } else if ($action === 'reject') {
        // Update lawyer verification status in lawyers table
        $stmt = $conn->prepare("UPDATE lawyers SET verification_status = 'rejected', updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$lawyer_id]);

        // Log the rejection
        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, target_id, details) VALUES (?, 'reject_advisor', ?, 'Advisor verification rejected')");
        $stmt->execute([$_SESSION['user_id'], $lawyer_id]);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Advisor verification rejected']);
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to process verification: ' . $e->getMessage()]);
}
?> 