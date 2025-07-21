<?php
// first!
$title = 'Wave Stalker - built from the remains of a decommmissioned early tsunami warning and prediction system\'s Artificial Intelligence, it\'s new mission objectives to track and exploit a vulnerability discovered in all the new coins, on every exchange, which allows this system to predict and profit from their price movements.';
$description = 'This is the new trading dashboard for Night Stalker, designed to manually execute trades.';
$keywords = 'trading, dashboard, wave stalker, y0 Mama, crypto, cryptocurrency, bitcoin, ethereum, altcoins, trading bot, automated trading, market analysis';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/TradingLogger.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session and check authentication
session_start();
requireAuth();

// Redirect to the new trading dashboard
header('Location: /NS/dashboard/trading_dashboard.php');
exit;
?>
