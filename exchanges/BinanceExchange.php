<?php

// Ensure config.php is included to access API keys
require_once __DIR__ . '/../includes/config.php';
// Include the CCXT integration functions
require_once __DIR__ . '/../includes/ccxt_integration.php';

/**
 * Binance Exchange Integration Class
 * 
 * This class handles communication with the Binance API to fetch account balances
 * using the CCXT library.
 */
class BinanceExchange {
    private $exchangeId = 'binance'; // CCXT exchange ID for Binance
    private $exchange; // CCXT exchange instance

    public function __construct() {
        try {
            // Create a CCXT exchange instance for Binance
            $this->exchange = create_exchange_instance($this->exchangeId);
            
            if (!$this->exchange) {
                throw new Exception("Failed to create CCXT Binance exchange instance. Check API keys and configuration.");
            }
            
            // Load markets to ensure the exchange is ready and authenticated
            $this->exchange->load_markets();
            
            error_log("BinanceExchange: CCXT client initialized and markets loaded.");
        } catch (Exception $e) {
            error_log("BinanceExchange: Error during initialization: " . $e->getMessage());
            throw new Exception("BinanceExchange initialization failed: " . $e->getMessage());
        }
    }

    /**
     * Fetches the account balance from Binance using direct cURL.
     * 
     * @return array An associative array of balances, e.g.,
     *               ['BTC' => ['free' => 0.5, 'used' => 0.1, 'total' => 0.6]]
     * @throws Exception If the API call fails or returns unexpected data.
     */
    public function fetchBalance() {
        try {
            $apiKey = get_exchange($this->exchangeId)['credentials']['api_key'];
            $secret = get_exchange($this->exchangeId)['credentials']['api_secret'];
            $baseUrl = 'https://testnet.binance.vision';

            $timestamp = round(microtime(true) * 1000);

            $params = [
                'timestamp' => $timestamp,
                'recvWindow' => 5000
            ];

            $query_string = http_build_query($params);
            $signature = hash_hmac('sha256', $query_string, $secret);

            $url = "{$baseUrl}/api/v3/account?{$query_string}&signature={$signature}";

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "X-MBX-APIKEY: {$apiKey}"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception('cURL error: ' . curl_error($ch));
            }

            $data = json_decode($response, true);

            if (isset($data['code']) && $data['code'] !== 200) {
                throw new Exception("Binance API Error: " . ($data['msg'] ?? $response));
            }

            $balances = [];
            foreach ($data['balances'] as $asset) {
                $total = (float)$asset['free'] + (float)$asset['locked'];
                if ($total > 0) {
                    $balances[strtoupper($asset['asset'])] = [
                        'free' => (float)$asset['free'],
                        'used' => (float)$asset['locked'],
                        'total' => (float)$total
                    ];
                }
            }
            error_log("BinanceExchange: Fetched real balances via cURL: " . json_encode($balances));
            return $balances;

        } catch (Exception $e) {
            error_log("BinanceExchange: Error fetching balances via cURL: " . $e->getMessage());
            throw new Exception("Failed to fetch Binance balances via cURL: " . $e->getMessage());
        }
    }
}