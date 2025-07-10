<?php
/**
 * PDO-compatible database functions
 * This file contains fixed versions of all database functions that were previously using mysqli methods
 */

/**
 * Get coin information by ID from either cryptocurrencies or coins table
 */
function getCoinInfoById($coinId) {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }
        
        // Try to get from cryptocurrencies table by ID
        $stmt = $db->prepare("SELECT id, symbol, name FROM cryptocurrencies WHERE id = ?");
        $stmt->execute([$coinId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If not found, try the coins table by ID
        if (!$result) {
            $stmt = $db->prepare("SELECT id, symbol, name FROM coins WHERE id = ?");
            $stmt->execute([$coinId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If found in coins table, try to find matching symbol in cryptocurrencies
            if ($result && !empty($result['symbol'])) {
                $symbol = $result['symbol'];
                $stmt = $db->prepare("SELECT id, symbol, name FROM cryptocurrencies WHERE symbol = ?");
                $stmt->execute([$symbol]);
                $cryptoResult = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // If found with matching symbol, use that data
                if ($cryptoResult) {
                    $result = $cryptoResult;
                }
            }
        }
        
        // If still no result, try to find by ID in any table (as string match)
        if (!$result) {
            $stmt = $db->prepare("SELECT id, symbol, name FROM cryptocurrencies WHERE id = ?");
            $stmt->execute([(string)$coinId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // If still no result, try to get symbol from coins table and use that
        if (!$result) {
            $stmt = $db->prepare("SELECT symbol, name FROM coins WHERE id = ?");
            $stmt->execute([$coinId]);
            $coinData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($coinData) {
                $result = [
                    'id' => $coinId,
                    'symbol' => $coinData['symbol'] ?? 'UNKNOWN',
                    'name' => $coinData['name'] ?? 'Unknown Coin'
                ];
            }
        }
        
        return $result ?: ['id' => $coinId, 'symbol' => 'UNKNOWN', 'name' => 'Unknown Coin'];
    } catch (Exception $e) {
        error_log("[getCoinInfoById] " . $e->getMessage());
        return ['id' => $coinId, 'symbol' => 'UNKNOWN', 'name' => 'Unknown Coin'];
    }
}

/**
 * Get trending coins
 */
function getTrendingCoinsPDO(): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }

        if (!defined('MIN_VOLUME_THRESHOLD')) {
            define('MIN_VOLUME_THRESHOLD', 1000000);
        }
        
        $minVolume = MIN_VOLUME_THRESHOLD;
        $stmt = $db->prepare("SELECT *, 
                              TIMESTAMPDIFF(HOUR, date_added, NOW()) as age_hours 
                              FROM coins 
                              WHERE volume_24h >= ? 
                              AND is_trending = TRUE 
                              ORDER BY volume_24h DESC");
        
        $stmt->execute([$minVolume]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("[getTrendingCoinsPDO] " . $e->getMessage());
        return [];
    }
}

/**
 * Get recent trades
 */
function getRecentTradesPDO(int $limit = 10): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }

        // Query using the actual database structure
        $stmt = $db->prepare("SELECT 
                    t.id, 
                    t.coin_id,
                    t.amount,
                    t.price,
                    t.trade_type,
                    t.trade_time,
                    t.total_value
                FROM trades t
                ORDER BY t.trade_time DESC
                LIMIT ?");
        $stmt->execute([$limit]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($result)) {
            return [];
        }
        
        $trades = [];
        foreach ($result as $row) {
            $coinId = $row['coin_id'];
            $coinInfo = getCoinInfoById($coinId);
            
            $trades[] = [
                'id' => $row['id'],
                'coin_id' => $coinId,
                'symbol' => $coinInfo['symbol'],
                'name' => $coinInfo['name'],
                'amount' => $row['amount'],
                'price' => $row['price'],
                'trade_type' => $row['trade_type'],
                'trade_time' => $row['trade_time'],
                'total_value' => $row['total_value']
            ];
        }
        
        return $trades;
    } catch (Exception $e) {
        error_log("[getRecentTradesPDO] " . $e->getMessage());
        return [];
    }
}

/**
 * Get recent trades with market data
 */
function getRecentTradesWithMarketDataPDO(int $limit = 10): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }

        // First get the basic trade data
        $trades = getRecentTradesPDO($limit);
        
        if (empty($trades)) {
            return [];
        }
        
        // Get all unique symbols from trades
        $symbols = array_unique(array_column($trades, 'symbol'));
        
        // Get current market data for these symbols
        $marketData = [];
        $stmt = $db->prepare("SELECT symbol, current_price, price_change_24h FROM coins WHERE symbol = ?");
        
        foreach ($symbols as $symbol) {
            $stmt->execute([$symbol]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $marketData[$symbol] = [
                    'current_price' => (float)$result['current_price'],
                    'price_change_24h' => (float)$result['price_change_24h']
                ];
            }
        }
        
        // FIFO matching for correct Sell trade P/L and invested calculation
        // Sort trades by trade_time ascending for FIFO
        usort($trades, function($a, $b) {
            return strtotime($a['trade_time']) <=> strtotime($b['trade_time']);
        });
        // Build per-coin trade lists for FIFO processing by symbol (not coin_id)
        $tradesBySymbol = [];
        foreach ($trades as $trade) {
            $tradesBySymbol[$trade['symbol']][] = $trade;
        }
        $fifoResults = [];
        foreach ($tradesBySymbol as $symbol => $coinTrades) {
            $openBuys = [];
            foreach ($coinTrades as $trade) {
                $symbol = $trade['symbol'];
                $currentPrice = $marketData[$symbol]['current_price'] ?? $trade['price'];
                $priceChange24h = $marketData[$symbol]['price_change_24h'] ?? 0;
                $currentValue = $trade['amount'] * $currentPrice;
                if ($trade['trade_type'] === 'buy') {
                    // Track open buy
                    $openBuys[] = [
                        'id' => $trade['id'],
                        'amount' => $trade['amount'],
                        'price' => $trade['price'],
                        'remaining' => $trade['amount'],
                        'total_value' => $trade['total_value'],
                        'trade_time' => $trade['trade_time'],
                    ];
                    $fifoResults[] = array_merge($trade, [
                        'current_price' => round($trade['price'], 2), // price at time of buy
                        'price_change_24h' => $priceChange24h,
                        'current_value' => round($trade['amount'] * $trade['price'], 2),
                        'profit_loss' => null,
                        'profit_loss_percent' => null,
                        'invested' => round($trade['total_value'], 2),
                        'entry_price' => round($trade['price'], 4),
                        'realized_pl' => null,
                    ]);
                } elseif ($trade['trade_type'] === 'sell') {
                    // FIFO match to open buys
                    $sellAmount = $trade['amount'];
                    $invested = 0;
                    $entryPrice = 0;
                    $realizedPL = 0;
                    $matchedAmount = 0;
                    $weightedBuyTotal = 0; // sum of (matched amount * buy price)
                    foreach ($openBuys as &$buy) {
                        if ($buy['remaining'] <= 0) continue;
                        $match = min($buy['remaining'], $sellAmount);
                        if ($match <= 0) continue;
                        $invested += $match * $buy['price'];
                        $weightedBuyTotal += $match * $buy['price'];
                        $realizedPL += ($trade['price'] - $buy['price']) * $match;
                        $buy['remaining'] -= $match;
                        $sellAmount -= $match;
                        $matchedAmount += $match;
                        if ($sellAmount <= 0) break;
                    }
                    unset($buy);
                    $entryPrice = $matchedAmount > 0 ? $weightedBuyTotal / $matchedAmount : 0;
                    $fifoResults[] = array_merge($trade, [
                        'current_price' => round($trade['price'], 2), // price at time of sell
                        'price_change_24h' => $priceChange24h,
                        'current_value' => round($trade['amount'] * $trade['price'], 2),
                        'profit_loss' => round($realizedPL, 2),
                        'profit_loss_percent' => $invested > 0 ? round(($realizedPL / $invested) * 100, 2) : null,
                        'invested' => round($invested, 2),
                        'entry_price' => round($entryPrice, 4),
                        'realized_pl' => round($realizedPL, 2),
                    ]);
                }
            }
        }
        // Return most recent $limit trades
        return array_slice(array_reverse($fifoResults), 0, $limit);
    } catch (Exception $e) {
        error_log("[getRecentTradesWithMarketDataPDO] " . $e->getMessage());
        return [];
    }
}

