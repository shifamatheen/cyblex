<?php
require_once '../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<h2>Fixing Database Tables</h2>";
    
    // Drop and recreate lawyer_verifications table with correct structure
    echo "Recreating lawyer_verifications table...<br>";
    
    // First drop the table if it exists
    $conn->exec("DROP TABLE IF EXISTS lawyer_verifications");
    
    // Create the table with correct structure
    $sql = "CREATE TABLE lawyer_verifications (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        lawyer_id INT(11) NOT NULL,
        specialization VARCHAR(100) NOT NULL,
        document_path VARCHAR(255) NOT NULL,
        status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        admin_notes TEXT,
        verified_by INT(11),
        verified_at TIMESTAMP NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
    )";
    $conn->exec($sql);
    echo "lawyer_verifications table created successfully!<br><br>";
    
    // Create test data
    echo "Creating test data...<br>";
    
    // Check if we have any lawyer users
    $result = $conn->query("SELECT id FROM users WHERE user_type = 'lawyer'");
    $lawyers = $result->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($lawyers)) {
        // Create test lawyer user
        $password = password_hash('lawyer123', PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password, full_name, user_type, status) 
                VALUES ('lawyer1', 'lawyer1@cyblex.com', ?, 'Test Lawyer', 'lawyer', 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$password]);
        $lawyer_id = $conn->lastInsertId();
        echo "Test lawyer user created with ID: $lawyer_id<br>";
    } else {
        $lawyer_id = $lawyers[0]['id'];
    }
    
    // Create test verification
    $sql = "INSERT INTO lawyer_verifications (lawyer_id, specialization, document_path, status) 
            VALUES (?, 'Criminal Law', 'test_document.pdf', 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$lawyer_id]);
    echo "Test verification created successfully!<br><br>";
    
    // Verify the data
    echo "<h3>Verifying Data:</h3>";
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
    
    echo "<br>Database tables have been fixed successfully!";
    
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