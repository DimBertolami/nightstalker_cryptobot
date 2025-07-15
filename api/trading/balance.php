<?php
/**
 * Balance API Endpoint
 * For Night Stalker Cryptobot
 * 
 * Returns wallet balance data for the selected exchange
 */

// Prevent any output before our JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Buffer all output to prevent headers already sent errors
ob_start();

try {
    // Set headers
    header('Content-Type: application/json');
    
    // Get selected exchange from request or session
    $exchangeId = $_GET['exchange'] ?? $_SESSION['selected_exchange'] ?? 'binance';
    $forceUpdate = isset($_GET['force_update']) && $_GET['force_update'] === 'true';
    
    $balanceData = [];
    
    // Try to load the wallet class and get real balance data
    $useRealData = false;
    
    try {
        if (file_exists(__DIR__ . '/../../config/database.php') && file_exists(__DIR__ . '/../../models/Wallet.php')) {
            require_once __DIR__ . '/../../config/database.php';
            require_once __DIR__ . '/../../models/Wallet.php';
            
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Initialize wallet
            $wallet = new Wallet($exchangeId);
            
            // Get balance data
            $balanceData = $wallet->getBalance();
            
            // If force update is requested, refresh the balance data
            if ($forceUpdate) {
                $balanceData = $wallet->refreshBalance(function($progress) {
                    // This is a callback function that can be used to update progress
                    // Not used in API context
                });
            }
            
            $useRealData = true;
        }
    } catch (Exception $e) {
        error_log("Wallet balance error: " . $e->getMessage());
        $useRealData = false;
    }
    
    // If we couldn't get real data, use demo data
    if (!$useRealData || empty($balanceData)) {
        // Provide demo balance data
        $balanceData = [
            'BTC' => [
                'free' => 0.5,
                'used' => 0.0,
                'total' => 0.5
            ],
            'ETH' => [
                'free' => 5.0,
                'used' => 0.0,
                'total' => 5.0
            ],
            'SOL' => [
                'free' => 25.0,
                'used' => 0.0,
                'total' => 25.0
            ],
            'USDT' => [
                'free' => 1000.0,
                'used' => 0.0,
                'total' => 1000.0
            ]
        ];
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'exchange' => $exchangeId,
        'balances' => $balanceData,
        'timestamp' => time(),
        'demo_data' => !$useRealData
    ]);
    
} catch (Exception $e) {
    // Clear any previous output
    ob_clean();
    
    // Return error response
    error_log("Balance API error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving balance data: ' . $e->getMessage()
    ]);
} catch (Throwable $e) {
    // Catch any other errors
    ob_clean();
    
    // Return error response
    error_log("Balance API critical error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Critical error retrieving balance data'
    ]);
}

// End output buffering and flush
ob_end_flush();
?>