/**
 * Get trading statistics
 */
function getTradingStatsPDO(): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }
        
        // Get total trades count
        $totalTradesStmt = $db->prepare("SELECT COUNT(*) as total FROM trades");
        $totalTradesStmt->execute();
        $totalTradesResult = $totalTradesStmt->fetch(PDO::FETCH_ASSOC);
        $totalTrades = $totalTradesResult['total'] ?? 0;
        
        // Get active trades (buy trades with remaining balance)
        $activeTradesStmt = $db->prepare("SELECT COUNT(*) as active FROM portfolio WHERE amount > 0");
        $activeTradesStmt->execute();
        $activeTradesResult = $activeTradesStmt->fetch(PDO::FETCH_ASSOC);
        $activeTrades = $activeTradesResult['active'] ?? 0;
        
        // Get total profit using FIFO-matched realized P/L from enriched trade data
        $allTrades = getRecentTradesWithMarketDataPDO(1000); // adjust limit as needed
        $totalProfit = 0;
        foreach ($allTrades as $trade) {
            if (strtolower($trade['trade_type']) === 'sell' && is_numeric($trade['profit_loss'])) {
                $totalProfit += $trade['profit_loss'];
            }
        }
        
        // Get total volume
        $volumeStmt = $db->prepare("SELECT SUM(total_value) as total_volume FROM trades");
        $volumeStmt->execute();
        $volumeResult = $volumeStmt->fetch(PDO::FETCH_ASSOC);
        $totalVolume = $volumeResult['total_volume'] ?? 0;
        
        return [
            'total_trades' => $totalTrades,
            'active_trades' => $activeTrades,
            'total_profit' => $totalProfit,
            'total_volume' => $totalVolume
        ];
    } catch (Exception $e) {
        error_log("[getTradingStatsPDO] " . $e->getMessage());
        return [
            'total_trades' => 0,
            'active_trades' => 0,
            'total_profit' => 0,
            'total_volume' => 0
        ];
    }
}

