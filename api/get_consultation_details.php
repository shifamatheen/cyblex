<?php
require_once 'config.php';
require_once 'auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get consultation ID from request
$consultation_id = isset($_GET['consultation_id']) ? intval($_GET['consultation_id']) : 0;

if (!$consultation_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid consultation ID']);
    exit;
}

try {
    // Get user info
    $user = getCurrentUser();
    
    // Get consultation details
    $stmt = $pdo->prepare("
        SELECT 
            lq.*,
            c.name as client_name,
            l.name as lawyer_name,
            lc.name as category_name
        FROM legal_queries lq
        LEFT JOIN clients c ON lq.client_id = c.id
        LEFT JOIN lawyers l ON lq.assigned_lawyer_id = l.id
        LEFT JOIN legal_query_categories lc ON lq.category = lc.name
        WHERE lq.id = ? AND (lq.client_id = ? OR lq.assigned_lawyer_id = ?)
    ");
    $stmt->execute([$consultation_id, $user['id'], $user['id']]);
    $consultation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$consultation) {
        echo json_encode(['success' => false, 'message' => 'Consultation not found or unauthorized access']);
        exit;
    }

    // Format the response
    $response = [
        'success' => true,
        'consultation' => [
            'id' => $consultation['id'],
            'title' => $consultation['title'],
            'category' => $consultation['category_name'],
            'description' => $consultation['description'],
            'urgency_level' => $consultation['urgency_level'],
            'status' => $consultation['status'],
            'client_name' => $consultation['client_name'],
            'lawyer_name' => $consultation['lawyer_name'],
            'created_at' => $consultation['created_at'],
            'updated_at' => $consultation['updated_at']
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} 