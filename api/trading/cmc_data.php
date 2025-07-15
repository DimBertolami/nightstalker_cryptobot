<?php
// Buffer all output to prevent headers already sent errors
ob_start();

try {
    // Start session and check authentication
    session_start();
    require_once __DIR__ . '/../../includes/auth.php';
    requireAuth();
    
    // Set headers
    header('Content-Type: application/json');
    
    // Include CMC utilities
    require_once __DIR__ . '/../../includes/cmc_utils.php';
    
    // Get gainers and losers data
    $data = getCMCGainersLosers();
    
    // Sort gainers by percent change (descending)
    usort($data['gainers'], function($a, $b) {
        return $b['quote']['USD']['percent_change_24h'] <=> $a['quote']['USD']['percent_change_24h'];
    });
    
    // Sort losers by percent change (ascending)
    usort($data['losers'], function($a, $b) {
        return $a['quote']['USD']['percent_change_24h'] <=> $b['quote']['USD']['percent_change_24h'];
    });
    
    // Return data
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('CMC Data API Error: ' . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch CMC data: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
