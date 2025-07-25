<?php
/**
 * Place a market buy order on Binance.
 *
 * This script takes a symbol and quantity as command-line arguments and places
 * a market buy order on Binance. It then logs the order to the database.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/TradingLogger.php';
require_once __DIR__ . '/../includes/exchanges/BinanceHandler.php';

// Initialize logger
$logger = new TradingLogger();

// Get command-line arguments
$options = getopt("s:q:", ["symbol:", "quantity:"]);
$symbol = $options['s'] ?? $options['symbol'] ?? null;
$quantity = $options['q'] ?? $options['quantity'] ?? null;

if (!$symbol || !$quantity) {
    echo "Usage: php place_buy_order.php --symbol <symbol> --quantity <quantity>\n";
    exit(1);
}

// Initialize Binance handler
$binance = new BinanceHandler();

// Place the order
$order = $binance->placeMarketBuyOrder($symbol, $quantity);

if ($order['success']) {
    $logger->logEvent("manual_buy", "success", [
        "symbol" => $symbol,
        "quantity" => $quantity,
        "orderId" => $order['orderId'],
        "message" => "Successfully placed market buy order for {$quantity} {$symbol}."
    ]);

    // Log the order to the database
    $db = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare(
        "INSERT INTO orders (user_id, symbol, type, side, quantity, price, status) " .
        "VALUES (:user_id, :symbol, :type, :side, :quantity, :price, :status)"
    );

    $stmt->execute([
        ':user_id' => 1, // Assuming a user_id of 1
        ':symbol' => $symbol,
        ':type' => 'market',
        ':side' => 'buy',
        ':quantity' => $quantity,
        ':price' => $order['price'],
        ':status' => 'filled'
    ]);

    echo "Successfully placed market buy order for {$quantity} {$symbol}.\n";
} else {
    $logger->logEvent("manual_buy", "error", [
        "symbol" => $symbol,
        "quantity" => $quantity,
        "error" => $order['error'],
        "message" => "Failed to place market buy order for {$quantity} {$symbol}."
    ]);

    echo "Error placing market buy order: {$order['error']}\n";
}

