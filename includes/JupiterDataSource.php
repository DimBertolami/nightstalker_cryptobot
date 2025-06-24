<?php

namespace NS\DataSources;

require_once '/opt/lampp/htdocs/NS/includes/SwapDataSourceInterface.php';

use NS\DataSources\SwapDataSourceInterface;

class JupiterDataSource implements SwapDataSourceInterface {
    private $apiKey;
    private $apiSecret;
    private $baseUrl = 'https://quote-api.jup.ag';
    
    public function __construct(string $apiKey = '', string $apiSecret = '') {
        $this->apiKey = !empty($apiKey) ? $apiKey : (defined('JUPITER_API_KEY') ? JUPITER_API_KEY : '');
        $this->apiSecret = !empty($apiSecret) ? $apiSecret : (defined('JUPITER_API_SECRET') ? JUPITER_API_SECRET : '');
    }
    
    public function testConnection(): bool {
        try {
            $response = $this->getSwapQuote(
                'So11111111111111111111111111111111111111112',
                'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
                1000000
            );
            return isset($response['outAmount']);
        } catch (\Exception $e) {
            $this->logError("API connection test failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function getSwapQuote(string $inputToken, string $outputToken, float $amount): array {
        // Token decimal mapping
        $tokenDecimals = [
            'So11111111111111111111111111111111111111112' => 9, // SOL
            'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v' => 6, // USDC
            'Es9vMFrzaCERmJfrF4H2FYD4KCoNkY11McCe8BenwNYB' => 6, // USDT
            'mSoLzYCxHdYgdzU16g5QSh3i5K3z3KZK7ytfqcJm7So' => 9, // mSOL
            '7dHbWXmci3dT8UFYWYZweBLXgycu7Y3iL6trKn1Y7ARj' => 5, // BONK
            'DezXAZ8z7PnrnRJjz3wXBoRgixCa6xjnB7YaB1pPB263' => 5, // BONK
            'DUSTawucrTsGU8hcqRdHDCbuYhCPADMLM2VcCb8VnFnQ' => 9, // DUST
            'JUPyiwrYJFskUPiHa7hkeR8VUtAeFoSYbKedZNsDvCN' => 6  // JUP
        ];
        
        // Get token decimals or default to 6
        $decimals = $tokenDecimals[$inputToken] ?? 6;
        
        // The frontend is now sending the amount already converted to smallest unit
        // Just ensure it's properly formatted as a string with no decimals
        $amountInSmallestUnit = (string)number_format($amount, 0, '.', '');
        
        $this->logDebug("Processing amount: {$amount} for token with {$decimals} decimals");
        
        $params = [
            'inputMint' => $inputToken,
            'outputMint' => $outputToken,
            'amount' => $amountInSmallestUnit, // Jupiter API expects amount as string
            'slippageBps' => 50,
            'onlyDirectRoutes' => 'false'
        ];
        
        $this->logDebug('Making quote request with params: ' . json_encode($params));
        $response = $this->makeApiCall('/v6/quote', 'GET', $params);
        
        // Add token decimal information to the response for frontend use
        $response['inputDecimals'] = $tokenDecimals[$inputToken] ?? 6;
        $response['outputDecimals'] = $tokenDecimals[$outputToken] ?? 6;
        
        return $response;
    }
    
    public function executeSwap(array $quote): array {
        if (!isset($quote['swapTransaction'])) {
            throw new \Exception('Invalid quote format - missing swapTransaction');
        }
        return $this->makeApiCall('/v6/swap', 'POST', $quote);
    }
    
    private function makeApiCall(string $endpoint, string $method = 'GET', array $params = []): array {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        
        if (!empty($this->apiKey)) {
            $headers[] = 'X-API-KEY: ' . $this->apiKey;
        }
        
        $ch = curl_init();
        
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $this->logDebug("Making $method request to: $url");
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_VERBOSE => true
        ]);
        
        if ($method !== 'GET' && !empty($params)) {
            $postData = json_encode($params);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            $this->logDebug("POST data: $postData");
        }
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            $this->logError("Curl error: $error");
            curl_close($ch);
            throw new \Exception('Curl error: ' . $error);
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        $this->logDebug("Response code: $httpCode");
        $this->logDebug("Response body: $response");
        
        curl_close($ch);
        
        $decodedResponse = json_decode($response, true) ?? [];
        
        if ($httpCode >= 400) {
            $errorMsg = $decodedResponse['message'] ?? "HTTP error $httpCode";
            $this->logError("API error: $errorMsg");
            throw new \Exception($errorMsg);
        }
        
        return $decodedResponse;
    }
    
    private function logError(string $message): void {
        file_put_contents(__DIR__ . '/../logs/jupiter_errors.log', date('Y-m-d H:i:s') . ' - ' . $message . "\n", FILE_APPEND);
    }
    
    private function logDebug(string $message): void {
        file_put_contents(__DIR__ . '/../logs/jupiter_debug.log', date('Y-m-d H:i:s') . ' - ' . $message . "\n", FILE_APPEND);
    }
}
