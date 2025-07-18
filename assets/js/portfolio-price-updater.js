/**
 * Portfolio Price Updater with Debug Logs
 * Polls the price-history API every 3 seconds to update portfolio item prices and potential profit
 */

(function() {
    const POLL_INTERVAL_MS = 3000;

    // Store purchase prices for portfolio coins (symbol -> purchase price)
    // This should be initialized from portfolio data or fetched from backend if available
    let purchasePrices = {};

    // Initialize purchasePrices from existing portfolio data on page load
    function initPurchasePrices() {
        $('#portfolio .crypto-widget').each(function() {
            const symbol = $(this).data('symbol');
            // Assuming purchase price is stored as data attribute or can be fetched from backend
            // For now, store the displayed price as purchase price
            const priceText = $(this).find('.crypto-widget-price').text();
            const match = priceText.match(/\\$([0-9,.]+)/);
            if (match) {
                purchasePrices[symbol.toUpperCase()] = parseFloat(match[1].replace(/,/g, ''));
            }
        });
        console.log('Initialized purchasePrices:', purchasePrices);
    }

    // Fetch latest price history data and update portfolio display
    function fetchAndUpdatePortfolioPrices() {
        // Get selected exchange from UI or default
        const exchange = $('#exchange-select').val() || 'binance';

        fetch(`/NS/api/trading/public-price-updates.php?exchange=${exchange}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && Array.isArray(data.data)) {
                    console.log('Public price updates data received:', data.data);
                    updatePortfolioPrices(data.data);
                } else {
                    console.warn('Public price updates API returned no data or failure:', data);
                }
            })
            .catch(error => {
                console.error('Error fetching public price updates for portfolio:', error);
            });
    }

    // Update portfolio widget prices and potential profit
    function updatePortfolioPrices(priceHistoryData) {
        priceHistoryData.forEach(coinData => {
            if (!coinData.in_portfolio) return;

            const symbol = coinData.symbol.toUpperCase();
            const newPrice = coinData.current_price;

            // Find portfolio widget for this coin
            const $widget = $(`#portfolio .crypto-widget[data-symbol="${symbol}"]`);
            if ($widget.length === 0) {
                console.warn(`Portfolio widget not found for symbol: ${symbol}`);
                return;
            }

            // Update new price display with color based on increase or decrease
            const $priceElem = $widget.find('.crypto-widget-price');
            const oldPriceText = $priceElem.text();
            const oldPriceMatch = oldPriceText.match(/\\$([0-9,.]+)/);
            const oldPrice = oldPriceMatch ? parseFloat(oldPriceMatch[1].replace(/,/g, '')) : null;

            if (oldPrice === null || newPrice === oldPrice) {
                $priceElem.text('');
            } else {
                const priceChangeClass = newPrice > oldPrice ? 'text-success' : 'text-danger';
                $priceElem.html(`new-price: <span class="${priceChangeClass}">$${newPrice.toFixed(2)}</span>`);
            }

            // Calculate potential profit = (new price - purchase price) * amount
            const purchasePriceAttr = $widget.attr('data-purchase-price');
            const purchasePrice = purchasePriceAttr ? parseFloat(purchasePriceAttr) : newPrice;
            const amountText = $widget.find('.crypto-widget-header-inline').text();
            const amountMatch = amountText.match(/([\d,.]+)/);
            const amount = amountMatch ? parseFloat(amountMatch[1].replace(/,/g, '')) : 0;
            const potentialProfit = (newPrice - purchasePrice) * amount;

            // Update potential profit display
            $widget.find('.crypto-widget-amount-label').text(`potential profit: $${potentialProfit.toFixed(2)}`);

            console.log(`Updated ${symbol}: newPrice=${newPrice}, purchasePrice=${purchasePrice}, amount=${amount}, potentialProfit=${potentialProfit}`);
        });
    }

    // Initialize and start polling
    function startPolling() {
        initPurchasePrices();
        fetchAndUpdatePortfolioPrices();
        setInterval(fetchAndUpdatePortfolioPrices, POLL_INTERVAL_MS);
    }

    // Start when document is ready
    $(document).ready(function() {
        startPolling();
    });
})();
