<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$provider = $data['provider'] ?? '';
$walletId = $provider === 'phantom' ? ($data['publicKey'] ?? '') : ($data['address'] ?? '');

if (empty($provider) || empty($walletId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Get database connection
$db = db_connect();

// Create users table if it doesn't exist
try {
    $db->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100),
        password VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    error_log("Error creating users table: " . $e->getMessage());
}

// Create user_wallets table if it doesn't exist
try {
    $db->query("CREATE TABLE IF NOT EXISTS user_wallets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        provider VARCHAR(50) NOT NULL,
        wallet_id VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_wallet (provider, wallet_id)
    )");
    
    // Add foreign key if possible (might fail if no users exist yet)
    try {
        $db->query("ALTER TABLE user_wallets ADD CONSTRAINT fk_user_wallet 
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
    } catch (Exception $e) {
        // Ignore foreign key error - it will be added when users are created
        error_log("Note: Foreign key will be added when users exist: " . $e->getMessage());
    }
} catch (Exception $e) {
    error_log("Error creating user_wallets table: " . $e->getMessage());
}

// For demo purposes, if a user is logged in, automatically link the wallet
if (isset($_SESSION['user_id'])) {
    try {
        // Check if wallet is already linked
        $checkStmt = $db->prepare("SELECT id FROM user_wallets WHERE provider = ? AND wallet_id = ?");
        $checkStmt->bind_param("ss", $provider, $walletId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows == 0) {
            // Link wallet to current user
            $linkStmt = $db->prepare("INSERT INTO user_wallets (user_id, provider, wallet_id) VALUES (?, ?, ?)");
            $linkStmt->bind_param("iss", $_SESSION['user_id'], $provider, $walletId);
            $linkStmt->execute();
        }
        
        $_SESSION['wallet_provider'] = $provider;
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        error_log("Error linking wallet: " . $e->getMessage());
    }
}

// Check database for wallet
$stmt = $db->prepare("SELECT user_id FROM user_wallets WHERE provider = ? AND wallet_id = ?");
$stmt->bind_param("ss", $provider, $walletId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['wallet_provider'] = $provider;
    echo json_encode(['success' => true]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Wallet not registered. Please register an account first or link this wallet to your existing account.'
    ]);
}
