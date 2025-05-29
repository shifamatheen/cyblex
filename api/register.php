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

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $required_fields = ['username', 'email', 'password', 'fullName', 'userType'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Validate user type
    if (!in_array($data['userType'], ['client', 'lawyer'])) {
        throw new Exception("Invalid user type");
    }

    // Start transaction
    $conn->beginTransaction();

    try {
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$data['username'], $data['email']]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Username or email already exists");
        }

        // Insert new user
        $stmt = $conn->prepare("
            INSERT INTO users (username, email, password, full_name, user_type, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        // Hash password
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt->execute([
            $data['username'],
            $data['email'],
            $hashed_password,
            $data['fullName'],
            $data['userType']
        ]);

        $user_id = $conn->lastInsertId();

        // If user is a lawyer, create lawyer record
        if ($data['userType'] === 'lawyer') {
            $stmt = $conn->prepare("
                INSERT INTO lawyers (user_id, status, created_at)
                VALUES (?, 'pending', NOW())
            ");
            $stmt->execute([$user_id]);
        }

        // Commit transaction
        $conn->commit();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'user' => [
                'id' => $user_id,
                'username' => $data['username'],
                'email' => $data['email'],
                'fullName' => $data['fullName'],
                'userType' => $data['userType']
            ]
        ]);

    } catch (Exception $e) {
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
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 