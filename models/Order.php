<?php

class Order {
    private $db;
    private $exchange;
    private $exchangeId;
    
    public function __construct($exchangeId = null) {
        global $db;
        $this->db = $db;
        $this->exchangeId = $exchangeId;
        
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
     * Create a new order
     */
    public function create($params) {
        // Validate required parameters
        $required = ['symbol', 'type', 'side', 'amount'];
        foreach ($required as $field) {
            if (!isset($params[$field])) {
                throw new Exception("Missing required parameter: {$field}");
            }
        }
        
        $symbol = strtoupper($params['symbol']);
        $type = strtolower($params['type']);
        $side = strtolower($params['side']);
        $amount = (float)$params['amount'];
        $price = isset($params['price']) ? (float)$params['price'] : null;
        $stopPrice = isset($params['stopPrice']) ? (float)$params['stopPrice'] : null;
        $clientOrderId = $params['clientOrderId'] ?? null;
        
        // Validate order parameters
        $this->validateOrder($symbol, $type, $side, $amount, $price, $stopPrice);
        
        try {
            // Start transaction
            $this->db->begin_transaction();
            
            // Create order in database with 'pending' status
            $orderId = $this->createOrderInDb([
                'symbol' => $symbol,
                'type' => $type,
                'side' => $side,
                'amount' => $amount,
                'price' => $price,
                'stop_price' => $stopPrice,
                'client_order_id' => $clientOrderId,
                'status' => 'pending'
            ]);
            
            // Execute order on exchange
            $exchangeOrder = $this->executeOrderOnExchange([
                'symbol' => $symbol,
                'type' => $type,
                'side' => $side,
                'amount' => $amount,
                'price' => $price,
                'stopPrice' => $stopPrice,
                'params' => $params['params'] ?? []
            ]);
            
            // Update order with exchange response
            $this->updateOrderFromExchange($orderId, $exchangeOrder);
            
            // Update wallet balances
            $this->updateWalletBalances($orderId, $exchangeOrder);
            
            // Commit transaction
            $this->db->commit();
            
            return $this->getOrder($orderId);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->db->rollback();
            
            // Update order status to 'rejected' if it was created
            if (isset($orderId)) {
                $this->updateOrderStatus($orderId, 'rejected', $e->getMessage());
            }
            
            throw $e;
        }
    }
    
    /**
     * Validate order parameters
     */
    private function validateOrder($symbol, $type, $side, $amount, $price, $stopPrice) {
        // Basic validation
        if ($amount <= 0) {
            throw new Exception("Amount must be greater than 0");
        }
        
        if (in_array($type, ['limit', 'stop_loss_limit', 'take_profit_limit']) && $price <= 0) {
            throw new Exception("Price is required for {$type} orders");
        }
        
        if (in_array($type, ['stop_loss', 'stop_loss_limit', 'take_profit', 'take_profit_limit']) && $stopPrice <= 0) {
            throw new Exception("Stop price is required for {$type} orders");
        }
        
        // TODO: Add more validation based on exchange-specific rules
        // - Minimum order size
        // - Price precision
        // - Amount precision
        // - Symbol availability
        
        return true;
    }
    
    /**
     * Create order in database
     */
    private function createOrderInDb($params) {
        $stmt = $this->db->prepare("
            INSERT INTO orders (
                exchange_id, symbol, type, side, price, stop_price, 
                amount, status, client_order_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param(
            'ssssdddss',
            $this->exchangeId,
            $params['symbol'],
            $params['type'],
            $params['side'],
            $params['price'],
            $params['stop_price'],
            $params['amount'],
            $params['status'],
            $params['client_order_id']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create order in database: " . $stmt->error);
        }
        
        return $this->db->insert_id;
    }
    
    /**
     * Execute order on exchange
     */
    private function executeOrderOnExchange($params) {
        if (!$this->exchange) {
            throw new Exception("No exchange configured");
        }
        
        try {
            $orderParams = [
                'symbol' => $params['symbol'],
                'type' => $params['type'],
                'side' => $params['side'],
                'amount' => $params['amount'],
                'params' => $params['params'] ?? []
            ];
            
            // Add price for limit orders
            if (in_array($params['type'], ['limit', 'stop_loss_limit', 'take_profit_limit'])) {
                $orderParams['price'] = $params['price'];
            }
            
            // Add stop price for stop/take-profit orders
            if (in_array($params['type'], ['stop_loss', 'stop_loss_limit', 'take_profit', 'take_profit_limit'])) {
                $orderParams['stopPrice'] = $params['stopPrice'];
            }
            
            return $this->exchange->createOrder($orderParams);
            
        } catch (Exception $e) {
            throw new Exception("Exchange error: " . $e->getMessage());
        }
    }
    
    /**
     * Update order from exchange response
     */
    private function updateOrderFromExchange($orderId, $exchangeOrder) {
        $status = $this->mapExchangeStatus($exchangeOrder['status'] ?? 'closed');
        
        $stmt = $this->db->prepare("
            UPDATE orders 
            SET order_id = ?,
                status = ?,
                filled = ?,
                remaining = ?,
                cost = ?,
                fee = ?,
                fee_currency = ?,
                trades = ?,
                updated_at = NOW(),
                closed_at = ?
            WHERE id = ?
        ");
        
        $closedAt = in_array($status, ['closed', 'canceled', 'expired', 'rejected']) ? date('Y-m-d H:i:s') : null;
        
        $stmt->bind_param(
            'ssdddssssi',
            $exchangeOrder['id'],
            $status,
            $exchangeOrder['filled'] ?? 0,
            $exchangeOrder['remaining'] ?? $exchangeOrder['amount'],
            $exchangeOrder['cost'] ?? null,
            $exchangeOrder['fee']['cost'] ?? 0,
            $exchangeOrder['fee']['currency'] ?? null,
            json_encode($exchangeOrder['trades'] ?? []),
            $closedAt,
            $orderId
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update order: " . $stmt->error);
        }
        
        return true;
    }
    
    /**
     * Update wallet balances after order execution
     */
    private function updateWalletBalances($orderId, $exchangeOrder) {
        // Get order details
        $order = $this->getOrder($orderId);
        
        if (!$order) {
            throw new Exception("Order not found: {$orderId}");
        }
        
        // Skip if order is not filled
        if ($order['status'] !== 'closed' || $order['filled'] <= 0) {
            return false;
        }
        
        // Parse symbol to get base and quote currencies
        list($baseCurrency, $quoteCurrency) = $this->parseSymbol($order['symbol']);
        
        // Initialize wallet
        $wallet = new Wallet($this->exchangeId);
        
        if ($order['side'] === 'buy') {
            // For buy orders:
            // - Decrease quote currency (e.g., USDT)
            // - Increase base currency (e.g., BTC)
            
            $cost = $order['cost'] ?: ($order['filled'] * $order['price']);
            
            // Decrease quote currency
            $wallet->updateBalance($quoteCurrency, -$cost, 'trade', [
                'order_id' => $order['order_id'],
                'type' => 'buy',
                'symbol' => $order['symbol']
            ]);
            
            // Increase base currency
            $wallet->updateBalance($baseCurrency, $order['filled'], 'trade', [
                'order_id' => $order['order_id'],
                'type' => 'buy',
                'symbol' => $order['symbol']
            ]);
            
        } else {
            // For sell orders:
            // - Decrease base currency (e.g., BTC)
            // - Increase quote currency (e.g., USDT)
            
            $cost = $order['cost'] ?: ($order['filled'] * $order['price']);
            
            // Decrease base currency
            $wallet->updateBalance($baseCurrency, -$order['filled'], 'trade', [
                'order_id' => $order['order_id'],
                'type' => 'sell',
                'symbol' => $order['symbol']
            ]);
            
            // Increase quote currency
            $wallet->updateBalance($quoteCurrency, $cost, 'trade', [
                'order_id' => $order['order_id'],
                'type' => 'sell',
                'symbol' => $order['symbol']
            ]);
        }
        
        return true;
    }
    
    /**
     * Parse symbol into base and quote currencies
     */
    private function parseSymbol($symbol) {
        // Handle different symbol formats (e.g., BTC/USDT, BTCUSDT, BTC-USDT)
        if (strpos($symbol, '/') !== false) {
            return explode('/', $symbol, 2);
        } elseif (strpos($symbol, '-') !== false) {
            return explode('-', $symbol, 2);
        } else {
            // Try to split by common quote currencies
            $quoteCurrencies = ['USDT', 'USDC', 'BTC', 'ETH', 'BNB', 'EUR', 'USD'];
            
            foreach ($quoteCurrencies as $quote) {
                if (str_ends_with($symbol, $quote)) {
                    $base = substr($symbol, 0, -strlen($quote));
                    return [$base, $quote];
                }
            }
            
            throw new Exception("Could not parse symbol: {$symbol}");
        }
    }
    
    /**
     * Map exchange status to our status
     */
    private function mapExchangeStatus($exchangeStatus) {
        $statusMap = [
            'open' => 'open',
            'closed' => 'closed',
            'canceled' => 'canceled',
            'expired' => 'expired',
            'rejected' => 'rejected',
            'new' => 'open',
            'filled' => 'closed',
            'partially_filled' => 'open',
            'cancelled' => 'canceled',
            'pending_cancel' => 'open',
            'pending_new' => 'open',
            'rejected' => 'rejected',
            'expired' => 'expired',
            'stopped' => 'canceled',
            'done' => 'closed'
        ];
        
        $status = strtolower($exchangeStatus);
        return $statusMap[$status] ?? 'rejected';
    }
    
    /**
     * Get order by ID
     */
    public function getOrder($orderId) {
        $stmt = $this->db->prepare("
            SELECT * FROM orders 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return null;
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Get order by exchange order ID
     */
    public function getOrderByExchangeId($orderId) {
        $stmt = $this->db->prepare("
            SELECT * FROM orders 
            WHERE exchange_id = ? AND order_id = ?
        ");
        $stmt->bind_param('ss', $this->exchangeId, $orderId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return null;
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Get open orders
     */
    public function getOpenOrders($symbol = null, $limit = 100, $offset = 0) {
        $query = "
            SELECT * FROM orders 
            WHERE exchange_id = ? AND status = 'open'
        ";
        
        $params = [$this->exchangeId];
        $types = 's';
        
        if ($symbol) {
            $query .= " AND symbol = ?";
            $params[] = strtoupper($symbol);
            $types .= 's';
        }
        
        $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        $types .= 'ii';
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Cancel an order
     */
    public function cancelOrder($orderId) {
        // Get order details
        $order = $this->getOrder($orderId);
        
        if (!$order) {
            throw new Exception("Order not found: {$orderId}");
        }
        
        if ($order['status'] !== 'open') {
            throw new Exception("Cannot cancel order with status: {$order['status']}");
        }
        
        try {
            // Start transaction
            $this->db->begin_transaction();
            
            // Cancel order on exchange
            $result = $this->exchange->cancelOrder($order['order_id'], $order['symbol']);
            
            // Update order status
            $this->updateOrderStatus($orderId, 'canceled');
            
            // Update wallet balances if needed
            $this->updateWalletBalances($orderId, $result);
            
            // Commit transaction
            $this->db->commit();
            
            return $this->getOrder($orderId);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Failed to cancel order: " . $e->getMessage());
        }
    }
    
    /**
     * Update order status
     */
    private function updateOrderStatus($orderId, $status, $message = null) {
        $stmt = $this->db->prepare("
            UPDATE orders 
            SET status = ?, 
                updated_at = NOW(),
                closed_at = CASE WHEN ? IN ('closed', 'canceled', 'expired', 'rejected') 
                               THEN NOW() ELSE closed_at END
            WHERE id = ?
        ");
        
        $stmt->bind_param('ssi', $status, $status, $orderId);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update order status: " . $stmt->error);
        }
        
        // Log the status change
        $this->logOrderEvent($orderId, "status_change", [
            'new_status' => $status,
            'message' => $message
        ]);
        
        return true;
    }
    
    /**
     * Log order event
     */
    private function logOrderEvent($orderId, $eventType, $data = []) {
        $stmt = $this->db->prepare("
            INSERT INTO order_events 
            (order_id, event_type, data, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        $dataJson = json_encode($data);
        $stmt->bind_param('iss', $orderId, $eventType, $dataJson);
        
        if (!$stmt->execute()) {
            error_log("Failed to log order event: " . $stmt->error);
            return false;
        }
        
        return $this->db->insert_id;
    }
}

// End of Order class
