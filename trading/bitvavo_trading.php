<?php
/**
 * Bitvavo Trading Script
 * 
 * This script demonstrates how to use the BitvavoTrader class to perform trading operations
 */

require_once __DIR__ . '/../includes/BitvavoTrader.php';

// Set to true for test mode, false for live trading
$testMode = true;

// Initialize the trader
try {
    $trader = new BitvavoTrader($testMode);
    echo "Bitvavo trader initialized successfully" . PHP_EOL;
    
    // Get account balance
    echo "Fetching account balance..." . PHP_EOL;
    $balance = $trader->getBalance();
    
    if (isset($balance['error'])) {
        echo "Error: " . $balance['error'] . PHP_EOL;
    } else {
        echo "Available balances:" . PHP_EOL;
        foreach ($balance['free'] as $currency => $amount) {
            if ($amount > 0) {
                echo "- {$currency}: {$amount}" . PHP_EOL;
            }
        }
    }
    
    // Get available markets
    echo PHP_EOL . "Fetching available markets..." . PHP_EOL;
    $markets = $trader->getMarkets();
    
    if (isset($markets['error'])) {
        echo "Error: " . $markets['error'] . PHP_EOL;
    } else {
        echo "Available markets: " . count($markets) . PHP_EOL;
        $counter = 0;
        foreach ($markets as $symbol => $market) {
            echo "- {$symbol}" . PHP_EOL;
            $counter++;
            if ($counter >= 10) {
                echo "... and " . (count($markets) - 10) . " more" . PHP_EOL;
                break;
            }
        }
    }
    
    // Get ticker for BTC/EUR
    $symbol = 'BTC/EUR';
    echo PHP_EOL . "Fetching ticker for {$symbol}..." . PHP_EOL;
    $ticker = $trader->getTicker($symbol);
    
    if (isset($ticker['error'])) {
        echo "Error: " . $ticker['error'] . PHP_EOL;
    } else {
        echo "Current price: {$ticker['last']} EUR" . PHP_EOL;
        echo "24h change: {$ticker['percentage']}%" . PHP_EOL;
        echo "24h high: {$ticker['high']} EUR" . PHP_EOL;
        echo "24h low: {$ticker['low']} EUR" . PHP_EOL;
        echo "24h volume: {$ticker['volume']} BTC" . PHP_EOL;
    }
    
    // Example of placing a market buy order (commented out for safety)
    /*
    $buyAmount = 0.001; // BTC
    echo PHP_EOL . "Placing market buy order for {$buyAmount} BTC..." . PHP_EOL;
    $buyOrder = $trader->marketBuy($symbol, $buyAmount);
    
    if (isset($buyOrder['error'])) {
        echo "Error: " . $buyOrder['error'] . PHP_EOL;
    } else {
        echo "Order placed successfully!" . PHP_EOL;
        echo "Order ID: " . $buyOrder['id'] . PHP_EOL;
        echo "Amount: " . $buyOrder['amount'] . " BTC" . PHP_EOL;
        echo "Cost: " . $buyOrder['cost'] . " EUR" . PHP_EOL;
        echo "Status: " . $buyOrder['status'] . PHP_EOL;
    }
    */
    
    // Example of placing a limit buy order (commented out for safety)
    /*
    $buyAmount = 0.001; // BTC
    $buyPrice = $ticker['last'] * 0.95; // 5% below current price
    echo PHP_EOL . "Placing limit buy order for {$buyAmount} BTC at {$buyPrice} EUR..." . PHP_EOL;
    $limitBuyOrder = $trader->limitBuy($symbol, $buyAmount, $buyPrice);
    
    if (isset($limitBuyOrder['error'])) {
        echo "Error: " . $limitBuyOrder['error'] . PHP_EOL;
    } else {
        echo "Order placed successfully!" . PHP_EOL;
        echo "Order ID: " . $limitBuyOrder['id'] . PHP_EOL;
        echo "Amount: " . $limitBuyOrder['amount'] . " BTC" . PHP_EOL;
        echo "Price: " . $limitBuyOrder['price'] . " EUR" . PHP_EOL;
        echo "Status: " . $limitBuyOrder['status'] . PHP_EOL;
    }
    */
    
    // Get open orders
    echo PHP_EOL . "Fetching open orders..." . PHP_EOL;
    $openOrders = $trader->getOpenOrders();
    
    if (isset($openOrders['error'])) {
        echo "Error: " . $openOrders['error'] . PHP_EOL;
    } else {
        echo "Open orders: " . count($openOrders) . PHP_EOL;
        foreach ($openOrders as $order) {
            echo "- Order ID: " . $order['id'] . PHP_EOL;
            echo "  Symbol: " . $order['symbol'] . PHP_EOL;
            echo "  Type: " . $order['type'] . " " . $order['side'] . PHP_EOL;
            echo "  Amount: " . $order['amount'] . PHP_EOL;
            echo "  Price: " . $order['price'] . PHP_EOL;
            echo "  Status: " . $order['status'] . PHP_EOL;
            echo PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
