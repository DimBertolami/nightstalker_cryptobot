<?php
/**
 * Portfolio API endpoint
 * Retrieves user's portfolio data
 */

// Include necessary files
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Start output buffering to ensure clean JSON output
ob_start();

// Set content type to JSON
header('Content-Type: application/json');

// Default response
$response = [
    'success' => false,
    'message' => 'Unknown error',
    'portfolio' => []
];

try {
    // Get database connection
    $db = getDBConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Query portfolio data
    $query = "SELECT p.id, p.coin_id, p.amount, p.avg_buy_price, 
              COALESCE(c.symbol, cr.symbol) as symbol, 
              COALESCE(c.name, cr.name) as name,
              COALESCE(c.current_price, cr.price) as current_price
              FROM portfolio p
              LEFT JOIN coins c ON c.id = p.coin_id
              LEFT JOIN cryptocurrencies cr ON cr.id = p.coin_id
              ORDER BY p.amount DESC";
    //$query = "SELECT * FROM portfolio";        
    $result = $db->query($query);
    
    if (!$result) {
        throw new Exception('Failed to query portfolio: ' . $db->error);
    }
    
    // Fetch all portfolio items
    $portfolio = [];
    while ($row = $result->fetch_assoc()) {
        $portfolio[] = $row;
    }
    
    // Update response
    $response['success'] = true;
    $response['message'] = 'Portfolio retrieved successfully';
    $response['portfolio'] = $portfolio;
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Portfolio API error: ' . $e->getMessage());
} finally {
    // Clean any output buffer and return JSON response
    ob_end_clean();
    echo json_encode($response);
}
