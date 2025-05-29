<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cyblex');
define('JWT_SECRET', 'cyblex-secure-jwt-secret-key-2024');

class Database {
    private static $instance = null;
    private $conn;
    
    private $host = 'localhost';
    private $db_name = 'cyblex';
    private $username = 'root';
    private $password = '';

    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                )
            );
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            throw $e;
        }
    }
}

// Initialize database and create tables
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        user_type ENUM('client', 'lawyer', 'admin') NOT NULL,
        status ENUM('pending', 'active', 'suspended') DEFAULT 'pending',
        language_preference ENUM('en', 'ta', 'si') DEFAULT 'en',
        profile_image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    
    // Add status column if it doesn't exist
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
        if ($stmt->rowCount() == 0) {
            $conn->exec("ALTER TABLE users ADD COLUMN status ENUM('pending', 'active', 'suspended') DEFAULT 'pending' AFTER user_type");
        }
    } catch (PDOException $e) {
        error_log("Error adding status column: " . $e->getMessage());
    }
    
    // Create lawyers table
    $sql = "CREATE TABLE IF NOT EXISTS lawyers (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        specialization VARCHAR(100) NOT NULL,
        experience_years INT(11) NOT NULL,
        bar_council_number VARCHAR(50) NOT NULL,
        verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        hourly_rate DECIMAL(10,2) NOT NULL,
        languages VARCHAR(100) NOT NULL,
        bio TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);
    
    // Create legal_queries table
    $sql = "CREATE TABLE IF NOT EXISTS legal_queries (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        client_id INT(11) NOT NULL,
        lawyer_id INT(11) NULL,
        category VARCHAR(100) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        urgency_level ENUM('low', 'medium', 'high') NOT NULL,
        status ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (lawyer_id) REFERENCES lawyers(id) ON DELETE SET NULL
    )";
    $conn->exec($sql);
    
    // Add lawyer_id column if it doesn't exist
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM legal_queries LIKE 'lawyer_id'");
        if ($stmt->rowCount() == 0) {
            $conn->exec("ALTER TABLE legal_queries ADD COLUMN lawyer_id INT(11) NULL AFTER client_id");
            $conn->exec("ALTER TABLE legal_queries ADD FOREIGN KEY (lawyer_id) REFERENCES lawyers(id) ON DELETE SET NULL");
        }
    } catch (PDOException $e) {
        error_log("Error adding lawyer_id column: " . $e->getMessage());
    }
    
    // Create messages table
    $sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        legal_query_id INT(11) NOT NULL,
        sender_id INT(11) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (legal_query_id) REFERENCES legal_queries(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_legal_query_id (legal_query_id),
        INDEX idx_sender_id (sender_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->exec($sql);
    
    // Add is_read column if it doesn't exist
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM messages LIKE 'is_read'");
        if ($stmt->rowCount() == 0) {
            $conn->exec("ALTER TABLE messages ADD COLUMN is_read BOOLEAN DEFAULT FALSE AFTER message");
        }
    } catch (PDOException $e) {
        error_log("Error adding is_read column: " . $e->getMessage());
    }
    
    // Create consultations table
    $sql = "CREATE TABLE IF NOT EXISTS consultations (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        client_id INT(11) NOT NULL,
        lawyer_id INT(11) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        status ENUM('pending', 'active', 'completed', 'cancelled') DEFAULT 'pending',
        start_time DATETIME,
        end_time DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (lawyer_id) REFERENCES lawyers(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);
    
    // Create reviews table
    $sql = "CREATE TABLE IF NOT EXISTS reviews (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        consultation_id INT(11) NOT NULL,
        client_id INT(11) NOT NULL,
        lawyer_id INT(11) NOT NULL,
        rating INT(11) NOT NULL CHECK (rating >= 1 AND rating <= 5),
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
        FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (lawyer_id) REFERENCES lawyers(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);
    
    // Create payments table
    $sql = "CREATE TABLE IF NOT EXISTS payments (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        consultation_id INT(11) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
        payment_method VARCHAR(50) NOT NULL,
        transaction_id VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);
    
    // Create lawyer_verifications table
    $sql = "CREATE TABLE IF NOT EXISTS lawyer_verifications (
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
    
    // Create admin_logs table
    $sql = "CREATE TABLE IF NOT EXISTS admin_logs (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        admin_id INT(11) NOT NULL,
        action VARCHAR(50) NOT NULL,
        target_id INT(11) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);
    
    // Create ratings table
    $sql = "CREATE TABLE IF NOT EXISTS ratings (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        query_id INT(11) NOT NULL,
        client_id INT(11) NOT NULL,
        lawyer_id INT(11) NOT NULL,
        rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
        review TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (query_id) REFERENCES legal_queries(id) ON DELETE CASCADE,
        FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_query_rating (query_id),
        INDEX idx_lawyer_id (lawyer_id),
        INDEX idx_client_id (client_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->exec($sql);
    
} catch(PDOException $e) {
    error_log("Database initialization error: " . $e->getMessage());
    die("Database initialization failed: " . $e->getMessage());
}
?> 