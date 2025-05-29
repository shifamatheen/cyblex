<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../includes/auth.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get request body
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit();
}

// Initialize auth handler
$auth = new Auth($conn);

// Handle different actions
$action = $data['action'] ?? '';

switch ($action) {
    case 'register':
        // Validate required fields
        $required_fields = ['username', 'email', 'password', 'full_name', 'user_type'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"]);
                exit();
            }
        }

        // Register user
        $result = $auth->register(
            $data['username'],
            $data['email'],
            $data['password'],
            $data['full_name'],
            $data['user_type']
        );
        break;

    case 'login':
        // Validate required fields
        if (empty($data['username']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Username and password are required']);
            exit();
        }

        // Login user
        $result = $auth->login($data['username'], $data['password']);
        break;

    case 'update_language':
        // Validate required fields
        if (empty($data['user_id']) || empty($data['language'])) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID and language are required']);
            exit();
        }

        // Update language preference
        $result = $auth->updateLanguagePreference($data['user_id'], $data['language']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        exit();
}

// Send response
if ($result['success']) {
    http_response_code(200);
} else {
    http_response_code(400);
}

echo json_encode($result);
?> 