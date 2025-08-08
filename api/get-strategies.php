<?php
/**
 * Get Strategies API Endpoint
 * 
 * Returns all trading strategies for the Night Stalker cryptobot
 */

header('Content-Type: application/json');

// Enable error reporting but don't display errors to the client
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/opt/lampp/htdocs/NS/logs/php-error.log');

// Include configuration
require_once '../includes/config.php';

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

try {
    // Get database connection
    $db = getDBConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Check if strategies table exists, create if not
    $stmt = $db->prepare("SHOW TABLES LIKE 'strategies'");
    $stmt->execute();
    $hasStrategiesTable = $stmt->rowCount() > 0;
    
    if (!$hasStrategiesTable) {
        // Create strategies table
        $db->exec("CREATE TABLE strategies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            description TEXT,
            type VARCHAR(50) NOT NULL,
            config JSON NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            last_run TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Insert default strategies
        $defaultStrategies = [
            [
                'name' => 'Volume Spike Strategy',
                'description' => 'Buy coins with significant volume increases in the last 24 hours',
                'type' => 'volume_spike',
                'config' => json_encode([
                    'min_volume_increase' => 20, // 20% increase
                    'timeframe' => '24h',
                    'max_investment' => 100, // $100 max per trade
                    'stop_loss' => 5, // 5% stop loss
                    'take_profit' => 10 // 10% take profit
                ]),
                'is_active' => true
            ],
            [
                'name' => 'Trending Coins Strategy',
                'description' => 'Buy new trending coins with good market capitalization',
                'type' => 'trending_coins',
                'config' => json_encode([
                    'min_market_cap' => 1000000, // $1M minimum market cap
                    'max_age_hours' => 24, // Only consider coins added in last 24h
                    'max_investment' => 50, // $50 max per trade
                    'stop_loss' => 7, // 7% stop loss
                    'take_profit' => 15 // 15% take profit
                ]),
                'is_active' => true
            ]
        ];
        
        foreach ($defaultStrategies as $strategy) {
            $stmt = $db->prepare("INSERT INTO strategies (name, description, type, config, is_active) 
                VALUES (:name, :description, :type, :config, :is_active)");
            $stmt->bindParam(':name', $strategy['name']);
            $stmt->bindParam(':description', $strategy['description']);
            $stmt->bindParam(':type', $strategy['type']);
            $stmt->bindParam(':config', $strategy['config']);
            $stmt->bindParam(':is_active', $strategy['is_active'], PDO::PARAM_BOOL);
            $stmt->execute();
        }
    }
    
    // Get all strategies
    $stmt = $db->prepare("SELECT * FROM strategies ORDER BY name");
    $stmt->execute();
    $strategies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Strategies retrieved successfully',
        'data' => $strategies
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
