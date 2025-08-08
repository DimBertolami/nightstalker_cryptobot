// GLOBAL FORMATTING FUNCTIONS (must be defined before any code that uses them)
window.formatCoinName = function(name, symbol) {
    return `<span class='coin-name'>${name}</span> <span class='coin-symbol'>${symbol}</span>`;
};

window.formatPrice = function(price) {
    if (price === null || price === undefined) return 'N/A';
    return parseFloat(price).toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 8
    });
};

window.formatPercentage = function(change) {
    if (change === null || change === undefined) return 'N/A';
    const num = parseFloat(change);
    const colorClass = num >= 0 ? 'positive' : 'negative';
    return `<span class='${colorClass}'>${num >= 0 ? '+' : ''}${num.toFixed(2)}%</span>`;
};

window.formatLargeNumber = function(num) {
    if (num === null || num === undefined) return 'N/A';
    return parseFloat(num).toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).replace('.00', '');
};

window.formatAge = function(timestamp) {
    if (!timestamp) return 'N/A';
    const now = new Date();
    const then = new Date(timestamp);
    const diffMs = now - then;
    const diffHours = diffMs / (1000 * 60 * 60);
    if (diffHours < 24) {
        return diffHours.toFixed(1) + 'h';
    }
    return then.toLocaleDateString();
};

window.formatStatus = function(isTrending, volumeSpike) {
    let statusHtml = '';
    
    // Add trending and volume spike indicators
    let indicators = [];
    if (isTrending) indicators.push('Trending');
    if (volumeSpike) indicators.push('Volume Spike');
    
    // Add indicators if any exist
    if (indicators.length > 0) {
        statusHtml += `<div class="coin-indicators">${indicators.join(' • ')}</div>`;
    }
    
    return statusHtml || 'Normal';
};

window.formatSource = function(source, exchangeName) {
    // Use exchangeName if available, otherwise fall back to source
    const displaySource = exchangeName || source || 'Local';
    let sourceHtml = '';
    
    // Just display the exchange name without logo
    if (displaySource) {
        sourceHtml = `<div style="text-align:center;">${displaySource}</div>`;
    } else {
        sourceHtml = '<div style="text-align:center;">Local</div>';
    }
    
    return sourceHtml;
};

window.formatTradeButtons = function(id, symbol, price, canSell, balance) {
    return `
        <div class="trade-controls">
            <div class="input-group input-group-sm mb-1">
                <input type="number" class="form-control buy-amount" placeholder="Amount" 
                       data-id="${id}" data-symbol="${symbol}" min="0.001" step="0.001">
                <button class='btn btn-sm btn-success buy-button' data-id='${id}' data-symbol='${symbol}' data-price='${price}'>
                    Buy
                </button>
            </div>
            ${canSell ? `
            <div class="input-group input-group-sm">
                <input type="number" class="form-control sell-amount" placeholder="Amount" 
                       data-id="${id}" data-symbol="${symbol}" min="0.001" step="0.001" max="${balance}">
                <button class='btn btn-sm btn-danger btn-sell' data-id='${id}' data-symbol='${symbol}' data-price='${price}' data-balance='${balance}'>
                    Sell
                </button>
            </div>` : ''}
        </div>
    `;
};

function logEvent(message) {
    $.ajax({
        url: '/NS/api/log_event.php',
        type: 'POST',
        data: { message: message },
        dataType: 'json'
    });
}

