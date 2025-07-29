/**
 * Portfolio Price Updater with Debug Logs
 * Polls the price-history API every 3 seconds to update portfolio item prices and potential profit
 */

    const POLL_INTERVAL_MS = 3000;
    const MAX_FAILURE_COUNT = 3; // Number of consecutive failures before refresh
    let priceUpdaterInterval = null;
    let hasLoggedPortfolioEmpty = false;
    let failureCount = 0; // Track consecutive failures

    // Store purchase prices for portfolio coins (symbol -> purchase price)
    // This should be initialized from portfolio data or fetched from backend if available
    let purchasePrices = {};

    // Initialize purchasePrices from provided data or by reading DOM
    function initPurchasePrices(portfolioData = null) {
        if (portfolioData && Array.isArray(portfolioData)) {
            portfolioData.forEach(coin => {
                const symbol = (coin.symbol || coin.coin_id.replace('COIN_', '')).trim();
                const avgBuyPrice = parseFloat(coin.avg_buy_price || 0);
                if (symbol && avgBuyPrice > 0) {
                    purchasePrices[symbol.toUpperCase()] = avgBuyPrice;
                }
            });
        } else {
            // Fallback to reading DOM if no data provided
            $('#portfolio .crypto-widget').each(function() {
                const symbol = $(this).data('symbol');
                const purchasePriceAttr = $(this).attr('data-purchase-price');
                if (symbol && purchasePriceAttr) {
                    purchasePrices[symbol.toUpperCase()] = parseFloat(purchasePriceAttr);
                }
            });
        }
        console.log('Purchase prices:', purchasePrices);
    }

    // Fetch latest price history data and update portfolio display
    function fetchAndUpdatePortfolioPrices() {
        if ($('#portfolio .crypto-widget').length === 0) {
            if (priceUpdaterInterval) {
                console.log('Portfolio has become empty. Stopping price updater.');
                clearInterval(priceUpdaterInterval);
                priceUpdaterInterval = null;
                hasLoggedPortfolioEmpty = true; // Set flag when it becomes empty
            } else if (!hasLoggedPortfolioEmpty) { // Only log if not already logged
                console.log('Portfolio is empty. Price updater will not start.');
                hasLoggedPortfolioEmpty = true; // Set flag
            }
            return;
        }
        // If portfolio is not empty, reset the flag
        hasLoggedPortfolioEmpty = false;

        // Get selected exchange from UI or default
        const exchange = $('#exchange-select').val() || 'binance';

        fetch(`/NS/api/trading/public-price-updates.php?exchange=${exchange}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && Array.isArray(data.data)) {
                    failureCount = 0; // Reset on success
                    updatePortfolioPrices(data.data);

                } else {
                    console.warn('Public price updates API returned no data or failure:', data);
                }
            })
            .catch(error => {
                console.error('Error fetching public price updates for portfolio:', error);
                failureCount++;
                
                // If we've hit max failures, trigger refresh
                if (failureCount >= MAX_FAILURE_COUNT) {
                    console.log('Max failures reached, triggering refresh...');
                    const refreshBtn = document.getElementById('refresh-data');
                    if (refreshBtn) {
                        refreshBtn.click();
                    }
                    failureCount = 0; // Reset counter
                }
            });
    }

    // Update portfolio widget prices and potential profit
    function updatePortfolioPrices(priceHistoryData) {
        priceHistoryData.forEach(coinData => {
            if (!coinData.in_portfolio || typeof coinData.current_price === 'undefined' || coinData.current_price === null) return;

            const symbol = coinData.symbol.toUpperCase();
            const newPrice = parseFloat(coinData.current_price);

            // Find portfolio widget for this coin
            const $widget = $(`#portfolio .crypto-widget[data-symbol="${symbol}"]`);
            if ($widget.length === 0) {
                return;
            }

            // Update new price display with color based on increase or decrease
            const $priceElem = $widget.find('.crypto-widget-current-price-value');
            const oldPrice = parseFloat($priceElem.text().replace(/[^0-9.]/g, ''));

            if (newPrice !== oldPrice) {
                const priceChangeClass = newPrice > oldPrice ? 'text-success' : 'text-danger';
                $priceElem.html(`<span class="${priceChangeClass}">${newPrice.toFixed(2)}</span>`);
            }

            // Calculate potential profit = (new price - purchase price) * amount
            const purchasePriceAttr = $widget.attr('data-purchase-price');
            const purchasePrice = purchasePriceAttr ? parseFloat(purchasePriceAttr) : newPrice;
            const amountText = $widget.find('.crypto-widget-header-inline').text();
            const amountMatch = amountText.match(/([\d,.]+)/);
            const amount = amountMatch ? parseFloat(amountMatch[1].replace(/,/g, '')) : 0;
            const potentialProfit = (newPrice - purchasePrice) * amount;

            // Update potential profit display
            $widget.find('.crypto-widget-amount-label').text(`potential profit: ${potentialProfit.toFixed(2)}`);

            // --- Reduced Logging ---
            const now = Date.now();
            const lastLogTime = $widget.data('lastLogTime') || 0;
            const lastLoggedPrice = $widget.data('lastLoggedPrice');

            if (newPrice !== lastLoggedPrice || (now - lastLogTime > 300000)) { // 5 minutes
                console.log(`Updated ${symbol}: newPrice=${newPrice}, purchasePrice=${purchasePrice}, amount=${amount}, potentialProfit=${potentialProfit}`);
                $widget.data('lastLogTime', now);
                $widget.data('lastLoggedPrice', newPrice);
            }
        });
    }

    // This function is now globally accessible
window.startPortfolioPriceUpdaterPolling = function(initialPortfolioData = null) {
        // If the interval is already running, do nothing.
        if (priceUpdaterInterval !== null) {
            return;
        }

        // Perform an immediate update. This call will also handle the case
        // where the portfolio is empty and prevent the interval from starting.
        // It will also set hasLoggedPortfolioEmpty if the portfolio is empty.
        fetchAndUpdatePortfolioPrices();

        // If after the initial check, the portfolio is still empty,
        // and we've already logged it as empty, don't log "started" and don't set interval.
        if ($('#portfolio .crypto-widget').length === 0 && hasLoggedPortfolioEmpty) {
            return; // Portfolio is empty and we've already handled it.
        }

        // Only log "started" if we are actually going to start the interval
        // or if it's the first time we're checking and the portfolio is not empty.
        if ($('#portfolio .crypto-widget').length > 0) {
            console.log('Portfolio Price Updater started.');
            initPurchasePrices(initialPortfolioData); // Initialize purchase prices with provided data
            priceUpdaterInterval = setInterval(fetchAndUpdatePortfolioPrices, POLL_INTERVAL_MS);
        }
    }

// New code to call the backend script immediately on script load
fetch('/NS/api/trigger_unified_price_updater.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Unified price updater backend script executed successfully.');
        } else {
            console.error('Failed to execute unified price updater backend script:', data.message);
        }
    })
    .catch(error => {
        console.error('Error calling unified price updater backend script:', error);
    });
