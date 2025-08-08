<?php
require_once __DIR__ . '/functions.php';
/**
 * PDO-compatible database functions
 * This file contains fixed versions of all database functions that were previously using mysqli methods
 */

/**
 * Execute a buy trade using PDO
 * @param string|int $coinId The coin ID to sell
 * @param float $amount Amount to sell
 * @param float $price Current price per coin
 * @return array|int Result with success status, message, profit_loss and profit_percentage or trade ID
 */
function executeBuyPDO($coinId, $amount, $price) {
    $db = getDBConnection();
    if (!$db) return false;

    syncPortfolioCoinsToCryptocurrenciesPDO();

    $totalValue = $amount * $price;

    $finalCoinIdForDb = null; // This will be the ID used for trades and portfolio
    $coinSymbol = null; // This will be the symbol (e.g., KMD)

    if (is_numeric($coinId)) {
        // If numeric, get symbol from 'coins' table
        $symbolStmt = $db->prepare("SELECT symbol FROM coins WHERE id = ?");
        if (!$symbolStmt) {
            log_message("[executeBuyPDO] Failed to prepare symbol query: " . $db->errorInfo()[2], 'error');
            return false;
        }
        $symbolStmt->execute([$coinId]);
        $symbolRow = $symbolStmt->fetch(PDO::FETCH_ASSOC);

        if ($symbolRow) {
            $coinSymbol = $symbolRow['symbol'];
            log_message("[executeBuyPDO] Determined coinSymbol from numeric ID: " . $coinSymbol);
        } else {
            log_message("[executeBuyPDO] Could not find symbol for numeric coin ID: " . $coinId, 'error');
            return false; // Cannot proceed without a symbol
        }
    } else {
        // If not numeric, assume $coinId is already the symbol
        $coinSymbol = $coinId;
        log_message("[executeBuyPDO] Using coinId as coinSymbol: " . $coinSymbol);
    }

    // Now, ensure the symbol exists in the 'cryptocurrencies' table and get its ID
    // The 'cryptocurrencies' table uses the symbol itself as the ID (VARCHAR)
    $checkCryptoStmt = $db->prepare("SELECT id FROM cryptocurrencies WHERE symbol = ? LIMIT 1");
    if (!$checkCryptoStmt) {
        log_message("[executeBuyPDO] Failed to prepare cryptocurrencies check query: " . $db->errorInfo()[2], 'error');
        return false;
    }
    $checkCryptoStmt->execute([$coinSymbol]);
    $cryptoRow = $checkCryptoStmt->fetch(PDO::FETCH_ASSOC);

    if ($cryptoRow) {
        $finalCoinIdForDb = $cryptoRow['id'];
        log_message("[executeBuyPDO] Coin found in cryptocurrencies: " . $finalCoinIdForDb);
    } else {
        // If not found in cryptocurrencies, insert it
        $finalCoinIdForDb = $coinSymbol; // Use symbol as ID for cryptocurrencies table
        log_message("[executeBuyPDO] Coin not found in cryptocurrencies, attempting insert: " . $finalCoinIdForDb);
        $insertCryptoStmt = $db->prepare("INSERT INTO cryptocurrencies 
                                           (id, symbol, name, created_at, price) 
                                           VALUES (?, ?, ?, NOW(), ?)");
        if ($insertCryptoStmt) {
            $coinName = $coinSymbol; // Default name to symbol if not found
            try {
                $insertSuccess = $insertCryptoStmt->execute([$finalCoinIdForDb, $coinSymbol, $coinName, $price]);
                if ($insertSuccess) {
                    log_message("[executeBuyPDO] Successfully inserted into cryptocurrencies: " . $finalCoinIdForDb);
                } else {
                    log_message("[executeBuyPDO] Failed to insert into cryptocurrencies (execute failed): " . json_encode($insertCryptoStmt->errorInfo()), 'error');
                    return false; // Critical failure, cannot proceed
                }
            } catch (PDOException $e) {
                // Catch duplicate entry errors specifically, as INSERT IGNORE was removed
                if ($e->getCode() == '23000') { // Integrity constraint violation
                    log_message("[executeBuyPDO] Duplicate entry for cryptocurrencies: " . $finalCoinIdForDb . " - " . $e->getMessage());
                    // If it's a duplicate, it means it exists, so we can proceed
                } else {
                    log_message("[executeBuyPDO] PDOException during cryptocurrencies insert: " . $e->getMessage(), 'error');
                    return false; // Other database error, cannot proceed
                }
            }
        } else {
            log_message("[executeBuyPDO] Failed to prepare cryptocurrencies insert query: " . $db->errorInfo()[2], 'error');
            return false;
        }
    }

    log_message("[executeBuyPDO] Final coin ID for trades/portfolio: " . $finalCoinIdForDb);

    // Now insert the trade with the correct finalCoinIdForDb
    try {
        $stmt = $db->prepare("INSERT INTO trades (coin_id, trade_type, amount, price, total_value, trade_time) VALUES (?, 'buy', ?, ?, ?, NOW())");
        if (!$stmt) {
            log_message("[executeBuyPDO] Failed to prepare trades insert query: " . $db->errorInfo()[2], 'error');
            return false;
        }
        $stmt->execute([$finalCoinIdForDb, $amount, $price, $totalValue]);

        if ($stmt->rowCount() > 0) {
            $tradeId = $db->lastInsertId();

            // Update the portfolio table
            $portfolioStmt = $db->prepare("INSERT INTO portfolio (user_id, coin_id, amount, avg_buy_price, last_updated) 
                                         VALUES (1, ?, ?, ?, NOW()) 
                                         ON DUPLICATE KEY UPDATE 
                                         amount = amount + VALUES(amount),
                                         avg_buy_price = ((amount * avg_buy_price) + (VALUES(amount) * ?)) / (amount + VALUES(amount)),
                                         last_updated = NOW()");

            if ($portfolioStmt) {
                $portfolioStmt->execute([$coinSymbol, $amount, $price, $price]);
                log_message("[executeBuyPDO] Updated portfolio for coin: $coinSymbol, amount: $amount, price: $price");

                // Reset coin_apex_prices status to 'monitoring' on buy
                $apexResetStmt = $db->prepare("INSERT INTO coin_apex_prices (coin_id, status, apex_price, apex_timestamp, drop_start_timestamp, last_checked) VALUES (?, 'monitoring', 0, NOW(), NULL, NOW()) ON DUPLICATE KEY UPDATE status = 'monitoring', apex_price = 0, apex_timestamp = NOW(), drop_start_timestamp = NULL, last_checked = NOW()");
                if ($apexResetStmt) {
                    $apexResetStmt->execute([$coinSymbol]);
                    log_message("[executeBuyPDO] Reset coin_apex_prices status for $coinSymbol to monitoring.");
                } else {
                    log_message("[executeBuyPDO] Failed to prepare apex reset statement: " . $db->errorInfo()[2], 'error');
                }

                // Launch the Python script for real-time price updates
                $script_path = '/opt/lampp/htdocs/NS/includes/bitvavo_price_udater_for_terminal.py';
                $python_executable = '/usr/bin/python3'; // Explicitly define the Python executable

                // Check if the script is already running
                $pgrep_command = "/usr/bin/pgrep -f \"^" . $python_executable . " " . escapeshellarg($script_path) . "$\"";
                $process_check = shell_exec($pgrep_command);

                if (empty($process_check)) {
                    // Not running, so launch it
                    $command = $python_executable . ' ' . escapeshellarg($script_path) . ' > /dev/null 2>&1 &';
                    log_message("[executeBuyPDO] Launching Python script: " . $command);
                    exec($command, $output, $return_var);
                    if ($return_var === 0) {
                        log_message("[executeBuyPDO] Python script ' . $script_path . ' launched successfully.");
                    } else {
                        log_message("[executeBuyPDO] Failed to launch Python script ' . $script_path . '. Return var: " . $return_var, 'error');
                    }
                } else {
                    log_message("[executeBuyPDO] Python script ' . $script_path . ' is already running (PID: " . trim($process_check) . "). Not launching a new instance.");
                }
            } else {
                log_message("[executeBuyPDO] Failed to update portfolio: " . $db->errorInfo()[2], 'error');
            }

            return [
                'trade_id' => $tradeId,
                'coin_symbol' => $coinSymbol
            ];
        } else {
            log_message("[executeBuyPDO] Failed to insert trade (no rows affected): " . $stmt->errorInfo()[2], 'error');
            return false;
        }
    } catch (PDOException $e) {
        log_message("[executeBuyPDO] PDOException during trades insert: " . $e->getMessage(), 'error');
        return false;
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
        log_message("[executeSellPDO] Processing sell request for coin ID: $coinId, amount: $amount, price: $price");

        // Try direct database query first - include user_id=1 filter
        $stmt = $db->prepare("SELECT * FROM portfolio WHERE coin_id = ? AND user_id = 1");
        $stmt->execute([$coinId]);
        $directResult = $stmt->fetch(PDO::FETCH_ASSOC);
        log_message("[executeSellPDO] Direct query result: " . json_encode($directResult));

        // If direct query found the coin, use that data
        if ($directResult) {
            log_message("[executeSellPDO] Found coin directly in portfolio table");
            $portfolioData = [
                'amount' => (float)$directResult['amount'],
                'avg_buy_price' => (float)$directResult['avg_buy_price'],
                'coin_id' => $directResult['coin_id']
            ];
        } else {
            // If not found directly, try with getUserCoinBalancePDO
            log_message("[executeSellPDO] Coin not found directly, trying getUserCoinBalancePDO");
            $portfolioData = getUserCoinBalancePDO($coinId);
            log_message("[executeSellPDO] getUserCoinBalancePDO result: " . json_encode($portfolioData));
        }

        // Check if we have portfolio data
        if (empty($portfolioData)) {
            log_message("[executeSellPDO] No portfolio data found for coin ID: $coinId", 'error');
            return [
                'success' => false,
                'message' => "Coin not found in your portfolio: $coinId"
            ];
        }

        $userBalance = isset($portfolioData['amount']) ? $portfolioData['amount'] : 0;
        $avgBuyPrice = isset($portfolioData['avg_buy_price']) ? $portfolioData['avg_buy_price'] : 0;
        $portfolioCoinId = isset($portfolioData['coin_id']) ? $portfolioData['coin_id'] : $coinId; // Use the coin_id from portfolioData

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

        // Get the numeric coin_id from the cryptocurrencies table
        $crypto_id_query = "SELECT id FROM cryptocurrencies WHERE symbol = :symbol";
        $stmt_crypto = $db->prepare($crypto_id_query);
        $stmt_crypto->bindParam(':symbol', $coinId); // Use the symbol to find the numeric ID
        $stmt_crypto->execute();
        $crypto_data = $stmt_crypto->fetch(PDO::FETCH_ASSOC);

        if (!$crypto_data) {
            log_message("[executeSellPDO] Error: Coin symbol '{$coinId}' not found in cryptocurrencies table.", 'error');
            return ['success' => false, 'message' => 'Coin not found in cryptocurrencies table.'];
        }

        $numeric_coin_id = $crypto_data['id'];

        // Insert sell trade record
        $tradeStmt = $db->prepare("INSERT INTO trades 
                                  (coin_id, trade_type, price, amount, total_value, profit_loss) 
                                  VALUES (?, 'sell', ?, ?, ?, ?)");

        if (!$tradeStmt) {
            throw new Exception("Failed to prepare trade statement: " . $db->errorInfo()[2]);
        }
        $tradeStmt->bindParam(1, $numeric_coin_id, PDO::PARAM_INT); // Bind as INT
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
            if (!$portfolioStmt) {
                throw new Exception("Failed to prepare portfolio delete statement: " . $db->errorInfo()[2]);
            }
            $portfolioStmt->bindParam(1, $portfolioCoinId, PDO::PARAM_STR);
        } else {
            // Update the portfolio with new amount
            $portfolioStmt = $db->prepare("UPDATE portfolio SET amount = ? WHERE coin_id = ? AND user_id = 1");
            if (!$portfolioStmt) {
                throw new Exception("Failed to prepare portfolio update statement: " . $db->errorInfo()[2]);
            }
            $portfolioStmt->bindParam(1, $newAmount, PDO::PARAM_STR);
            $portfolioStmt->bindParam(2, $portfolioCoinId, PDO::PARAM_STR);
        }

        $portfolioStmt->execute();

        // Update coin_apex_prices table for the sold coin
        $apexUpdateStmt = $db->prepare("UPDATE coin_apex_prices SET status = 'sold', drop_start_timestamp = NULL WHERE coin_id = ?");
        if ($apexUpdateStmt) {
            $executed = $apexUpdateStmt->execute([$coinId]);
            if ($executed) {
                $rowCount = $apexUpdateStmt->rowCount();
                log_message("[executeSellPDO] coin_apex_prices update for $coinId executed. Rows affected: $rowCount");
            } else {
                log_message("[executeSellPDO] Failed to execute coin_apex_prices update statement for $coinId: " . json_encode($apexUpdateStmt->errorInfo()), 'error');
            }
        } else {
            log_message("[executeSellPDO] Failed to prepare coin_apex_prices update statement: " . json_encode($db->errorInfo()), 'error');
        }

        // Commit transaction
        $db->commit();

        // Log the trade
        log_message(sprintf("Sell executed: %s of %s at %s. Profit: %.10f (%.10f%%)", $amount, $coinId, $price, $profitLoss, $profitPercentage));

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
        log_message("[executeSellPDO] Error: " . $e->getMessage(), 'error');
        return [
            "success" => false,
            "message" => "Error executing sell: " . $e->getMessage(),
            "profit_loss" => 0,
            "profit_percentage" => 0
        ];
    }
}


/**
 * Get recent trades from trade_log table with market data
 */
function getTradeLogWithMarketDataPDO(int $limit = 100): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }

        // First, get the raw trade data
        $rawTrades = getTradeLogPDO($limit);
        
        log_message("Raw trades fetched from trade_log: " . json_encode(array_slice($rawTrades, 0, 5)));
        
        if (empty($rawTrades)) {
            log_message("No trades returned from getTradeLogPDO");
            return [];
        }

        // Process the raw trades into a consistent format
        $trades = [];
        foreach ($rawTrades as $row) {
            // Map fields from whatever structure we have to our expected structure
            // Use null coalescing to handle potential missing fields
            $trade = [
                'id' => $row['id'] ?? null,
                'coin_id' => $row['coin_id'] ?? null,
                'symbol' => $row['symbol'] ?? $row['coin'] ?? 'UNKNOWN',
                'name' => $row['symbol'] ?? $row['coin'] ?? 'Unknown',
                'amount' => $row['amount'] ?? 0,
                'price' => $row['price'] ?? 0,
                'trade_type' => $row['action'] ?? $row['trade_type'] ?? 'unknown',
                'trade_time' => $row['trade_date'] ?? $row['date'] ?? $row['trade_time'] ?? date('Y-m-d H:i:s'),
                'total_value' => isset($row['amount']) && isset($row['price']) ? ($row['amount'] * $row['price']) : 0,
                'strategy' => $row['strategy'] ?? 'manual'
            ];
            
            $trades[] = $trade;
        }

        // Get current prices for all symbols
        $symbols = array_unique(array_column($trades, 'symbol'));
        $currentPrices = [];
        
        if (!empty($symbols)) {
            // Filter out empty or null symbols
            $symbols = array_filter($symbols, fn($s) => !empty($s));
        }
        
        if (!empty($symbols)) {
            $placeholders = implode(',', array_fill(0, count($symbols), '?'));
            $stmt = $db->prepare("SELECT symbol, current_price FROM coins WHERE symbol IN ($placeholders)");
            // Fix: bind parameters as individual values
            $stmt->execute(array_values($symbols));
            $priceData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($priceData as $row) {
                $currentPrices[$row['symbol']] = $row['current_price'];
            }
        } else {
            $currentPrices = [];
        }
        
        // Enrich trade data with current prices
        foreach ($trades as &$trade) {
            $symbol = $trade['symbol'];
            $trade['current_price'] = $currentPrices[$symbol] ?? 0;
        }
        
        log_message("Enriched trades with current prices: " . json_encode(array_slice($trades, 0, 5)));
        
        return $trades;
    } catch (Exception $e) {
        log_message("[getTradeLogWithMarketDataPDO] " . $e->getMessage(), 'error');
        return [];
    }
}


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
        log_message("[getCoinInfoById] " . $e->getMessage(), 'error');
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
        log_message("[getTrendingCoinsPDO] " . $e->getMessage(), 'error');
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
        log_message("[getRecentTradesPDO] " . $e->getMessage(), 'error');
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
        // Sort trades by trade_time ascending for FIFO - ensure stable sort with ID as secondary key
        usort($trades, function($a, $b) {
            $timeCompare = strtotime($a['trade_time']) <=> strtotime($b['trade_time']);
            return $timeCompare !== 0 ? $timeCompare : ($a['id'] <=> $b['id']);
        });
        
        // Log trades for debugging
        log_message("Sorted trades for FIFO matching: " . json_encode(array_map(function($t) { 
            return ['id' => $t['id'], 'symbol' => $t['symbol'], 'type' => $t['trade_type'], 'time' => $t['trade_time'], 'amount' => $t['amount'], 'price' => $t['price']];
        }, $trades)));
        
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
                    $totalSaleValue = $trade['amount'] * $trade['price']; // Total value of the sale
                    
                    // Debug log before matching
                    log_message("Processing sell for {$trade['symbol']} - Amount: {$trade['amount']} at price: {$trade['price']} - Open buys: " . json_encode($openBuys));
                    
                    foreach ($openBuys as &$buy) {
                        if ($buy['remaining'] <= 0) continue;
                        $match = min($buy['remaining'], $sellAmount);
                        if ($match <= 0) continue;
                        
                        // Calculate this match's contribution to invested amount and P/L
                        $matchInvested = $match * $buy['price'];
                        $matchSaleValue = $match * $trade['price'];
                        $matchPL = $matchSaleValue - $matchInvested;
                        
                        $invested += $matchInvested;
                        $weightedBuyTotal += $matchInvested;
                        $realizedPL += $matchPL;
                        
                        // Debug log with detailed calculation
                        log_message("FIFO Match Detail: Buy price: {$buy['price']}, Sell price: {$trade['price']}, Match amount: {$match}");
                        log_message("FIFO Calculation: Buy value: {$matchInvested}, Sell value: {$matchSaleValue}, P/L: {$matchPL}");
                        
                        // Debug log for this match
                        log_message("Matched {$match} units from buy ID {$buy['id']} at {$buy['price']} - Match P/L: {$matchPL}");
                        
                        $buy['remaining'] -= $match;
                        $sellAmount -= $match;
                        $matchedAmount += $match;
                        
                        if ($sellAmount <= 0) break;
                    }
                    unset($buy);
                    
                    // Calculate the correct profit/loss - total sale value minus total invested
                    // This ensures we're using the full sale value and not just the matched portions
                    $correctRealizedPL = $totalSaleValue - $invested;
                    
                    // Debug log after matching with corrected calculation
                    log_message("Final results for sell ID {$trade['id']}: Invested: {$invested}, Sale Value: {$totalSaleValue}, Corrected P/L: {$correctRealizedPL}");
                    
                    $entryPrice = $matchedAmount > 0 ? $weightedBuyTotal / $matchedAmount : 0;
                    $fifoResults[] = array_merge($trade, [
                        'current_price' => round($trade['price'], 2), // price at time of sell
                        'price_change_24h' => $priceChange24h,
                        'current_value' => round($trade['amount'] * $trade['price'], 2),
                        'profit_loss' => round($correctRealizedPL, 2),
                        'profit_loss_percent' => $invested > 0 ? round(($correctRealizedPL / $invested) * 100, 2) : null,
                        'invested' => round($invested, 2),
                        'entry_price' => round($entryPrice, 4),
                        'realized_pl' => round($correctRealizedPL, 2),
                    ]);
                }
            }
        }
        // Return most recent $limit trades
        return array_slice(array_reverse($fifoResults), 0, $limit);
    } catch (Exception $e) {
        log_message("[getRecentTradesWithMarketDataPDO] " . $e->getMessage(), 'error');
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
        log_message("[getTradingStatsPDO] " . $e->getMessage(), 'error');
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
        
        log_message("[getNewCryptocurrenciesPDO] Starting");
        
        $db = getDBConnection();
        if (!$db) {
            log_message("[getNewCryptocurrenciesPDO] Database connection failed", 'error');
            throw new Exception("Database connection failed");
        }

        $maxAge = MAX_COIN_AGE; // Store constant in variable
        log_message("[getNewCryptocurrenciesPDO] MAX_COIN_AGE: $maxAge");
        
        // Calculate age in hours using TIMESTAMPDIFF since we don't have an age_hours column
        $query = "SELECT *, 
                 TIMESTAMPDIFF(HOUR, date_added, NOW()) as age_hours 
                 FROM coins 
                 WHERE date_added >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                 ORDER BY date_added DESC";
                 
        log_message("[getNewCryptocurrenciesPDO] Query: $query");
        
        try {
            $stmt = $db->prepare($query);
            if (!$stmt) {
                log_message("[getNewCryptocurrenciesPDO] Prepare failed", 'error');
                throw new Exception("Prepare failed");
            }
            
            $executed = $stmt->execute([$maxAge]);
            if (!$executed) {
                log_message("[getNewCryptocurrenciesPDO] Execute failed", 'error');
                throw new Exception("Execute failed");
            }
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            log_message("[getNewCryptocurrenciesPDO] Found " . count($data) . " coins");
            if (!empty($data)) {
                log_message("[getNewCryptocurrenciesPDO] First coin: " . json_encode($data[0]));
            }
            
            return $data;
        } catch (PDOException $e) {
            log_message("[getNewCryptocurrenciesPDO] PDO Error: " . $e->getMessage(), 'error');
            throw new Exception("Database error: " . $e->getMessage());
        }
    } catch (Exception $e) {
        log_message("[getNewCryptocurrenciesPDO] " . $e->getMessage(), 'error');
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
        log_message("Error getting user balance: " . $e->getMessage(), 'error');
        return [];
    }
}


