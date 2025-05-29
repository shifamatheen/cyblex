<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $analytics = [];

    // User Growth Data (last 6 months)
    $userGrowthQuery = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                               COUNT(*) as count
                        FROM users
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                        ORDER BY month ASC";
    
    $stmt = $conn->prepare($userGrowthQuery);
    $stmt->execute();
    $userGrowth = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $analytics['user_growth'] = [
        'labels' => array_column($userGrowth, 'month'),
        'data' => array_column($userGrowth, 'count')
    ];

    // Query Categories Distribution
    $categoriesQuery = "SELECT category, COUNT(*) as count
                       FROM queries
                       GROUP BY category
                       ORDER BY count DESC";
    
    $stmt = $conn->prepare($categoriesQuery);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $analytics['query_categories'] = [
        'labels' => array_column($categories, 'category'),
        'data' => array_column($categories, 'count')
    ];

    // Average Response Time by Category
    $responseTimeQuery = "SELECT q.category,
                                AVG(TIMESTAMPDIFF(HOUR, q.created_at, 
                                    (SELECT MIN(created_at) 
                                     FROM chat_messages 
                                     WHERE query_id = q.id AND sender_type = 'lawyer'))) as avg_response_time
                         FROM queries q
                         WHERE EXISTS (
                             SELECT 1 
                             FROM chat_messages 
                             WHERE query_id = q.id AND sender_type = 'lawyer'
                         )
                         GROUP BY q.category";
    
    $stmt = $conn->prepare($responseTimeQuery);
    $stmt->execute();
    $responseTimes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $analytics['response_time'] = [
        'labels' => array_column($responseTimes, 'category'),
        'data' => array_map(function($time) {
            return round($time, 1);
        }, array_column($responseTimes, 'avg_response_time'))
    ];

    // User Satisfaction Metrics
    $satisfactionQuery = "SELECT 
                            AVG(rating) as avg_rating,
                            COUNT(CASE WHEN rating >= 4 THEN 1 END) * 100.0 / COUNT(*) as satisfaction_rate,
                            COUNT(CASE WHEN status = 'flagged' THEN 1 END) * 100.0 / COUNT(*) as flag_rate,
                            COUNT(CASE WHEN status = 'resolved' THEN 1 END) * 100.0 / COUNT(*) as resolution_rate
                         FROM reviews";
    
    $stmt = $conn->prepare($satisfactionQuery);
    $stmt->execute();
    $satisfaction = $stmt->fetch(PDO::FETCH_ASSOC);

    $analytics['satisfaction'] = [
        'labels' => ['Average Rating', 'Satisfaction Rate', 'Flag Rate', 'Resolution Rate'],
        'data' => [
            round($satisfaction['avg_rating'], 1),
            round($satisfaction['satisfaction_rate'], 1),
            round($satisfaction['flag_rate'], 1),
            round($satisfaction['resolution_rate'], 1)
        ]
    ];

    // Additional Statistics
    $statsQuery = "SELECT 
                    (SELECT COUNT(*) FROM users WHERE user_type = 'client') as total_clients,
                    (SELECT COUNT(*) FROM users WHERE user_type = 'lawyer') as total_lawyers,
                    (SELECT COUNT(*) FROM queries) as total_queries,
                    (SELECT COUNT(*) FROM queries WHERE status = 'active') as active_queries,
                    (SELECT COUNT(*) FROM reviews) as total_reviews,
                    (SELECT AVG(rating) FROM reviews) as avg_rating";
    
    $stmt = $conn->prepare($statsQuery);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $analytics['stats'] = $stats;

    echo json_encode([
        'success' => true,
        'analytics' => $analytics
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching analytics'
    ]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred'
    ]);
} 