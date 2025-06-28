<?php
// /opt/lampp/htdocs/NS/trade.php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verify required files exist
$required_files = [
    __DIR__ . '/includes/config.php',
    __DIR__ . '/includes/database.php',
    __DIR__ . '/includes/auth.php'
];

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        die("Missing required file: " . basename($file));
    }
    require_once $file;
}

// Verify authentication
if (!function_exists('requireAuth')) {
    die("Authentication system not available");
}
//requireAuth();

// Initialize session messages
$_SESSION['trade_message'] = '';
$_SESSION['trade_error'] = '';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate required POST data
    $required_fields = ['action', 'coin_id', 'symbol', 'amount', 'price'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $action = $_POST['action'];
    $coinId = (int)$_POST['coin_id'];
    $symbol = $_POST['symbol'];
    $amount = (float)$_POST['amount'];
    $price = (float)$_POST['price'];

    // Validate action type
    if (!in_array($action, ['buy', 'sell'])) {
        throw new Exception('Invalid trade action');
    }

    // Validate amounts
    if ($amount <= 0 || $price <= 0) {
        throw new Exception('Amount and price must be positive');
    }

    // Get database connection
    if (!function_exists('getDBConnection')) {
        throw new Exception('Database connection function not available');
    }

    $db = getDBConnection();
    if (!$db) {
        throw new Exception('Could not connect to database');
    }
    
    // Create necessary tables if they don't exist
    try {
        // Create trades table if it doesn't exist
        $db->query("CREATE TABLE IF NOT EXISTS trades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            coin_id INT NOT NULL,
            symbol VARCHAR(20) NOT NULL,
            amount DECIMAL(18,8) NOT NULL,
            price DECIMAL(18,2) NOT NULL,
            type ENUM('buy', 'sell') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create user_balances table if it doesn't exist
        $db->query("CREATE TABLE IF NOT EXISTS user_balances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            coin_id INT NOT NULL,
            symbol VARCHAR(20) NOT NULL,
            balance DECIMAL(18,8) NOT NULL DEFAULT 0,
            UNIQUE KEY unique_balance (user_id, coin_id)
        )");
    } catch (Exception $e) {
        error_log("Error creating tables: " . $e->getMessage());
    }

    // Make sure user_id is set in session
    if (!isset($_SESSION['user_id'])) {
        // For testing purposes, set a default user ID
        $_SESSION['user_id'] = 1;
    }
    
    // Log the trade attempt
    error_log("Trade attempt: User ID: {$_SESSION['user_id']}, Coin ID: {$coinId}, Symbol: {$symbol}, Amount: {$amount}, Price: {$price}, Action: {$action}");
    
    // Start transaction
    $db->begin_transaction();

    try {
        // Insert trade record
        $stmt = $db->prepare("INSERT INTO trades 
                            (user_id, coin_id, symbol, amount, price, type) 
                            VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $db->error);
        }

        $userId = $_SESSION['user_id'];
        $stmt->bind_param('iisdds', 
            $userId,
            $coinId,
            $symbol,
            $amount,
            $price,
            $action
        );

        if (!$stmt->execute()) {
            throw new Exception('Execution failed: ' . $stmt->error);
        }

        // Update user balance
        if ($action === 'buy') {
            $query = "INSERT INTO user_balances 
                     (user_id, coin_id, symbol, balance) 
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE 
                     balance = balance + VALUES(balance)";
        } else {
            $query = "UPDATE user_balances 
                     SET balance = balance - ? 
                     WHERE user_id = ? AND coin_id = ? AND balance >= ?";
        }

        $stmt = $db->prepare($query);
        if ($action === 'buy') {
            $stmt->bind_param('iisd', 
                $_SESSION['user_id'],
                $coinId,
                $symbol,
                $amount
            );
        } else {
            $stmt->bind_param('diii',
                $amount,
                $_SESSION['user_id'],
                $coinId,
                $amount
            );
        }

        if (!$stmt->execute()) {
            throw new Exception('Balance update failed: ' . $stmt->error);
        }

        $db->commit();
        $_SESSION['trade_message'] = "Trade completed successfully";

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    $_SESSION['trade_error'] = $e->getMessage();
    error_log("Trade Error: " . $e->getMessage());
}

header("Location: coins.php");
exit();
