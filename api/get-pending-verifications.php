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

    // Get pending verifications with more detailed information
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
            lv.id as verification_id,
            lv.status as verification_status,
            lv.document_path,
            lv.admin_notes,
            lv.submitted_at,
            lv.verified_at,
            lv.verified_by
        FROM users u
        LEFT JOIN lawyers l ON u.id = l.user_id
        LEFT JOIN lawyer_verifications lv ON u.id = lv.lawyer_id
        WHERE u.user_type = 'lawyer' 
        AND (
            (u.status = 'pending' AND lv.id IS NULL)
            OR 
            (lv.status = 'pending')
        )
        ORDER BY 
            CASE 
                WHEN lv.id IS NULL THEN 1 
                ELSE 0 
            END,
            lv.submitted_at DESC
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