/**
 * Get new cryptocurrencies added within MAX_COIN_AGE hours
 */
function getNewCryptocurrenciesPDO(): array {
    try {
        if (!defined('MAX_COIN_AGE')) {
            define('MAX_COIN_AGE', 24); // Default to 24 hours if not defined
        }
        
        error_log("[getNewCryptocurrenciesPDO] Starting");
        
        $db = getDBConnection();
        if (!$db) {
            error_log("[getNewCryptocurrenciesPDO] Database connection failed");
            throw new Exception("Database connection failed");
        }

        $maxAge = MAX_COIN_AGE; // Store constant in variable
        error_log("[getNewCryptocurrenciesPDO] MAX_COIN_AGE: $maxAge");
        
        // Calculate age in hours using TIMESTAMPDIFF since we don't have an age_hours column
        $query = "SELECT *, 
                 TIMESTAMPDIFF(HOUR, date_added, NOW()) as age_hours 
                 FROM coins 
                 WHERE date_added >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                 ORDER BY date_added DESC";
                 
        error_log("[getNewCryptocurrenciesPDO] Query: $query");
        
        try {
            $stmt = $db->prepare($query);
            if (!$stmt) {
                error_log("[getNewCryptocurrenciesPDO] Prepare failed");
                throw new Exception("Prepare failed");
            }
            
            $executed = $stmt->execute([$maxAge]);
            if (!$executed) {
                error_log("[getNewCryptocurrenciesPDO] Execute failed");
                throw new Exception("Execute failed");
            }
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("[getNewCryptocurrenciesPDO] Found " . count($data) . " coins");
            if (!empty($data)) {
                error_log("[getNewCryptocurrenciesPDO] First coin: " . json_encode($data[0]));
            }
            
            return $data;
        } catch (PDOException $e) {
            error_log("[getNewCryptocurrenciesPDO] PDO Error: " . $e->getMessage());
            throw new Exception("Database error: " . $e->getMessage());
        }
    } catch (Exception $e) {
        error_log("[getNewCryptocurrenciesPDO] " . $e->getMessage());
        return [];
    }
}

/**
 * Get user's cryptocurrency balances with PDO
 */
