<?php
ob_start();
/**
 * Wallet Disconnection API Endpoint
 * 
 * This endpoint allows users to safely disconnect their wallets from their Night Stalker account.
 * It performs authentication checks, logs the disconnection for security auditing,
 * and removes wallet session data.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
];

// Check if user is authenticated
if (!isLoggedIn()) {
    $response['message'] = 'Authentication required';
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode($response);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode($response);
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    $response['message'] = 'User ID not found in session';
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode($response);
    exit;
}

// Get wallet ID from request
$walletId = isset($_POST['wallet_id']) ? intval($_POST['wallet_id']) : null;
$walletAddress = isset($_POST['wallet_address']) ? trim($_POST['wallet_address']) : null;

// Validate input - either wallet_id or wallet_address must be provided
if (!$walletId && !$walletAddress) {
    $response['message'] = 'Wallet ID or address is required';
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode($response);
    exit;
}

try {
    // Connect to database
    $pdo = getDbConnection();
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get wallet details for logging
    if ($walletId) {
        $stmt = $pdo->prepare("SELECT wallet_id, provider FROM user_wallets WHERE id = :wallet_id AND user_id = :user_id");
        $stmt->bindParam(':wallet_id', $walletId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare("SELECT id, provider FROM user_wallets WHERE wallet_id = :wallet_id AND user_id = :user_id");
        $stmt->bindParam(':wallet_id', $walletAddress, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$wallet) {
        throw new Exception('Wallet not found or does not belong to this user');
    }
    
    // Set walletId if we only had address before
    if (!$walletId && isset($wallet['id'])) {
        $walletId = $wallet['id'];
    }
    
    // Set walletAddress if we only had ID before
    if (!$walletAddress && isset($wallet['wallet_id'])) {
        $walletAddress = $wallet['wallet_id'];
    }
    
    $walletType = $wallet['provider'] ?? 'unknown';
    
    // Log the disconnection for security auditing
    $stmt = $pdo->prepare("
        INSERT INTO security_log 
        (user_id, action, details, ip_address, user_agent) 
        VALUES 
        (:user_id, 'wallet_disconnect', :details, :ip_address, :user_agent)
    ");
    
    $details = json_encode([
        'wallet_id' => $walletId,
        'wallet_address' => $walletAddress,
        'wallet_type' => $walletType
    ]);
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':details', $details, PDO::PARAM_STR);
    $stmt->bindParam(':ip_address', $ipAddress, PDO::PARAM_STR);
    $stmt->bindParam(':user_agent', $userAgent, PDO::PARAM_STR);
    $stmt->execute();
    
    // Update wallet status in database (mark as disconnected)
    // Since status and last_disconnected columns do not exist, skip this step or handle gracefully
    // Commenting out the update query to avoid errors
    /*
    $stmt = $pdo->prepare("
        UPDATE user_wallets 
        SET status = 'disconnected', 
            last_disconnected = NOW() 
        WHERE id = :wallet_id AND user_id = :user_id
    ");
    
    $stmt->bindParam(':wallet_id', $walletId, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    */
    
    // Remove wallet data from session
    if (isset($_SESSION['connected_wallets'])) {
        foreach ($_SESSION['connected_wallets'] as $key => $sessionWallet) {
            if (
                ($walletId && isset($sessionWallet['id']) && $sessionWallet['id'] == $walletId) ||
                ($walletAddress && isset($sessionWallet['address']) && $sessionWallet['address'] == $walletAddress)
            ) {
                unset($_SESSION['connected_wallets'][$key]);
                break;
            }
        }
        
        // Reindex array if needed
        if (is_array($_SESSION['connected_wallets'])) {
            $_SESSION['connected_wallets'] = array_values($_SESSION['connected_wallets']);
        }
    }
    
    // Delete wallet from user_wallets table
    $stmt = $pdo->prepare("DELETE FROM user_wallets WHERE id = :wallet_id AND user_id = :user_id");
    $stmt->bindParam(':wallet_id', $walletId, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    // Commit transaction
    $pdo->commit();
    
    // Success response
    $response['success'] = true;
    $response['message'] = 'Wallet disconnected successfully';
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $response['message'] = 'Error: ' . $e->getMessage();
    http_response_code(500);
} finally {
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    ob_end_flush();
}
