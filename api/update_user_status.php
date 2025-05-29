<?php
require_once '../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Update lawyer user status to active
    $stmt = $conn->prepare("
        UPDATE users 
        SET status = 'active' 
        WHERE id = 2 AND user_type = 'lawyer'
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "Lawyer user status updated to active successfully\n";
        
        // Verify the update
        $stmt = $conn->prepare("SELECT id, username, status FROM users WHERE id = 2");
        $stmt->execute();
        $user = $stmt->fetch();
        echo "Updated user details:\n";
        print_r($user);
    } else {
        echo "No changes made. User might not exist or already be active.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 