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

    // First, check if lawyers table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'lawyers'");
    if ($tableCheck->rowCount() == 0) {
        // Create lawyers table if it doesn't exist
        $sql = "CREATE TABLE IF NOT EXISTS lawyers (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            specialization VARCHAR(255),
            experience_years INT,
            bar_council_number VARCHAR(50),
            hourly_rate DECIMAL(10,2),
            status ENUM('pending', 'active', 'suspended') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $conn->exec($sql);
        echo json_encode(['message' => 'Lawyers table created successfully']);
    } else {
        // Update existing table structure
        $updates = [];

        // First, add any missing columns
        $columns = [
            'user_id' => "ADD COLUMN user_id INT(11) NOT NULL",
            'specialization' => "ADD COLUMN specialization VARCHAR(255)",
            'experience_years' => "ADD COLUMN experience_years INT",
            'bar_council_number' => "ADD COLUMN bar_council_number VARCHAR(50)",
            'hourly_rate' => "ADD COLUMN hourly_rate DECIMAL(10,2)",
            'status' => "ADD COLUMN status ENUM('pending', 'active', 'suspended') DEFAULT 'pending'",
            'created_at' => "ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP"
        ];

        foreach ($columns as $column => $sql) {
            $columnCheck = $conn->query("SHOW COLUMNS FROM lawyers LIKE '$column'");
            if ($columnCheck->rowCount() == 0) {
                $updates[] = $sql;
            }
        }

        // Apply column updates if any
        if (!empty($updates)) {
            $sql = "ALTER TABLE lawyers " . implode(", ", $updates);
            $conn->exec($sql);
            echo json_encode(['message' => 'Columns added successfully']);
        }

        // Now check and add foreign key
        $fkCheck = $conn->query("
            SELECT COUNT(*) as count 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'lawyers' 
            AND COLUMN_NAME = 'user_id' 
            AND REFERENCED_TABLE_NAME = 'users'
        ");
        if ($fkCheck->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
            // First, ensure all existing records have a valid user_id
            $conn->exec("
                UPDATE lawyers l
                INNER JOIN users u ON l.user_id = u.id
                SET l.user_id = u.id
                WHERE l.user_id IS NOT NULL
            ");

            // Then add the foreign key constraint
            $sql = "ALTER TABLE lawyers ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE";
            $conn->exec($sql);
            echo json_encode(['message' => 'Foreign key added successfully']);
        }

        // Update any existing lawyer records to link with users
        $conn->exec("
            UPDATE lawyers l
            INNER JOIN users u ON u.email = (
                SELECT email 
                FROM users 
                WHERE user_type = 'lawyer' 
                AND id NOT IN (SELECT user_id FROM lawyers)
                LIMIT 1
            )
            SET l.user_id = u.id
            WHERE l.user_id IS NULL
        ");
    }

    // Verify the table structure
    $columns = $conn->query("SHOW COLUMNS FROM lawyers")->fetchAll(PDO::FETCH_ASSOC);
    $foreignKeys = $conn->query("
        SELECT 
            COLUMN_NAME, 
            REFERENCED_TABLE_NAME, 
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'lawyers'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get count of unlinked lawyers
    $unlinkedCount = $conn->query("
        SELECT COUNT(*) as count 
        FROM lawyers 
        WHERE user_id IS NULL
    ")->fetch(PDO::FETCH_ASSOC)['count'];

    echo json_encode([
        'message' => 'Table structure verification complete',
        'columns' => $columns,
        'foreign_keys' => $foreignKeys,
        'unlinked_lawyers' => $unlinkedCount
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