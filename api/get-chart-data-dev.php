<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$coinId = $_GET['coin_id'] ?? null;

if (!$coinId) {
    echo json_encode(['success' => false, 'error' => 'Coin ID is required.']);
    exit();
}

function calculateSMA($prices, $period) {
    $sma = [];
    $numPrices = count($prices);
    for ($i = 0; $i < $numPrices; $i++) {
        if ($i >= $period - 1) {
            $sum = 0;
            for ($j = 0; $j < $period; $j++) {
                $sum += $prices[$i - $j];
            }
            $sma[] = $sum / $period;
        } else {
            $sma[] = null; // Not enough data for SMA
        }
    }
    return $sma;
}

function calculateEMA($prices, $period) {
    $ema = [];
    $numPrices = count($prices);
    $multiplier = 2 / ($period + 1);
    $ema[] = array_sum(array_slice($prices, 0, $period)) / $period; // Initial SMA for first EMA

    for ($i = $period; $i < $numPrices; $i++) {
        $ema[] = (($prices[$i] - $ema[$i - $period]) * $multiplier) + $ema[$i - $period];
    }
    return array_merge(array_fill(0, $period - 1, null), $ema);
}

function calculateRSI($prices, $period) {
    $rsi = [];
    $gains = [];
    $losses = [];

    for ($i = 1; $i < count($prices); $i++) {
        $change = $prices[$i] - $prices[$i - 1];
        $gains[] = max(0, $change);
        $losses[] = max(0, -$change);
    }

    $avg_gain = array_sum(array_slice($gains, 0, $period)) / $period;
    $avg_loss = array_sum(array_slice($losses, 0, $period)) / $period;

    if ($avg_loss == 0) {
        $rs = ($avg_gain == 0) ? 0 : 2; // Handle division by zero
    } else {
        $rs = $avg_gain / $avg_loss;
    }
    $rsi[] = 100 - (100 / (1 + $rs));

    for ($i = $period; $i < count($gains); $i++) {
        $avg_gain = (($avg_gain * ($period - 1)) + $gains[$i]) / $period;
        $avg_loss = (($avg_loss * ($period - 1)) + $losses[$i]) / $period;

        if ($avg_loss == 0) {
            $rs = ($avg_gain == 0) ? 0 : 2; // Handle division by zero
        } else {
            $rs = $avg_gain / $avg_loss;
        }
        $rsi[] = 100 - (100 / (1 + $rs));
    }
    return array_merge(array_fill(0, $period, null), $rsi);
}

function calculateBollingerBands($prices, $period = 20, $stdDevMultiplier = 2) {
    $bands = [];
    $sma = calculateSMA($prices, $period);
    $numPrices = count($prices);

    for ($i = 0; $i < $numPrices; $i++) {
        if ($sma[$i] !== null) {
            $sumOfSquares = 0;
            for ($j = 0; $j < $period; $j++) {
                $sumOfSquares += pow($prices[$i - $j] - $sma[$i], 2);
            }
            $stdDev = sqrt($sumOfSquares / $period);
            $upperBand = $sma[$i] + ($stdDev * $stdDevMultiplier);
            $lowerBand = $sma[$i] - ($stdDev * $stdDevMultiplier);
            $bands[] = ['upper' => $upperBand, 'middle' => $sma[$i], 'lower' => $lowerBand];
        } else {
            $bands[] = ['upper' => null, 'middle' => null, 'lower' => null];
        }
    }
    return $bands;
}

function calculateMACD($prices, $fastPeriod = 12, $slowPeriod = 26, $signalPeriod = 9) {
    $emaFast = calculateEMA($prices, $fastPeriod);
    $emaSlow = calculateEMA($prices, $slowPeriod);
    $macdLine = [];

    for ($i = 0; $i < count($prices); $i++) {
        if ($emaFast[$i] !== null && $emaSlow[$i] !== null) {
            $macdLine[] = $emaFast[$i] - $emaSlow[$i];
        } else {
            $macdLine[] = null;
        }
    }

    // Calculate Signal Line (EMA of MACD Line)
    $signalLine = calculateEMA($macdLine, $signalPeriod);

    // Calculate Histogram
    $histogram = [];
    for ($i = 0; $i < count($macdLine); $i++) {
        if ($macdLine[$i] !== null && $signalLine[$i] !== null) {
            $histogram[] = $macdLine[$i] - $signalLine[$i];
        } else {
            $histogram[] = null;
        }
    }

    return ['macdLine' => $macdLine, 'signalLine' => $signalLine, 'histogram' => $histogram];
}

