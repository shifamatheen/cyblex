<?php
// Start output buffering
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set content type
header('Content-Type: application/json');

// Function to output JSON response
function jsonResponse($data, $status = 200) {
    ob_clean();
    http_response_code($status);
    echo json_encode($data);
    exit();
}

try {
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Include required files
    require_once '../config/database.php';
    require_once '../config/auth.php';

    // Check admin access
    if (!isAdmin()) {
        jsonResponse(['error' => 'Admin access required'], 403);
    }

    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // First, check total number of lawyers
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'lawyer'");
    $totalLawyers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Check pending lawyers
    $stmt = $conn->query("SELECT COUNT(*) as pending FROM users WHERE user_type = 'lawyer' AND status = 'pending'");
    $pendingLawyers = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];

    // Get pending verifications - simplified query to match the actual data structure
    $stmt = $conn->prepare("
        SELECT 
            u.id as lawyer_id,
            u.full_name,
            u.email,
            u.profile_image,
            u.status as user_status,
            l.specialization,
            l.experience_years,
            l.bar_council_number,
            l.verification_status,
            l.created_at as submitted_at
        FROM users u
        JOIN lawyers l ON u.id = l.user_id
        WHERE u.user_type = 'lawyer' 
        AND l.verification_status = 'pending'
        ORDER BY l.created_at DESC
    ");
    $stmt->execute();
    $verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return success response with additional information
    jsonResponse([
        'success' => true,
        'data' => $verifications,
        'stats' => [
            'total_lawyers' => $totalLawyers,
            'pending_lawyers' => $pendingLawyers,
            'pending_verifications' => count($verifications)
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    jsonResponse([
        'error' => 'Database error occurred',
        'message' => $e->getMessage()
    ], 500);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    jsonResponse([
        'error' => 'Server error occurred',
        'message' => $e->getMessage()
    ], 500);
}
?> 