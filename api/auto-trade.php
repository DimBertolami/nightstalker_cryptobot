<?php
/**
 * Autonomous Trading API Endpoint
 * 
 * Handles automatic trading based on selected strategies
 * Part of the Night Stalker cryptobot platform
 */

header('Content-Type: application/json');

// Enable error reporting but don't display errors to the client
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/opt/lampp/htdocs/NS/logs/php-error.log');

// Include configuration and required files
require_once '../includes/config.php';
require_once '../includes/strategies/StrategyFactory.php';

// Include trader classes
$traderClasses = glob('../includes/traders/*.php');
foreach ($traderClasses as $traderClass) {
    require_once $traderClass;
}

// Direct database connection to avoid circular dependencies
function getDBConnection() {
    static $db = null;
    
    if ($db === null) {
        try {
            $db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            return false;
        }
    }
    
    return $db;
}

// Function to get a trader instance based on exchange name
function getTraderInstance($exchangeName) {
    // Default to Binance if not specified
    $exchange = $exchangeName ?? 'Binance';
    
    // Check if trader class exists
    $className = "\\NightStalker\\Traders\\{$exchange}Trader";
    if (!class_exists($className)) {
        // Fallback to mock trader for testing
        return new class() {
            public function isConnected() { return true; }
            public function getBalance($symbol) { return 1000; } // Mock balance
            public function placeOrder($symbol, $action, $amount, $price) {
                return [
                    'id' => uniqid(),
                    'symbol' => $symbol,
                    'action' => $action,
                    'amount' => $amount,
                    'price' => $price,
                    'status' => 'filled',
                    'timestamp' => time()
                ];
            }
        };
    }
    
    // Create and return trader instance
    try {
        return new $className();
    } catch (\Exception $e) {
        error_log("Failed to create trader instance: " . $e->getMessage());
        throw new \Exception("Failed to create trader for exchange: $exchange");
    }
}

// Function to load strategy configuration
function loadStrategyConfig($strategyName) {
    $db = getDBConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get strategy by name
    $stmt = $db->prepare("SELECT * FROM strategies WHERE name = :name OR type = :type");
    $stmt->bindParam(':name', $strategyName);
    $stmt->bindParam(':type', $strategyName);
    $stmt->execute();
    $strategy = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$strategy) {
        throw new Exception("Strategy not found: $strategyName");
    }
    
    if (!$strategy['is_active']) {
        throw new Exception("Strategy is not active: $strategyName");
    }
    
    // Parse config
    $config = json_decode($strategy['config'], true);
    if (!$config) {
        throw new Exception("Invalid strategy configuration for: $strategyName");
    }
    
    // Update last run timestamp
    $stmt = $db->prepare("UPDATE strategies SET last_run = CURRENT_TIMESTAMP WHERE id = :id");
    $stmt->bindParam(':id', $strategy['id']);
    $stmt->execute();
    
    return [
        'name' => $strategy['name'],
        'type' => $strategy['type'],
        'config' => $config
    ];
}

// Function to execute trades using the execute-trade.php endpoint
function executeTrade($action, $coinId, $symbol, $amount, $price, $strategy) {
    $url = 'http://localhost/NS/api/execute-trade.php';
    
    $data = [
        'action' => $action,
        'coin_id' => $coinId,
        'symbol' => $symbol,
        'amount' => $amount,
        'price' => $price,
        'strategy' => $strategy
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        throw new Exception("Failed to execute trade");
    }
    
    return json_decode($result, true);
}

try {
    // Check if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }
    
    // Check if strategy parameter is provided
    if (!isset($_POST['strategy']) || empty($_POST['strategy'])) {
        throw new Exception('Strategy parameter is required');
    }
    
    $strategyName = $_POST['strategy'];
    $exchangeName = isset($_POST['exchange']) ? $_POST['exchange'] : 'Binance'; // Default to Binance
    
    // Get database connection
    $db = getDBConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Load strategy configuration
    $strategyData = loadStrategyConfig($strategyName);
    
    // Get trader instance
    $trader = getTraderInstance($exchangeName);
    
    // Create strategy instance
    $strategyType = $strategyData['type'] ?? $strategyName;
    $strategyConfig = $strategyData['config'];
    
    // Use the strategy factory to create the appropriate strategy
    $strategy = \NightStalker\Strategies\StrategyFactory::createStrategy(
        $strategyType,
        $strategyConfig,
        $trader,
        $db
    );
    
    // Execute the strategy
    $result = $strategy->execute();
    
    // If using the direct API execution method instead of the strategy class
    if (isset($_POST['use_direct_api']) && $_POST['use_direct_api'] === 'true') {
        // For backward compatibility, execute trades using the old method
        // This section can be removed once all clients are updated to use the new strategy classes
        $trades = [];
        
        // Execute the trade through the execute-trade.php endpoint
        foreach ($result['trades'] as $trade) {
            try {
                $tradeResult = executeTrade(
                    $trade['action'],
                    $trade['coin_id'] ?? 0,
                    $trade['symbol'],
                    $trade['amount'],
                    $trade['price'],
                    $strategyName
                );
                
                if ($tradeResult['success']) {
                    $trades[] = $trade;
                }
            } catch (Exception $e) {
                error_log("Error executing trade: " . $e->getMessage());
            }
        }
        
        $result['trades'] = $trades;
    }
    
    // Return success response
    echo json_encode([
        'success' => $result['success'],
        'message' => $result['message'],
        'strategy' => $strategyName,
        'exchange' => $exchangeName,
        'trades' => $result['trades']
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
