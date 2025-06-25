<?php
namespace NS\Exchanges;

use ccxt\bitget;
use Exception;

class BitgetTrader {
    private $exchange;
    private $testMode;

    public function __construct($testMode = true) {
        $this->testMode = $testMode;
        $this->exchange = new bitget([
            'apiKey' => '', // Set dynamically from config
            'secret' => '', // Set dynamically from config
            'enableRateLimit' => true,
        ]);
        if ($testMode) {
            if (method_exists($this->exchange, 'set_sandbox_mode')) {
                $this->exchange->set_sandbox_mode(true);
            }
        }
    }

    public function getAccountBalance() {
        try {
            $balance = $this->exchange->fetch_balance();
            return $balance;
        } catch (Exception $e) {
            logEvent("Error fetching balance: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }

    public function getAvailableMarkets() {
        try {
            $markets = $this->exchange->fetch_markets();
            return $markets;
        } catch (Exception $e) {
            logEvent("Error fetching markets: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }

    public function getTicker($symbol) {
        try {
            $ticker = $this->exchange->fetch_ticker($symbol);
            return $ticker;
        } catch (Exception $e) {
            logEvent("Error fetching ticker for {$symbol}: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }

    public function placeMarketBuyOrder($symbol, $amount) {
        try {
            $order = $this->exchange->create_market_buy_order($symbol, $amount);
            return $order;
        } catch (Exception $e) {
            logEvent("Error placing market buy order for {$symbol}: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }

    public function placeMarketSellOrder($symbol, $amount) {
        try {
            $order = $this->exchange->create_market_sell_order($symbol, $amount);
            return $order;
        } catch (Exception $e) {
            logEvent("Error placing market sell order for {$symbol}: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }

    public function placeLimitBuyOrder($symbol, $amount, $price) {
        try {
            $order = $this->exchange->create_limit_buy_order($symbol, $amount, $price);
            return $order;
        } catch (Exception $e) {
            logEvent("Error placing limit buy order for {$symbol}: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }

    public function placeLimitSellOrder($symbol, $amount, $price) {
        try {
            $order = $this->exchange->create_limit_sell_order($symbol, $amount, $price);
            return $order;
        } catch (Exception $e) {
            logEvent("Error placing limit sell order for {$symbol}: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }

    public function cancelOrder($orderId, $symbol = null) {
        try {
            $order = $this->exchange->cancel_order($orderId, $symbol);
            return $order;
        } catch (Exception $e) {
            logEvent("Error canceling order {$orderId}: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }

    public function getOrderInfo($orderId, $symbol) {
        try {
            $order = $this->exchange->fetch_order($orderId, $symbol);
            return $order;
        } catch (Exception $e) {
            logEvent("Error fetching order info for {$orderId}: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }

    public function getOpenOrders($symbol = null) {
        try {
            $orders = $this->exchange->fetch_open_orders($symbol);
            return $orders;
        } catch (Exception $e) {
            logEvent("Error fetching open orders: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }

    public function getOrderHistory($symbol = null) {
        try {
            $history = $this->exchange->fetch_closed_orders($symbol);
            return $history;
        } catch (Exception $e) {
            logEvent("Error fetching order history: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }

    public function getTradeHistory($symbol) {
        try {
            $trades = $this->exchange->fetch_my_trades($symbol);
            return $trades;
        } catch (Exception $e) {
            logEvent("Error fetching trade history for {$symbol}: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }
}