try {
    error_log("API: Attempting to get DB connection.");
    $db = getDbConnection();
    error_log("API: DB connection established.");

    // Fetch historical price data
    error_log("API: Fetching price history for coin ID: " . $coinId);
    try {
        $stmt = $db->prepare("SELECT recorded_at, price FROM price_history WHERE coin_id = ? ORDER BY recorded_at ASC");
        $stmt->execute([$coinId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("API: Fetched price history count: " . count($history));
    } catch (PDOException $e) {
        error_log("API: PDO Error fetching price history for $coinId: " . $e->getMessage());
        throw $e; // Re-throw to be caught by the outer catch block
    }

    $formattedHistory = [];
    $prices = [];
    if (empty($history)) {
        error_log("API: No history data for $coinId. Returning empty response.");
        echo json_encode([
            'history' => [],
            'apex' => null,
            'purchase_time' => null,
            'latest_recorded_time' => null,
            'coin_status' => 'no_data',
            'drop_start_timestamp' => null
        ]);
        exit();
    }

    foreach ($history as $row) {
        $formattedHistory[] = [
            'time' => strtotime($row['recorded_at']) * 1000, // Convert to milliseconds for JavaScript
            'price' => (float)$row['price']
        ];
        $prices[] = (float)$row['price'];
    }
    error_log("API: Formatted history and prices prepared.");

    // Fetch apex data from coin_apex_prices table
    $apexData = null;
    $coinStatus = 'monitoring'; // Default status
    $dropStartTimestamp = null;

    error_log("API: Fetching apex data for coin ID: " . $coinId);
    try {
        $stmtApex = $db->prepare("SELECT apex_price, apex_timestamp, drop_start_timestamp, status FROM coin_apex_prices WHERE coin_id = ?");
        $stmtApex->execute([$coinId]);
        $apexResult = $stmtApex->fetch(PDO::FETCH_ASSOC);
        error_log("API: Apex data fetch result: " . json_encode($apexResult));
    } catch (PDOException $e) {
        error_log("API: PDO Error fetching apex data for $coinId: " . $e->getMessage());
        throw $e; // Re-throw
    }

    if ($apexResult) {
        $apexData = [
            'price' => (float)$apexResult['apex_price'],
            'timestamp' => strtotime($apexResult['apex_timestamp']) * 1000
        ];
        $coinStatus = $apexResult['status'];
        $dropStartTimestamp = $apexResult['drop_start_timestamp'] ? strtotime($apexResult['drop_start_timestamp']) * 1000 : null;

        error_log("API: Before status check - coinStatus: " . $coinStatus . ", dropStartTimestamp: " . ($dropStartTimestamp ? date('Y-m-d H:i:s', $dropStartTimestamp / 1000) : 'null'));

        // If coin is dropping and drop_start_timestamp is in the past, adjust status
        if ($coinStatus === 'dropping' && $dropStartTimestamp !== null && $dropStartTimestamp < (time() * 1000)) {
            error_log("API: Coin $coinId was dropping, but drop_start_timestamp is in the past. Setting status to dropped.");
            $coinStatus = 'dropped'; // Or 'stable' depending on desired behavior after drop
            $dropStartTimestamp = null; // No longer relevant for countdown
        }
    } else {
        $coinStatus = 'not_monitored';
    }
    error_log("API: Apex data and coin status processed.");

    $purchaseTime = null;
    $latestRecordedTime = null;

    if (!empty($formattedHistory)) {
        $purchaseTime = $formattedHistory[0]['time'];
        $latestRecordedTime = end($formattedHistory)['time'];
    }
    error_log("API: Purchase and latest recorded time determined.");

    error_log("API: Final Chart Data - Coin Status: " . $coinStatus);
    error_log("API: Final Chart Data - Drop Start Timestamp: " . $dropStartTimestamp);

    echo json_encode([
        'history' => $formattedHistory,
        'apex' => $apexData,
        'purchase_time' => $purchaseTime,
        'latest_recorded_time' => $latestRecordedTime,
        'coin_status' => $coinStatus,
        'drop_start_timestamp' => $dropStartTimestamp
    ]);

} catch (PDOException $e) {
    error_log("API: Caught PDO Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("API: Caught General Error: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred: ' . $e->getMessage()]);
}
?>