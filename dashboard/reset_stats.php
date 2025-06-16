<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/TradingLogger.php';

// Initialize the trading logger
$logger = new TradingLogger();

// Get selected strategy from session or default to new_coin_strategy
session_start();
$selectedStrategy = $_SESSION['selected_strategy'] ?? 'new_coin_strategy';

// Reset trading statistics
$success = false;
$message = '';

try {
    // Reset stats in the database
    $logger->resetStatistics($selectedStrategy);
    $success = true;
    $message = 'Trading statistics have been reset successfully.';
} catch (Exception $e) {
    $message = 'Error resetting statistics: ' . $e->getMessage();
}

// Set session message
session_start();
$_SESSION['message'] = $message;
$_SESSION['message_type'] = $success ? 'success' : 'danger';

// Redirect back to settings page
header('Location: settings.php');
exit;
?>
