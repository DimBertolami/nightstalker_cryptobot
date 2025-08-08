<?php
// Include database connection
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/pdo_functions.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get database connection
    $db = getDBConnection();
    
    if (!$db) {
        throw new PDOException("Database connection failed");
    }
    
    // Check if symbol is provided
    if (!isset($_GET['symbol'])) {
        throw new Exception("Symbol parameter is required");
    }
    
    $symbol = $_GET['symbol'];
    
    // First try to get price from coins table
    $stmt = $db->prepare("SELECT price FROM coins WHERE symbol = :symbol LIMIT 1");
    $stmt->bindParam(':symbol', $symbol);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && isset($result['price'])) {
        // Return the price from coins table
        echo json_encode([
            'success' => true,
            'price' => $result['price'],
            'source' => 'coins'
        ]);
        exit;
    }
    
    // If not found in coins, try cryptocurrencies table
    $stmt = $db->prepare("SELECT price FROM cryptocurrencies WHERE symbol = :symbol LIMIT 1");
    $stmt->bindParam(':symbol', $symbol);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && isset($result['price'])) {
        // Return the price from cryptocurrencies table
        echo json_encode([
            'success' => true,
            'price' => $result['price'],
            'source' => 'cryptocurrencies'
        ]);
        exit;
    }
    
    // If we get here, we couldn't find the price
    throw new Exception("Could not find price for symbol: $symbol");
    
} catch (PDOException $e) {
    // Database connection or query error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
