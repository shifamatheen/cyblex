<?php
/**
 * Manual Payment Status Update API
 * 
 * This endpoint allows manual updating of payment status for testing purposes
 * In production, this should be removed or secured with admin authentication
 */

session_start();
require_once '../config/database.php';
require_once 'payment_handler.php';

// Set content type
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // Get POST data
    $orderId = $_POST['order_id'] ?? null;
    $status = $_POST['status'] ?? null;
    
    if (!$orderId || !$status) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit();
    }
    
    // Validate status
    $validStatuses = ['pending', 'success', 'failed', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    // Initialize payment handler
    $paymentHandler = new PayHerePaymentHandler();
    
    // Get current payment
    $paymentResult = $paymentHandler->getPaymentStatus($orderId);
    if (!$paymentResult['success']) {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit();
    }
    
    $payment = $paymentResult['payment'];
    
    // Update payment status directly in database
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        UPDATE payments 
        SET payment_status = ?, 
            updated_at = NOW()
        WHERE transaction_id = ?
    ");
    
    if ($stmt->execute([$status, $orderId])) {
        // Update query payment status if payment is successful
        if ($status === 'success') {
            $stmt = $conn->prepare("
                UPDATE legal_queries 
                SET payment_status = 'completed', 
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$payment['consultation_id']]);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Payment status updated successfully',
            'order_id' => $orderId,
            'new_status' => $status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update payment status']);
    }
    
} catch (Exception $e) {
    error_log("Error in update_payment_status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
