<?php
// Buffer all output to prevent headers already sent errors
ob_start();

try {
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/database.php';
    require_once __DIR__ . '/includes/auth.php';

    /**
     * Fetches all wallets for a user and updates the session.
     *
     * @param PDO $db The database connection object.
     * @param int $userId The ID of the user.
     * @throws Exception if the database query fails.
     */
    function updateUserWalletSession($db, $userId) {
        $walletsStmt = $db->prepare("SELECT id, provider, wallet_id FROM user_wallets WHERE user_id = ?");
        if (!$walletsStmt) {
            throw new Exception('Failed to prepare statement for fetching wallets: ' . print_r($db->errorInfo(), true));
        }
        $walletsStmt->execute([$userId]);
        $wallets = $walletsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $_SESSION['connected_wallets'] = [];
        foreach ($wallets as $wallet) {
            $_SESSION['connected_wallets'][] = [
                'id'      => $wallet['id'],
                'type'    => $wallet['provider'],
                'address' => $wallet['wallet_id']
            ];
        }
    }
    
    // Set headers
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
        throw new Exception('Invalid request: Missing provider or wallet ID');
    }
    
    // Get database connection
    $db = db_connect();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Create users table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100),
        password VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create user_wallets table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS user_wallets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        provider VARCHAR(50) NOT NULL,
        wallet_id VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_wallet (provider, wallet_id)
    )");
    
    // Add foreign key if possible (might fail if no users exist yet)
    try {
        $db->exec("ALTER TABLE user_wallets ADD CONSTRAINT fk_user_wallet 
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
    } catch (Exception $e) {
        // Ignore foreign key error - it will be added when users are created
        error_log("Note: Foreign key will be added when users exist: " . $e->getMessage());
    }
    
    // If a user is logged in, automatically link the wallet
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];

        // Check if wallet is already linked to this user
        $checkStmt = $db->prepare("SELECT id FROM user_wallets WHERE user_id = ? AND provider = ? AND wallet_id = ?");
        if (!$checkStmt) {
            throw new Exception('Failed to prepare statement: ' . print_r($db->errorInfo(), true));
        }
        
        $checkStmt->execute([$userId, $provider, $walletId]);
        $isAlreadyLinked = $checkStmt->fetch();
        
        if (!$isAlreadyLinked) {
            // Link wallet to current user
            $linkStmt = $db->prepare("INSERT INTO user_wallets (user_id, provider, wallet_id) VALUES (?, ?, ?)");
            if (!$linkStmt) {
                throw new Exception('Failed to prepare statement: ' . print_r($db->errorInfo(), true));
            }
            
            $linkStmt->execute([$userId, $provider, $walletId]);
        }
        
        // Update the session with all connected wallets for the user
        updateUserWalletSession($db, $userId);
        
        echo json_encode(['success' => true]);
        ob_end_flush();
        exit;
    }
    
    // If user is not logged in, check if wallet is registered to any user and log them in
    $stmt = $db->prepare("SELECT user_id FROM user_wallets WHERE provider = ? AND wallet_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . print_r($db->errorInfo(), true));
    }
    
    $stmt->execute([$provider, $walletId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $userId = $result['user_id'];
        $_SESSION['user_id'] = $userId;
        
        // Update the session with all connected wallets for the user
        updateUserWalletSession($db, $userId);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Wallet not registered. Please register an account first or link this wallet to your existing account.'
        ]);
    }

} catch (Exception $e) {
    // Clear any previous output
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    // Log the error
    error_log('Wallet Auth Error: ' . $e->getMessage());
    
    // Return error response
    // Ensure headers are not sent again if already sent
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request: ' . $e->getMessage()
    ]);
}

// End output buffering and flush
if (ob_get_level() > 0) {
    ob_end_flush();
}
