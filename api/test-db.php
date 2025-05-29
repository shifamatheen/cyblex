<?php
require_once '../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<h2>Database Connection Test</h2>";
    echo "Connection successful!<br><br>";
    
    // Check if tables exist
    $tables = ['users', 'lawyer_verifications', 'admin_logs'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        echo "Table '$table' exists: " . ($result->rowCount() > 0 ? 'Yes' : 'No') . "<br>";
    }
    
    // Check users table structure
    echo "<br><h3>Users Table Structure:</h3>";
    $result = $conn->query("DESCRIBE users");
    echo "<pre>";
    print_r($result->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
    
    // Check lawyer_verifications table structure
    echo "<br><h3>Lawyer Verifications Table Structure:</h3>";
    $result = $conn->query("DESCRIBE lawyer_verifications");
    echo "<pre>";
    print_r($result->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
    
    // Check if we have any admin users
    echo "<br><h3>Admin Users:</h3>";
    $result = $conn->query("SELECT id, username, email, user_type FROM users WHERE user_type = 'admin'");
    $admins = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($admins);
    echo "</pre>";
    
    // If no admin exists, create one
    if (empty($admins)) {
        echo "<br>Creating admin user...<br>";
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password, full_name, user_type, status) 
                VALUES ('admin', 'admin@cyblex.com', ?, 'System Admin', 'admin', 'active')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$password]);
        echo "Admin user created successfully!<br>";
    }
    
    // Check pending verifications
    echo "<br><h3>Pending Verifications:</h3>";
    $result = $conn->query("
        SELECT 
            lv.lawyer_id,
            u.full_name,
            u.email,
            lv.specialization,
            lv.submitted_at,
            lv.status
        FROM lawyer_verifications lv
        JOIN users u ON lv.lawyer_id = u.id
        WHERE lv.status = 'pending'
    ");
    $verifications = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($verifications);
    echo "</pre>";
    
    // If no verifications exist, create a test one
    if (empty($verifications)) {
        echo "<br>Creating test lawyer and verification...<br>";
        
        // Create test lawyer user
        $password = password_hash('lawyer123', PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password, full_name, user_type, status) 
                VALUES ('lawyer1', 'lawyer1@cyblex.com', ?, 'Test Lawyer', 'lawyer', 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$password]);
        $lawyer_id = $conn->lastInsertId();
        
        // Create test verification
        $sql = "INSERT INTO lawyer_verifications (lawyer_id, specialization, document_path, status) 
                VALUES (?, 'Criminal Law', 'test_document.pdf', 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$lawyer_id]);
        
        echo "Test lawyer and verification created successfully!<br>";
    }
    
} catch (PDOException $e) {
    echo "<h2>Database Error:</h2>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Code: " . $e->getCode() . "<br>";
    echo "Error Info: <pre>" . print_r($e->errorInfo, true) . "</pre>";
} catch (Exception $e) {
    echo "<h2>General Error:</h2>";
    echo "Error: " . $e->getMessage() . "<br>";
}
?> 