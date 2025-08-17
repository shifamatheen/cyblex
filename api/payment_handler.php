<?php
/**
 * PayHere Payment Handler
 * 
 * Core payment processing functionality for PayHere integration
 */

require_once '../config/database.php';
require_once '../config/payment_config.php';

class PayHerePaymentHandler {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }
    
    /**
     * Initialize a new payment for a legal query
     * 
     * @param int $queryId
     * @param int $clientId
     * @param float $amount
     * @return array
     */
    public function initializePayment($queryId, $clientId, $amount) {
        try {
            // Validate input parameters
            if (!$this->validatePaymentRequest($queryId, $clientId, $amount)) {
                return ['success' => false, 'message' => 'Invalid payment request'];
            }
            
            // Get query details
            $query = $this->getQueryDetails($queryId, $clientId);
            if (!$query) {
                return ['success' => false, 'message' => 'Query not found or access denied'];
            }
            
            // Check if payment is already completed
            if ($query['payment_status'] === 'completed') {
                return ['success' => false, 'message' => 'Payment already completed for this query'];
            }
            
            // Generate unique order ID
            $orderId = $this->generateOrderId($queryId);
            
            // Create payment record
            $paymentId = $this->createPaymentRecord($queryId, $amount, $orderId);
            if (!$paymentId) {
                return ['success' => false, 'message' => 'Failed to create payment record'];
            }
            
            // Generate PayHere payment form data
            $paymentData = $this->generatePaymentFormData($query, $orderId, $amount);
            
            logPayHerePayment('Payment initialized', [
                'query_id' => $queryId,
                'client_id' => $clientId,
                'amount' => $amount,
                'order_id' => $orderId,
                'payment_id' => $paymentId
            ]);
            
            return [
                'success' => true,
                'payment_data' => $paymentData,
                'order_id' => $orderId,
                'payment_id' => $paymentId
            ];
            
        } catch (Exception $e) {
            logPayHerePayment('Payment initialization error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Payment initialization failed'];
        }
    }
    
    /**
     * Process PayHere payment notification
     * 
     * @param array $notificationData
     * @return array
     */
    public function processPaymentNotification($notificationData) {
        try {
            logPayHerePayment('Payment notification received', $notificationData);
            
            // Verify payment notification
            if (!verifyPayHerePayment($notificationData, PAYHERE_MERCHANT_SECRET)) {
                logPayHerePayment('Payment verification failed', $notificationData);
                return ['success' => false, 'message' => 'Payment verification failed'];
            }
            
            // Extract payment details
            $orderId = $notificationData['order_id'];
            $paymentId = $notificationData['payment_id'];
            $amount = $notificationData['payhere_amount'];
            $statusCode = $notificationData['status_code'];
            $method = $notificationData['method'] ?? 'unknown';
            
            // Get payment record
            $payment = $this->getPaymentByOrderId($orderId);
            if (!$payment) {
                logPayHerePayment('Payment record not found', ['order_id' => $orderId]);
                return ['success' => false, 'message' => 'Payment record not found'];
            }
            
            // Update payment status
            $status = $this->updatePaymentStatus($payment['id'], $statusCode, $paymentId, $method);
            if (!$status) {
                return ['success' => false, 'message' => 'Failed to update payment status'];
            }
            
            // Update query payment status if payment successful
            if ($statusCode == '2') { // Success
                $this->updateQueryPaymentStatus($payment['consultation_id'], 'completed');
                logPayHerePayment('Payment completed successfully', [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'amount' => $amount
                ]);
            } else {
                $this->updateQueryPaymentStatus($payment['consultation_id'], 'failed');
                logPayHerePayment('Payment failed', [
                    'order_id' => $orderId,
                    'status_code' => $statusCode
                ]);
            }
            
            return ['success' => true, 'message' => 'Payment notification processed'];
            
        } catch (Exception $e) {
            logPayHerePayment('Payment notification error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Payment notification processing failed'];
        }
    }
    
    /**
     * Get payment status
     * 
     * @param string $orderId
     * @return array
     */
    public function getPaymentStatus($orderId) {
        try {
            $payment = $this->getPaymentByOrderId($orderId);
            if (!$payment) {
                return ['success' => false, 'message' => 'Payment not found'];
            }
            
            // If payment is still pending, try to check with PayHere
            if ($payment['payment_status'] === 'pending') {
                $this->checkPaymentStatusWithPayHere($orderId);
                // Get updated payment status
                $payment = $this->getPaymentByOrderId($orderId);
            }
            
            return [
                'success' => true,
                'payment' => $payment,
                'status_description' => getPaymentStatusDescription($payment['payment_status'])
            ];
            
        } catch (Exception $e) {
            logPayHerePayment('Get payment status error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Failed to get payment status'];
        }
    }
    
    /**
     * Check payment status with PayHere API
     * 
     * @param string $orderId
     * @return bool
     */
    private function checkPaymentStatusWithPayHere($orderId) {
        try {
            // This would typically call PayHere's payment status API
            // For now, we'll just log that we're checking
            logPayHerePayment('Checking payment status with PayHere', ['order_id' => $orderId]);
            
            // In a real implementation, you would:
            // 1. Call PayHere's payment status API
            // 2. Update the payment status based on the response
            // 3. Return true if successful
            
            return true;
        } catch (Exception $e) {
            logPayHerePayment('Error checking payment status with PayHere', ['error' => $e->getMessage()]);
            return false;
        }
    }
    

    
    /**
     * Validate payment request
     * 
     * @param int $queryId
     * @param int $clientId
     * @param float $amount
     * @return bool
     */
    private function validatePaymentRequest($queryId, $clientId, $amount) {
        return $queryId > 0 && 
               $clientId > 0 && 
               validatePaymentAmount($amount);
    }
    
    /**
     * Get query details
     * 
     * @param int $queryId
     * @param int $clientId
     * @return array|false
     */
    private function getQueryDetails($queryId, $clientId) {
        $stmt = $this->conn->prepare("
            SELECT lq.*, u.full_name, u.email
            FROM legal_queries lq
            JOIN users u ON lq.client_id = u.id
            WHERE lq.id = ? AND lq.client_id = ?
        ");
        $stmt->execute([$queryId, $clientId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate unique order ID
     * 
     * @param int $queryId
     * @return string
     */
    private function generateOrderId($queryId) {
        return 'CYB_' . $queryId . '_' . time() . '_' . rand(1000, 9999);
    }
    
    /**
     * Create payment record
     * 
     * @param int $queryId
     * @param float $amount
     * @param string $orderId
     * @return int|false
     */
    private function createPaymentRecord($queryId, $amount, $orderId) {
        $stmt = $this->conn->prepare("
            INSERT INTO payments (
                consultation_id, 
                amount, 
                payment_status, 
                payment_method, 
                transaction_id,
                created_at
            ) VALUES (?, ?, 'pending', 'payhere', ?, NOW())
        ");
        
        if ($stmt->execute([$queryId, $amount, $orderId])) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Generate PayHere payment form data
     * 
     * @param array $query
     * @param string $orderId
     * @param float $amount
     * @return array
     */
    private function generatePaymentFormData($query, $orderId, $amount) {
        $hash = generatePayHereHash(
            PAYHERE_MERCHANT_ID,
            $orderId,
            $amount,
            PAYHERE_CURRENCY,
            PAYHERE_MERCHANT_SECRET
        );
        
        // Split full_name into first_name and last_name
        $nameParts = explode(' ', $query['full_name'], 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        
        return [
            'merchant_id' => PAYHERE_MERCHANT_ID,
            'return_url' => PAYHERE_RETURN_URL,
            'cancel_url' => PAYHERE_CANCEL_URL,
            'notify_url' => PAYHERE_NOTIFY_URL,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $query['email'],
            'phone' => '0771234567', // Default phone number since it's not in users table
            'address' => 'Legal Consultation',
            'city' => 'Colombo',
            'country' => 'Sri Lanka',
            'order_id' => $orderId,
            'items' => 'Legal Consultation - ' . $query['title'],
            'currency' => PAYHERE_CURRENCY,
            'amount' => number_format($amount, 2, '.', ''),
            'hash' => $hash,
            'custom_1' => $query['id'], // Query ID
            'custom_2' => $query['client_id'] // Client ID
        ];
    }
    
    /**
     * Get payment by order ID
     * 
     * @param string $orderId
     * @return array|false
     */
    private function getPaymentByOrderId($orderId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM payments 
            WHERE transaction_id = ?
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get payment by ID
     * 
     * @param int $paymentId
     * @return array|false
     */
    private function getPaymentById($paymentId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM payments 
            WHERE id = ?
        ");
        $stmt->execute([$paymentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update payment status
     * 
     * @param int $paymentId
     * @param string $statusCode
     * @param string $payherePaymentId
     * @param string $method
     * @return bool
     */
    private function updatePaymentStatus($paymentId, $statusCode, $payherePaymentId, $method) {
        $status = getPaymentStatusDescription($statusCode);
        
        $stmt = $this->conn->prepare("
            UPDATE payments 
            SET payment_status = ?, 
                payhere_payment_id = ?,
                payment_method = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$status, $payherePaymentId, $method, $paymentId]);
    }
    
    /**
     * Update query payment status
     * 
     * @param int $queryId
     * @param string $status
     * @return bool
     */
    private function updateQueryPaymentStatus($queryId, $status) {
        $stmt = $this->conn->prepare("
            UPDATE legal_queries 
            SET payment_status = ?, 
                updated_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$status, $queryId]);
    }
}
?>
