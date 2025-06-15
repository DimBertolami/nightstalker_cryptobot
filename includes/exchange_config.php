<?php
/**
 * Exchange Configuration
 * Handles CCXT exchange settings and credentials
 */

// Define the path to store exchange credentials
define('EXCHANGE_CONFIG_PATH', __DIR__ . '/../config/exchanges.json');

// Create exchanges directory if it doesn't exist
if (!file_exists(__DIR__ . '/../config')) {
    mkdir(__DIR__ . '/../config', 0755, true);
}

// Initialize exchanges config if it doesn't exist
if (!file_exists(EXCHANGE_CONFIG_PATH)) {
    $default_exchanges = [
        'jupiter' => [
            'name' => 'Jupiter (Solana)',
            'enabled' => true,
            'is_default' => true,
            'credentials' => [
                'api_key' => '',
                'api_secret' => '',
                'test_mode' => false,
                'additional_params' => []
            ]
        ],
        'binance' => [
            'name' => 'Binance',
            'enabled' => true,
            'is_default' => false,
            'credentials' => [
                'api_key' => '',
                'api_secret' => '',
                'test_mode' => false,
                'additional_params' => []
            ]
        ],
        'bitvavo' => [
            'name' => 'Bitvavo',
            'enabled' => true,
            'is_default' => false,
            'credentials' => [
                'api_key' => '',
                'api_secret' => '',
                'test_mode' => false,
                'additional_params' => []
            ]
        ]
    ];
    
    file_put_contents(EXCHANGE_CONFIG_PATH, json_encode($default_exchanges, JSON_PRETTY_PRINT));
}

/**
 * Get all configured exchanges
 * 
 * @return array Exchange configurations
 */
function get_exchanges() {
    if (file_exists(EXCHANGE_CONFIG_PATH)) {
        $exchanges = json_decode(file_get_contents(EXCHANGE_CONFIG_PATH), true);
        return $exchanges ?: [];
    }
    return [];
}

/**
 * Get a specific exchange configuration
 * 
 * @param string $exchange_id The exchange ID
 * @return array|null Exchange configuration or null if not found
 */
function get_exchange($exchange_id) {
    $exchanges = get_exchanges();
    return isset($exchanges[$exchange_id]) ? $exchanges[$exchange_id] : null;
}

/**
 * Save exchange configuration
 * 
 * @param string $exchange_id The exchange ID
 * @param array $config Exchange configuration
 * @return bool Success status
 */
function save_exchange($exchange_id, $config) {
    $exchanges = get_exchanges();
    $exchanges[$exchange_id] = $config;
    return file_put_contents(EXCHANGE_CONFIG_PATH, json_encode($exchanges, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Delete an exchange configuration
 * 
 * @param string $exchange_id The exchange ID
 * @return bool Success status
 */
function delete_exchange($exchange_id) {
    $exchanges = get_exchanges();
    if (isset($exchanges[$exchange_id])) {
        unset($exchanges[$exchange_id]);
        return file_put_contents(EXCHANGE_CONFIG_PATH, json_encode($exchanges, JSON_PRETTY_PRINT)) !== false;
    }
    return false;
}

/**
 * Set default exchange
 * 
 * @param string $exchange_id The exchange ID to set as default
 * @return bool Success status
 */
function set_default_exchange($exchange_id) {
    $exchanges = get_exchanges();
    
    // First, set all exchanges to non-default
    foreach ($exchanges as $id => $config) {
        $exchanges[$id]['is_default'] = false;
    }
    
    // Then set the specified exchange as default
    if (isset($exchanges[$exchange_id])) {
        $exchanges[$exchange_id]['is_default'] = true;
        return file_put_contents(EXCHANGE_CONFIG_PATH, json_encode($exchanges, JSON_PRETTY_PRINT)) !== false;
    }
    
    return false;
}

/**
 * Get the default exchange
 * 
 * @return string|null The default exchange ID or null if none is set
 */
function get_default_exchange() {
    $exchanges = get_exchanges();
    
    foreach ($exchanges as $id => $config) {
        if (isset($config['is_default']) && $config['is_default']) {
            return $id;
        }
    }
    
    // If no default is set but we have exchanges, return the first one
    if (!empty($exchanges)) {
        $ids = array_keys($exchanges);
        return $ids[0];
    }
    
    return null;
}