function getUserBalancesPDO(): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }
        
        // Get portfolio data
        $stmt = $db->prepare("SELECT p.coin_id, p.amount, p.avg_buy_price, c.symbol, c.name 
                             FROM portfolio p 
                             LEFT JOIN cryptocurrencies c ON p.coin_id = c.id 
                             WHERE p.amount > 0 
                             ORDER BY p.amount * p.avg_buy_price DESC");
        $stmt->execute();
        $portfolioData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($portfolioData)) {
            return [];
        }
        
        // Get cryptocurrency data to map coin_id to symbol
        $cryptoData = [];
        $cryptoStmt = $db->prepare("SELECT id, symbol, name FROM cryptocurrencies");
        $cryptoStmt->execute();
        $cryptoResult = $cryptoStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cryptoResult as $row) {
            $cryptoData[$row['id']] = $row;
        }
        
        // Format balances
        $balances = [];
        foreach ($portfolioData as $row) {
            $coinId = $row['coin_id'];
            $symbol = $row['symbol'] ?? $cryptoData[$coinId]['symbol'] ?? 'UNKNOWN';
            $name = $row['name'] ?? $cryptoData[$coinId]['name'] ?? $symbol;
            
            $balances[] = [
                'coin_id' => $coinId,
                'symbol' => $symbol,
                'name' => $name,
                'amount' => (float)$row['amount'],
                'avg_buy_price' => (float)$row['avg_buy_price'],
                'value' => (float)$row['amount'] * (float)$row['avg_buy_price']
            ];
        }
        
        return $balances;
        
    } catch (Exception $e) {
        error_log("Error getting user balance: " . $e->getMessage());
        return [];
    }
}
    
/**
 * Get user's coin balance for a specific coin with PDO
 * @param string|int $coinId The coin ID to check balance for
 * @return array Balance information with amount, avg_buy_price and coin_id
 */
