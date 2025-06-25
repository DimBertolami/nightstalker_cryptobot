<?php
namespace NS\DataSources;

/**
 * Interface for all cryptocurrency data sources
 * All data source adapters should implement this interface to ensure consistent methods
 */
interface CryptoDataSourceInterface {
    /**
     * Get new coins listed within a specified timeframe
     * 
     * @param int $days Maximum age of coins in days
     * @param float $minMarketCap Minimum market cap in USD
     * @param float $minVolume Minimum 24h volume in USD
     * @return array List of coins with their details
     */
    public function getNewCoins(int $days = 30, float $minMarketCap = 1000000, float $minVolume = 100000): array;
    
    /**
     * Get detailed information for a specific coin
     * 
     * @param string $symbol Coin symbol (e.g., BTC, ETH)
     * @return array|null Coin details or null if not found
     */
    public function getCoinDetails(string $symbol): ?array;
    
    /**
     * Get current market metrics for a list of coins
     * 
     * @param array $symbols List of coin symbols
     * @return array Market data keyed by symbol
     */
    public function getMarketMetrics(array $symbols): array;
    
    /**
     * Search for coins matching specific criteria
     * 
     * @param array $filters Associative array of filter criteria
     * @return array Matching coins
     */
    public function searchCoins(array $filters): array;
    
    /**
     * Get trending coins based on specified metrics
     * 
     * @param string $metric Metric to sort by (e.g., 'volume_change_24h', 'price_change_24h')
     * @param int $limit Maximum number of results
     * @return array List of trending coins
     */
    public function getTrendingCoins(string $metric = 'volume_change_24h', int $limit = 20): array;
    
    /**
     * Test the connection to the API
     * 
     * @return bool True if connection successful
     */
    public function testConnection(): bool;
}