// Function to update the portfolio display
function updatePortfolioDisplay() {
    console.groupCollapsed('updatePortfolioDisplay called');
    const $portfolio = $('#portfolio');
    const $totalValue = $('#total-portfolio-value');
    
    // Check if portfolio elements exist
    if ($portfolio.length === 0 || $totalValue.length === 0) {
        return; // Exit if portfolio section is not on the page
    }
    
    // Show loading state
    $('#portfolio-loading').html('<div class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>Loading portfolio...</div>');
    $totalValue.html('Total: $0.00');
    
    $.ajax({
        url: '/NS/api/get-portfolio.php',
        method: 'GET',
        dataType: 'json',
        cache: false, // Always get fresh data
        success: function(response) {
            $('#portfolio-loading').empty(); // Clear loading state
            $('#portfolio').empty(); // Clear previous portfolio items
            
            // Check for success and data presence
            if (!response.success || !response.portfolio || response.portfolio.length === 0) {
                $('#portfolio').html('<div class="text-muted">No coins in portfolio.</div>');
                return;
            }
            
            let totalValue = 0;
            
            // Filter out coins with zero or negative amount or zero price
            const validCoins = response.portfolio.filter(coin => {
                const amount = parseFloat(coin.amount || 0);
                const priceUsd = parseFloat(coin.current_price_usd || 0);
                if (amount > 0 && priceUsd > 0) {
                    totalValue += amount * priceUsd;
                    return true;
                }
                return false;
            });
            
            // Update total value display
            $totalValue.html(`Total: $${totalValue.toFixed(2)}`);
            
            // Handle case where no valid coins are left after filtering
            if (validCoins.length === 0) {
                $('#portfolio').html('<div class="text-muted">No coins with a positive balance found.</div>');
                return;
            }
            
            // Sort coins by value (amount * price)
            validCoins.sort((a, b) => {
                const valueA = (parseFloat(a.amount) || 0) * (parseFloat(a.current_price_usd) || 0);
                const valueB = (parseFloat(b.amount) || 0) * (parseFloat(b.current_price_usd) || 0);
                return valueB - valueA;
            });
            
            // Get widget settings from localStorage
            const widgetSettings = JSON.parse(localStorage.getItem('cryptoWidgetSettings') || '{}');
            const theme = widgetSettings.theme || 'blue';
            const showChange = widgetSettings.showChange !== false;
            const roundDecimals = parseInt(widgetSettings.roundDecimals || 2);
            
            // Create and append a widget for each coin
            validCoins.forEach(coin => {
                const symbol = (coin.symbol || coin.coin_id.replace('COIN_', '')).trim();
                const name = coin.name || symbol;
                const amount = parseFloat(coin.amount || 0);
                const coinId = coin.coin_id;
                const priceUsd = parseFloat(coin.current_price || 0);
                const totalCoinValue = amount * priceUsd;
                const priceChange = parseFloat(coin.price_change_percentage_24h || 0);
                const iconLetter = symbol.charAt(0).toUpperCase();
                
                // Calculate potential profit based on average buy price
                const avgBuyPrice = parseFloat(coin.avg_buy_price || 0);
                const potentialProfit = (priceUsd - avgBuyPrice) * amount;
                const profitClass = potentialProfit >= 0 ? 'text-success' : 'text-danger';
                
                // Create the widget HTML
                const widgetHtml = `
                    <div class="card portfolio-item crypto-widget" data-symbol="${symbol}" data-coin="${coinId}" data-purchase-price="${avgBuyPrice}">
                        <div class="card-header">
                            <div class="crypto-widget-header-inline">
                                ${amount.toFixed(2)} ${name} at ${priceUsd.toFixed(2)}
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="crypto-widget-price">Current Price: ${priceUsd.toFixed(2)}</div>
                            <div class="crypto-widget-amount-label ${profitClass}">
                                Potential Profit: ${potentialProfit.toFixed(2)}
                            </div>
                            <button class="crypto-widget-action sell sell-portfolio-btn" 
                                    data-coin="${coinId}" 
                                    data-symbol="${symbol}" 
                                    data-price="${priceUsd}" 
                                    data-amount="${amount}">
                                Sell
                            </button>
                        </div>
                    </div>
                `;
                
                $('#portfolio').append(widgetHtml);
            });
            
            // Start the portfolio price updater after portfolio items are loaded
            if (typeof startPortfolioPriceUpdaterPolling === 'function') {
                startPortfolioPriceUpdaterPolling(validCoins);
            } else {
                console.warn('startPortfolioPriceUpdaterPolling function not found. Portfolio price updater may not start.');
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to load portfolio:', {
                status: status,
                error: error,
                statusCode: xhr.status,
                statusText: xhr.statusText,
                response: xhr.responseText
            });
            
            let errorMessage = 'Failed to load portfolio data';
            try {
                const response = JSON.parse(xhr.responseText);
                if (response && response.message) {
                    errorMessage = response.message;
                } else if (xhr.status === 0) {
                    errorMessage = 'Network error: Could not connect to server';
                }
            } catch (e) {
                console.error('Error parsing error response:', e);
            }
            
            // Show error to user
            $('#portfolio').html(`<div class="alert alert-danger">${errorMessage}</div>`);
            showToast('Failed to load portfolio data', 'error');
        }
    });
}

