<?php
/**
 * Volume Spike Strategy
 * 
 * Trading strategy that identifies and trades coins with significant volume increases
 * Part of the Night Stalker cryptobot autonomous trading system
 */

namespace NightStalker\Strategies;

require_once __DIR__ . '/BaseStrategy.php';

class VolumeSpikeStrategy extends BaseStrategy {
    
    /**
     * Constructor
     * 
     * @param array $config Strategy configuration
     * @param object $trader Trader instance (Binance, Bitvavo, etc.)
     * @param object $db Database connection
     */
    public function __construct($config, $trader, $db) {
        parent::__construct($config, $trader, $db);
        $this->name = 'Volume Spike Strategy';
    }
    
    /**
     * Get trading signals based on volume spikes
     * 
     * @return array Trading signals
     */
    public function getSignals() {
        $this->log("Analyzing market for volume spikes");
        
        $signals = [];
        $minVolumeIncrease = $this->config['min_volume_increase'] ?? 20; // Default 20%
        $timeframe = $this->config['timeframe'] ?? '24h';
        $maxInvestment = $this->config['max_investment'] ?? 100; // Default $100 per trade
        
        try {
            // Get coins with volume spikes from database
            $hours = 24;
            if ($timeframe === '1h') $hours = 1;
            if ($timeframe === '4h') $hours = 4;
            if ($timeframe === '12h') $hours = 12;
            
            $query = "SELECT c.* FROM coins c 
                WHERE c.volume_change_24h >= :min_volume_increase 
                AND c.volume_24h > 0
                AND c.current_price > 0
                ORDER BY c.volume_change_24h DESC 
                LIMIT 5";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':min_volume_increase', $minVolumeIncrease);
            $stmt->execute();
            $coins = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->log("Found " . count($coins) . " coins with volume spikes >= $minVolumeIncrease%");
            
            // Check if we already have positions in these coins
            foreach ($coins as $coin) {
                $coinId = $coin['id'];
                $symbol = strtoupper($coin['symbol']);
                $price = $coin['current_price'];
                
                // Skip coins with no price data
                if ($price <= 0) {
                    $this->log("Skipping $symbol due to invalid price data", 'warning');
                    continue;
                }
                
                // Check if we already have a position in this coin
                $stmt = $this->db->prepare("SELECT * FROM portfolio WHERE coin_id = :coin_id");
                $stmt->bindParam(':coin_id', $coinId);
                $stmt->execute();
                $existingPosition = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                // For now, we'll only buy coins we don't already own
                if (!$existingPosition) {
                    // Calculate amount to buy based on max investment
                    $amount = $maxInvestment / $price;
                    $amount = round($amount, 8); // Round to 8 decimal places
                    
                    // Skip if amount is too small
                    if ($amount <= 0) {
                        $this->log("Skipping $symbol due to insufficient amount", 'warning');
                        continue;
                    }
                    
                    $signals[] = [
                        'symbol' => $symbol,
                        'action' => 'buy',
                        'amount' => $amount,
                        'price' => $price,
                        'reason' => "Volume spike of {$coin['volume_change_24h']}%"
                    ];
                    
                    $this->log("Generated BUY signal for $symbol at $price, amount: $amount, reason: Volume spike of {$coin['volume_change_24h']}%");
                } else {
                    $this->log("Skipping $symbol as we already have a position", 'info');
                }
            }
            
            // Check for sell signals based on stop loss or take profit
            $this->generateSellSignals($signals);
            
            return $signals;
        } catch (\Exception $e) {
            $this->log("Error generating signals: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Generate sell signals based on stop loss or take profit
     * 
     * @param array &$signals Array to add sell signals to
     */
    protected function generateSellSignals(&$signals) {
        $stopLoss = $this->config['stop_loss'] ?? 5; // Default 5%
        $takeProfit = $this->config['take_profit'] ?? 10; // Default 10%
        
        try {
            // Get all positions from portfolio
            $stmt = $this->db->prepare("SELECT p.*, c.current_price, c.symbol 
                FROM portfolio p 
                JOIN coins c ON p.coin_id = c.id 
                WHERE p.amount > 0");
            $stmt->execute();
            $positions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($positions as $position) {
                $coinId = $position['coin_id'];
                $symbol = strtoupper($position['symbol']);
                $amount = $position['amount'];
                $avgBuyPrice = $position['avg_buy_price'];
                $currentPrice = $position['current_price'];
                
                // Skip if no current price
                if ($currentPrice <= 0) continue;
                
                // Calculate profit/loss percentage
                $pnlPercent = (($currentPrice - $avgBuyPrice) / $avgBuyPrice) * 100;
                
                // Check for stop loss
                if ($pnlPercent <= -$stopLoss) {
                    $signals[] = [
                        'symbol' => $symbol,
                        'action' => 'sell',
                        'amount' => $amount,
                        'price' => $currentPrice,
                        'reason' => "Stop loss triggered at {$pnlPercent}%"
                    ];
                    
                    $this->log("Generated SELL signal for $symbol at $currentPrice, amount: $amount, reason: Stop loss triggered at {$pnlPercent}%");
                }
                
                // Check for take profit
                if ($pnlPercent >= $takeProfit) {
                    $signals[] = [
                        'symbol' => $symbol,
                        'action' => 'sell',
                        'amount' => $amount,
                        'price' => $currentPrice,
                        'reason' => "Take profit triggered at {$pnlPercent}%"
                    ];
                    
                    $this->log("Generated SELL signal for $symbol at $currentPrice, amount: $amount, reason: Take profit triggered at {$pnlPercent}%");
                }
            }
        } catch (\Exception $e) {
            $this->log("Error generating sell signals: " . $e->getMessage(), 'error');
        }
    }
}
