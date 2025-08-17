<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a lawyer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lawyer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['queryId']) || !isset($data['paymentAmount'])) {
    echo json_encode(['success' => false, 'message' => 'Query ID and payment amount are required']);
    exit();
}

// Validate payment amount
$paymentAmount = floatval($data['paymentAmount']);
if ($paymentAmount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Payment amount must be greater than 0']);
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Start transaction
    $conn->beginTransaction();

    // Get lawyer ID
    $stmt = $conn->prepare("SELECT id FROM lawyers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $lawyer = $stmt->fetch();

    if (!$lawyer) {
        // Create lawyer profile if it doesn't exist
        $stmt = $conn->prepare("
            INSERT INTO lawyers (
                user_id, 
                specialization, 
                experience_years, 
                bar_council_number, 
                hourly_rate, 
                languages
            ) VALUES (?, 'General', 0, 'PENDING', 0.00, 'en')
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $lawyerId = $conn->lastInsertId();
    } else {
        $lawyerId = $lawyer['id'];
    }

    // Check if lawyer_id column exists
    $stmt = $conn->query("SHOW COLUMNS FROM legal_queries LIKE 'lawyer_id'");
    if ($stmt->rowCount() == 0) {
        // Add lawyer_id column if it doesn't exist
        $conn->exec("ALTER TABLE legal_queries ADD COLUMN lawyer_id INT(11) NULL AFTER client_id");
        $conn->exec("ALTER TABLE legal_queries ADD FOREIGN KEY (lawyer_id) REFERENCES lawyers(id) ON DELETE SET NULL");
    }

    // Check if payment_amount and payment_status columns exist
    $stmt = $conn->query("SHOW COLUMNS FROM legal_queries LIKE 'payment_amount'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE legal_queries ADD COLUMN payment_amount DECIMAL(10,2) DEFAULT NULL AFTER status");
    }

    $stmt = $conn->query("SHOW COLUMNS FROM legal_queries LIKE 'payment_status'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE legal_queries ADD COLUMN payment_status ENUM('pending','completed','failed') DEFAULT 'pending' AFTER payment_amount");
    }

    // Update query status with payment information
    $stmt = $conn->prepare("
        UPDATE legal_queries 
        SET lawyer_id = ?, 
            status = 'assigned',
            payment_amount = ?,
            payment_status = 'pending'
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$lawyerId, $paymentAmount, $data['queryId']]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Query not found or already assigned');
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Query accepted successfully with payment amount of LKR ' . number_format($paymentAmount, 2)
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error in accept_query.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 