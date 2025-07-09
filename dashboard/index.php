<?php
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