// Wait for jQuery to be available before executing code
$(document).ready(function() {
    const urlParams = new URLSearchParams(window.location.search);
            // Check if BASE_URL is defined, if not set a default
            if (typeof BASE_URL === 'undefined') {
                BASE_URL = '';
            }
            
            // Toast notification function
            function showToast(message, type = 'info') {
                // Remove any existing toasts
                $('.toast-notification').remove();
                
                // Create toast element
                const toast = $(`<div class="toast-notification toast-${type}">
                    <div class="toast-message">${message}</div>
                </div>`);
                
                // Add to body
                $('body').append(toast);
                
                // Show the toast
                setTimeout(() => {
                    toast.addClass('show');
                    
                    // Auto-hide after 3 seconds
                    setTimeout(() => {
                        toast.removeClass('show');
                        setTimeout(() => toast.remove(), 500); // Remove after fade out animation
                    }, 3000);
                }, 100);
            }
            
            // Global variables
            const filters = {
                age: { enabled: false, value: 24 },
                marketCap: { enabled: true, value: 1500000 },
                volume: { enabled: true, value: 1500000 },
                autoRefresh: false
            };

            let isLoading = false;
            let filterTimeout = null;
            let lastRefreshTime = null;

            let autoRefreshInterval;
            let isAutoRefreshEnabled = false; // Default: auto-refresh OFF
            let showAllCoins = false; // Default: show filtered coins
            let defaultExchangeId = ''; // New variable to store default exchange ID
            let app = {}; // Main application object to hold methods
            let isProcessingTrade = false; // Global flag to prevent multiple trades
            
            // Handle filter changes with debounce to prevent rapid firing
            function handleFilterChange() {
                // Clear any existing timeout
                if (filterTimeout) {
                    clearTimeout(filterTimeout);
                }
                
                // Set a new timeout to prevent rapid consecutive calls
                filterTimeout = setTimeout(applyFilters, 500); // 500ms debounce
            }
            
            // Bind filter inputs
            $('.filter-input').on('input', handleFilterChange);
            
            // Update the toggle state based on URL parameter on page load
            if (urlParams.get('show_all') === '1') {
                $('#show-all-coins-toggle').prop('checked', true);
                showAllCoins = true;
            }

            // Function to update URL parameters without page reload
            function updateUrlParams(params) {
                const url = new URL(window.location);
                Object.keys(params).forEach(key => {
                    if (params[key] === null || params[key] === '' || params[key] === false) {
                        url.searchParams.delete(key);
                    } else {
                        url.searchParams.set(key, params[key]);
                    }
                });
                window.history.pushState({}, '', url);
            }

            // Function to read URL parameters and update filters
            function updateFiltersFromUrl() {
                const params = new URLSearchParams(window.location.search);
                let anyParam = params.has('max_age') || params.has('min_marketcap') || params.has('min_volume');
                // If no filter params, set UI to JS defaults
                if (!anyParam) {
                    // Age < 24h
                    $('#filter-age').prop('checked', false);
                    // Market Cap > 1,500,000
                    $('#filter-marketcap-toggle').prop('checked', true);
                    $('#filter-marketcap').val(1500000).prop('disabled', false);
                    // 24h Volume > 1,500,000
                    $('#filter-volume-toggle').prop('checked', true);
                    $('#filter-volume').val(1500000).prop('disabled', false);
                }
                
                // Update showAllCoins
                showAllCoins = params.get('show_all') === '1';
                
                // Update filters from URL
                if (params.has('max_age')) {
                    const maxAge = parseInt(params.get('max_age'));
                    if (!isNaN(maxAge)) {
                        filters.age.value = maxAge;
                        $('#filter-age').prop('checked', false);
                    }
                }
                
                if (params.has('min_marketcap')) {
                    const minMarketCap = parseFloat(params.get('min_marketcap'));
                    if (!isNaN(minMarketCap)) {
                        filters.marketCap.value = minMarketCap;
                        $('#filter-marketcap').val(minMarketCap);
                        $('#filter-marketcap-toggle').prop('checked', true);
                        $('#filter-marketcap').prop('disabled', false);
                    }
                }
                
                if (params.has('min_volume')) {
                    const minVolume = parseFloat(params.get('min_volume'));
                    if (!isNaN(minVolume)) {
                        filters.volume.value = minVolume;
                        $('#filter-volume').val(minVolume);
                        $('#filter-volume-toggle').prop('checked', true);
                        $('#filter-volume').prop('disabled', false);
                    }
                }
                
                // Update UI
                $('#show-all-coins-toggle').prop('checked', showAllCoins);
            }
            
            // Initialize filters from URL on page load
            updateFiltersFromUrl();
            
            // Handle popstate (back/forward button)
            window.addEventListener('popstate', function() {
                updateFiltersFromUrl();
                fetchAndUpdateData();
            });

            // Handle filter changes
            function applyFilters() {
                const $loading = $('#loading');
                $loading.show();
                
                const params = {
                    show_all: showAllCoins ? '1' : '0'
                };
                
                // Update filters from UI with validation
                if ($('#filter-age').is(':checked')) {
                    params.max_age = 24; // Always use 24h for age filter
                    console.log('Setting max age filter: 24h');
                } else {
                    params.max_age = null;
                }
                
                // Handle market cap filter
                if ($('#filter-marketcap-toggle').is(':checked')) {
                    const marketCapVal = parseFloat($('#filter-marketcap').val().replace(/[^0-9.]/g, ''));
                    if (!isNaN(marketCapVal) && marketCapVal > 0) {
                        filters.marketCap.value = marketCapVal;
                        params.min_marketcap = marketCapVal;
                        console.log('Setting min market cap filter:', marketCapVal);
                    }
                } else {
                    params.min_marketcap = null;
                }
                
                // Handle volume filter
                if ($('#filter-volume-toggle').is(':checked')) {
                    const volumeVal = parseFloat($('#filter-volume').val().replace(/[^0-9.]/g, ''));
                    if (!isNaN(volumeVal) && volumeVal > 0) {
                        filters.volume.value = volumeVal;
                        params.min_volume = volumeVal;
                        console.log('Setting min volume filter:', volumeVal);
                    }
                } else {
                    params.min_volume = null;
                }
                
                // Log the parameters being sent
                console.log('Applying filters with params:', params);
                
                // Update URL
                updateUrlParams(params);
                
                // Clear the current table data
                if (typeof coinsTable !== 'undefined' && $.fn.DataTable.isDataTable('#coins-table')) {
                    coinsTable.clear().draw();
                }
                
                // Force a fresh data fetch
                fetchAndUpdateData(true);
            }
            
            // Handle show all coins toggle
            $('#show-all-coins-toggle').on('change', function() {
                showAllCoins = $(this).is(':checked');
                applyFilters();
            });
            
            // Handle filter toggle changes
            $('.filter-toggle').on('change', function() {
                const target = $(this).data('target');
                const $input = $(`#filter-${target}`);
                
                if (target === 'age') {
                    // For age filter, just apply the filters
                    applyFilters();
                }
                
                if (filterTimeout) {
                    clearTimeout(filterTimeout);
                }
                
                // Set a new timeout to prevent rapid consecutive calls
                filterTimeout = setTimeout(applyFilters, 500); // 500ms debounce
            });
            
            // Bind filter inputs
            $('.filter-input').on('input', handleFilterChange);
            
            // Update the toggle state based on URL parameter on page load
            if (typeof initialUrlParams === 'undefined' || initialUrlParams === null) {
                initialUrlParams = new URLSearchParams(window.location.search);
            }
            if (initialUrlParams.get('show_all') === '1') {
                $('#show-all-coins-toggle').prop('checked', true);
                showAllCoins = true;
            }

            // Function to fetch default exchange
            function fetchDefaultExchange() {
                $.ajax({
                    url: `/NS/api/get-exchanges.php`,
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.default_exchange) {
                            defaultExchangeId = response.default_exchange;
                            console.log('Default exchange fetched:', defaultExchangeId);
                        } else {
                            console.error('Error fetching default exchange:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error fetching default exchange:', {
                            status: status,
                            error: error,
                            statusCode: xhr.status,
                            statusText: xhr.statusText,
                            response: xhr.responseText
                        });
                        
                        let errorMessage = 'Failed to load exchange settings';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response && response.message) {
                                errorMessage = response.message;
                            }
                        } catch (e) {
                            console.error('Error parsing error response:', e);
                        }
                        
                        // Show error to user
                        const errorHtml = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ${errorMessage}
                                <button class="btn btn-sm btn-outline-light ms-3" onclick="fetchDefaultExchange()">Try Again</button>
                            </div>
                        `;
                        $('#loading').html(errorHtml).show();
                    }
                });
            }

            // Initialize DataTables
            let coinsTable;
            if (typeof $.fn.DataTable === 'function') {
                try {
                    coinsTable = $('#coins-table').DataTable({
                        responsive: true,
                        pageLength: 25, // Default entries per page
                        language: {
                            search: "_INPUT_",
                            searchPlaceholder: "Search coins..."
                        }
                    });
                } catch (e) {
                    console.error('DataTables initialization failed:', e);
                    // Fallback: add basic table styling if DataTables fails
                    $('#coins-table').addClass('table table-striped');
                }
            } else {
                // Fallback if DataTables is not loaded
                $('#coins-table').addClass('table table-striped');
            }

            // Function to fetch and update data
            function fetchAndUpdateData(forceRefresh = false) {
                const $loading = $('#loading');
                if (!$loading.length) {
                    $('#coins-table').before('<div id="loading" class="loading-overlay"><div class="spinner"></div></div>');
                }
                $loading.show();
                
                // Construct the URL with all filter parameters
                const url = new URL(`/NS/api/get-coins.php`, window.location.origin);
                
                // Add show_all parameter
                url.searchParams.append('show_all', showAllCoins ? '1' : '0');
                
                // Add other filters if they are enabled
                if ($('#filter-age').is(':checked')) {
                    url.searchParams.append('max_age', 24);
                }
                
                if ($('#filter-marketcap-toggle').is(':checked')) {
                    const marketCapVal = parseFloat($('#filter-marketcap').val().replace(/[^0-9.]/g, ''));
                    if (!isNaN(marketCapVal) && marketCapVal > 0) {
                        filters.marketCap.value = marketCapVal;
                        url.searchParams.append('min_marketcap', marketCapVal);
                    }
                }
                
                if ($('#filter-volume-toggle').is(':checked')) {
                    const volumeVal = parseFloat($('#filter-volume').val().replace(/[^0-9.]/g, ''));
                    if (!isNaN(volumeVal) && volumeVal > 0) {
                        filters.volume.value = volumeVal;
                        url.searchParams.append('min_volume', volumeVal);
                    }
                }
                
                // Add cache-busting parameter if forcing a refresh
                if (forceRefresh) {
                    url.searchParams.append('t', new Date().getTime());
                }
                
                // Log the final URL for debugging
                console.log('Fetching data from URL:', url.toString());
                
                $.ajax({
                    url: url.toString(),
                    method: 'GET',
                    dataType: 'json',
                    cache: false, // Ensure we get fresh data
                    success: function(response) {
                        if (response.success && response.data) {
                            // Clear existing data
                            if (window.coinsTable && typeof window.coinsTable.clear === 'function') {
                                window.coinsTable.clear().draw();
                            } else {
                                $('#coins-table tbody').empty();
                            }
                            
                            // Process and add new data
                            processCoinData(response.data);
                            
                            // Redraw the table
                            if (window.coinsTable && typeof window.coinsTable.draw === 'function') {
                                window.coinsTable.draw();
                            }
                        } else {
                            console.error('Failed to fetch market data:', response.message);
                            showToast('Failed to load market data', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error fetching market data:', {
                            status: status,
                            error: error,
                            statusCode: xhr.status,
                            statusText: xhr.statusText,
                            response: xhr.responseText
                        });
                        
                        let errorMessage = 'Failed to load market data';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response && response.message) {
                                errorMessage = response.message;
                            }
                        } catch (e) {
                            console.error('Error parsing error response:', e);
                        }
                        
                        // Show error to user
                        const errorHtml = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ${errorMessage}
                                <button class="btn btn-sm btn-outline-light ms-3" onclick="fetchAndUpdateData(true)">Try Again</button>
                            </div>
                        `;
                        $('#coins-table tbody').html(`<tr><td colspan="9">${errorHtml}</td></tr>`);
                    },
                    complete: function() {
                        // Hide loading indicator
                        $loading.hide();
                    }
                });
            }

            // Function to handle buying a coin
            function buyCoin(coinId, amount) {
                // Check if a trade is already in progress
                if (isProcessingTrade) {
                    alert('A trade is already in progress. Please wait for it to complete.');
                    return;
                }
                
                isProcessingTrade = true;
                $('.buy-btn, .sell-btn').prop('disabled', true);
                
                // Use fetch API for modern approach
                fetch('/NS/api/trade.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'buy',
                        coinId: coinId.toString(),
                        amount: amount
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Refresh data to show updated portfolio
                        fetchAndUpdateData();
                        updatePortfolioDisplay();

                        // Notify nightstalker.php to refresh chart via localStorage event
                        try {
                            localStorage.setItem('nightstalker_chart_refresh', Date.now().toString());
                        } catch (e) {
                            console.warn('Failed to set localStorage for chart refresh notification:', e);
                        }
                    } else {
                        // Show error message
                        showToast(`Error: ${data.message || 'Unknown error'}`, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Trade failed: ' + error.message, 'error');
                })
                .finally(() => {
                    // Re-enable buttons after trade is complete
                    isProcessingTrade = false;
                    $('.buy-btn, .sell-btn').prop('disabled', false);
                });
            }

            // Function to handle selling a coin
