<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'client') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get and sanitize input
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            throw new Exception('Invalid input data');
        }

        // Validate required fields
        $required_fields = ['category', 'title', 'description', 'urgency_level'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Sanitize and validate input
        $category = filter_var($data['category'], FILTER_SANITIZE_STRING);
        $title = filter_var($data['title'], FILTER_SANITIZE_STRING);
        $description = filter_var($data['description'], FILTER_SANITIZE_STRING);
        $urgency_level = filter_var($data['urgency_level'], FILTER_SANITIZE_STRING);

        // Validate category
        $stmt = $conn->prepare("SELECT id FROM legal_query_categories WHERE name = ?");
        $stmt->execute([$category]);
        if (!$stmt->fetch()) {
            throw new Exception('Invalid category');
        }

        // Validate urgency level
        $valid_urgency_levels = ['low', 'medium', 'high'];
        if (!in_array($urgency_level, $valid_urgency_levels)) {
            throw new Exception('Invalid urgency level');
        }

        // Validate title length
        if (strlen($title) < 5 || strlen($title) > 255) {
            throw new Exception('Title must be between 5 and 255 characters');
        }

        // Validate description length
        if (strlen($description) < 20) {
            throw new Exception('Description must be at least 20 characters long');
        }

        // Insert query into database
        $stmt = $conn->prepare("
            INSERT INTO legal_queries 
            (client_id, category, title, description, urgency_level) 
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_SESSION['user_id'],
            $category,
            $title,
            $description,
            $urgency_level
        ]);

        $query_id = $conn->lastInsertId();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Legal query submitted successfully',
            'query_id' => $query_id
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'error' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} 