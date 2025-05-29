<?php
// Prevent any output before headers
ob_start();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable HTML error display
ini_set('log_errors', 1); // Enable error logging
ini_set('error_log', '../logs/php_errors.log'); // Set error log file

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response);
    exit();
}

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

try {
    require_once '../config/database.php';

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }

    // Check if action is set
    if (!isset($data['action'])) {
        sendJsonResponse(false, 'No action specified');
    }

    // Handle different actions
    switch ($data['action']) {
        case 'login':
            handleLogin($data);
            break;
        case 'register':
            handleRegistration($data);
            break;
        default:
            sendJsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    sendJsonResponse(false, 'An error occurred. Please try again later.');
}

function handleLogin($data) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Validate required fields
    if (!isset($data['username']) || !isset($data['password'])) {
        sendJsonResponse(false, 'Username and password are required');
    }

    try {
        $db = Database::getInstance();
        
        // Get user from database
        $stmt = $db->query(
            "SELECT id, username, password, user_type, full_name, language_preference 
             FROM users 
             WHERE username = ?", 
            [$data['username']]
        );
        
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['password'], $user['password'])) {
            sendJsonResponse(false, 'Invalid username or password');
        }

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['language_preference'] = $user['language_preference'];

        // Generate JWT token
        $token = generateToken($user);

        // Remove password from user data
        unset($user['password']);

        sendJsonResponse(true, 'Login successful', [
            'token' => $token,
            'user' => $user
        ]);

    } catch (Exception $e) {
        error_log($e->getMessage());
        sendJsonResponse(false, 'An error occurred during login');
    }
}

function handleRegistration($data) {
    try {
        // Sanitize inputs
        $username = sanitize_input($data['username']);
        $email = sanitize_input($data['email']);
        $password = $data['password']; // Don't sanitize password before hashing
        $full_name = sanitize_input($data['fullName']);
        $user_type = sanitize_input($data['userType']);

        // Validate inputs
        if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($user_type)) {
            sendJsonResponse(false, 'All fields are required');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJsonResponse(false, 'Invalid email format');
        }

        $db = Database::getInstance();
        $conn = $db->getConnection();

        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            sendJsonResponse(false, 'Username or email already exists');
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user
        $stmt = $conn->prepare("
            INSERT INTO users (username, email, password, full_name, user_type)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$username, $email, $hashed_password, $full_name, $user_type]);
        
        sendJsonResponse(true, 'Registration successful', [
            'user_id' => $conn->lastInsertId()
        ]);

    } catch (Exception $e) {
        error_log($e->getMessage());
        sendJsonResponse(false, 'An error occurred during registration');
    }
}

function generateToken($user) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $user['id'],
        'username' => $user['username'],
        'user_type' => $user['user_type'],
        'exp' => time() + (60 * 60 * 24) // 24 hours
    ]);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

    $signature = hash_hmac('sha256', 
        $base64UrlHeader . "." . $base64UrlPayload, 
        JWT_SECRET,
        true
    );
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}
?> 