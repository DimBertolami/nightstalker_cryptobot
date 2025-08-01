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
    $db = getDbConnection();

    // Fetch historical price data
    error_log("Fetching price history for coin ID: " . $coinId);
    try {
        $stmt = $db->prepare("SELECT recorded_at, price FROM price_history WHERE coin_id = ? ORDER BY recorded_at ASC");
        $stmt->execute([$coinId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Fetched price history: " . json_encode($history));
    } catch (PDOException $e) {
        error_log("PDO Error fetching price history for $coinId: " . $e->getMessage());
        throw $e; // Re-throw to be caught by the outer catch block
    }

    $formattedHistory = [];
    foreach ($history as $row) {
        $formattedHistory[] = [
            'time' => strtotime($row['recorded_at']) * 1000, // Convert to milliseconds for JavaScript
            'price' => (float)$row['price']
        ];
        $prices[] = (float)$row['price'];
    }

    // Calculate indicators
    $sma = calculateSMA($prices, 20); // 20-period SMA
    $ema = calculateEMA($prices, 14); // 14-period EMA
    $rsi = calculateRSI($prices, 14); // 14-period RSI
    $bollingerBands = calculateBollingerBands($prices); // Default 20-period, 2 std dev
    $macd = calculateMACD($prices);

    // Add indicators to formatted history
    foreach ($formattedHistory as $key => $value) {
        $formattedHistory[$key]['sma'] = $sma[$key] ?? null;
        $formattedHistory[$key]['ema'] = $ema[$key] ?? null;
        $formattedHistory[$key]['rsi'] = $rsi[$key] ?? null;
        $formattedHistory[$key]['bb_upper'] = $bollingerBands[$key]['upper'] ?? null;
        $formattedHistory[$key]['bb_middle'] = $bollingerBands[$key]['middle'] ?? null;
        $formattedHistory[$key]['bb_lower'] = $bollingerBands[$key]['lower'] ?? null;
        $formattedHistory[$key]['macd_line'] = $macd['macdLine'][$key] ?? null;
        $formattedHistory[$key]['macd_signal'] = $macd['signalLine'][$key] ?? null;
        $formattedHistory[$key]['macd_histogram'] = $macd['histogram'][$key] ?? null;
    }

    // Fetch apex data from coin_apex_prices table
    $apexData = null;
    try {
        $stmtApex = $db->prepare("SELECT apex_price, apex_timestamp, drop_start_timestamp, status FROM coin_apex_prices WHERE coin_id = ?");
        $stmtApex->execute([$coinId]);
        $apexResult = $stmtApex->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("PDO Error fetching apex data for $coinId: " . $e->getMessage());
        throw $e; // Re-throw
    }

    if ($apexResult) {
        $apexData = [
            'price' => (float)$apexResult['apex_price'],
            'timestamp' => strtotime($apexResult['apex_timestamp']) * 1000
        ];
        $coinStatus = $apexResult['status'];
        $dropStartTimestamp = $apexResult['drop_start_timestamp'] ? strtotime($apexResult['drop_start_timestamp']) * 1000 : null;
    } else {
        $coinStatus = 'not_monitored';
    }

    $purchaseTime = null;
    $latestRecordedTime = null;

    if (!empty($formattedHistory)) {
        // Assuming the first entry in history is close to purchase time for simplicity
        $purchaseTime = $formattedHistory[0]['time'];
        $latestRecordedTime = end($formattedHistory)['time'];
    }

    error_log("Chart Data - History: " . json_encode($formattedHistory));
    error_log("Chart Data - Apex: " . json_encode($apexData));
    error_log("Chart Data - Purchase Time: " . $purchaseTime);
    error_log("Chart Data - Latest Recorded Time: " . $latestRecordedTime);
    error_log("Chart Data - Coin Status: " . $coinStatus);
    error_log("Chart Data - Drop Start Timestamp: " . $dropStartTimestamp);

    echo json_encode([
        'history' => $formattedHistory,
        'apex' => $apexData,
        'purchase_time' => $purchaseTime,
        'latest_recorded_time' => $latestRecordedTime,
        'coin_status' => $coinStatus,
        'drop_start_timestamp' => $dropStartTimestamp
    ]);

} catch (PDOException $e) {
    error_log("Error fetching chart data: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error fetching chart data: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred: ' . $e->getMessage()]);
}
?>