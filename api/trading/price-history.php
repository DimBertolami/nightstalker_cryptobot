<?php
/**
 * Price History API Endpoint
 * For Night Stalker Cryptobot
 * 
 * Returns price history data for coins at different time intervals
 * Optimized for autonomous trading strategy visualization
 */

// Prevent any output before our JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Buffer all output to prevent headers already sent errors
ob_start();

try {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/functions.php';
    require_once __DIR__ . '/../../includes/pdo_functions.php';
    require_once __DIR__ . '/../../includes/auth.php';
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    requireAuth();
    
    // Set headers
    header('Content-Type: application/json');
    
    // Get selected exchange from request or session
    $exchange = $_GET['exchange'] ?? $_SESSION['selected_exchange'] ?? 'binance';
    
    // Get interval parameter (optional)
    $interval = $_GET['interval'] ?? 'auto';
    
    $db = getDBConnection();
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Get all coins with price history data
    $stmt = $db->prepare("SELECT DISTINCT coin_id FROM price_history");
    $stmt->execute();
    $availableCoins = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If no coins with price history, return empty array
    if (empty($availableCoins)) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => 'No price history data available'
        ]);
        exit;
    }
    
    // Get price history data for each coin
    $priceHistoryData = [];
    
    // Get current user ID safely
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    foreach ($availableCoins as $coinId) {
        // Get the most recent price data
        $stmt = $db->prepare("
        SELECT price, recorded_at
        FROM price_history 
        WHERE coin_id = ? 
        ORDER BY recorded_at DESC 
        LIMIT 1
        ");
        $stmt->execute([$coinId]);
        $latestData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$latestData) {
            continue; // Skip if no data found
        }
        
        $currentPrice = (float)$latestData['price'];
        $currentTime = strtotime($latestData['recorded_at']);
        
        // Get all price history for this coin
        $stmt = $db->prepare("
            SELECT price, recorded_at 
            FROM price_history 
            WHERE coin_id = ? 
            ORDER BY recorded_at ASC
        ");
        $stmt->execute([$coinId]);
        $priceHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate price changes at different intervals
        $pricePoints = [];
        $firstPrice = (float)$priceHistory[0]['price'];
        $firstTime = strtotime($priceHistory[0]['recorded_at']);
        $totalChange = calculatePercentChange($firstPrice, $currentPrice);
        
        // Calculate time-based intervals for the available data
        $totalTimeSpan = $currentTime - $firstTime; // in seconds
        $interval1 = $totalTimeSpan / 5; // 1/5 of total time
        $interval2 = $totalTimeSpan / 4; // 1/4 of total time
        $interval3 = $totalTimeSpan / 3; // 1/3 of total time
        $interval4 = $totalTimeSpan / 2; // 1/2 of total time
        
        // Initialize change values
        $change1 = null;
        $change2 = null;
        $change3 = null;
        $change4 = null;
        $change5 = $totalChange; // Total change
        
        // Process historical prices
        foreach ($priceHistory as $record) {
            $timestamp = strtotime($record['recorded_at']);
            $timeDiff = $currentTime - $timestamp;
            $price = (float)$record['price'];
            
            // Add to price points for sparkline (sample evenly)
            if (count($priceHistory) <= 24 || count($pricePoints) % (intval(count($priceHistory) / 24) + 1) == 0) {
                if (count($pricePoints) < 24) {
                    $pricePoints[] = $price;
                }
            }
            
            // Calculate changes at specific intervals
            if ($timeDiff <= $interval1 && $change1 === null) {
                $change1 = calculatePercentChange($price, $currentPrice);
            }
            
            if ($timeDiff <= $interval2 && $change2 === null) {
                $change2 = calculatePercentChange($price, $currentPrice);
            }
            
            if ($timeDiff <= $interval3 && $change3 === null) {
                $change3 = calculatePercentChange($price, $currentPrice);
            }
            
            if ($timeDiff <= $interval4 && $change4 === null) {
                $change4 = calculatePercentChange($price, $currentPrice);
            }
        }
        
        // If we don't have enough data points, interpolate
        if (count($pricePoints) < 5) {
            // Use linear interpolation to create more points
            $pricePoints = interpolatePricePoints($pricePoints, 24);
        }
        
        // Format interval labels based on total time span
        $totalHours = $totalTimeSpan / 3600;
        
        if ($totalHours < 1) {
            // Less than 1 hour of data
            $intervalLabels = [
                'i1' => round($totalTimeSpan / 5 / 60) . 'm',
                'i2' => round($totalTimeSpan / 4 / 60) . 'm',
                'i3' => round($totalTimeSpan / 3 / 60) . 'm',
                'i4' => round($totalTimeSpan / 2 / 60) . 'm',
                'i5' => round($totalTimeSpan / 60) . 'm'
            ];
        } else if ($totalHours < 24) {
            // Less than 24 hours of data
            $intervalLabels = [
                'i1' => round($totalHours / 5, 1) . 'h',
                'i2' => round($totalHours / 4, 1) . 'h',
                'i3' => round($totalHours / 3, 1) . 'h',
                'i4' => round($totalHours / 2, 1) . 'h',
                'i5' => round($totalHours, 1) . 'h'
            ];
        } else {
            // More than 24 hours of data
            $totalDays = $totalHours / 24;
            $intervalLabels = [
                'i1' => round($totalDays / 5, 1) . 'd',
                'i2' => round($totalDays / 4, 1) . 'd',
                'i3' => round($totalDays / 3, 1) . 'd',
                'i4' => round($totalDays / 2, 1) . 'd',
                'i5' => round($totalDays, 1) . 'd'
            ];
        }
        
        // Get portfolio amount if available
        $stmt = $db->prepare("SELECT SUM(amount) as total_amount FROM portfolio WHERE user_id = ? AND coin_id = ?");
        $stmt->execute([$userId, $coinId]);
        $portfolioData = $stmt->fetch(PDO::FETCH_ASSOC);
        $portfolioAmount = $portfolioData ? (float)$portfolioData['total_amount'] : 0;
        
        // Add coin data to result
        $priceHistoryData[] = [
            'symbol' => $coinId,
            'name' => ucfirst(strtolower(str_replace('COIN_', '', $coinId))),
            'current_price' => $currentPrice,
            'change_1' => (float)$change1 ?? 0,
            'change_2' => (float)$change2 ?? 0,
            'change_3' => (float)$change3 ?? 0,
            'change_4' => (float)$change4 ?? 0,
            'change_5' => (float)$change5 ?? 0,
            'interval_labels' => $intervalLabels,
            'price_points' => $pricePoints,
            'amount' => $portfolioAmount,
            //'volume' => (float)$latestData['volume'],
            //'market_cap' => (float)$latestData['market_cap'],
            'in_portfolio' => $portfolioAmount > 0
        ];
    }
    
    // Sort by whether in portfolio first, then by total change
    usort($priceHistoryData, function($a, $b) {
        if ($a['in_portfolio'] != $b['in_portfolio']) {
            return $b['in_portfolio'] - $a['in_portfolio']; // Portfolio coins first
        }
        return $b['change_5'] - $a['change_5']; // Then by total change (descending)
    });
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $priceHistoryData,
        'timestamp' => time(),
        'interval' => $interval
    ]);
    
} catch (Exception $e) {
    // Clear any previous output
    ob_clean();
    
    // Return error response
    error_log("Price history API error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving price history data: ' . $e->getMessage()
    ]);
} catch (Throwable $e) {
    // Catch any other errors
    ob_clean();
    
    // Return error response
    error_log("Price history API critical error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Critical error retrieving price history data'
    ]);
}

