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

    // Function to check if table exists
    function tableExists($conn, $table) {
        try {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            return $result->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    // Function to create table if it doesn't exist
    function createTable($conn, $table, $columns) {
        if (!tableExists($conn, $table)) {
            try {
                $sql = "CREATE TABLE $table (";
                $sql .= implode(', ', $columns);
                $sql .= ")";
                $conn->exec($sql);
                return true;
            } catch (PDOException $e) {
                throw new Exception("Failed to create table $table: " . $e->getMessage());
            }
        }
        return true;
    }

    // Function to check if column exists
    function columnExists($conn, $table, $column) {
        try {
            $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
            return $result->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    // Function to add column if it doesn't exist
    function addColumn($conn, $table, $column, $definition) {
        if (!columnExists($conn, $table, $column)) {
            try {
                $conn->exec("ALTER TABLE $table ADD COLUMN $column $definition");
                return true;
            } catch (PDOException $e) {
                throw new Exception("Failed to add column $column to table $table: " . $e->getMessage());
            }
        }
        return true;
    }

    // Function to drop foreign key if exists
    function dropForeignKey($conn, $table, $constraint) {
        try {
            $conn->exec("ALTER TABLE $table DROP FOREIGN KEY $constraint");
            return true;
        } catch (PDOException $e) {
            // Ignore error if constraint doesn't exist
            return false;
        }
    }

    // Function to add foreign key
    function addForeignKey($conn, $table, $constraint, $sql) {
        try {
            $conn->exec($sql);
            return true;
        } catch (PDOException $e) {
            throw new Exception("Failed to add foreign key $constraint to table $table: " . $e->getMessage());
        }
    }

    // Disable foreign key checks temporarily
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Start transaction
    $conn->beginTransaction();

    try {
        // Create tables if they don't exist
        createTable($conn, 'lawyers', [
            'id INT(11) AUTO_INCREMENT PRIMARY KEY',
            'user_id INT(11) NOT NULL',
            'specialization VARCHAR(255)',
            'experience_years INT',
            'bar_council_number VARCHAR(50)',
            'hourly_rate DECIMAL(10,2)',
            'status ENUM("pending", "active", "suspended") DEFAULT "pending"',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'
        ]);

        createTable($conn, 'lawyer_verifications', [
            'id INT(11) AUTO_INCREMENT PRIMARY KEY',
            'lawyer_id INT(11) NOT NULL',
            'verification_status ENUM("pending", "approved", "rejected") DEFAULT "pending"',
            'document_path VARCHAR(255)',
            'admin_notes TEXT',
            'submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'verified_at TIMESTAMP NULL DEFAULT NULL',
            'verified_by INT(11) NULL'
        ]);

        createTable($conn, 'legal_queries', [
            'id INT(11) AUTO_INCREMENT PRIMARY KEY',
            'client_id INT(11) NOT NULL',
            'lawyer_id INT(11) NULL',
            'title VARCHAR(255) NOT NULL',
            'description TEXT NOT NULL',
            'status ENUM("pending", "assigned", "in_progress", "completed", "cancelled") DEFAULT "pending"',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'
        ]);

        createTable($conn, 'messages', [
            'id INT(11) AUTO_INCREMENT PRIMARY KEY',
            'legal_query_id INT(11) NOT NULL',
            'sender_id INT(11) NOT NULL',
            'message TEXT NOT NULL',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ]);

        createTable($conn, 'ratings', [
            'id INT(11) AUTO_INCREMENT PRIMARY KEY',
            'query_id INT(11) NOT NULL',
            'client_id INT(11) NOT NULL',
            'lawyer_id INT(11) NOT NULL',
            'rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5)',
            'review TEXT',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ]);

        createTable($conn, 'reviews', [
            'id INT(11) AUTO_INCREMENT PRIMARY KEY',
            'consultation_id INT(11) NOT NULL',
            'client_id INT(11) NOT NULL',
            'lawyer_id INT(11) NOT NULL',
            'rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5)',
            'review TEXT',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ]);

        createTable($conn, 'admin_logs', [
            'id INT(11) AUTO_INCREMENT PRIMARY KEY',
            'admin_id INT(11) NOT NULL',
            'action VARCHAR(255) NOT NULL',
            'details TEXT',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ]);

        createTable($conn, 'consultations', [
            'id INT(11) AUTO_INCREMENT PRIMARY KEY',
            'client_id INT(11) NOT NULL',
            'lawyer_id INT(11) NOT NULL',
            'status ENUM("scheduled", "completed", "cancelled") DEFAULT "scheduled"',
            'scheduled_at TIMESTAMP NOT NULL',
            'completed_at TIMESTAMP NULL DEFAULT NULL',
            'notes TEXT',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ]);

        createTable($conn, 'payments', [
            'id INT(11) AUTO_INCREMENT PRIMARY KEY',
            'client_id INT(11) NOT NULL',
            'lawyer_id INT(11) NOT NULL',
            'consultation_id INT(11) NOT NULL',
            'amount DECIMAL(10,2) NOT NULL',
            'status ENUM("pending", "completed", "failed", "refunded") DEFAULT "pending"',
            'payment_method VARCHAR(50)',
            'transaction_id VARCHAR(255)',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ]);

        createTable($conn, 'notifications', [
            'id INT(11) AUTO_INCREMENT PRIMARY KEY',
            'user_id INT(11) NOT NULL',
            'type VARCHAR(50) NOT NULL',
            'message TEXT NOT NULL',
            'is_read BOOLEAN DEFAULT FALSE',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ]);

        // Now add foreign keys
        // 1. Update lawyers table
        dropForeignKey($conn, 'lawyers', 'fk_lawyers_user_id');
        addForeignKey($conn, 'lawyers', 'fk_lawyers_user_id', "
            ALTER TABLE lawyers
            ADD CONSTRAINT fk_lawyers_user_id
            FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE
        ");

        // 2. Update lawyer_verifications table
        dropForeignKey($conn, 'lawyer_verifications', 'fk_verifications_lawyer_id');
        dropForeignKey($conn, 'lawyer_verifications', 'fk_verifications_verified_by');
        addForeignKey($conn, 'lawyer_verifications', 'fk_verifications_lawyer_id', "
            ALTER TABLE lawyer_verifications
            ADD CONSTRAINT fk_verifications_lawyer_id
            FOREIGN KEY (lawyer_id) REFERENCES users(id)
            ON DELETE CASCADE
        ");
        addForeignKey($conn, 'lawyer_verifications', 'fk_verifications_verified_by', "
            ALTER TABLE lawyer_verifications
            ADD CONSTRAINT fk_verifications_verified_by
            FOREIGN KEY (verified_by) REFERENCES users(id)
            ON DELETE SET NULL
        ");

        // 3. Update legal_queries table
        dropForeignKey($conn, 'legal_queries', 'fk_queries_client_id');
        dropForeignKey($conn, 'legal_queries', 'fk_queries_lawyer_id');
        addForeignKey($conn, 'legal_queries', 'fk_queries_client_id', "
            ALTER TABLE legal_queries
            ADD CONSTRAINT fk_queries_client_id
            FOREIGN KEY (client_id) REFERENCES users(id)
            ON DELETE CASCADE
        ");
        addForeignKey($conn, 'legal_queries', 'fk_queries_lawyer_id', "
            ALTER TABLE legal_queries
            ADD CONSTRAINT fk_queries_lawyer_id
            FOREIGN KEY (lawyer_id) REFERENCES lawyers(id)
            ON DELETE SET NULL
        ");

        // 4. Update messages table
        dropForeignKey($conn, 'messages', 'fk_messages_query_id');
        dropForeignKey($conn, 'messages', 'fk_messages_sender_id');
        addForeignKey($conn, 'messages', 'fk_messages_query_id', "
            ALTER TABLE messages
            ADD CONSTRAINT fk_messages_query_id
            FOREIGN KEY (legal_query_id) REFERENCES legal_queries(id)
            ON DELETE CASCADE
        ");
        addForeignKey($conn, 'messages', 'fk_messages_sender_id', "
            ALTER TABLE messages
            ADD CONSTRAINT fk_messages_sender_id
            FOREIGN KEY (sender_id) REFERENCES users(id)
            ON DELETE CASCADE
        ");

        // 5. Update ratings table
        dropForeignKey($conn, 'ratings', 'fk_ratings_query_id');
        dropForeignKey($conn, 'ratings', 'fk_ratings_client_id');
        dropForeignKey($conn, 'ratings', 'fk_ratings_lawyer_id');
        addForeignKey($conn, 'ratings', 'fk_ratings_query_id', "
            ALTER TABLE ratings
            ADD CONSTRAINT fk_ratings_query_id
            FOREIGN KEY (query_id) REFERENCES legal_queries(id)
            ON DELETE CASCADE
        ");
        addForeignKey($conn, 'ratings', 'fk_ratings_client_id', "
            ALTER TABLE ratings
            ADD CONSTRAINT fk_ratings_client_id
            FOREIGN KEY (client_id) REFERENCES users(id)
            ON DELETE CASCADE
        ");
        addForeignKey($conn, 'ratings', 'fk_ratings_lawyer_id', "
            ALTER TABLE ratings
            ADD CONSTRAINT fk_ratings_lawyer_id
            FOREIGN KEY (lawyer_id) REFERENCES users(id)
            ON DELETE CASCADE
        ");

        // 6. Update reviews table
        dropForeignKey($conn, 'reviews', 'fk_reviews_consultation_id');
        dropForeignKey($conn, 'reviews', 'fk_reviews_client_id');
        dropForeignKey($conn, 'reviews', 'fk_reviews_lawyer_id');
        addForeignKey($conn, 'reviews', 'fk_reviews_consultation_id', "
            ALTER TABLE reviews
            ADD CONSTRAINT fk_reviews_consultation_id
            FOREIGN KEY (consultation_id) REFERENCES consultations(id)
            ON DELETE CASCADE
        ");
        addForeignKey($conn, 'reviews', 'fk_reviews_client_id', "
            ALTER TABLE reviews
            ADD CONSTRAINT fk_reviews_client_id
            FOREIGN KEY (client_id) REFERENCES users(id)
            ON DELETE CASCADE
        ");
        addForeignKey($conn, 'reviews', 'fk_reviews_lawyer_id', "
            ALTER TABLE reviews
            ADD CONSTRAINT fk_reviews_lawyer_id
            FOREIGN KEY (lawyer_id) REFERENCES lawyers(id)
            ON DELETE CASCADE
        ");

        // 7. Update admin_logs table
        dropForeignKey($conn, 'admin_logs', 'fk_logs_admin_id');
        addForeignKey($conn, 'admin_logs', 'fk_logs_admin_id', "
            ALTER TABLE admin_logs
            ADD CONSTRAINT fk_logs_admin_id
            FOREIGN KEY (admin_id) REFERENCES users(id)
            ON DELETE CASCADE
        ");

        // 8. Update consultations table
        dropForeignKey($conn, 'consultations', 'fk_consultations_client_id');
        dropForeignKey($conn, 'consultations', 'fk_consultations_lawyer_id');
        addForeignKey($conn, 'consultations', 'fk_consultations_client_id', "
            ALTER TABLE consultations
            ADD CONSTRAINT fk_consultations_client_id
            FOREIGN KEY (client_id) REFERENCES users(id)
            ON DELETE CASCADE
        ");
        addForeignKey($conn, 'consultations', 'fk_consultations_lawyer_id', "
            ALTER TABLE consultations
            ADD CONSTRAINT fk_consultations_lawyer_id
            FOREIGN KEY (lawyer_id) REFERENCES lawyers(id)
            ON DELETE CASCADE
        ");

        // 9. Update payments table
        dropForeignKey($conn, 'payments', 'fk_payments_client_id');
        dropForeignKey($conn, 'payments', 'fk_payments_lawyer_id');
        dropForeignKey($conn, 'payments', 'fk_payments_consultation_id');
        addForeignKey($conn, 'payments', 'fk_payments_client_id', "
            ALTER TABLE payments
            ADD CONSTRAINT fk_payments_client_id
            FOREIGN KEY (client_id) REFERENCES users(id)
            ON DELETE CASCADE
        ");
        addForeignKey($conn, 'payments', 'fk_payments_lawyer_id', "
            ALTER TABLE payments
            ADD CONSTRAINT fk_payments_lawyer_id
            FOREIGN KEY (lawyer_id) REFERENCES lawyers(id)
            ON DELETE CASCADE
        ");
        addForeignKey($conn, 'payments', 'fk_payments_consultation_id', "
            ALTER TABLE payments
            ADD CONSTRAINT fk_payments_consultation_id
            FOREIGN KEY (consultation_id) REFERENCES consultations(id)
            ON DELETE CASCADE
        ");

        // 10. Update notifications table
        dropForeignKey($conn, 'notifications', 'fk_notifications_user_id');
        addForeignKey($conn, 'notifications', 'fk_notifications_user_id', "
            ALTER TABLE notifications
            ADD CONSTRAINT fk_notifications_user_id
            FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE
        ");

        // Re-enable foreign key checks
        $conn->exec("SET FOREIGN_KEY_CHECKS = 1");

        // Commit transaction
        if ($conn->inTransaction()) {
            $conn->commit();
        }

        // Verify all foreign keys
        $foreignKeys = $conn->query("
            SELECT 
                TABLE_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY TABLE_NAME, COLUMN_NAME
        ")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Database schema updated successfully',
            'foreign_keys' => $foreignKeys
        ]);

    } catch (Exception $e) {
        // Re-enable foreign key checks before rollback
        $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} 