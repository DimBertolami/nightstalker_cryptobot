<?php
/**
 * Run Autonomous Trader
 * 
 * This script is designed to be run via cron job to execute the autonomous trading bot
 * Recommended cron schedule: Every hour (0 * * * *)
 */

// Include the autonomous trader
require_once __DIR__ . '/../autonomous_trader.php';

// Log the execution
logEvent("Autonomous trader cron job started", 'info');

// The autonomous_trader.php script will handle the rest
// It already creates an instance of AutonomousTrader and runs the trading cycle
