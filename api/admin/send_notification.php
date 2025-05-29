<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate required fields
if (!isset($_POST['title']) || !isset($_POST['message']) || !isset($_POST['type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$title = trim($_POST['title']);
$message = trim($_POST['message']);
$type = $_POST['type'];
$targetUsers = isset($_POST['target_users']) ? $_POST['target_users'] : 'all';

if (empty($title) || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title and message cannot be empty']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Start transaction
    $conn->beginTransaction();

    // Insert notification
    $query = "INSERT INTO notifications (title, message, type, created_by, created_at)
              VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$title, $message, $type, $_SESSION['user_id']]);
    $notificationId = $conn->lastInsertId();

    // Determine target users based on type
    $userQuery = "SELECT id FROM users WHERE 1=1";
    if ($targetUsers === 'lawyers') {
        $userQuery .= " AND user_type = 'lawyer'";
    } elseif ($targetUsers === 'clients') {
        $userQuery .= " AND user_type = 'client'";
    }

    $userStmt = $conn->prepare($userQuery);
    $userStmt->execute();
    $users = $userStmt->fetchAll(PDO::FETCH_COLUMN);

    // Create notification entries for each user
    if (!empty($users)) {
        $values = [];
        $params = [];
        foreach ($users as $userId) {
            $values[] = "(?, ?, NOW())";
            $params[] = $notificationId;
            $params[] = $userId;
        }

        $userNotifQuery = "INSERT INTO user_notifications (notification_id, user_id, created_at)
                          VALUES " . implode(', ', $values);
        
        $userNotifStmt = $conn->prepare($userNotifQuery);
        $userNotifStmt->execute($params);
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Notification sent successfully',
        'notification_id' => $notificationId
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while sending notification'
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred'
    ]);
} 