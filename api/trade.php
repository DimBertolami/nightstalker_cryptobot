<?php
// Set JSON content type header
header('Content-Type: application/json');

// Suppress all errors - this is critical to prevent HTML errors from breaking JSON
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

// Register a shutdown function to ensure we always return JSON
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Clean any output that might have been generated
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Send proper JSON error response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error occurred: ' . $error['message'],
            'error_details' => $error
        ]);
    }
});

// Create a function to handle errors and return JSON
function returnJsonError($message, $code = 500) {
    // Clean any output that might have been generated
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

// Include required files
try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/database.php';
} catch (Exception $e) {
    returnJsonError('Failed to load required files: ' . $e->getMessage());
}

// This is a simplified version - in production you'd have proper authentication
try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        returnJsonError('Invalid JSON input: ' . json_last_error_msg(), 400);
    }
    
    $action = $input['action'] ?? '';
    $coinId = $input['coinId'] ?? '';
    $amount = $input['amount'] ?? 0;
    
    // Log the parsed input for debugging
    error_log("Trade API - Action: $action, CoinId: $coinId, Amount: $amount");
    
    // Special validation for sell action with 'all' amount
    if ($action === 'sell' && $amount === 'all') {
        // This is valid, we'll handle it later
    } else if (empty($action) || empty($coinId) || ($amount <= 0 && $amount !== 'all')) {
        returnJsonError('Invalid parameters. Action, coinId, and amount are required.', 400);
    }
} catch (Exception $e) {
    returnJsonError('Error processing input: ' . $e->getMessage(), 400);
}