function sellCoin(coinId, amount, price, symbol = null) {
    // Check if a trade is already in progress
    if (isProcessingTrade) {
        alert('A trade is already in progress. Please wait for it to complete.');
        return;
    }
    
    isProcessingTrade = true;
    $('.buy-btn, .sell-btn').prop('disabled', true);
    
    // Use jQuery AJAX for this request
    $.ajax({
        url: '/NS/api/execute-trade.php',
        type: 'POST',
        data: {
            action: 'sell',
            coin_id: coinId, // Use numeric coin_id
            symbol: symbol || coinId, // Use symbol if provided, else coinId
            amount: amount,
            price: price
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast(`Successfully sold ${amount} ${symbol || coinId}!`, 'success');
                // Refresh portfolio and clear input field
                updatePortfolioDisplay();
                if (symbol) {
                    $('.sell-amount[data-symbol="' + symbol + '"]').val(''); // Clear specific input
                }
            } else {
                showToast(`Error: ${response.message || 'Unknown error'}`, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Trade error:', xhr, status, error);
            let errorMessage = 'Trade failed: Unknown error'; // Default generic message
            try {
                const response = JSON.parse(xhr.responseText);
                if (response && response.message) {
                    errorMessage = response.message;
                } else if (error) {
                    errorMessage = 'Trade failed: ' + error;
                }
            } catch (e) {
                console.error('Error parsing error response:', e);
                if (error) {
                    errorMessage = 'Trade failed: ' + error;
                }
            }
            console.log('Final error message for toast:', errorMessage);
            showToast(errorMessage, 'error');
        },
        complete: function() {
            // Re-enable button
            isProcessingTrade = false;
            $('.buy-btn, .sell-btn').prop('disabled', false);
        }
    });
}

            // Event handler for selling from portfolio widget
            $(document).on('click', '.btn-sell, .crypto-widget-action.sell', function() {
                const $button = $(this);
                const coinId = $button.data('id') || $button.data('coin');
                const symbol = $button.data('symbol');
                const price = $button.data('price');
                let amount;
                
                // If it's a sell-all button from the portfolio, get the full balance
                if ($button.hasClass('sell-portfolio-btn')) {
                    amount = parseFloat($button.data('amount'));
                } else {
                    // Otherwise, get from the input field
                    const $inputField = $button.closest('.input-group').find('.sell-amount');
                    amount = parseFloat($inputField.val());
                }
                
                // Validate amount
                if (!amount || isNaN(amount) || amount <= 0) {
                    showToast('Please enter a valid amount to sell', 'warning');
                    return;
                }
                
                // Confirmation popup
                if (confirm(`Are you sure you want to sell ${amount} ${symbol}?`)) {
                    sellCoin(symbol, amount, price); // Pass symbol instead of coinId
                }
            });

            // Auto-refresh functionality
            function updateAutoRefresh() {
                clearInterval(autoRefreshInterval);
                if (isAutoRefreshEnabled) {
                    autoRefreshInterval = setInterval(function() {
                        fetchAndUpdateData();
                        updatePortfolioDisplay();
                    }, 30000); // Refresh every 30 seconds
                }
            }

            // Initialize auto-refresh based on toggle state
            isAutoRefreshEnabled = $('#auto-refresh-toggle').is(':checked');
            updateAutoRefresh();

            // Handle auto-refresh toggle change
            $('#auto-refresh-toggle').on('change', function() {
                isAutoRefreshEnabled = $(this).is(':checked');
                updateAutoRefresh();
            });

            // Handle show all coins toggle change
            $('#show-all-coins-toggle').on('change', function() {
                showAllCoins = $(this).prop('checked');
                
                // Update URL parameter
                const url = new URL(window.location);
                if (showAllCoins) {
                    url.searchParams.set('show_all', '1');
                } else {
                    url.searchParams.delete('show_all');
                }
                window.history.pushState({}, '', url);
                
                // Update label text
                const label = $(this).next('label');
                label.text(showAllCoins ? 'Show All Coins (All)' : 'Show All Coins (Filtered)');
                
                // Fetch new data
                fetchAndUpdateData();
            });

            // Set initial state of show all coins toggle from URL
            const showAllParam = new URLSearchParams(window.location.search).get('show_all');
            if (showAllParam === '1') {
                showAllCoins = true;
                $('#show-all-coins-toggle').prop('checked', true);
                const label = $('#show-all-coins-toggle').next('label');
                label.text('Show All Coins (All)');
            }

            // Refresh button handler
            $('#refresh-data').on('click', function(e) {
                e.preventDefault();
                fetchAndUpdateData();
                updatePortfolioDisplay();
                return false; // Prevent default action
            });

            // Generic refresh button handler for any button with refresh class
            $(document).on('click', '.refresh-btn, .refresh-data, button:contains("Refresh")', function(e) {
                e.preventDefault();
                fetchAndUpdateData();
                updatePortfolioDisplay();
                return false; // Prevent default action
            });

            // Initial data load
            fetchAndUpdateData();
            updatePortfolioDisplay();

            // Event handler for selling from the main portfolio display
            $(document).on('click', '.sell-portfolio-btn', function() {
                const $button = $(this);
                const coinId = $button.data('coin');
                const symbol = $button.data('symbol');
                const price = $button.data('price');
                const amount = $button.data('amount');
                
                // Confirmation popup
                if (confirm(`Are you sure you want to sell all ${amount} ${symbol}?`)) {
                    sellCoin(coinId, amount, price, symbol);
                }
            });
});