function syncPortfolioCoinsToCryptocurrenciesPDO() {
    static $debugLogs = [];
    $db = getDBConnection();
    if (!$db) {
        $debugLogs[] = "Database connection failed";
        echo "Database connection failed";
        return false;
    }

    // Get distinct coin_ids from portfolio with avg_buy_price
    $query = "SELECT DISTINCT coin_id, avg_buy_price FROM portfolio";
    $stmt = $db->query($query);
    if (!$stmt) {
        $debugLogs[] = "Failed to query portfolio: " . $db->errorInfo()[2];
        echo "Failed to query portfolio: " . $db->errorInfo()[2];
        return false;
    }

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $coinId = $row['coin_id'];
        $avgBuyPrice = $row['avg_buy_price'] ?? 0;
        $debugLogs[] = "Processing coinId: $coinId with avgBuyPrice: $avgBuyPrice";
        echo "Processing coinId: $coinId with avgBuyPrice: $avgBuyPrice";

        // Check if coinId is numeric (from coins table)
        if (is_numeric($coinId)) {
            // Get symbol and name from coins table
            $coinStmt = $db->prepare("SELECT symbol, coin_name FROM coins WHERE id = ?");
            if (!$coinStmt) {
                $debugLogs[] = "Failed to prepare coins query: " . $db->errorInfo()[2];
                echo "Failed to prepare coins query: " . $db->errorInfo()[2];
                continue;
            }
            $coinStmt->execute([$coinId]);
            $coinRow = $coinStmt->fetch(PDO::FETCH_ASSOC);

            if ($coinRow) {
                $symbol = $coinRow['symbol'];
                $name = $coinRow['coin_name'];
                echo "Found coin symbol: $symbol, name: $name";

                // Check if symbol exists in cryptocurrencies
                $checkStmt = $db->prepare("SELECT id FROM cryptocurrencies WHERE symbol = ? LIMIT 1");
                if (!$checkStmt) {
                    $debugLogs[] = "Failed to prepare crypto check query: " . $db->errorInfo()[2];
                    echo "Failed to prepare crypto check query: " . $db->errorInfo()[2];
                    continue;
                }
                $checkStmt->execute([$symbol]);
                
                if ($checkStmt->rowCount() == 0) {
                    // Insert into cryptocurrencies
                    $insertStmt = $db->prepare("INSERT INTO cryptocurrencies (id, symbol, name, created_at, price) VALUES (?, ?, ?, NOW(), ?)");
                    if ($insertStmt) {
                        $insertStmt->execute([$symbol, $symbol, $name, $avgBuyPrice]);
                        $debugLogs[] = "Inserted coin $symbol into cryptocurrencies with price $avgBuyPrice";
                        echo "Inserted coin $symbol into cryptocurrencies with price $avgBuyPrice";
                    }
                } else {
                    // Update price in cryptocurrencies
                    $updateStmt = $db->prepare("UPDATE cryptocurrencies SET price = ?, last_updated = NOW() WHERE id = ?");
                    if ($updateStmt) {
                        $debugLogs[] = "Executing update for $symbol with price $avgBuyPrice";
                        $debugLogs[] = "SQL: UPDATE cryptocurrencies SET price = $avgBuyPrice, last_updated = NOW() WHERE id = $symbol";
                        echo "Executing update for $symbol with price $avgBuyPrice";
                        echo "SQL: UPDATE cryptocurrencies SET price = $avgBuyPrice, last_updated = NOW() WHERE id = $symbol";

                        $debugLogs[] = "Updated price for $symbol to $avgBuyPrice";
                        echo "Updated price for $symbol to $avgBuyPrice";
                        if (!$db->inTransaction()) {
                            $db->commit();
                            $debugLogs[] = "Committed transaction";
                            echo "Committed transaction";
                        } else {
                            $debugLogs[] = "Transaction already active, no commit performed";
                            echo "Transaction already active, no commit performed";
                        }
                    }
                }
            }
        }
    }
}

