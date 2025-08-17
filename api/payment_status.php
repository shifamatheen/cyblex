<?php
/**
 * Payment Status Check Endpoint
 * 
 * Allows clients to check the status of their payments
 */

session_start();
require_once '../config/database.php';
require_once '../config/payment_config.php';
require_once 'payment_handler.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

try {
    // Get request parameters
    $orderId = $_GET['order_id'] ?? $_POST['order_id'] ?? null;
    $queryId = $_GET['query_id'] ?? $_POST['query_id'] ?? null;
    
    if (!$orderId && !$queryId) {
        echo json_encode(['success' => false, 'message' => 'Order ID or Query ID is required']);
        exit();
    }
    
    // Initialize payment handler
    $paymentHandler = new PayHerePaymentHandler();
    
    if ($orderId) {
        // Check payment status by order ID
        $result = $paymentHandler->getPaymentStatus($orderId);
    } else {
        // Get payment status by query ID
        $result = getPaymentStatusByQueryId($queryId, $_SESSION['user_id']);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    logPayHerePayment('Payment status check error', ['error' => $e->getMessage()]);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to check payment status'
    ]);
}

/**
 * Get payment status by query ID
 * 
 * @param int $queryId
 * @param int $userId
 * @return array
 */
function getPaymentStatusByQueryId($queryId, $userId) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        // Get the latest payment for this query
        $stmt = $conn->prepare("
            SELECT p.*, lq.title as query_title, lq.payment_status as query_payment_status
            FROM payments p
            JOIN legal_queries lq ON p.consultation_id = lq.id
            WHERE lq.id = ? AND lq.client_id = ?
            ORDER BY p.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$queryId, $userId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }
        
        return [
            'success' => true,
            'payment' => $payment,
            'status_description' => getPaymentStatusDescription($payment['payment_status']),
            'query_title' => $payment['query_title'],
            'query_payment_status' => $payment['query_payment_status']
        ];
        
    } catch (Exception $e) {
        logPayHerePayment('Get payment status by query ID error', ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => 'Failed to get payment status'];
    }
}
?>
