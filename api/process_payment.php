<?php
session_start();
require_once '../config/database.php';
require_once 'payment_handler.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'client') {
    header('Location: /login.html?error=unauthorized');
    exit();
}

// Check if this is a POST request with payment data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $queryId = $_POST['queryId'] ?? null;
    $amount = $_POST['amount'] ?? null;
    
    if (!$queryId || !$amount) {
        header('Location: /client-dashboard.php?error=invalid_payment_data');
        exit();
    }
    
    try {
        // Initialize PayHere payment handler
        $paymentHandler = new PayHerePaymentHandler();
        
        // Initialize payment
        $result = $paymentHandler->initializePayment(
            $queryId,
            $_SESSION['user_id'],
            $amount
        );
        
        if ($result['success']) {
            // Create and submit PayHere form directly
            $paymentData = $result['payment_data'];
            $payhereUrl = getPayHereUrl();
            
            // Output HTML form that auto-submits
            echo '<!DOCTYPE html>
            <html>
            <head>
                <title>Redirecting to Payment Gateway...</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                    .loading { margin: 20px 0; }
                    .spinner { 
                        width: 40px; height: 40px; border: 4px solid #f3f3f3; 
                        border-top: 4px solid #3498db; border-radius: 50%; 
                        animation: spin 1s linear infinite; margin: 0 auto; 
                    }
                    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                </style>
            </head>
            <body>
                <h2>Redirecting to Payment Gateway...</h2>
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Please wait while we redirect you to the secure payment gateway.</p>
                </div>
                <form id="payhere-form" method="POST" action="' . htmlspecialchars($payhereUrl) . '">';
            
            // Add all payment form fields
            foreach ($paymentData as $key => $value) {
                echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
            }
            
            echo '</form>
                <script>
                    // Auto-submit form after 1 second
                    setTimeout(function() {
                        document.getElementById("payhere-form").submit();
                    }, 1000);
                </script>
            </body>
            </html>';
            
        } else {
            header('Location: /client-dashboard.php?error=' . urlencode($result['message']));
            exit();
        }
        
    } catch (Exception $e) {
        error_log("Error in process_payment.php: " . $e->getMessage());
        header('Location: /client-dashboard.php?error=payment_failed');
        exit();
    }
} else {
    // If accessed directly without POST data, redirect to dashboard
    header('Location: /client-dashboard.php');
    exit();
}