// Event handler for buying from the main coin list
$(document).on('click', '.buy-button, .crypto-widget-action.buy', function() {
    const $button = $(this);
    const coinId = $button.data('id') || $button.data('coin');
    const symbol = $button.data('symbol');
    const price = $button.data('price');
    let amount;
    
    // If it's a widget, prompt for amount
    if ($button.hasClass('crypto-widget-action')) {
        amount = parseFloat(prompt(`Enter amount of ${symbol} to buy:`));
    } else {
        // Otherwise, get from the input field
        const $inputField = $button.closest('.input-group').find('.buy-amount');
        amount = parseFloat($inputField.val());
    }
    
    // Validate amount
    if (!amount || isNaN(amount) || amount <= 0) {
        showToast('Please enter a valid amount to buy', 'warning');
        return;
    }
    
    // Confirmation popup
    const totalCost = (amount * price).toFixed(2);
    // Disable button and show loading state
    $button.prop('disabled', true).html(`<i class="fas fa-spinner fa-spin"></i> Buying ${symbol}...`);
    
    // Send buy request to API
    $.ajax({
            url: '/NS/api/execute-trade.php',
            method: 'POST',
            data: {
                action: 'buy',
                coin_id: coinId.toString(),
                symbol: symbol,
                amount: amount,
                price: price
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast(`Successfully purchased ${amount} ${symbol}!`, 'success');
                    // Refresh portfolio and coin list
                    updatePortfolioDisplay();
                    
                    // Clear input field if it exists
                    if (!$button.hasClass('crypto-widget-action')) {
                        const $inputField = $button.closest('.input-group').find('.buy-amount');
                        if ($inputField.length) {
                            $inputField.val('');
                        }
                    }
                } else {
                    showToast(response.message || `Failed to buy ${symbol}`, 'error');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = `Failed to buy ${symbol}: ${error}`;
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    // Ignore parsing error
                }
                showToast(errorMessage, 'error');
            },
            complete: function() {
                // Re-enable button
                $button.prop('disabled', false);
                if ($button.hasClass('crypto-widget-action')) {
                    $button.html('Buy');
                } else {
                    $button.html('Buy');
                }
            }
        });
});

