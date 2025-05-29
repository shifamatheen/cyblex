<?php
require_once '../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if users table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        // Create users table
        $conn->exec("
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                full_name VARCHAR(100) NOT NULL,
                user_type ENUM('client', 'lawyer', 'admin') NOT NULL,
                status ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        echo "Users table created successfully\n";
    }
    
    // Check if lawyer user exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = 2");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user) {
        // Insert lawyer user
        $stmt = $conn->prepare("
            INSERT INTO users (id, username, password, email, full_name, user_type, status)
            VALUES (2, 'shifa', ?, 'shifa@example.com', 'Shifa', 'lawyer', 'active')
        ");
        $stmt->execute([password_hash('password123', PASSWORD_DEFAULT)]);
        echo "Lawyer user created successfully\n";
    } else {
        echo "Lawyer user exists:\n";
        print_r($user);
    }
    
    // Check if lawyers table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'lawyers'");
    if ($stmt->rowCount() == 0) {
        // Create lawyers table
        $conn->exec("
            CREATE TABLE lawyers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                specialization VARCHAR(100) NOT NULL,
                experience_years INT NOT NULL DEFAULT 0,
                bar_council_number VARCHAR(50) NOT NULL,
                hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                languages VARCHAR(100) NOT NULL DEFAULT 'en',
                bio TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        echo "Lawyers table created successfully\n";
    }
    
    // Check if lawyer record exists
    $stmt = $conn->prepare("SELECT * FROM lawyers WHERE user_id = 2");
    $stmt->execute();
    $lawyer = $stmt->fetch();
    
    if (!$lawyer) {
        // Insert lawyer record
        $stmt = $conn->prepare("
            INSERT INTO lawyers (user_id, specialization, experience_years, bar_council_number, hourly_rate)
            VALUES (2, 'General', 5, 'BC123456', 100.00)
        ");
        $stmt->execute();
        echo "Lawyer record created successfully\n";
    } else {
        echo "Lawyer record exists:\n";
        print_r($lawyer);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 