<?php

class Wallet {
    private $db;
    private $exchangeId;
    private $exchange;
    
    public function __construct($exchangeId = null) {
        global $db; // Assuming $db is your database connection
        $this->db = $db;
        $this->exchangeId = $exchangeId ?? 'default';
        
        // Initialize exchange if needed
        if ($exchangeId) {
            $this->exchange = $this->initializeExchange($exchangeId);
        }
    }
    
    /**
     * Initialize exchange instance
     */
    private function initializeExchange($exchangeId) {
        $exchangeClass = ucfirst(strtolower($exchangeId)) . 'Exchange';
        $exchangeFile = __DIR__ . '/../exchanges/' . $exchangeClass . '.php';
        
        if (file_exists($exchangeFile)) {
            require_once $exchangeFile;
            return new $exchangeClass();
        }
        
        throw new Exception("Exchange {$exchangeId} not found");
    }
    
    /**
     * Get wallet balance for a specific currency
     */
    public function getBalance($currency, $forceUpdate = false) {
        $currency = strtoupper($currency);
        
        // Check if we should update from exchange
        if ($forceUpdate || $this->shouldUpdateBalance($currency)) {
            $this->updateFromExchange($currency);
        }
        
        // Get from local database
        $stmt = $this->db->prepare("
            SELECT * FROM wallets 
            WHERE exchange_id = ? AND currency = ?
        ");
        $stmt->bind_param('ss', $this->exchangeId, $currency);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'available' => 0,
                'in_orders' => 0,
                'total' => 0,
                'currency' => $currency,
                'exchange' => $this->exchangeId
            ];
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Get all wallet balances
     */
    public function getAllBalances($forceUpdate = false) {
        if ($forceUpdate) {
            $this->updateAllFromExchange();
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM wallets 
            WHERE exchange_id = ?
            ORDER BY total_balance DESC
        ");
        $stmt->bind_param('s', $this->exchangeId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $balances = [];
        while ($row = $result->fetch_assoc()) {
            $balances[$row['currency']] = $row;
        }
        
        return $balances;
    }
    
    /**
     * Update wallet balance from exchange
     */
    private function updateFromExchange($currency) {
        if (!$this->exchange) {
            throw new Exception("No exchange configured for wallet");
        }
        
        try {
            $balance = $this->exchange->fetchBalance(['currency' => $currency]);
            
            if (isset($balance[$currency])) {
                $this->updateLocalBalance(
                    $currency,
                    $balance[$currency]['free'],
                    $balance[$currency]['used'],
                    $balance[$currency]['total']
                );
            }
        } catch (Exception $e) {
            error_log("Failed to update balance from exchange: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update all balances from exchange
     */
    private function updateAllFromExchange() {
        if (!$this->exchange) {
            throw new Exception("No exchange configured for wallet");
        }
        
        try {
            $balances = $this->exchange->fetchBalance();
            
            foreach ($balances as $currency => $balance) {
                if ($balance['total'] > 0) {
                    $this->updateLocalBalance(
                        $currency,
                        $balance['free'],
                        $balance['used'],
                        $balance['total']
                    );
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to update all balances: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update local wallet balance in database
     */
    private function updateLocalBalance($currency, $available, $inOrders, $total) {
        $currency = strtoupper($currency);
        
        // Get current balance for comparison
        $current = $this->getBalance($currency);
        
        // Check if record exists
        if (empty($current['id'])) {
            // Insert new record
            $stmt = $this->db->prepare("
                INSERT INTO wallets 
                (exchange_id, currency, available_balance, in_orders, last_updated)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param('ssdd', $this->exchangeId, $currency, $available, $inOrders);
        } else {
            // Update existing record
            $stmt = $this->db->prepare("
                UPDATE wallets 
                SET available_balance = ?, 
                    in_orders = ?,
                    last_updated = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param('ddi', $available, $inOrders, $current['id']);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update wallet balance: " . $stmt->error);
        }
        
        // Log the transaction if balance changed
        if (empty($current['id']) || 
            $current['available_balance'] != $available || 
            $current['in_orders'] != $inOrders) {
            
            $this->logTransaction(
                $current['id'] ?? $this->db->insert_id,
                $currency,
                $available - ($current['available_balance'] ?? 0),
                $current['available_balance'] ?? 0,
                $available
            );
        }
        
        return true;
    }
    
    /**
     * Log wallet transaction
     */
    private function logTransaction($walletId, $currency, $amount, $balanceBefore, $balanceAfter) {
        $type = $amount >= 0 ? 'deposit' : 'withdrawal';
        
        $stmt = $this->db->prepare("
            INSERT INTO wallet_transactions 
            (wallet_id, exchange_id, currency, type, amount, balance_before, balance_after)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isssddd', 
            $walletId,
            $this->exchangeId,
            $currency,
            $type,
            abs($amount),
            $balanceBefore,
            $balanceAfter
        );
        
        if (!$stmt->execute()) {
            error_log("Failed to log wallet transaction: " . $stmt->error);
            return false;
        }
        
        return $this->db->insert_id;
    }
    
    /**
     * Check if balance should be updated from exchange
     */
    private function shouldUpdateBalance($currency) {
        // Update balance if it's older than 5 minutes
        $stmt = $this->db->prepare("
            SELECT TIMESTAMPDIFF(MINUTE, last_updated, NOW()) as minutes_old 
            FROM wallets 
            WHERE exchange_id = ? AND currency = ?
        ");
        $stmt->bind_param('ss', $this->exchangeId, $currency);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return true; // Doesn't exist, needs update
        }
        
        $row = $result->fetch_assoc();
        return $row['minutes_old'] > 5; // Update if older than 5 minutes
    }
    
    /**
     * Get transaction history
     */
    public function getTransactionHistory($currency = null, $limit = 100, $offset = 0) {
        $query = "
            SELECT t.*, w.currency 
            FROM wallet_transactions t
            JOIN wallets w ON t.wallet_id = w.id
            WHERE w.exchange_id = ?
        ";
        
        $params = [$this->exchangeId];
        $types = 's';
        
        if ($currency) {
            $query .= " AND w.currency = ?";
            $params[] = strtoupper($currency);
            $types .= 's';
        }
        
        $query .= " ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        $types .= 'ii';
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// End of Wallet class