function getUserCoinBalancePDO($coinId): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }
        
        error_log("[getUserCoinBalancePDO] Looking for coin ID: $coinId");
        
        // IMPORTANT: The database query was failing because we need to ensure the coinId is treated correctly
        // For numeric IDs, we need to make sure we're comparing with the same type
        
        // Try exact match first - this is the most common case
        // IMPORTANT: Since coin_id is VARCHAR(50) in the database, we should always treat it as a string
        // Always filter by user_id=1 (hardcoded for now)
        $sql = "SELECT coin_id, amount, avg_buy_price FROM portfolio WHERE coin_id = :coinId AND user_id = 1";
        $stmt = $db->prepare($sql);
        
        if ($stmt) {
            // Always bind as string since the column is VARCHAR
            $stmt->bindValue(':coinId', (string)$coinId, PDO::PARAM_STR);
            error_log("[getUserCoinBalancePDO] Binding as string: $coinId");
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['coin_id'])) {
                error_log("[getUserCoinBalancePDO] Found exact match for coin ID: $coinId");
                return [
                    'amount' => (float)$result['amount'],
                    'avg_buy_price' => (float)$result['avg_buy_price'],
                    'coin_id' => $result['coin_id']
                ];
            } else {
                error_log("[getUserCoinBalancePDO] No exact match found for coin ID: $coinId");
            }
        }
        
        // If not found, try with COIN_ prefix (legacy format) for numeric IDs
        if (is_numeric($coinId)) {
            $fullCoinId = 'COIN_' . strtoupper($coinId);
            $stmt = $db->prepare("SELECT coin_id, amount, avg_buy_price FROM portfolio WHERE coin_id = :coinId AND user_id = 1");
            $stmt->bindValue(':coinId', $fullCoinId, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['coin_id'])) {
                error_log("[getUserCoinBalancePDO] Found with COIN_ prefix: $fullCoinId");
                return [
                    'amount' => (float)$result['amount'],
                    'avg_buy_price' => (float)$result['avg_buy_price'],
                    'coin_id' => $result['coin_id']
                ];
            }
        }
        
        // If still not found, try case-insensitive search as last resort
        $stmt = $db->prepare("SELECT coin_id, amount, avg_buy_price FROM portfolio WHERE UPPER(coin_id) LIKE UPPER(CONCAT('%', :coinId, '%')) AND user_id = 1 LIMIT 1");
        if ($stmt) {
            $stmt->bindValue(':coinId', (string)$coinId, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['coin_id'])) {
                error_log("[getUserCoinBalancePDO] Found with LIKE search: {$result['coin_id']}");
                return [
                    'amount' => (float)$result['amount'],
                    'avg_buy_price' => (float)$result['avg_buy_price'],
                    'coin_id' => $result['coin_id']
                ];
            }
        }
        
        error_log("[getUserCoinBalancePDO] No portfolio entry found for coin ID: $coinId");
        return [];
    } catch (Exception $e) {
        error_log("[getUserCoinBalancePDO] Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get coin data by ID or symbol with PDO
 */
function getCoinDataPDO($coinId) {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }
        
        $data = [];
        
        // Try to get from coins table first (if numeric ID)
        if (is_numeric($coinId)) {
            $stmt = $db->prepare("SELECT id, symbol, name, current_price as price, price_change_24h as price_change,
                                    volume_24h as volume, market_cap, date_added, is_trending as trending 
                                    FROM coins WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->execute([$coinId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && isset($result['id'])) {
                    $data = $result;
                    error_log("Found coin in coins table: " . json_encode($data));
                    return $data;
                }
            }
        }
        
        // Try to get from cryptocurrencies table by ID
        $cryptoIdStmt = $db->prepare("SELECT id, symbol, name, current_price as price, price_change_percentage_24h as price_change,
                                    volume, market_cap, created_at, is_trending as trending 
                                    FROM cryptocurrencies WHERE id = ? LIMIT 1");
        if ($cryptoIdStmt) {
            $cryptoIdStmt->execute([$coinId]);
            $cryptoIdResult = $cryptoIdStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cryptoIdResult && isset($cryptoIdResult['id'])) {
                $data = $cryptoIdResult;
                error_log("Found coin in cryptocurrencies table by ID: " . json_encode($data));
                return $data;
            }
        }
        
        // If we have a numeric ID but no direct match, try to get the symbol first
        $symbol = '';
        if (is_numeric($coinId)) {
            error_log("Getting symbol for numeric ID $coinId");
            $symbolStmt = $db->prepare("SELECT symbol FROM coins WHERE id = ? LIMIT 1");
            if ($symbolStmt) {
                $symbolStmt->execute([$coinId]);
                $symbolResult = $symbolStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($symbolResult && isset($symbolResult['symbol'])) {
                    $symbol = $symbolResult['symbol'];
                    error_log("Found symbol $symbol for ID $coinId");
                }
            }
        } else {
            // If not numeric, assume it's a symbol
            $symbol = $coinId;
        }
        
        // If we have a symbol, try to get data by symbol
        if ($symbol) {
            // Try cryptocurrencies table first
            $cryptoStmt = $db->prepare("SELECT id, symbol, name, current_price as price, price_change_percentage_24h as price_change,
                                        volume, market_cap, created_at, is_trending as trending 
                                        FROM cryptocurrencies WHERE symbol = ? LIMIT 1");
            if ($cryptoStmt) {
                $cryptoStmt->execute([$symbol]);
                $cryptoResult = $cryptoStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($cryptoResult && isset($cryptoResult['id'])) {
                    $data = $cryptoResult;
                    error_log("Found coin in cryptocurrencies table by symbol: " . json_encode($data));
                    return $data;
                }
            }
            
            // Then try coins table
            $coinSymbolStmt = $db->prepare("SELECT id, symbol, name, current_price as price, price_change_24h as price_change,
                                           volume_24h as volume, market_cap, date_added, is_trending as trending 
                                           FROM coins WHERE symbol = ? LIMIT 1");
            if ($coinSymbolStmt) {
                $coinSymbolStmt->execute([$symbol]);
                $coinSymbolResult = $coinSymbolStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($coinSymbolResult && isset($coinSymbolResult['id'])) {
                    $data = $coinSymbolResult;
                    error_log("Found coin in coins table by symbol: " . json_encode($data));
                    return $data;
                }
            }
        }
        
        // No data found
        return null;
    } catch (Exception $e) {
        error_log("[getCoinDataPDO] Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Execute a sell trade using PDO
 * @param string|int $coinId The coin ID to sell
 * @param float $amount Amount to sell
 * @param float $price Current price per coin
 * @return array|int Result with success status, message, profit_loss and profit_percentage or trade ID
 */
function executeSellPDO($coinId, $amount, $price) {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }
        
        // Ensure coin ID is treated as a string
        $coinId = (string)$coinId;
        error_log("[executeSellPDO] Processing sell request for coin ID: $coinId, amount: $amount, price: $price");
        
        // Try direct database query first - include user_id=1 filter
        $stmt = $db->prepare("SELECT * FROM portfolio WHERE coin_id = ? AND user_id = 1");
        $stmt->execute([$coinId]);
        $directResult = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("[executeSellPDO] Direct query result: " . json_encode($directResult));
        
        // If direct query found the coin, use that data
        if ($directResult) {
            error_log("[executeSellPDO] Found coin directly in portfolio table");
            $portfolioData = [
                'amount' => (float)$directResult['amount'],
                'avg_buy_price' => (float)$directResult['avg_buy_price'],
                'coin_id' => $directResult['coin_id']
            ];
        } else {
            // If not found directly, try with getUserCoinBalancePDO
            error_log("[executeSellPDO] Coin not found directly, trying getUserCoinBalancePDO");
            $portfolioData = getUserCoinBalancePDO($coinId);
            error_log("[executeSellPDO] getUserCoinBalancePDO result: " . json_encode($portfolioData));
        }
        
        // Check if we have portfolio data
        if (empty($portfolioData)) {
            error_log("[executeSellPDO] No portfolio data found for coin ID: $coinId");
            return [
                'success' => false,
                'message' => "Coin not found in your portfolio: $coinId"
            ];
        }
        
        $userBalance = isset($portfolioData['amount']) ? $portfolioData['amount'] : 0;
        $avgBuyPrice = isset($portfolioData['avg_buy_price']) ? $portfolioData['avg_buy_price'] : 0;
        
        // Validate amount
        if ($amount <= 0) {
            throw new Exception("Invalid amount to sell");
        }
        
        if ($amount > $userBalance) {
            throw new Exception("Cannot sell more than you own. Your balance: $userBalance");
        }
        
        // Calculate profit/loss
        $sellValue = $amount * $price;
        $buyValue = $amount * $avgBuyPrice;
        $profitLoss = $sellValue - $buyValue;
        $profitPercentage = $buyValue > 0 ? ($profitLoss / $buyValue) * 100 : 0;
        
        // Start transaction
        $db->beginTransaction();
        
        // Insert sell trade record
        $tradeStmt = $db->prepare("INSERT INTO trades 
                                  (coin_id, trade_type, price, amount, total_value, profit_loss) 
                                  VALUES (?, 'sell', ?, ?, ?, ?)");
        
        $tradeStmt->bindParam(1, $coinId, PDO::PARAM_STR);
        $tradeStmt->bindParam(2, $price, PDO::PARAM_STR);
        $tradeStmt->bindParam(3, $amount, PDO::PARAM_STR);
        $tradeStmt->bindParam(4, $sellValue, PDO::PARAM_STR);
        $tradeStmt->bindParam(5, $profitLoss, PDO::PARAM_STR);
        $tradeStmt->execute();
        
        $tradeId = $db->lastInsertId();
        
        // Update portfolio - reduce amount
        $newAmount = $userBalance - $amount;
        
        if ($newAmount <= 0.000001) { // Effectively zero
            // Delete the portfolio entry if no coins left
            $portfolioStmt = $db->prepare("DELETE FROM portfolio WHERE coin_id = ? AND user_id = 1");
            $portfolioStmt->bindParam(1, $coinId, PDO::PARAM_STR);
        } else {
            // Update the portfolio with new amount
            $portfolioStmt = $db->prepare("UPDATE portfolio SET amount = ? WHERE coin_id = ? AND user_id = 1");
            $portfolioStmt->bindParam(1, $newAmount, PDO::PARAM_STR);
            $portfolioStmt->bindParam(2, $coinId, PDO::PARAM_STR);
        }
        
        $portfolioStmt->execute();
        
        // Commit transaction
        $db->commit();
        
        // Log the trade
        error_log("Sell executed: $amount of $coinId at $price. Profit: $profitLoss ($profitPercentage%)");
        
        return [
            "success" => true,
            "message" => "Successfully sold $amount coins",
            "profit_loss" => $profitLoss,
            "profit_percentage" => $profitPercentage,
            "trade_id" => $tradeId
        ];
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("[executeSellPDO] Error: " . $e->getMessage());
        return [
            "success" => false,
            "message" => "Error executing sell: " . $e->getMessage(),
            "profit_loss" => 0,
            "profit_percentage" => 0
        ];
    }
}
