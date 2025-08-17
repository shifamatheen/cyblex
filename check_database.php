<?php
/**
 * Database Schema Check
 * 
 * This script checks if the required database columns exist for payment processing
 */

require_once 'config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "Database Schema Check\n";
    echo "====================\n\n";
    
    // Check payments table structure
    echo "Checking payments table:\n";
    $stmt = $conn->query("DESCRIBE payments");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredColumns = [
        'id' => false,
        'consultation_id' => false,
        'amount' => false,
        'payment_status' => false,
        'payment_method' => false,
        'transaction_id' => false,
        'payhere_payment_id' => false,
        'created_at' => false,
        'updated_at' => false
    ];
    
    foreach ($columns as $column) {
        $columnName = $column['Field'];
        if (isset($requiredColumns[$columnName])) {
            $requiredColumns[$columnName] = true;
            echo "✅ " . $columnName . " - " . $column['Type'] . "\n";
        }
    }
    
    echo "\nMissing columns:\n";
    $missingColumns = [];
    foreach ($requiredColumns as $column => $exists) {
        if (!$exists) {
            echo "❌ " . $column . "\n";
            $missingColumns[] = $column;
        }
    }
    
    // Check legal_queries table for payment_status column
    echo "\nChecking legal_queries table:\n";
    $stmt = $conn->query("DESCRIBE legal_queries");
    $queryColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasPaymentStatus = false;
    foreach ($queryColumns as $column) {
        if ($column['Field'] === 'payment_status') {
            $hasPaymentStatus = true;
            echo "✅ payment_status - " . $column['Type'] . "\n";
            break;
        }
    }
    
    if (!$hasPaymentStatus) {
        echo "❌ payment_status column missing from legal_queries table\n";
        $missingColumns[] = 'legal_queries.payment_status';
    }
    
    // Check specific payment record
    echo "\nChecking payment record:\n";
    $orderId = 'CYB_5_1755459501_3631';
    $stmt = $conn->prepare("SELECT * FROM payments WHERE transaction_id = ?");
    $stmt->execute([$orderId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment) {
        echo "✅ Payment record found\n";
        echo "   ID: " . $payment['id'] . "\n";
        echo "   Status: " . $payment['payment_status'] . "\n";
        echo "   Amount: " . $payment['amount'] . "\n";
        echo "   Created: " . $payment['created_at'] . "\n";
        if (isset($payment['updated_at'])) {
            echo "   Updated: " . $payment['updated_at'] . "\n";
        }
        if (isset($payment['payhere_payment_id'])) {
            echo "   PayHere ID: " . $payment['payhere_payment_id'] . "\n";
        }
    } else {
        echo "❌ Payment record not found for order ID: " . $orderId . "\n";
    }
    
    // Provide recommendations
    echo "\nRecommendations:\n";
    if (!empty($missingColumns)) {
        echo "1. Run the database schema updates from database/payment_schema_updates.sql\n";
        echo "2. The following columns need to be added:\n";
        foreach ($missingColumns as $column) {
            echo "   - " . $column . "\n";
        }
    } else {
        echo "✅ Database schema looks good!\n";
    }
    
    echo "\n2. Test the payment status update manually using the debug mode\n";
    echo "   URL: http://localhost/cyblex/payment/return.php?order_id=" . $orderId . "&debug=1\n";
    
    echo "\n3. Check the payment logs for webhook activity\n";
    echo "   Log file: logs/payhere_payments.log\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
