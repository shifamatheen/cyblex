<?php
/**
 * Payment Return Page
 * 
 * This page is shown to users after they complete payment on PayHere
 */

session_start();
require_once '../config/database.php';
require_once '../config/payment_config.php';
require_once '../api/payment_handler.php';

// Get order ID from URL parameters
$orderId = $_GET['order_id'] ?? null;
$status = $_GET['status'] ?? null;

// If no order ID, redirect to dashboard
if (!$orderId) {
    header('Location: ../client-dashboard.php');
    exit();
}

// Get payment status
$paymentHandler = new PayHerePaymentHandler();
$paymentResult = $paymentHandler->getPaymentStatus($orderId);

$payment = $paymentResult['payment'] ?? null;
$isSuccess = $payment && $payment['payment_status'] === 'success';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Result - Cyblex</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .payment-result {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .payment-success {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
        }
        
        .payment-failed {
            background: linear-gradient(135deg, #f44336, #da190b);
            color: white;
        }
        
        .payment-pending {
            background: linear-gradient(135deg, #ff9800, #e68900);
            color: white;
        }
        
        .payment-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .payment-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .payment-message {
            font-size: 16px;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        
        .payment-details {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        
        .payment-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .payment-detail:last-child {
            border-bottom: none;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .btn-primary {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body>
    <div class="payment-result <?php echo $isSuccess ? 'payment-success' : ($payment && $payment['payment_status'] === 'pending' ? 'payment-pending' : 'payment-failed'); ?>">
        <?php if ($isSuccess): ?>
            <div class="payment-icon">✅</div>
            <div class="payment-title">Payment Successful!</div>
            <div class="payment-message">
                Your payment has been processed successfully. Your legal consultation is now active.
            </div>
        <?php elseif ($payment && $payment['payment_status'] === 'pending'): ?>
            <div class="payment-icon">⏳</div>
            <div class="payment-title">Payment Pending</div>
            <div class="payment-message">
                Your payment is being processed. Please wait while we confirm your payment.
            </div>
        <?php else: ?>
            <div class="payment-icon">❌</div>
            <div class="payment-title">Payment Failed</div>
            <div class="payment-message">
                Unfortunately, your payment could not be processed. Please try again or contact support.
            </div>
        <?php endif; ?>
        
        <?php if ($payment): ?>
            <div class="payment-details">
                <div class="payment-detail">
                    <span>Order ID:</span>
                    <span><?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                </div>
                <div class="payment-detail">
                    <span>Amount:</span>
                    <span>LKR <?php echo number_format($payment['amount'], 2); ?></span>
                </div>
                <div class="payment-detail">
                    <span>Status:</span>
                    <span><?php echo ucfirst($payment['payment_status']); ?></span>
                </div>
                <div class="payment-detail">
                    <span>Date:</span>
                    <span><?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="payment-actions">
            <a href="../client-dashboard.php" class="btn btn-primary">Go to Dashboard</a>
            <?php if (!$isSuccess): ?>
                <a href="../client-dashboard.php?retry_payment=<?php echo $orderId; ?>" class="btn">Try Again</a>
            <?php endif; ?>
            <a href="../index.php" class="btn">Back to Home</a>
            
            <!-- Debug section for testing -->
            <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
                <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 8px;">
                    <h4>Debug Information</h4>
                    <p><strong>Order ID:</strong> <?php echo htmlspecialchars($orderId); ?></p>
                    <p><strong>Current Status:</strong> <?php echo $payment ? htmlspecialchars($payment['payment_status']) : 'Unknown'; ?></p>
                    <p><strong>Payment ID:</strong> <?php echo $payment ? htmlspecialchars($payment['id']) : 'Unknown'; ?></p>
                    
                    <div style="margin-top: 15px;">
                        <button onclick="updatePaymentStatus('success')" class="btn" style="background: #4CAF50;">Mark as Success</button>
                        <button onclick="updatePaymentStatus('failed')" class="btn" style="background: #f44336;">Mark as Failed</button>
                        <button onclick="updatePaymentStatus('pending')" class="btn" style="background: #ff9800;">Mark as Pending</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-refresh for pending payments
        <?php if ($payment && $payment['payment_status'] === 'pending'): ?>
        setTimeout(function() {
            location.reload();
        }, 5000); // Refresh every 5 seconds
        <?php endif; ?>
        
        // Redirect to dashboard after 10 seconds for successful payments
        <?php if ($isSuccess): ?>
        setTimeout(function() {
            window.location.href = '../client-dashboard.php';
        }, 10000);
        <?php endif; ?>
        
        // Debug function to update payment status
        function updatePaymentStatus(status) {
            const orderId = '<?php echo htmlspecialchars($orderId); ?>';
            
            fetch('../api/update_payment_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'order_id=' + encodeURIComponent(orderId) + '&status=' + encodeURIComponent(status)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payment status updated to: ' + status);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating payment status');
            });
        }
    </script>
</body>
</html>