function getSyncDebugLogs() {
    static $debugLogs = [];
    return $debugLogs;
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
        
        log_message("[getUserCoinBalancePDO] Looking for coin ID: $coinId");
        
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
            log_message("[getUserCoinBalancePDO] Binding as string: $coinId");
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['coin_id'])) {
                log_message("[getUserCoinBalancePDO] Found exact match for coin ID: $coinId");
                return [
                    'amount' => (float)$result['amount'],
                    'avg_buy_price' => (float)$result['avg_buy_price'],
                    'coin_id' => $result['coin_id']
                ];
            } else {
                log_message("[getUserCoinBalancePDO] No exact match found for coin ID: $coinId");
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
                log_message("[getUserCoinBalancePDO] Found with COIN_ prefix: $fullCoinId");
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
                log_message("[getUserCoinBalancePDO] Found with LIKE search: {$result['coin_id']}");
                return [
                    'amount' => (float)$result['amount'],
                    'avg_buy_price' => (float)$result['avg_buy_price'],
                    'coin_id' => $result['coin_id']
                ];
            }
        }
        
        log_message("[getUserCoinBalancePDO] No portfolio entry found for coin ID: $coinId", 'error');
        return [];
    } catch (Exception $e) {
        log_message("[getUserCoinBalancePDO] Error: " . $e->getMessage(), 'error');
        return [];
    }
}