// Get current price and coin data based on action (buy or sell)
try {
    $db = getDBConnection();
    if (!$db) {
        returnJsonError('Database connection failed', 500);
    }
    
    $price = null;
    $symbol = null;
    $name = null;
    $originalCoinId = $coinId; // Save the original input for logging
    
    error_log("Processing $action for coin: $coinId");
    
    // Different lookup strategy based on action
    if ($action === 'buy') {
        // For buying, first look in the coins table
        error_log("BUY action - Looking up coin in coins table first");
        $foundInCoins = false;
        
        if (is_numeric($coinId)) {
            // Look up by ID in coins table
            $stmt = $db->prepare("SELECT id, current_price, symbol, name, market_cap, volume_24h FROM coins WHERE id = ?");
            if (!$stmt) {
                returnJsonError('Database prepare error: ' . $db->error, 500);
            }
            
            $stmt->bind_param('i', $coinId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $price = $row['current_price'];
                $symbol = $row['symbol'];
                $name = $row['name'];
                $marketCap = $row['market_cap'];
                $volume24h = $row['volume_24h'];
                $coinId = $row['id']; // Keep using the numeric ID
                $foundInCoins = true;
                error_log("Found coin in coins table by ID: $name ($symbol) with price: $price");
            }
        } else {
            // Look up by symbol in coins table
            $stmt = $db->prepare("SELECT id, current_price, symbol, name, market_cap, volume_24h FROM coins WHERE symbol = ?");
            if (!$stmt) {
                returnJsonError('Database prepare error: ' . $db->error, 500);
            }
            
            $stmt->bind_param('s', $coinId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $price = $row['current_price'];
                $symbol = $row['symbol'];
                $name = $row['name'];
                $marketCap = $row['market_cap'];
                $volume24h = $row['volume_24h'];
                $coinId = $row['id']; // Use the numeric ID
                $foundInCoins = true;
                error_log("Found coin in coins table by symbol: $name ($symbol) with price: $price");
            }
        }
        
        // If not found in coins table, try to find in the CSV file
        if (!$foundInCoins) {
            error_log("Coin not found in coins table, checking CSV file");
            $csvFile = __DIR__ . '/../../data/csv/crypto_data_*.csv';
            $csvFiles = glob($csvFile);
            
            if (!empty($csvFiles)) {
                // Sort by modification time to get the most recent file
                usort($csvFiles, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                $latestCsv = $csvFiles[0];
                
                if (($handle = fopen($latestCsv, 'r')) !== false) {
                    $header = fgetcsv($handle); // Skip header
                    
                    while (($data = fgetcsv($handle)) !== false) {
                        $csvSymbol = $data[1] ?? ''; // Symbol is in the second column
                        $csvName = $data[0] ?? '';   // Name is in the first column
                        
                        // Try to match by symbol or name
                        if (strtoupper($csvSymbol) === strtoupper($coinId) || 
                            strtoupper($csvName) === strtoupper($coinId)) {
                            
                            // Try to extract price (remove $ and commas)
                            $priceStr = str_replace(['$', ','], '', $data[3] ?? '0');
                            $price = is_numeric($priceStr) ? (float)$priceStr : 0;
                            
                            $symbol = $csvSymbol;
                            $name = $csvName;
                            
                            // Generate a unique ID for this coin since it's not in the coins table
                            $coinId = 'CSV_' . strtoupper($symbol);
                            
                            error_log("Found coin in CSV: $name ($symbol) with price: $price");
                            break;
                        }
                    }
                    fclose($handle);
                }
            }
            
            // If still not found, return error
            if ($price === null) {
                returnJsonError("Coin not found in coins table or CSV: $originalCoinId", 404);
            }
        }
    } else if ($action === 'sell') {
        // For selling, ONLY check the portfolio table
        error_log("SELL action - ONLY checking portfolio table for coin: $coinId");
        
        // Check if this is a numeric ID from coins table
        if (is_numeric($coinId)) {
            // Convert numeric ID to portfolio ID format
            $stmt = $db->prepare("SELECT symbol FROM coins WHERE id = ?");
            if (!$stmt) {
                returnJsonError('Database prepare error: ' . $db->error, 500);
            }
            
            $stmt->bind_param('i', $coinId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $symbol = $row['symbol'];
                $portfolioId = "COIN_" . $symbol;
                error_log("Converting numeric ID $coinId to portfolio ID: $portfolioId");
                $coinId = $portfolioId;
            }
        }
        
        // Check portfolio
        $portfolioStmt = $db->prepare("SELECT p.coin_id, p.amount, p.avg_buy_price, 
                                     COALESCE(c.symbol, cr.symbol) as symbol, 
                                     COALESCE(c.name, cr.name) as name,
                                     COALESCE(c.current_price, cr.price) as price
                                     FROM portfolio p
                                     LEFT JOIN coins c ON c.symbol = SUBSTRING(p.coin_id, 6)
                                     LEFT JOIN cryptocurrencies cr ON cr.id = p.coin_id
                                     WHERE p.coin_id = ?");
        if (!$portfolioStmt) {
            returnJsonError('Database prepare error: ' . $db->error, 500);
        }
        
        $portfolioStmt->bind_param('s', $coinId);
        $portfolioStmt->execute();
        $portfolioResult = $portfolioStmt->get_result();
        
        if ($portfolioResult && $portfolioResult->num_rows > 0) {
            $portfolioRow = $portfolioResult->fetch_assoc();
            $portfolioAmount = $portfolioRow['amount'];
            $symbol = $portfolioRow['symbol'];
            $name = $portfolioRow['name'];
            $price = $portfolioRow['price'];
            
            error_log("Found coin in portfolio: $coinId with amount: $portfolioAmount, symbol: $symbol, price: $price");
            
            // If price is null or zero, try to get it from cryptocurrencies or coins table
            if ($price === null || $price == 0) {
                // Try to get price from cryptocurrencies table
                $cryptoStmt = $db->prepare("SELECT price FROM cryptocurrencies WHERE symbol = ?");
                if ($cryptoStmt) {
                    $cryptoStmt->bind_param('s', $symbol);
                    $cryptoStmt->execute();
                    $cryptoResult = $cryptoStmt->get_result();
                    
                    if ($cryptoResult && $cryptoResult->num_rows > 0) {
                        $row = $cryptoResult->fetch_assoc();
                        $price = $row['price'];
                        error_log("Got price from cryptocurrencies table: $price");
                    }
                    $cryptoStmt->close();
                }
                
                // If still no price, try coins table
                if ($price === null || $price == 0) {
                    $coinStmt = $db->prepare("SELECT current_price FROM coins WHERE symbol = ?");
                    if ($coinStmt) {
                        $coinStmt->bind_param('s', $symbol);
                        $coinStmt->execute();
                        $coinResult = $coinStmt->get_result();
                        
                        if ($coinResult && $coinResult->num_rows > 0) {
                            $row = $coinResult->fetch_assoc();
                            $price = $row['current_price'];
                            error_log("Got price from coins table: $price");
                        }
                        $coinStmt->close();
                    }
                }
            }
        } else {
            error_log("Coin not found in portfolio: $coinId");
            returnJsonError("Coin not found in your portfolio: $originalCoinId", 404);
        }
    }
    
    // If we couldn't find the coin or its price
    if ($price === null || $price <= 0) {
        returnJsonError("No valid price available for coin: $originalCoinId", 404);
    }  
    error_log("Final coin data for $action: $name ($symbol) with ID: $coinId and price: $price");
} catch (Exception $e) {
    returnJsonError('Error looking up coin: ' . $e->getMessage(), 500);
}

// We already have the price from our earlier query
// No need to fetch it again

// Clean any output that might have been generated before this point
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Simulate trade execution
if ($action === 'buy') {
    try {
        if (!function_exists('executeBuy')) {
            returnJsonError('Buy function not available', 500);
        }
        
        $tradeId = executeBuy($coinId, $amount, $price);
        
        if ($tradeId === false) {
            returnJsonError('Failed to execute buy order', 500);
        } else {
            // Clean any output that might have been generated
            while (ob_get_level()) {
                ob_end_clean();
            }
            ob_start();
            
            echo json_encode([
                'success' => true,
                'message' => 'Buy order executed',
                'tradeId' => $tradeId,
                'details' => [
                    'coin' => $name ?? $symbol,
                    'symbol' => $symbol,
                    'amount' => $amount,
                    'price' => $price,
                    'total' => $amount * $price
                ]
            ]);
            
            // End output buffering and flush
            ob_end_flush();
            exit;
        }
    } catch (Exception $e) {
        returnJsonError('Buy error: ' . $e->getMessage(), 500);
    }
} elseif ($action === 'sell') {
    try {
        // Get user's current balance for this coin
        if (!function_exists('getUserCoinBalance')) {
            returnJsonError('Balance function not available', 500);
        }
        
        $portfolioData = getUserCoinBalance($coinId);
        $userBalance = $portfolioData['amount'];
        
        // Check if user has any coins to sell
        if ($userBalance <= 0) {
            returnJsonError('You don\'t have any coins to sell.', 400);
        }
        
        // Check if amount is 0 or 'all' to sell entire balance
        if ($amount === 'all') {
            $amount = $userBalance;
        }
        
        // Make sure user isn't trying to sell more than they have
        if ($amount > $userBalance) {
            returnJsonError("You can't sell more than you own. Your balance: {$userBalance}", 400);
        }
        
        // Execute the sell operation
        if (!function_exists('executeSell')) {
            returnJsonError('Sell function not available', 500);
        }
        
        $result = executeSell($coinId, $amount, $price);
        
        // Clean any output that might have been generated
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        
        // Return the result directly from executeSell
        if (is_array($result)) {
            echo json_encode($result);
        } else {
            $tradeId = $result;
            $profit = 0; // Default value if not available
            
            echo json_encode([
                'success' => true,
                'message' => 'Sell order executed',
                'tradeId' => $tradeId,
                'details' => [
                    'coin' => $name ?? $symbol,
                    'symbol' => $symbol,
                    'amount' => $amount,
                    'price' => $price,
                    'total' => $amount * $price,
                    'profit' => $profit
                ]
            ]);
        }
        
        // End output buffering and flush
        ob_end_flush();
        exit;
    } catch (Exception $e) {
        returnJsonError('Sell error: ' . $e->getMessage(), 500);
    }
} else {
    // Invalid action
    returnJsonError('Invalid action: ' . $action, 400);
}