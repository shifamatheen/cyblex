<?php
// Start output buffering
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type
header('Content-Type: application/json');

try {
    require_once '../config/database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // First, check if lawyer_verifications table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'lawyer_verifications'");
    if ($tableCheck->rowCount() == 0) {
        // Create lawyer_verifications table if it doesn't exist
        $sql = "CREATE TABLE IF NOT EXISTS lawyer_verifications (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            lawyer_id INT(11) NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            documents TEXT,
            verification_notes TEXT,
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_at TIMESTAMP NULL DEFAULT NULL,
            reviewed_by INT(11),
            FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        )";
        $conn->exec($sql);
        echo json_encode(['message' => 'Lawyer verifications table created successfully']);
    } else {
        // Update existing table structure
        $updates = [];

        // First, add any missing columns
        $columns = [
            'status' => "ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'",
            'submitted_at' => "ADD COLUMN submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            'reviewed_at' => "ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL",
            'reviewed_by' => "ADD COLUMN reviewed_by INT(11) NULL"
        ];

        foreach ($columns as $column => $sql) {
            $columnCheck = $conn->query("SHOW COLUMNS FROM lawyer_verifications LIKE '$column'");
            if ($columnCheck->rowCount() == 0) {
                $updates[] = $sql;
            }
        }

        // Apply column updates if any
        if (!empty($updates)) {
            $sql = "ALTER TABLE lawyer_verifications " . implode(", ", $updates);
            $conn->exec($sql);
            echo json_encode(['message' => 'Columns added successfully']);
        }

        // Now check and add foreign keys
        $updates = [];

        // Check and add lawyer_id foreign key if it doesn't exist
        $fkCheck = $conn->query("
            SELECT COUNT(*) as count 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'lawyer_verifications' 
            AND COLUMN_NAME = 'lawyer_id' 
            AND REFERENCED_TABLE_NAME = 'users'
        ");
        if ($fkCheck->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
            $updates[] = "ADD FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE CASCADE";
        }

        // Check and add reviewed_by foreign key if it doesn't exist
        $fkCheck = $conn->query("
            SELECT COUNT(*) as count 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'lawyer_verifications' 
            AND COLUMN_NAME = 'reviewed_by' 
            AND REFERENCED_TABLE_NAME = 'users'
        ");
        if ($fkCheck->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
            $updates[] = "ADD FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL";
        }

        // Apply foreign key updates if any
        if (!empty($updates)) {
            $sql = "ALTER TABLE lawyer_verifications " . implode(", ", $updates);
            $conn->exec($sql);
            echo json_encode(['message' => 'Foreign keys added successfully']);
        }
    }

    // Verify the table structure
    $columns = $conn->query("SHOW COLUMNS FROM lawyer_verifications")->fetchAll(PDO::FETCH_ASSOC);
    $foreignKeys = $conn->query("
        SELECT 
            COLUMN_NAME, 
            REFERENCED_TABLE_NAME, 
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'lawyer_verifications'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'message' => 'Table structure verification complete',
        'columns' => $columns,
        'foreign_keys' => $foreignKeys
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error occurred',
        'message' => $e->getMessage()
    ]);
} 