/**
 * Get coins from the user's portfolio with PDO
 * @return array An array of associative arrays, each containing 'id', 'symbol', and 'name' for portfolio coins.
 */
function getPortfolioCoinsPDO(): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }

        // Select coins from the portfolio that have an amount > 0
        // Join with cryptocurrencies table to get symbol and name
        $stmt = $db->prepare("SELECT p.coin_id AS id, c.symbol, c.name
                             FROM portfolio p
                             JOIN cryptocurrencies c ON p.coin_id = c.symbol
                             WHERE p.amount > 0
                             ORDER BY c.name ASC"); // Order by name for better display

        $stmt->execute();
        $portfolioCoins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If no coins found in cryptocurrencies, try joining with the 'coins' table as a fallback
        if (empty($portfolioCoins)) {
            $stmt = $db->prepare("SELECT p.coin_id AS id, co.symbol, co.coin_name AS name
                                 FROM portfolio p
                                 JOIN coins co ON p.coin_id = co.symbol
                                 WHERE p.amount > 0
                                 ORDER BY co.coin_name ASC");
            $stmt->execute();
            $portfolioCoins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Ensure 'id', 'symbol', and 'name' are always present
        $formattedCoins = [];
        foreach ($portfolioCoins as $coin) {
            $formattedCoins[] = [
                'id' => $coin['id'] ?? null,
                'symbol' => $coin['symbol'] ?? 'UNKNOWN',
                'name' => $coin['name'] ?? $coin['symbol'] ?? 'Unknown Coin'
            ];
        }

        return $formattedCoins;

    } catch (Exception $e) {
        log_message("[getPortfolioCoinsPDO] Error: " . $e->getMessage(), 'error');
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
            $stmt = $db->prepare("SELECT id, symbol, coin_name as name, current_price as price, price_change_24h as price_change,
                                    volume_24h as volume, market_cap, date_added, is_trending as trending 
                                    FROM coins WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->execute([$coinId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && isset($result['id'])) {
                    $data = $result;
                    log_message("Found coin in coins table: " . json_encode($data));
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
                log_message("Found coin in cryptocurrencies table by ID: " . json_encode($data));
                return $data;
            }
        }
        
        // If we have a numeric ID but no direct match, try to get the symbol first
        $symbol = '';
        if (is_numeric($coinId)) {
            log_message("Getting symbol for numeric ID $coinId");
            $symbolStmt = $db->prepare("SELECT symbol FROM coins WHERE id = ? LIMIT 1");
            if ($symbolStmt) {
                $symbolStmt->execute([$coinId]);
                $symbolResult = $symbolStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($symbolResult && isset($symbolResult['symbol'])) {
                    $symbol = $symbolResult['symbol'];
                    log_message("Found symbol $symbol for ID $coinId");
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
                    log_message("Found coin in cryptocurrencies table by symbol: " . json_encode($data));
                    return $data;
                }
            }
            
            // Then try coins table
            $coinSymbolStmt = $db->prepare("SELECT id, symbol, coin_name as name, current_price as price, price_change_24h as price_change,
                                           volume_24h as volume, market_cap, date_added, is_trending as trending 
                                           FROM coins WHERE symbol = ? LIMIT 1");
            if ($coinSymbolStmt) {
                $coinSymbolStmt->execute([$symbol]);
                $coinSymbolResult = $coinSymbolStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($coinSymbolResult && isset($coinSymbolResult['id'])) {
                    $data = $coinSymbolResult;
                    log_message("Found coin in coins table by symbol: " . json_encode($data));
                    return $data;
                }
            }
        }
        
        // No data found
        return null;
    } catch (Exception $e) {
        log_message("[getCoinDataPDO] Error: " . $e->getMessage(), 'error');
        return null;
    }
}


/**
 * Get recent trades from trade_log table - direct query
 */
function getTradeLogPDO(int $limit = 100): array {
    try {
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }

        // First check the structure of the table to see what columns are available
        try {
            $structureStmt = $db->query("DESCRIBE trade_log");
            $columns = $structureStmt->fetchAll(PDO::FETCH_COLUMN);
            log_message("Available trade_log columns: " . implode(", ", $columns));
        } catch (Exception $e) {
            log_message("Could not get trade_log structure: " . $e->getMessage(), 'error');
        }

        // Query directly from trade_log table with minimal assumptions about structure
        $stmt = $db->query("SELECT * FROM trade_log ORDER BY id DESC LIMIT $limit");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($result)) {
            log_message("No trades found in trade_log table");
            return [];
        }
        
        // Debug the first result to see actual structure
        if (!empty($result)) {
            log_message("First trade from trade_log: " . json_encode($result[0]));
        }
        
        // Return the raw data for now - we'll process it in the calling function
        return $result;
    } catch (Exception $e) {
        log_message("[getTradeLogPDO] " . $e->getMessage(), 'error');
        return [];
    }
}