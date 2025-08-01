<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/pdo_functions.php';

header('Content-Type: application/json');

try {
    $portfolioCoins = getPortfolioCoinsPDO(); // You will need to implement this function
    error_log("Portfolio Coins: " . json_encode($portfolioCoins));
    echo json_encode($portfolioCoins);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
