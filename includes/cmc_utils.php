<?php

function getCMCTrendingCoins() {
    $apiKey = getenv('CMC_API_KEY');
    $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest?limit=50&sort=percent_change_24h';
    
    $headers = [
        'Accepts: application/json',
        'X-CMC_PRO_API_KEY: ' . $apiKey
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("CMC API Error: " . $error);
        return [];
    }
    
    $decoded = json_decode($response, true);
    
    // Check for API errors
    if (isset($decoded['status']) && $decoded['status']['error_code'] != 0) {
        error_log("CMC API Error: " . $decoded['status']['error_message']);
        return [];
    }
    
    // For testing when API key isn't set, return mock data
    if (empty($apiKey) || empty($decoded['data'])) {
        error_log("Using mock CMC data (API key not set or no data returned)");
        return getMockCMCData();
    }
    
    return $decoded['data'] ?? [];
}

function getMockCMCData() {
    // Return 10 mock coins for testing
    $mockCoins = [];
    $symbols = ['BTC', 'ETH', 'SOL', 'XRP', 'ADA', 'DOGE', 'DOT', 'AVAX', 'MATIC', 'LINK'];
    $names = ['Bitcoin', 'Ethereum', 'Solana', 'Ripple', 'Cardano', 'Dogecoin', 'Polkadot', 'Avalanche', 'Polygon', 'Chainlink'];
    
    for ($i = 0; $i < 10; $i++) {
        $price = rand(10, 60000) / (($i == 0) ? 1 : 10);
        $change = rand(-1500, 1500) / 100;
        
        $mockCoins[] = [
            'symbol' => $symbols[$i],
            'name' => $names[$i],
            'quote' => [
                'USD' => [
                    'price' => $price,
                    'percent_change_24h' => $change
                ]
            ]
        ];
    }
    
    return $mockCoins;
}

function getCMCGainersLosers() {
    $coins = getCMCTrendingCoins();
    $result = ['gainers' => [], 'losers' => []];
    
    foreach ($coins as $coin) {
        $change = $coin['quote']['USD']['percent_change_24h'];
        if ($change > 0) {
            $result['gainers'][] = $coin;
        } else {
            $result['losers'][] = $coin;
        }
    }
    
    return $result;
}

function fetch_coin_price_from_cmc($symbol) {
    $apiKey = getenv('CMC_API_KEY');
    $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest?symbol=' . urlencode($symbol);
    
    $headers = [
        'Accepts: application/json',
        'X-CMC_PRO_API_KEY: ' . $apiKey
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("CMC API Error: " . $error);
        return ['success' => false, 'message' => $error];
    }
    
    $decoded = json_decode($response, true);
    
    if (isset($decoded['status']) && $decoded['status']['error_code'] != 0) {
        error_log("CMC API Error: " . $decoded['status']['error_message']);
        return ['success' => false, 'message' => $decoded['status']['error_message']];
    }
    
    if (isset($decoded['data']) && isset($decoded['data'][$symbol]) && isset($decoded['data'][$symbol]['quote']['USD']['price'])) {
        return ['success' => true, 'price' => $decoded['data'][$symbol]['quote']['USD']['price']];
    } else {
        return ['success' => false, 'message' => 'Price data not found for symbol.'];
    }
}
