<?php
/**
 * CCXT Integration
 * Handles interaction with cryptocurrency exchanges via CCXT library
 */

// Check if Composer autoload exists, otherwise suggest installation
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    die('CCXT library not found. Please run "composer install" in the root directory.');
}

// Load Composer dependencies
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/exchange_config.php';

use ccxt\Exchange;

/**
 * Create a CCXT exchange instance
 * 
 * @param string $exchange_id The exchange ID
 * @param array $credentials Exchange credentials (optional, will use stored credentials if not provided)
 * @return Exchange|null CCXT exchange instance or null on failure
 */
function create_exchange_instance($exchange_id, $credentials = null) {
    try {
        // If credentials are not provided, load from config
        if ($credentials === null) {
            $exchange_config = get_exchange($exchange_id);
            if (!$exchange_config || empty($exchange_config['credentials'])) {
                return null;
            }
            $credentials = $exchange_config['credentials'];
        }
        
        // Create exchange class name
        $exchange_class = '\\ccxt\\' . $exchange_id;
        
        // Check if the exchange is supported by CCXT
        if (!class_exists($exchange_class)) {
            return null;
        }
        
        // Prepare exchange options
        $options = [
            'apiKey' => $credentials['api_key'] ?? '',
            'secret' => $credentials['api_secret'] ?? '',
            'enableRateLimit' => true
        ];
        
        // Add additional parameters if provided
        if (!empty($credentials['additional_params'])) {
            $additional_params = is_string($credentials['additional_params']) 
                ? json_decode($credentials['additional_params'], true) 
                : $credentials['additional_params'];
                
            if (is_array($additional_params)) {
                $options = array_merge($options, $additional_params);
            }
        }
        
        // Set sandbox mode if enabled
        if (!empty($credentials['test_mode'])) {
            $options['sandbox'] = true;
        }
        
        // Create and return exchange instance
        $exchange = new $exchange_class($options);
        return $exchange;
    } catch (\Exception $e) {
        error_log('CCXT Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Test connection to an exchange
 * 
 * @param string $exchange_id The exchange ID
 * @param array $credentials Exchange credentials
 * @return array Result with success status and message
 */
function test_exchange_connection($exchange_id, $credentials) {
    try {
        $exchange = create_exchange_instance($exchange_id, $credentials);
        
        if (!$exchange) {
            return [
                'success' => false,
                'message' => "Exchange '$exchange_id' is not supported by CCXT"
            ];
        }
        
        // Try to load markets to verify connection
        $markets = $exchange->load_markets();
        
        return [
            'success' => true,
            'message' => 'Connection successful',
            'markets_count' => count($markets)
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get available markets for an exchange
 * 
 * @param string $exchange_id The exchange ID
 * @return array Markets information
 */
function get_exchange_markets($exchange_id) {
    try {
        $exchange = create_exchange_instance($exchange_id);
        
        if (!$exchange) {
            return [
                'success' => false,
                'message' => "Exchange '$exchange_id' not found or not configured"
            ];
        }
        
        $markets = $exchange->load_markets();
        
        return [
            'success' => true,
            'markets' => $markets
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get account balance for an exchange
 * 
 * @param string $exchange_id The exchange ID
 * @return array Balance information
 */
function get_exchange_balance($exchange_id) {
    try {
        $exchange = create_exchange_instance($exchange_id);
        
        if (!$exchange) {
            return [
                'success' => false,
                'message' => "Exchange '$exchange_id' not found or not configured"
            ];
        }
        
        $balance = $exchange->fetch_balance();
        
        return [
            'success' => true,
            'balance' => $balance
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Place a market order
 * 
 * @param string $exchange_id The exchange ID
 * @param string $symbol Trading pair symbol (e.g., 'BTC/USDT')
 * @param string $side Order side ('buy' or 'sell')
 * @param float $amount Order amount
 * @return array Order result
 */
function place_market_order($exchange_id, $symbol, $side, $amount) {
    try {
        $exchange = create_exchange_instance($exchange_id);
        
        if (!$exchange) {
            return [
                'success' => false,
                'message' => "Exchange '$exchange_id' not found or not configured"
            ];
        }
        
        // Place market order
        $order = $exchange->create_order($symbol, 'market', $side, $amount);
        
        return [
            'success' => true,
            'order' => $order
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Place a limit order
 * 
 * @param string $exchange_id The exchange ID
 * @param string $symbol Trading pair symbol (e.g., 'BTC/USDT')
 * @param string $side Order side ('buy' or 'sell')
 * @param float $amount Order amount
 * @param float $price Limit price
 * @return array Order result
 */
function place_limit_order($exchange_id, $symbol, $side, $amount, $price) {
    try {
        $exchange = create_exchange_instance($exchange_id);
        
        if (!$exchange) {
            return [
                'success' => false,
                'message' => "Exchange '$exchange_id' not found or not configured"
            ];
        }
        
        // Place limit order
        $order = $exchange->create_order($symbol, 'limit', $side, $amount, $price);
        
        return [
            'success' => true,
            'order' => $order
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get supported exchanges from CCXT
 * 
 * @return array List of supported exchanges
 */
function get_supported_exchanges() {
    try {
        $exchanges = \ccxt\Exchange::$exchanges;
        return [
            'success' => true,
            'exchanges' => $exchanges
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
