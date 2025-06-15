<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Fetch latest listings from CoinMarketCap
$data = fetchFromCMC('/cryptocurrency/listings/latest', [
    'start' => 1,
    'limit' => 100,
    'convert' => 'USD'
]);

if (!$data || !isset($data['data'])) {
    logEvent("Failed to fetch from CoinMarketCap", 'error');
    exit;
}

$db = connectDB();

foreach ($data['data'] as $coin) {
    try {
        // Convert CMC datetime to MySQL format
        $createdAt = $coin['date_added'] 
            ? date('Y-m-d H:i:s', strtotime($coin['date_added']))
            : date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        $ageHours = $coin['date_added'] 
            ? round((time() - strtotime($coin['date_added'])) / 3600)
            : 24;
        
        // Check if coin exists
        $stmt = $db->prepare("SELECT id FROM cryptocurrencies WHERE id = ?");
        $stmt->bind_param('s', $coin['id']);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            // Insert new coin
            $stmt = $db->prepare("INSERT INTO cryptocurrencies 
                (id, name, symbol, created_at, age_hours, market_cap, volume, price, price_change_24h, last_updated)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param(
                'ssssidddd',
                $coin['id'],
                $coin['name'],
                $coin['symbol'],
                $createdAt,
                $ageHours,
                $coin['quote']['USD']['market_cap'],
                $coin['quote']['USD']['volume_24h'],
                $coin['quote']['USD']['price'],
                $coin['quote']['USD']['percent_change_24h']
            );
            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $stmt->error);
            }
            
            logEvent("Added new coin: {$coin['name']}", 'info');
        }
        
        // Update price history
        $stmt = $db->prepare("INSERT INTO price_history 
            (coin_id, price, volume, market_cap)
            VALUES (?, ?, ?, ?)");
        $stmt->bind_param(
            'sddd',
            $coin['id'],
            $coin['quote']['USD']['price'],
            $coin['quote']['USD']['volume_24h'],
            $coin['quote']['USD']['market_cap']
        );
        $stmt->execute();
        
        // Check for volume spikes
        if ($coin['quote']['USD']['volume_24h'] >= MIN_VOLUME_THRESHOLD) {
            $db->query("UPDATE cryptocurrencies SET is_trending=1 WHERE id='{$coin['id']}'");
        }
    } catch (Exception $e) {
        logEvent("Error processing {$coin['name']}: " . $e->getMessage(), 'error');
        continue;
    }
}

logEvent("Successfully updated from CoinMarketCap", 'info');
