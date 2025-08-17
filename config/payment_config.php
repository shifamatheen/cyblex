<?php
/**
 * PayHere Payment Configuration
 * 
 * This file contains all PayHere payment gateway configuration settings.
 * Environment variables should be set in your .env file or server configuration.
 */

// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/../.env')) {
    $envFile = file_get_contents(__DIR__ . '/../.env');
    $lines = explode("\n", $envFile);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// PayHere Merchant Configuration
$merchantId = $_ENV['PAYHERE_MERCHANT_ID'] ?? getenv('PAYHERE_MERCHANT_ID');
$merchantSecret = $_ENV['PAYHERE_MERCHANT_SECRET'] ?? getenv('PAYHERE_MERCHANT_SECRET');

// Validate required environment variables
if (!$merchantId || !$merchantSecret) {
    error_log('PayHere configuration error: Missing required environment variables PAYHERE_MERCHANT_ID or PAYHERE_MERCHANT_SECRET');
    throw new Exception('PayHere configuration error: Missing required environment variables');
}

define('PAYHERE_MERCHANT_ID', $merchantId);
define('PAYHERE_MERCHANT_SECRET', $merchantSecret);

// PayHere API Endpoints
define('PAYHERE_LIVE_URL', 'https://www.payhere.lk/pay/checkout');
define('PAYHERE_SANDBOX_URL', 'https://sandbox.payhere.lk/pay/checkout');

// Environment Configuration
define('PAYHERE_ENVIRONMENT', 'sandbox'); // Change to 'live' for production

// Get the appropriate PayHere URL based on environment
function getPayHereUrl() {
    return PAYHERE_ENVIRONMENT === 'live' ? PAYHERE_LIVE_URL : PAYHERE_SANDBOX_URL;
}

// Application URLs for PayHere callbacks
define('PAYHERE_RETURN_URL', 'http://localhost/cyblex/payment/return.php');
define('PAYHERE_CANCEL_URL', 'http://localhost/cyblex/client-dashboard.php?error=payment_cancelled');
define('PAYHERE_NOTIFY_URL', 'http://localhost/cyblex/api/payment_webhook.php');

// Currency Configuration
define('PAYHERE_CURRENCY', 'LKR'); // LKR for Sri Lankan Rupees

// Payment Settings
define('PAYHERE_MIN_AMOUNT', 100); // Minimum payment amount in LKR
define('PAYHERE_MAX_AMOUNT', 100000); // Maximum payment amount in LKR

// Security Settings
define('PAYHERE_HASH_ALGORITHM', 'MD5');
define('PAYHERE_TIMEOUT_SECONDS', 300); // 5 minutes timeout for payment sessions

// Error Messages
define('PAYHERE_ERROR_MESSAGES', [
    'INVALID_MERCHANT' => 'Invalid merchant configuration',
    'INVALID_AMOUNT' => 'Invalid payment amount',
    'HASH_MISMATCH' => 'Payment verification failed',
    'PAYMENT_FAILED' => 'Payment processing failed',
    'TIMEOUT' => 'Payment session expired',
    'CANCELLED' => 'Payment was cancelled by user',
    'INVALID_ORDER' => 'Invalid order information'
]);

// Payment Status Codes
define('PAYHERE_STATUS_CODES', [
    '2' => 'success',
    '0' => 'pending', 
    '-1' => 'cancelled',
    '-2' => 'failed',
    '-3' => 'chargedback'
]);

// Logging Configuration
define('PAYHERE_LOG_ENABLED', true);
define('PAYHERE_LOG_FILE', '../logs/payhere_payments.log');

/**
 * Generate PayHere hash for payment verification
 * 
 * @param string $merchantId
 * @param string $orderId
 * @param float $amount
 * @param string $currency
 * @param string $merchantSecret
 * @return string
 */
function generatePayHereHash($merchantId, $orderId, $amount, $currency, $merchantSecret) {
    $amountFormatted = number_format($amount, 2, '.', '');
    $hashedSecret = strtoupper(md5($merchantSecret));
    
    $hash = strtoupper(
        md5(
            $merchantId . 
            $orderId . 
            $amountFormatted . 
            $currency .  
            $hashedSecret 
        ) 
    );
    
    return $hash;
}

/**
 * Verify PayHere payment response
 * 
 * @param array $responseData
 * @param string $merchantSecret
 * @return bool
 */
function verifyPayHerePayment($responseData, $merchantSecret) {
    if (!isset($responseData['merchant_id']) || 
        !isset($responseData['order_id']) || 
        !isset($responseData['payhere_amount']) || 
        !isset($responseData['payhere_currency']) || 
        !isset($responseData['status_code']) || 
        !isset($responseData['md5sig'])) {
        return false;
    }
    
    $localMd5sig = strtoupper(
        md5(
            $responseData['merchant_id'] . 
            $responseData['order_id'] . 
            $responseData['payhere_amount'] . 
            $responseData['payhere_currency'] . 
            $responseData['status_code'] . 
            strtoupper(md5($merchantSecret)) 
        ) 
    );
    
    return $localMd5sig === $responseData['md5sig'];
}

/**
 * Log PayHere payment activities
 * 
 * @param string $message
 * @param array $data
 * @return void
 */
function logPayHerePayment($message, $data = []) {
    if (!PAYHERE_LOG_ENABLED) {
        return;
    }
    
    $logEntry = date('Y-m-d H:i:s') . ' - ' . $message;
    if (!empty($data)) {
        $logEntry .= ' - Data: ' . json_encode($data);
    }
    $logEntry .= PHP_EOL;
    
    $logDir = dirname(PAYHERE_LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents(PAYHERE_LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Validate payment amount
 * 
 * @param float $amount
 * @return bool
 */
function validatePaymentAmount($amount) {
    return $amount >= PAYHERE_MIN_AMOUNT && $amount <= PAYHERE_MAX_AMOUNT;
}

/**
 * Get payment status description
 * 
 * @param string $statusCode
 * @return string
 */
function getPaymentStatusDescription($statusCode) {
    return PAYHERE_STATUS_CODES[$statusCode] ?? 'unknown';
}
?>
