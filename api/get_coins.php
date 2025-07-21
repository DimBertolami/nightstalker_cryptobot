<?php

require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

header('Content-Type: application/json');

try {
    $db = connectDB();
    $result = $db->query("SELECT * FROM cryptocurrencies ORDER BY volume DESC");
    $coins = [];
    
    while($row = $result->fetch_assoc()) {
        $coins[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'symbol' => strtoupper($row['symbol']),
            'price' => (float)$row['price'],
            'price_change_24h' => (float)$row['price_change_24h'],
            'volume' => (float)$row['volume'],
            'market_cap' => (float)$row['market_cap'],
            'age_hours' => (int)$row['age_hours']
        ];
    }
    
    echo json_encode($coins);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
