<?php
/**
 * Strategy Factory
 * 
 * Factory class to create strategy instances based on configuration
 * Part of the Night Stalker cryptobot autonomous trading system
 */

namespace NightStalker\Strategies;

require_once __DIR__ . '/BaseStrategy.php';
require_once __DIR__ . '/VolumeSpikeStrategy.php';
require_once __DIR__ . '/TrendingCoinsStrategy.php';

class StrategyFactory {
    /**
     * Create a strategy instance based on type
     * 
     * @param string $type Strategy type
     * @param array $config Strategy configuration
     * @param object $trader Trader instance
     * @param object $db Database connection
     * @return BaseStrategy Strategy instance
     * @throws \Exception If strategy type is invalid
     */
    public static function createStrategy($type, $config, $trader, $db) {
        switch ($type) {
            case 'volume_spike':
                return new VolumeSpikeStrategy($config, $trader, $db);
                
            case 'trending_coins':
                return new TrendingCoinsStrategy($config, $trader, $db);
                
            default:
                throw new \Exception("Unknown strategy type: $type");
        }
    }
    
    /**
     * Get all available strategy types
     * 
     * @return array List of available strategy types
     */
    public static function getAvailableStrategyTypes() {
        return [
            'volume_spike' => 'Volume Spike Strategy',
            'trending_coins' => 'Trending Coins Strategy'
        ];
    }
}
