<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pdo_functions.php';

if (function_exists('getTradeLogWithMarketDataPDO')) {
    echo "SUCCESS: The function getTradeLogWithMarketDataPDO() is defined.";
} else {
    echo "FAILURE: The function getTradeLogWithMarketDataPDO() is NOT defined.";
}