// End output buffering and flush
ob_end_flush();

/**
 * Calculate percent change between two prices
 * 
 * @param float $oldPrice Old price
 * @param float $newPrice New price
 * @return float Percent change
 */
function calculatePercentChange($oldPrice, $newPrice) {
    if ($oldPrice == 0) return 0;
    return (($newPrice - $oldPrice) / $oldPrice) * 100;
}

/**
 * Interpolate price points to create a smooth curve with desired number of points
 * 
 * @param array $points Original price points
 * @param int $targetCount Desired number of points
 * @return array Interpolated price points
 */
function interpolatePricePoints($points, $targetCount) {
    // If we have no points or just one, return an array of that value repeated
    if (empty($points)) {
        return array_fill(0, $targetCount, 0);
    }
    
    if (count($points) == 1) {
        return array_fill(0, $targetCount, $points[0]);
    }
    
    // If we already have enough points, return as is
    if (count($points) >= $targetCount) {
        return array_slice($points, 0, $targetCount);
    }
    
    $result = [];
    $originalCount = count($points);
    
    // Linear interpolation between existing points
    for ($i = 0; $i < $targetCount; $i++) {
        $position = $i / ($targetCount - 1) * ($originalCount - 1);
        $index = floor($position);
        $fraction = $position - $index;
        
        if ($index + 1 < $originalCount) {
            $result[] = $points[$index] * (1 - $fraction) + $points[$index + 1] * $fraction;
        } else {
            $result[] = $points[$index];
        }
    }
    
    return $result;
}
