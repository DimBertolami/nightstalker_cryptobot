<?php
/**
 * New Coin Price History API Endpoint
 * For Night Stalker Cryptobot
 * 
 * Returns price history data for newly added coins at different time intervals
 */

// Prevent any output before our JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Buffer all output to prevent headers already sent errors
ob_start();

try {
    require_once '/opt/lampp/htdocs/NS/includes/config.php';
    require_once '/opt/lampp/htdocs/NS/includes/functions.php';
    require_once '/opt/lampp/htdocs/NS/includes/pdo_functions.php';
    require_once '/opt/lampp/htdocs/NS/includes/auth.php';
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    requireAuth();
    
    // Set headers
    header('Content-Type: application/json');
    
    $db = getDBConnection();
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Get all new coins from the newcoins table
    $stmt = $db->prepare("SELECT symbol FROM newcoins");
    $stmt->execute();
    $newCoinSymbols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If no new coins, return empty array
    if (empty($newCoinSymbols)) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => 'No new coin data available'
        ]);
        exit;
    }
    
    $priceHistoryData = [];
    
    foreach ($newCoinSymbols as $coinSymbol) {
        // Get the most recent price data for this new coin
        $stmt = $db->prepare("
        SELECT price, recorded_at
        FROM price_history 
        WHERE coin_id = ? 
        ORDER BY recorded_at DESC 
        LIMIT 1
        ");
        $stmt->execute([$coinSymbol]);
        $latestData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$latestData) {
            continue; // Skip if no data found for this coin
        }
        
        $currentPrice = (float)$latestData['price'];
        $currentTime = strtotime($latestData['recorded_at']);
        
        // Get all price history for this new coin
        $stmt = $db->prepare("
            SELECT price, recorded_at 
            FROM price_history 
            WHERE coin_id = ? 
            ORDER BY recorded_at ASC
        ");
        $stmt->execute([$coinSymbol]);
        $priceHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate price changes at different intervals and generate price points for sparkline
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
            $price = (float)$record['price'];
            
            // Add to price points for sparkline (sample evenly)
            // Ensure we get at least 2 points for a line, and up to 24 for detail
            if (count($priceHistory) <= 24 || count($pricePoints) % (intval(count($priceHistory) / 24) + 1) == 0) {
                if (count($pricePoints) < 24) {
                    $pricePoints[] = $price;
                }
            }
            
            // Calculate changes at specific intervals
            $timeDiff = $currentTime - $timestamp;
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
        
        // If we don't have enough data points, interpolate to 24 points
        if (count($pricePoints) < 24) {
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
        
        // Add coin data to result
        $priceHistoryData[] = [
            'symbol' => $coinSymbol,
            'current_price' => $currentPrice,
            'change_1' => (float)$change1 ?? 0,
            'change_2' => (float)$change2 ?? 0,
            'change_3' => (float)$change3 ?? 0,
            'change_4' => (float)$change4 ?? 0,
            'change_5' => (float)$change5 ?? 0,
            'interval_labels' => $intervalLabels,
            'price_points' => $pricePoints
        ];
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $priceHistoryData,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    // Clear any previous output
    ob_clean();
    
    // Return error response
    error_log("New coin price history API error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving new coin price history data: ' . $e->getMessage()
    ]);
} catch (Throwable $e) {
    // Catch any other errors
    ob_clean();
    
    // Return error response
    error_log("New coin price history API critical error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Critical error retrieving new coin price history data: ' . $e->getMessage()
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