// Event handler for selling from the main coin list
$(document).on('click', '.btn-sell', function() {
    const $button = $(this);
    const coinId = $button.data('id');
    const symbol = $button.data('symbol');
    const price = $button.data('price');
    const balance = parseFloat($button.data('balance'));
    
    // Get amount from input field
    const $inputField = $button.closest('.input-group').find('.sell-amount');
    const amount = parseFloat($inputField.val());
    
    // Validate amount
    if (!amount || isNaN(amount) || amount <= 0) {
        showToast('Please enter a valid amount to sell', 'warning');
        return;
    }
    
    // Check if selling more than available balance
    if (amount > balance) {
        showToast(`You only have ${balance} ${symbol} available to sell`, 'warning');
        return;
    }
    
    // Disable button and show loading state
    $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    
    // Send sell request to API
    $.ajax({
            url: '/NS/api/execute-trade.php',
            method: 'POST',
            data: {
                action: 'sell',
                coin_id: coinId.toString(),
                symbol: symbol,
                amount: amount,
                price: price
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast(`Successfully sold ${amount} ${symbol}!`, 'success');
                    // Refresh portfolio and clear input field
                    updatePortfolioDisplay();
                    $inputField.val('');
                } else {
                    showToast(`Error: ${response.message || 'Unknown error'}`, 'error');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Trade failed: ' + error;
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    // Ignore parsing error
                }
                showToast(errorMessage, 'error');
            },
            complete: function() {
                // Re-enable button
                isProcessingTrade = false;
                $button.prop('disabled', false).html('Sell');
            }
        });
});

