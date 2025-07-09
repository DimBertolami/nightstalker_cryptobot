<?php
// Set JSON content type header
header('Content-Type: application/json');

// Set CORS headers to allow browser requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

// Create a function to return JSON response
function returnJsonResponse($data) {
    // Clean any output that might have been generated
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code(200);
    echo json_encode($data);
    exit;
}

// Include required files
try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/database.php';
    require_once __DIR__ . '/../includes/pdo_functions.php';
} catch (Exception $e) {
    returnJsonError('Failed to load required files: ' . $e->getMessage());
}

// This is a simplified version - in production you'd have proper authentication
try {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Don't display errors, but log them
    
    // Get raw input and log it
    $rawInput = file_get_contents('php://input');
    error_log("[trade.php] Raw input: $rawInput");
    file_put_contents('/opt/lampp/htdocs/NS/logs/trade_api.log', date('Y-m-d H:i:s') . " Raw input: $rawInput\n", FILE_APPEND);
    
    // Handle empty input
    if (empty($rawInput)) {
        error_log("[trade.php] Empty input received");
        file_put_contents('/opt/lampp/htdocs/NS/logs/trade_api.log', date('Y-m-d H:i:s') . " Empty input received\n", FILE_APPEND);
        returnJsonError('No input data provided', 400);
    }
    
    // Decode JSON with detailed error handling
    $input = json_decode($rawInput, true);
    $jsonError = json_last_error();
    
    if ($jsonError !== JSON_ERROR_NONE) {
        $errorMsg = json_last_error_msg();
        error_log("[trade.php] Invalid JSON input: $errorMsg");
        file_put_contents('/opt/lampp/htdocs/NS/logs/trade_api.log', date('Y-m-d H:i:s') . " Invalid JSON input: $errorMsg\n", FILE_APPEND);
        returnJsonError('Invalid JSON input: ' . $errorMsg, 400);
    }
    
    // Log successful parsing
    error_log("[trade.php] JSON successfully parsed: " . json_encode($input));
    file_put_contents('/opt/lampp/htdocs/NS/logs/trade_api.log', date('Y-m-d H:i:s') . " JSON parsed: " . json_encode($input) . "\n", FILE_APPEND);
    
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
                returnJsonError('Database prepare error: PDO prepare failed', 500);
            }
            
            $stmt->bindParam(1, $coinId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
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
                returnJsonError('Database prepare error: PDO prepare failed', 500);
            }
            
            $stmt->bindParam(1, $coinId, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $price = $row['current_price'];
                $symbol = $row['symbol'];
                $name = $row['name'];
                $marketCap = $row['market_cap'];
                $volume24h = $row['volume_24h'];
                $coinId = $row['id']; // Use the numeric ID for consistency
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
        error_log("SELL action - Using direct portfolio lookup for coin ID: $coinId");
        
        // Simple direct portfolio lookup by coin_id and user_id
        $portfolioStmt = $db->prepare("SELECT p.coin_id, p.amount, p.avg_buy_price, 
                                     p.coin_id as symbol, 
                                     p.coin_id as name,
                                     COALESCE(
                                         (SELECT price FROM cryptocurrencies WHERE symbol = p.coin_id OR id = p.coin_id LIMIT 1),
                                         (SELECT current_price FROM coins WHERE symbol = p.coin_id OR id = p.coin_id LIMIT 1),
                                         0
                                     ) as price
                                     FROM portfolio p
                                     WHERE p.coin_id = :coin_id AND p.user_id = 1");
        if (!$portfolioStmt) {
            returnJsonError('Database prepare error: PDO prepare failed', 500);
        }
        
        // Bind parameters for the query
        $portfolioStmt->bindParam(':coin_id', $coinId, PDO::PARAM_STR);
        $portfolioStmt->execute();
        $portfolioResult = $portfolioStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($portfolioResult)) {
            $portfolioRow = $portfolioResult[0];
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
                    $cryptoStmt->bindParam(1, $symbol, PDO::PARAM_STR);
                    $cryptoStmt->execute();
                    $row = $cryptoStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($row) {
                        $price = $row['price'];
                        error_log("Got price from cryptocurrencies table: $price");
                    }
                }
                
                // If still no price, try coins table
                if ($price === null || $price == 0) {
                    $coinStmt = $db->prepare("SELECT current_price FROM coins WHERE symbol = ?");
                    if ($coinStmt) {
                        $coinStmt->bindParam(1, $symbol, PDO::PARAM_STR);
                        $coinStmt->execute();
                        $row = $coinStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($row) {
                            $price = $row['current_price'];
                            error_log("Got price from coins table: $price");
                        }
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
        // Log the sell request details
        error_log("[trade.php] Sell request received for coin ID: $coinId, amount: $amount, price: $price");
        
        // Ensure coin ID is treated as a string
        $coinId = (string)$coinId;
        error_log("[trade.php] Coin ID converted to string: $coinId");
        
        // Check if price is provided, if not, use current price
        if (empty($price) || $price <= 0) {
            error_log("[trade.php] No valid price provided, using current price");
            // Default to a reasonable price if none provided
            $price = 0.01;
        }
        
        // Skip the portfolio lookup and go straight to executeSellPDO
        // The executeSellPDO function already handles portfolio lookup internally
        if (function_exists('executeSellPDO')) {
            error_log("[trade.php] Using executeSellPDO function directly");
            
            // Check if amount is 'all' to sell entire balance
            if ($amount === 'all') {
                error_log("[trade.php] Selling all coins for coin ID: $coinId");
                
                // Get the user's balance for this coin
                $db = getDBConnection();
                $stmt = $db->prepare("SELECT amount FROM portfolio WHERE coin_id = ? AND user_id = 1");
                $stmt->execute([$coinId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && isset($result['amount'])) {
                    $amount = (float)$result['amount'];
                    error_log("[trade.php] Found balance for 'all': $amount");
                } else {
                    error_log("[trade.php] Could not find balance for 'all' amount");
                    returnJsonError("Could not determine balance for coin: $coinId", 404);
                }
            } else {
                // Convert amount to float for comparison
                $amount = (float)$amount;
                error_log("[trade.php] Selling $amount coins for coin ID: $coinId");
            }
            
            // Execute the sell operation directly
            $result = executeSellPDO($coinId, $amount, $price);
            
            // Check if the sell was successful
            if (!$result['success']) {
                error_log("[trade.php] Sell failed: " . $result['message']);
                returnJsonError($result['message'], 400);
            }
            
            // If we get here, the sell was successful
            error_log("[trade.php] Sell successful: " . $result['message']);
            
            // Return the result
            returnJsonResponse([
                'success' => true,
                'message' => $result['message'],
                'profit_loss' => $result['profit_loss'],
                'profit_percentage' => $result['profit_percentage'],
                'trade_id' => $result['trade_id']
            ]);
        } else {
            error_log("[trade.php] executeSellPDO function not available");
            returnJsonError('Sell function not available', 500);
        }
        
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