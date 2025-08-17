<?php
/**
 * PayHere Payment Webhook Handler
 * 
 * This endpoint receives payment notifications from PayHere
 * and processes them to update payment status in the database.
 */

require_once '../config/database.php';
require_once '../config/payment_config.php';
require_once 'payment_handler.php';

// Set content type to text/plain for webhook responses
header('Content-Type: text/plain');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit();
}

try {
    // Get POST data from PayHere
    $notificationData = $_POST;
    
    // Log the incoming webhook data
    logPayHerePayment('Webhook received', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'headers' => getallheaders(),
        'post_data' => $notificationData,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    // Validate required fields
    $requiredFields = [
        'merchant_id',
        'order_id', 
        'payment_id',
        'payhere_amount',
        'payhere_currency',
        'status_code',
        'md5sig'
    ];
    
    foreach ($requiredFields as $field) {
        if (!isset($notificationData[$field]) || empty($notificationData[$field])) {
            logPayHerePayment('Missing required field', ['field' => $field]);
            http_response_code(400);
            echo 'Missing required field: ' . $field;
            exit();
        }
    }
    
    // Verify merchant ID
    if ($notificationData['merchant_id'] !== PAYHERE_MERCHANT_ID) {
        logPayHerePayment('Invalid merchant ID', [
            'received' => $notificationData['merchant_id'],
            'expected' => PAYHERE_MERCHANT_ID
        ]);
        http_response_code(400);
        echo 'Invalid merchant ID';
        exit();
    }
    
    // Initialize payment handler
    $paymentHandler = new PayHerePaymentHandler();
    
    // Process the payment notification
    $result = $paymentHandler->processPaymentNotification($notificationData);
    
    if ($result['success']) {
        // Return success response to PayHere
        http_response_code(200);
        echo 'OK';
        
        logPayHerePayment('Webhook processed successfully', [
            'order_id' => $notificationData['order_id'],
            'payment_id' => $notificationData['payment_id'],
            'status_code' => $notificationData['status_code']
        ]);
    } else {
        // Log error but still return 200 to prevent PayHere from retrying
        logPayHerePayment('Webhook processing failed', [
            'error' => $result['message'],
            'order_id' => $notificationData['order_id']
        ]);
        
        http_response_code(200);
        echo 'OK'; // Still return OK to prevent retries
    }
    
} catch (Exception $e) {
    // Log the exception
    logPayHerePayment('Webhook exception', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Return 200 to prevent PayHere from retrying
    http_response_code(200);
    echo 'OK';
}

/**
 * Get all HTTP headers
 * 
 * @return array
 */
function getallheaders() {
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
    }
    return $headers;
}
?>
