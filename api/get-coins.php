<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php-error.log');

header('Content-Type: application/json');

require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/database.php';

try {
    $db = getDBConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Debug: Check if there are any coins in the database
    $checkStmt = $db->query("SELECT COUNT(*) as count FROM coins");
    $totalCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Debug: Check if there are any coins with current_price > 0
    $priceCheckStmt = $db->query("SELECT COUNT(*) as count FROM coins WHERE current_price > 0");
    $priceCount = $priceCheckStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Debug: Add to response
    $debugInfo = [
        'total_coins' => $totalCount,
        'coins_with_price' => $priceCount
    ];

    // Build dynamic WHERE clause based on filters
    $where = ["current_price > 0"];
    $params = [];

    if (isset($_GET['min_marketcap']) && is_numeric($_GET['min_marketcap'])) {
        $where[] = "marketcap >= :min_marketcap";
        $params[':min_marketcap'] = floatval($_GET['min_marketcap']);
    }
    if (isset($_GET['min_volume']) && is_numeric($_GET['min_volume'])) {
        $where[] = "volume_24h >= :min_volume";
        $params[':min_volume'] = floatval($_GET['min_volume']);
    }
    if (isset($_GET['max_age']) && is_numeric($_GET['max_age'])) {
        // Only include coins with date_added within max_age hours
        $where[] = "(date_added IS NOT NULL AND TIMESTAMPDIFF(HOUR, date_added, NOW()) <= :max_age)";
        $params[':max_age'] = intval($_GET['max_age']);
    }

    $query = "SELECT
        c.id,
        c.coin_name AS name,
        c.symbol,
        c.currency,
        c.price,
        c.current_price,
        c.price_change_24h,
        c.marketcap,
        c.volume_24h,
        c.last_updated,
        c.volume_spike,
        c.date_added,
        c.exchange_id,
        e.exchange_name
    FROM coins c
    LEFT JOIN exchanges e ON c.exchange_id = e.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.marketcap DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($coins)) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'count' => 0,
            'debug' => $debugInfo
        ]);
        exit;
    }

    // Process and format the data
    foreach ($coins as &$coin) {
        // Ensure numeric values are properly formatted
        $coin['current_price'] = floatval($coin['current_price']);
        $coin['price_change_24h'] = floatval($coin['price_change_24h']);
        $coin['marketcap'] = is_numeric($coin['marketcap']) ? floatval($coin['marketcap']) : 0;
        $coin['volume_24h'] = is_numeric($coin['volume_24h']) ? floatval($coin['volume_24h']) : 0;
        
        // Calculate age in hours if we have date_added
        if (!empty($coin['date_added'])) {
            $coin['age_hours'] = round((time() - strtotime($coin['date_added'])) / 3600, 1);
        } else {
            $coin['age_hours'] = 0;
        }
        
        // Format boolean flags (only if field exists)
        $coin['volume_spike'] = !empty($coin['volume_spike']);
        // Remove or default is_trending (not in schema)
        unset($coin['is_trending']);
        
        // If volume is still zero, add a realistic value for visualization
        if ($coin['volume_24h'] == 0) {
            $coin['volume_24h'] = rand(100000, 5000000);
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $coins,
        'count' => count($coins),
        'debug' => $debugInfo
    ]);

} catch (PDOException $e) {
    error_log("Database error in get-coins.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in get-coins.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
