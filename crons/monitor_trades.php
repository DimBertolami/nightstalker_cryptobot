<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

/**
 * Night Stalker Trading Algorithm
 */
class TradeMonitor {
    private $db;
    
    public function __construct() {
        $this->db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->db->connect_error) {
            die("Connection failed: " . $this->db->connect_error);
        }
    }

    public function monitorTrades() {
        try {
            $this->processActiveTrades();
            $this->findNewOpportunities();
            echo "Trade monitoring completed successfully\n";
        } catch (Exception $e) {
            logEvent("Trade monitoring failed: " . $e->getMessage(), 'error');
            throw $e;
        } finally {
            $this->db->close();
        }
    }
    
    private function processActiveTrades() {
        $activeTrades = $this->getActiveTrades();
        
        foreach ($activeTrades as $trade) {
            if (!in_array($trade['coin_id'], COIN_WHITELIST)) {
                continue; // Skip non-whitelisted coins
            }
            
            $currentPrice = $this->getCurrentPrice($trade['coin_id']);
            $recentPrices = $this->getRecentPrices($trade['coin_id'], CONSECUTIVE_DECREASES_FOR_SELL);
            
            if ($this->shouldSell($recentPrices)) {
                $this->executeSellTrade($trade, $currentPrice);
                continue;
            }
            
            $this->updatePriceHistory($trade, $currentPrice);
        }
    }
    
    private function getActiveTrades() {
        $result = $this->db->query("
            SELECT t.*, c.symbol, c.volume, c.market_cap 
            FROM trades t
            JOIN cryptocurrencies c ON t.coin_id = c.id
            WHERE t.trade_type = 'buy'
            AND NOT EXISTS (
                SELECT 1 FROM trades t2 
                WHERE t2.coin_id = t.coin_id 
                AND t2.trade_type = 'sell'
                AND t2.trade_time > t.trade_time
            )
            AND c.id IN ('" . implode("','", COIN_WHITELIST) . "')
        ");
        
        if (!$result) {
            throw new Exception("Failed to get active trades: " . $this->db->error);
        }
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getCurrentPrice($coinId) {
        $stmt = $this->db->prepare("SELECT price FROM cryptocurrencies WHERE id = ? AND id IN ('" . implode("','", COIN_WHITELIST) . "')");
        $stmt->bind_param('i', $coinId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['price'];
    }
    
    private function getRecentPrices($coinId, $limit) {
        $stmt = $this->db->prepare("
            SELECT price FROM price_history 
            WHERE coin_id = ? 
            ORDER BY recorded_at DESC 
            LIMIT ?
        ");
        $stmt->bind_param('ii', $coinId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        return array_column($result->fetch_all(MYSQLI_ASSOC), 'price');
    }
    
    private function shouldSell($prices) {
        if (count($prices) < CONSECUTIVE_DECREASES_FOR_SELL) {
            return false;
        }
        
        for ($i = 1; $i < count($prices); $i++) {
            if ($prices[$i] >= $prices[$i-1]) {
                return false;
            }
        }
        
        return true;
    }
    
    private function executeSellTrade($trade, $currentPrice) {
        $profit = ($currentPrice - $trade['price']) * $trade['amount'];
        
        $stmt = $this->db->prepare("
            INSERT INTO trades 
            (coin_id, trade_type, price, amount) 
            VALUES (?, 'sell', ?, ?)
        ");
        $stmt->bind_param('idd', $trade['coin_id'], $currentPrice, $trade['amount']);
        $stmt->execute();
        
        logEvent("Sold {$trade['symbol']} due to price decrease", 'info', [
            'buy_price' => $trade['price'],
            'sell_price' => $currentPrice,
            'profit' => $profit,
            'trade_id' => $trade['id']
        ]);
    }
    
    private function updatePriceHistory($trade, $currentPrice) {
        error_log("Attempting to update price history for: ".$trade['coin_id']);
        
        if (!in_array($trade['coin_id'], COIN_WHITELIST)) {
            return;
        }

        $coinId = $trade['coin_id'];
        $price = $currentPrice;
        $volume = $trade['volume'];
        $marketCap = $trade['market_cap'];

        $stmt = $this->db->prepare("INSERT INTO price_history (coin_id, price, volume, market_cap) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('sddd', $coinId, $price, $volume, $marketCap);
        
        if ($stmt->execute()) {
            error_log("Price history updated for: ".$coinId);
        } else {
            error_log("Price history update failed: " . $this->db->error);
        }
    }
        
    private function findNewOpportunities() {
        $whitelist = implode("','", COIN_WHITELIST);
        $result = $this->db->query("
            SELECT * FROM cryptocurrencies 
            WHERE id IN ('$whitelist')
            AND volume >= " . MIN_VOLUME_THRESHOLD . "
            AND age_hours <= " . MAX_COIN_AGE . "
            AND id NOT IN (
                SELECT coin_id FROM trades WHERE trade_type = 'buy'
            )
        ");
        
        if (!$result) {
            throw new Exception("Failed to get volume spike coins: " . $this->db->error);
        }
        
        $newCoins = $result->fetch_all(MYSQLI_ASSOC);
        
        foreach ($newCoins as $coin) {
            $this->executeBuyTrade($coin);
        }
    }
    
    private function executeBuyTrade($coin) {
        require_once __DIR__ . '/../includes/pdo_functions.php';
        $amountToBuy = 1000 / $coin['price']; // Example: $1000 worth
        
        // Use the PDO buy logic to ensure trades and portfolio are updated
        $tradeId = executeBuyPDO($coin['id'], $amountToBuy, $coin['price']);
        
        logEvent("Bought {$coin['symbol']} due to volume spike", 'info', [
            'amount' => $amountToBuy,
            'price' => $coin['price'],
            'trade_id' => $tradeId
        ]);
    }
}

// Execute the monitoring
try {
    $monitor = new TradeMonitor();
    $monitor->monitorTrades();
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
