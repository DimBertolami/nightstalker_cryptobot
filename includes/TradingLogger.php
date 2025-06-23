<?php
/**
 * TradingLogger Class
 * 
 * Handles detailed logging of trading activities and performance metrics
 */
class TradingLogger {
    private $conn;
    private $logTable = 'trading_logs';
    private $statsTable = 'trading_stats';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Connect to database
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            die("Database connection failed: " . $this->conn->connect_error);
        }
        
        // Ensure log tables exist
        $this->ensureTablesExist();
    }
    
    /**
     * Log a trading event
     * 
     * @param string $strategy Strategy name
     * @param string $event Event type (e.g., 'buy', 'sell', 'monitor', 'error')
     * @param array $data Event data
     * @return bool Success or failure
     */
    public function logEvent($strategy, $event, $data = []) {
        $dataJson = json_encode($data);
        
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO {$this->logTable} (strategy, event_type, event_data, event_time)
                VALUES (?, ?, ?, NOW())
            ");
            
            $stmt->bind_param("sss", $strategy, $event, $dataJson);
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
        } catch (Exception $e) {
            error_log("Error logging trading event: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update trading statistics
     * 
     * @param string $strategy Strategy name
     * @param array $stats Statistics to update
     * @return bool Success or failure
     */
    public function updateStats($strategy, $stats) {
        try {
            // Check if stats exist for this strategy
            $stmt = $this->conn->prepare("SELECT id FROM {$this->statsTable} WHERE strategy = ?");
            $stmt->bind_param("s", $strategy);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result->num_rows > 0;
            $stmt->close();
            
            if ($exists) {
                // Update existing stats
                $query = "UPDATE {$this->statsTable} SET ";
                $params = [];
                $types = "";
                
                foreach ($stats as $key => $value) {
                    $query .= "$key = ?, ";
                    $params[] = $value;
                    
                    if (is_int($value)) {
                        $types .= "i";
                    } elseif (is_float($value)) {
                        $types .= "d";
                    } else {
                        $types .= "s";
                    }
                }
                
                $query .= "last_updated = NOW() WHERE strategy = ?";
                $params[] = $strategy;
                $types .= "s";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $result = $stmt->execute();
                $stmt->close();
                
                return $result;
            } else {
                // Insert new stats
                $keys = array_keys($stats);
                $keys[] = 'strategy';
                $keys[] = 'created_at';
                $keys[] = 'last_updated';
                
                $placeholders = array_fill(0, count($stats), '?');
                $placeholders[] = '?';
                $placeholders[] = 'NOW()';
                $placeholders[] = 'NOW()';
                
                $query = "INSERT INTO {$this->statsTable} (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $placeholders) . ")";
                
                $params = array_values($stats);
                $params[] = $strategy;
                
                $types = "";
                foreach ($params as $value) {
                    if (is_int($value)) {
                        $types .= "i";
                    } elseif (is_float($value)) {
                        $types .= "d";
                    } else {
                        $types .= "s";
                    }
                }
                
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $result = $stmt->execute();
                $stmt->close();
                
                return $result;
            }
        } catch (Exception $e) {
            error_log("Error updating trading stats: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get trading statistics for a strategy
     * 
     * @param string $strategy Strategy name
     * @return array Statistics
     */
    public function getStats($strategy) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->statsTable} WHERE strategy = ?");
            $stmt->bind_param("s", $strategy);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats = $result->fetch_assoc();
            $stmt->close();
            
            return $stats ?: [];
        } catch (Exception $e) {
            error_log("Error getting trading stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent trading events
     * 
     * @param string $strategy Strategy name (optional)
     * @param int $limit Maximum number of events to return
     * @return array Events
     */
    public function getRecentEvents($strategy = null, $limit = 100) {
        try {
            if ($strategy) {
                $stmt = $this->conn->prepare("
                    SELECT * FROM {$this->logTable} 
                    WHERE strategy = ? 
                    ORDER BY event_time DESC 
                    LIMIT ?
                ");
                $stmt->bind_param("si", $strategy, $limit);
            } else {
                $stmt = $this->conn->prepare("
                    SELECT * FROM {$this->logTable} 
                    ORDER BY event_time DESC 
                    LIMIT ?
                ");
                $stmt->bind_param("i", $limit);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $events = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Parse JSON data
            foreach ($events as &$event) {
                if (isset($event['event_data'])) {
                    $event['event_data'] = json_decode($event['event_data'], true);
                }
            }
            
            return $events;
        } catch (Exception $e) {
            error_log("Error getting recent events: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get trading performance metrics
     * 
     * @param string $strategy Strategy name
     * @param string $period Period ('day', 'week', 'month', 'all')
     * @return array Performance metrics
     */
    public function getPerformance($strategy, $period = 'all') {
        try {
            $whereClause = "strategy = ?";
            
            switch ($period) {
                case 'day':
                    $whereClause .= " AND event_time >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
                    break;
                case 'week':
                    $whereClause .= " AND event_time >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                    break;
                case 'month':
                    $whereClause .= " AND event_time >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                    break;
            }
            
            // Get buy events
            $buyQuery = "
                SELECT COUNT(*) as count, SUM(JSON_EXTRACT(event_data, '$.cost')) as total_cost
                FROM {$this->logTable}
                WHERE {$whereClause} AND event_type = 'buy'
            ";
            
            $stmt = $this->conn->prepare($buyQuery);
            $stmt->bind_param("s", $strategy);
            $stmt->execute();
            $buyResult = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            // Get sell events
            $sellQuery = "
                SELECT COUNT(*) as count, SUM(JSON_EXTRACT(event_data, '$.proceeds')) as total_proceeds,
                SUM(JSON_EXTRACT(event_data, '$.profit')) as total_profit
                FROM {$this->logTable}
                WHERE {$whereClause} AND event_type = 'sell'
            ";
            
            $stmt = $this->conn->prepare($sellQuery);
            $stmt->bind_param("s", $strategy);
            $stmt->execute();
            $sellResult = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            // Calculate performance metrics
            $performance = [
                'buys' => $buyResult['count'] ?: 0,
                'sells' => $sellResult['count'] ?: 0,
                'total_cost' => $buyResult['total_cost'] ?: 0,
                'total_proceeds' => $sellResult['total_proceeds'] ?: 0,
                'total_profit' => $sellResult['total_profit'] ?: 0,
                'roi_percentage' => 0
            ];
            
            // Calculate ROI percentage
            if ($performance['total_cost'] > 0) {
                $performance['roi_percentage'] = ($performance['total_profit'] / $performance['total_cost']) * 100;
            }
            
            return $performance;
        } catch (Exception $e) {
            error_log("Error getting performance metrics: " . $e->getMessage());
            return [
                'buys' => 0,
                'sells' => 0,
                'total_cost' => 0,
                'total_proceeds' => 0,
                'total_profit' => 0,
                'roi_percentage' => 0
            ];
        }
    }
    
    /**
     * Get filtered events based on various criteria
     * 
     * @param string $strategy The trading strategy name
     * @param string $eventType Filter by event type (buy, sell, monitor, etc.)
     * @param string $symbol Filter by trading symbol
     * @param string $dateFrom Filter by start date (YYYY-MM-DD)
     * @param string $dateTo Filter by end date (YYYY-MM-DD)
     * @param int $limit Maximum number of events to return
     * @return array Filtered events
     */
    public function getFilteredEvents($strategy, $eventType = '', $symbol = '', $dateFrom = '', $dateTo = '', $limit = 100) {
        $this->ensureTablesExist();
        
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log('[TradingLogger] Fetching events with params: ' . 
                print_r(func_get_args(), true));
        }
        
        $query = "SELECT * FROM {$this->logTable} WHERE strategy = ?";
        $types = "s";
        $params = [$strategy];
        
        if (!empty($eventType)) {
            $query .= " AND event_type = ?";
            $params[] = $eventType;
            $types .= "s";
        }
        
        if (!empty($symbol)) {
            $query .= " AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.symbol')) = ?";
            $params[] = $symbol;
            $types .= "s";
        }
        
        if (!empty($dateFrom)) {
            $query .= " AND event_time >= ?";
            $params[] = $dateFrom;
            $types .= "s";
        }
        
        if (!empty($dateTo)) {
            $query .= " AND event_time <= ?";
            $params[] = $dateTo;
            $types .= "s";
        }
        
        $query .= " ORDER BY event_time DESC LIMIT ?";
        $params[] = $limit;
        $types .= "i";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $events = [];
            while ($row = $result->fetch_assoc()) {
                $row['event_data'] = json_decode($row['event_data'], true);
                $events[] = $row;
            }
            
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('[TradingLogger] Found ' . count($events) . ' events');
            }
            
            return $events;
        } catch (Exception $e) {
            error_log("Error fetching filtered events: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get unique symbols from trading logs
     * 
     * @param string $strategy The trading strategy name
     * @return array List of unique symbols
     */
    public function getUniqueSymbols($strategy) {
        $this->ensureTablesExist();
        
        $query = "SELECT DISTINCT JSON_EXTRACT(event_data, '$.symbol') as symbol 
                 FROM {$this->logTable} 
                 WHERE strategy = ? AND JSON_EXTRACT(event_data, '$.symbol') IS NOT NULL 
                 ORDER BY symbol ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $strategy);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $symbols = [];
        while ($row = $result->fetch_assoc()) {
            // Remove quotes from JSON extraction
            $symbol = trim($row['symbol'], '"');
            if (!empty($symbol)) {
                $symbols[] = $symbol;
            }
        }
        
        $stmt->close();
        return $symbols;
    }
    
    /**
     * Reset trading statistics for a strategy
     * This will reset all calculated statistics but preserve the trading logs
     * 
     * @param string $strategy The trading strategy name
     * @return bool Success status
     */
    public function resetStatistics($strategy) {
        $this->ensureTablesExist();
        
        // Reset the trading stats table for this strategy
        $query = "UPDATE {$this->statsTable} SET 
                 trades_executed = 0,
                 successful_trades = 0,
                 failed_trades = 0,
                 total_profit = 0,
                 win_rate = 0,
                 avg_profit_percentage = 0,
                 avg_holding_time = 0,
                 best_trade_profit = 0,
                 worst_trade_loss = 0,
                 last_updated = NOW()
                 WHERE strategy = ?";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $strategy);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Ensure the required tables exist
     */
    private function ensureTablesExist() {
        // Create logs table
        $logTableSql = "CREATE TABLE IF NOT EXISTS {$this->logTable} (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            strategy VARCHAR(50) NOT NULL,
            event_type VARCHAR(20) NOT NULL,
            event_data JSON,
            event_time DATETIME NOT NULL,
            INDEX idx_strategy (strategy),
            INDEX idx_event_type (event_type),
            INDEX idx_event_time (event_time)
        )";
        
        if (!$this->conn->query($logTableSql)) {
            error_log("Error creating log table: " . $this->conn->error);
        }
        
        // Create stats table
        $statsTableSql = "CREATE TABLE IF NOT EXISTS {$this->statsTable} (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            strategy VARCHAR(50) NOT NULL UNIQUE,
            trades_executed INT(11) DEFAULT 0,
            successful_trades INT(11) DEFAULT 0,
            failed_trades INT(11) DEFAULT 0,
            total_profit DECIMAL(18,8) DEFAULT 0,
            win_rate DECIMAL(5,2) DEFAULT 0,
            avg_profit_percentage DECIMAL(5,2) DEFAULT 0,
            avg_holding_time INT(11) DEFAULT 0,
            best_trade_profit DECIMAL(18,8) DEFAULT 0,
            worst_trade_loss DECIMAL(18,8) DEFAULT 0,
            active_trade_symbol VARCHAR(20) DEFAULT NULL,
            active_trade_buy_price DECIMAL(18,8) DEFAULT NULL,
            active_trade_time DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            last_updated DATETIME NOT NULL,
            INDEX idx_strategy (strategy)
        )";
        
        if (!$this->conn->query($statsTableSql)) {
            error_log("Error creating stats table: " . $this->conn->error);
        }
    }
}