// Function to process coin data and add to the table
function processCoinData(data) {
    data.forEach(coin => {
        // Get user balance for this coin
        const userBalance = coin.user_balance || 0;
        const canSell = userBalance > 0;
        
        // Use DataTables API to add a new row
        if (window.coinsTable && typeof window.coinsTable.row.add === 'function') {
            window.coinsTable.row.add([
                window.formatCoinName(coin.name, coin.symbol),
                window.formatPrice(coin.current_price || coin.price || 0),
                window.formatPercentage(coin.price_change_24h || 0),
                window.formatLargeNumber(coin.volume_24h || 0),
                window.formatLargeNumber(coin.marketcap || 0),
                window.formatAge(coin.date_added || coin.last_updated),
                window.formatStatus(coin.is_trending, coin.volume_spike),
                window.formatSource(coin.source || coin.data_source || '', coin.exchange_name),
                window.formatTradeButtons(coin.id, coin.symbol, coin.current_price || coin.price || 0, canSell, userBalance)
            ]);
        } else {
            // Fallback for when DataTables is not available
            const rowHtml = `
                <tr>
                    <td>${window.formatCoinName(coin.name, coin.symbol)}</td>
                    <td>${window.formatPrice(coin.current_price || coin.price || 0)}</td>
                    <td>${window.formatPercentage(coin.price_change_24h || 0)}</td>
                    <td>${window.formatLargeNumber(coin.volume_24h || 0)}</td>
                    <td>${window.formatLargeNumber(coin.marketcap || 0)}</td>
                    <td data-sort="${new Date(coin.date_added || coin.last_updated).getTime()}">${window.formatAge(coin.date_added || coin.last_updated)}</td>
                    <td>${window.formatStatus(coin.is_trending, coin.volume_spike)}</td>
                    <td>${window.formatSource(coin.source || coin.data_source || '', coin.exchange_name)}</td>
                    <td>${window.formatTradeButtons(coin.id, coin.symbol, coin.current_price || coin.price || 0, canSell, userBalance)}</td>
                </tr>
            `;
            $('#coins-table tbody').append(rowHtml);
        }
    });
}
