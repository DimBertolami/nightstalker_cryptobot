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

window.formatStatus = function(isTrending, volumeSpike, source) {
    let status = [];
    if (isTrending) status.push('Trending');
    if (volumeSpike) status.push('Volume Spike');
    if (source) status.push(source);
    return status.join(' • ') || 'Normal';
};

window.formatTradeButtons = function(id, symbol, price, canSell, balance) {
    return `
        <div class="trade-controls">
            <div class="input-group input-group-sm mb-1">
                <input type="number" class="form-control buy-amount" placeholder="Amount" 
                       data-id="${id}" data-symbol="${symbol}" min="0.001" step="0.001">
                <button class='btn btn-sm btn-success btn-buy' data-id='${id}' data-symbol='${symbol}' data-price='${price}'>
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

// Wait for jQuery to be available before executing code
(function() {
    function checkJQuery() {
        if (window.jQuery) {
        // jQuery is loaded, initialize everything
        $(document).ready(function() {
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
            let autoRefreshInterval;
            let isAutoRefreshEnabled = false; // Default: auto-refresh OFF
            let showAllCoins = false; // Default: show filtered coins
            let filters = {
                maxAge: 24, // hours
                minMarketCap: 1500000, // Default Market Cap filter
                minVolume: 1500000    // Default Volume filter
            };
            let isProcessingTrade = false; // Track if a trade is in progress
            let defaultExchangeId = ''; // New variable to store default exchange ID
            let app = {}; // Main application object to hold methods
            
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
                    $('#filter-age').prop('checked', true);
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
                        filters.maxAge = maxAge;
                        $('#filter-age').prop('checked', true);
                    }
                }
                
                if (params.has('min_marketcap')) {
                    const minMarketCap = parseFloat(params.get('min_marketcap'));
                    if (!isNaN(minMarketCap)) {
                        filters.minMarketCap = minMarketCap;
                        $('#filter-marketcap').val(minMarketCap);
                        $('#filter-marketcap-toggle').prop('checked', true);
                        $('#filter-marketcap').prop('disabled', false);
                    }
                }
                
                if (params.has('min_volume')) {
                    const minVolume = parseFloat(params.get('min_volume'));
                    if (!isNaN(minVolume)) {
                        filters.minVolume = minVolume;
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
                        filters.minMarketCap = marketCapVal;
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
                        filters.minVolume = volumeVal;
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
                } else {
                    // For marketcap and volume, enable/disable the input
                    if ($(this).is(':checked')) {
                        $input.prop('disabled', false).focus();
                        if (!$input.val()) {
                            // Set default values if empty
                            $input.val(target === 'marketcap' ? '1000000' : '1000000');
                        }
                        applyFilters();
                    } else {
                        $input.prop('disabled', true).val('');
                        applyFilters();
                    }
                }
            });
            
            // Handle filter changes with debounce to prevent rapid firing
            let filterTimeout;
            function handleFilterChange() {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(applyFilters, 500); // 500ms debounce
            }
            
            // Bind filter inputs
            $('.filter-input').on('input', handleFilterChange);
            
            // Update the toggle state based on URL parameter on page load
            const initialUrlParams = new URLSearchParams(window.location.search);
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
                            } else if (xhr.status === 0) {
                                errorMessage = 'Network error: Could not connect to server';
                            }
                        } catch (e) {
                            console.error('Error parsing error response:', e);
                        }
                        
                        showToast(errorMessage, 'error');
                        
                        // If we have a 401 Unauthorized, redirect to login
                        if (xhr.status === 401) {
                            window.location.href = '/login.php';
                        }
                    }
                });
            }
            // Initialize DataTable with existing data
            let coinsTable;
            
            // Check if DataTables is available
            if (typeof $.fn.DataTable === 'function' && !window.disableDataTables) {
                try {
                    coinsTable = $('#coins-table').DataTable({
                        responsive: true,
                        pageLength: 25,
                        language: {
                            search: "_INPUT_",
                            searchPlaceholder: "Search coins..."
                        }
                    });
                } catch (e) {
                    console.error('DataTables initialization failed:', e);
                    // Fallback to basic table display
                    $('#coins-table').addClass('table').addClass('table-striped');
                }
            } else {
                console.log('DataTables is disabled or not available');
                // Fallback to basic table display
                $('#coins-table').addClass('table').addClass('table-striped');
            }
            
            // Add filter controls to the page
            if ($('#coins-controls').length === 0) {
                // Create controls container above the table
                const controlsHtml = `
                    <div id="coins-controls" class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center">
                            <button id="refresh-data" class="btn btn-sm btn-primary me-2">
                                <i class="fas fa-sync-alt"></i> Refresh Data
                            </button>
                        </div>
                        <div id="last-updated" class="text-muted small"></div>
                    </div>
                `;
                
                // Add the show all coins toggle as a separate control next to the 'Show X entries' dropdown
                const showAllToggleHtml = `
                    <div class="form-check form-switch ms-3 d-inline-block">
                        <input class="form-check-input" type="checkbox" id="show-all-coins-toggle">
                        <label class="form-check-label" for="show-all-coins-toggle">Show All Coins</label>
                    </div>
                `;
                
                // Insert the main controls before the table
                $('#coins-table').before(controlsHtml);
                
                // Insert the show all coins toggle after the 'Show X entries' dropdown
                setTimeout(() => {
                    $('.dataTables_length').append(showAllToggleHtml);
                }, 100);
            }
            
            /**
             * Fetches coin data from the server and updates the table
             * @param {boolean} forceRefresh - If true, bypasses cache
             */
            function fetchAndUpdateData(forceRefresh = true) {
                console.log('Fetching data...');
                
                // Show loading state
                const $loading = $('#loading');
                $loading.show();
                
                // Update last updated time
                const now = new Date();
                const lastUpdatedText = 'Last updated: ' + now.toLocaleTimeString();
                console.log(lastUpdatedText);
                
                // Prepare URL with parameters
                const url = new URL('/NS/api/get-coins.php', window.location.origin);
                url.searchParams.append('show_all', showAllCoins ? '1' : '0');
                
                // Add cache buster if forcing refresh
                if (forceRefresh) {
                    url.searchParams.append('t', new Date().getTime());
                }
                
                // Add any additional parameters from the URL except those we control
                const currentUrlParams = new URLSearchParams(window.location.search);
                const excludedParams = ['show_all', 't'];
                for (const [key, value] of currentUrlParams.entries()) {
                    if (!excludedParams.includes(key)) {
                        url.searchParams.append(key, value);
                    }
                }
                
                console.log('Fetching from URL:', url.toString());
                
                // Fetch coins data
                $.ajax({
                    url: url.toString(),
                    method: 'GET',
                    dataType: 'json',
                    cache: false, // Prevent caching
                    success: function(response) {
                        console.log('API Response received');
                        console.log('Response data:', response);
                        
                        if (response.success && response.data) {
                            console.log('Received', response.count, 'coins');
                            console.log('First coin sample:', response.data[0]);
                            
                            // Clear existing table data - with null check
                            if (window.coinsTable && typeof window.coinsTable.clear === 'function') {
                                window.coinsTable.clear().draw();
                            } else {
                                $('#coins-table tbody').empty();
                            }
                            
                            // Process and add new data
                            processCoinData(response.data);
                            
                            // Refresh DataTables if available
                            if (window.coinsTable && typeof window.coinsTable.draw === 'function') {
                                window.coinsTable.draw();
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        // Log detailed error information
                        const errorDetails = {
                            status: status,
                            error: error,
                            statusCode: xhr.status,
                            statusText: xhr.statusText,
                            url: xhr.responseURL || window.location.href,
                            timestamp: new Date().toISOString()
                        };
                        
                        // Log the error with more context
                        console.error('Failed to fetch market data:', errorDetails);
                        
                        // Try to extract a user-friendly error message
                        let errorMessage = 'Failed to load market data';
                        let showRetry = true;
                        
                        try {
                            // Try to parse JSON response if available
                            if (xhr.responseText) {
                                const response = JSON.parse(xhr.responseText);
                                if (response && response.message) {
                                    errorMessage = response.message;
                                    // If we have a specific error code, handle it
                                    if (response.code === 'RATE_LIMITED') {
                                        errorMessage = 'Rate limit exceeded. Please wait before trying again.';
                                        showRetry = false;
                                    }
                                }
                            }
                            
                            // Handle specific HTTP status codes
                            if (xhr.status === 0) {
                                errorMessage = 'Network error: Could not connect to the server';
                            } else if (xhr.status === 401) {
                                errorMessage = 'Session expired. Redirecting to login...';
                                showRetry = false;
                                setTimeout(() => window.location.href = '/login.php', 1500);
                                return;
                            } else if (xhr.status === 403) {
                                errorMessage = 'Access denied. Please check your permissions.';
                            } else if (xhr.status === 404) {
                                errorMessage = 'Market data endpoint not found';
                            } else if (xhr.status >= 500) {
                                errorMessage = 'Server error. Our team has been notified.';
                            }
                        } catch (e) {
                            console.error('Error processing error response:', e);
                        }
                        
                        // Show error to user
                        const errorHtml = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ${errorMessage}
                                ${showRetry ? `
                                    <button class="btn btn-sm btn-outline-light ms-3" onclick="fetchAndUpdateData(true)">
                                        <i class="fas fa-sync-alt me-1"></i> Retry
                                    </button>
                                ` : ''}
                            </div>
                        `;
                        
                        // Only show the error in the table if it's empty
                        if (coinsTable.data().count() === 0) {
                            coinsTable.clear();
                            coinsTable.row.add({
                                'symbol': '<i class="fas fa-exclamation-triangle text-warning"></i>',
                                'name': errorMessage,
                                'price': 'Error',
                                'price_change_24h': '',
                                'volume_24h': '',
                                'market_cap': ''
                            }).draw();
                        }
                        
                        // Also show a toast notification
                        showToast(errorMessage, 'error');
                    },
                    complete: function() {
                        // Always hide loading indicator when the request is complete
                        $loading.hide();
                    }
                });
            }
            
            // Function to update the portfolio display with sell buttons
            window.updatePortfolioDisplay = function() {
                console.log('Updating portfolio display...');
                
                // Find the portfolio container and total element
                const $portfolio = $('#portfolio');
                const $totalValue = $('#total-portfolio-value');
                
                if ($portfolio.length === 0 || $totalValue.length === 0) {
                    console.error('Portfolio elements not found!');
                    return;
                }
                
                // Show loading state
                const loadingHtml = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>Loading portfolio...</div>';
                $portfolio.html(loadingHtml);
                $totalValue.html('Total: $0.00');
                
                // Fetch portfolio data from the server
                $.ajax({
                    url: '/NS/api/get-portfolio.php',
                    method: 'GET',
                    dataType: 'json',
                    cache: false,
                    success: function(response) {
                        console.log('Portfolio API Response:', response);
                        
                        // Clear previous content
                        $portfolio.empty();
                        
                        if (!response.success || !response.portfolio || response.portfolio.length === 0) {
                            $portfolio.html('<div class="text-muted">No coins in portfolio.</div>');
                            return;
                        }
                        
                        // Filter out coins with zero or negative balance and calculate total value
                        let totalValue = 0;
                        const validCoins = response.portfolio.filter(coin => {
                            const amount = parseFloat(coin.amount || 0);
                            if (amount > 0) {
                                totalValue += amount;
                                return true;
                            }
                            return false;
                        });
                        
                        // Update the total value display
                        $totalValue.html(`Total: $${totalValue.toFixed(2)}`);
                        
                        if (validCoins.length === 0) {
                            $portfolio.html('<div class="text-muted">No coins with balance found.</div>');
                            return;
                        }
                        
                        // Sort coins by balance (highest first)
                        validCoins.sort((a, b) => parseFloat(b.amount || 0) - parseFloat(a.amount || 0));
                        
                        // Create and append sell buttons
                        validCoins.forEach(coin => {
                            const symbol = (coin.symbol || coin.coin_id.replace('COIN_', '')).trim();
                            const amount = parseFloat(coin.amount || 0).toFixed(2);
                            const coinId = coin.coin_id;
                            
                            console.log(`Adding portfolio item: ${symbol} (${amount})`);
                            
                            // Create the button HTML
                            const buttonHtml = `
                                <button class="sell-portfolio-btn" data-coin="${coinId}" data-symbol="${symbol}">
                                    <i class="fas fa-money-bill-wave me-1"></i>${symbol} (${amount})
                                </button>
                            `;
                            
                            // Append the button to the portfolio container
                            $portfolio.append(buttonHtml);
                        });
                        
                        console.log('Finished rendering portfolio buttons');
                    },
                    error: function(xhr, status, error) {
                        // Log detailed error information
                        const errorDetails = {
                            status: status,
                            error: error,
                            statusCode: xhr.status,
                            statusText: xhr.statusText,
                            url: xhr.responseURL || window.location.href,
                            timestamp: new Date().toISOString(),
                            responseText: xhr.responseText ? xhr.responseText.substring(0, 500) : '' // Log first 500 chars of response
                        };
                        
                        console.error('Failed to load portfolio:', errorDetails);
                        
                        // Try to extract a user-friendly error message
                        let errorMessage = 'Failed to load portfolio data';
                        let showRetry = true;
                        let isHtmlResponse = false;
                        
                        try {
                            // Check if response is HTML (starts with <)
                            if (xhr.responseText && xhr.responseText.trim().startsWith('<')) {
                                isHtmlResponse = true;
                                
                                // Try to extract error message from HTML
                                const tempDiv = document.createElement('div');
                                tempDiv.innerHTML = xhr.responseText;
                                const errorText = tempDiv.textContent || tempDiv.innerText || '';
                                
                                if (errorText.includes('Database Error') || errorText.includes('SQL syntax')) {
                                    errorMessage = 'Database error occurred. Please try again later.';
                                } else if (errorText.includes('Fatal error') || errorText.includes('Parse error')) {
                                    errorMessage = 'Server error. Our team has been notified.';
                                } else if (errorText.includes('CoinGecko') && errorText.includes('429')) {
                                    errorMessage = 'Rate limit reached. Please wait before trying again.';
                                    showRetry = false;
                                    setTimeout(() => updatePortfolioDisplay(), 30000); // Retry after 30 seconds
                                } else {
                                    errorMessage = 'Server returned an error page. Please try again.';
                                }
                            } 
                            // Try to parse JSON response if available
                            else if (xhr.responseText) {
                                try {
                                    const response = JSON.parse(xhr.responseText);
                                    if (response && response.message) {
                                        errorMessage = response.message;
                                    }
                                } catch (e) {
                                    console.error('Error parsing error response:', e);
                                }
                            }
                            
                            // Handle specific HTTP status codes
                            if (xhr.status === 0) {
                                errorMessage = 'Network error: Could not connect to the server';
                            } else if (xhr.status === 401) {
                                errorMessage = 'Session expired. Redirecting to login...';
                                showRetry = false;
                                setTimeout(() => window.location.href = '/login.php', 1500);
                                return;
                            } else if (xhr.status === 403) {
                                errorMessage = 'Access denied. Please check your permissions.';
                            } else if (xhr.status === 404) {
                                errorMessage = 'Portfolio data endpoint not found';
                            } else if (xhr.status === 429) {
                                errorMessage = 'Too many requests. Please wait before trying again.';
                                showRetry = false;
                                setTimeout(() => updatePortfolioDisplay(), 30000); // Retry after 30 seconds
                            } else if (xhr.status >= 500) {
                                errorMessage = 'Server error. Our team has been notified.';
                            }
                        } catch (e) {
                            console.error('Error processing error response:', e);
                        }
                        
                        // Show error to user with retry option
                        const errorHtml = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ${errorMessage}
                                ${showRetry ? `
                                    <button class="btn btn-sm btn-outline-light ms-3" onclick="updatePortfolioDisplay()">
                                        <i class="fas fa-sync-alt me-1"></i> Retry
                                    </button>
                                ` : ''}
                            </div>
                        `;
                        
                        $portfolio.html(errorHtml);
                        
                        // Also show a toast notification
                        showToast(errorMessage, 'error');
                    }
                });
            }

            // Buy coin via AJAX
            function buyCoin(coinId, amount) {
                // Prevent duplicate submissions
                if (isProcessingTrade) {
                    console.log('Trade already in progress, please wait...');
                    alert('A trade is already in progress. Please wait for it to complete.');
                    return;
                }
                
                // Set flag to indicate trade is in progress
                isProcessingTrade = true;
                
                console.log(`Buying ${amount} of ${coinId}`);
                
                // Disable buy/sell buttons during the trade
                $('.buy-btn, .sell-btn').prop('disabled', true);
                
                // Make the API call
                fetch('/NS/api/trade.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'buy',
                        coinId: coinId.toString(),
                        amount: amount
                    })
                })
                .then(response => {
                    // Check if the response is valid JSON
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        throw new Error('Invalid response format. Expected JSON.');
                    }
                })
                .then(data => {
                    if (data.success) {
                        // Show toast notification
                        //showToast(`Successfully bought ${amount} ${coinId}`, 'success');
                        // Refresh data
                        fetchAndUpdateData();
                        updatePortfolioDisplay();
                    } else {
                        showToast(`Error: ${data.message || 'Unknown error'}`, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Trade failed: ' + error.message, 'error');
                })
                .finally(() => {
                    // Reset processing flag and re-enable buttons
                    isProcessingTrade = false;
                    $('.buy-btn, .sell-btn').prop('disabled', false);
                });
            }

            // Sell coin via AJAX
            function sellCoin(coinId, amount) {
                // Prevent duplicate submissions
                if (isProcessingTrade) {
                    console.log('Trade already in progress, please wait...');
                    alert('A trade is already in progress. Please wait for it to complete.');
                    return;
                }
                
                // Set flag to indicate trade is in progress
                isProcessingTrade = true;
                
                console.log(`Selling ${amount} of ${coinId}`);
                
                // Disable buy/sell buttons during the trade
                $('.buy-btn, .sell-btn').prop('disabled', true);
                
                // Make the API call
                fetch('/NS/api/trade.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'sell',
                        coinId: coinId.toString(),
                        amount: amount
                    })
                })
                .then(response => {
                    // Check if the response is valid JSON
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        throw new Error('Invalid response format. Expected JSON.');
                    }
                })
                .then(data => {
                    if (data.success) {
                        // Show toast notification
                        showToast(`Successfully sold ${amount} ${coinId}`, 'success');
                        // Refresh data
                        fetchAndUpdateData();
                        updatePortfolioDisplay();
                    } else {
                        showToast(`Error: ${data.message || 'Unknown error'}`, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Trade failed: ' + error.message, 'error');
                })
                .finally(() => {
                    // Reset processing flag and re-enable buttons
                    isProcessingTrade = false;
                    $('.buy-btn, .sell-btn').prop('disabled', false);
                });
            }
            
            // Buy button handler using event delegation
            $('#coins-table').on('click', '.buy-btn', function() {
                const coinId = $(this).data('coin-id');
                const amount = $(this).closest('.d-flex').find('.trade-amount').val() || 0;

                if (amount <= 0) {
                    alert('Please enter a valid amount to buy');
                    return;
                }

                buyCoin(coinId, amount);
            });

            // Sell button handler using event delegation
            $('#coins-table').on('click', '.sell-btn', function() {
                const coinId = $(this).data('coin-id');
                const symbol = $(this).data('symbol');
                const maxAmount = parseFloat($(this).data('max-amount'));
                let amount = $(this).closest('.d-flex').find('.trade-amount').val();
                
                // If no amount entered, use the max amount available in portfolio
                if (!amount || amount.trim() === '') {
                    // Use 'all' as a special value to tell the backend to sell everything
                    sellCoin(coinId, 'all');
                    return;
                } else {
                    // Make sure we have a valid number
                    amount = parseFloat(amount);
                    if (isNaN(amount) || amount <= 0) {
                        alert('Please enter a valid amount to sell');
                        return;
                    }
                }
                
                if (amount > maxAmount) {
                    alert(`You can only sell up to ${maxAmount} ${symbol}`);
                    return;
                }

                // Use the sellCoin function instead of redirecting
                if (typeof sellCoin === 'function') {
                    sellCoin(coinId, amount);
                } else {
                    console.error('sellCoin function is not defined');
                    alert('Error: Sell function not available');
                }
            });
        
            // Set up interval for subsequent fetches
            window.autoRefreshInterval = setInterval(function() {
                console.log('Auto-refresh: Fetching data...');
                fetchAndUpdateData();
                updatePortfolioDisplay();
            }, 30000); // 30 seconds
            
            // Function to update auto-refresh state
            function updateAutoRefresh() {
                console.log('Auto-refresh:', isAutoRefreshEnabled ? 'enabled' : 'disabled');
                clearInterval(window.autoRefreshInterval);
                
                if (isAutoRefreshEnabled) {
                    // Set up interval for subsequent fetches
                    window.autoRefreshInterval = setInterval(function() {
                        console.log('Auto-refresh: Fetching data...');
                        fetchAndUpdateData();
                        updatePortfolioDisplay();
                    }, 30000); // 30 seconds
                }
            }
            
            // Initialize auto-refresh based on toggle state
            isAutoRefreshEnabled = $('#auto-refresh-toggle').is(':checked');
            updateAutoRefresh();

            // Listen for toggle changes
            $('#auto-refresh-toggle').on('change', function() {
                isAutoRefreshEnabled = $(this).is(':checked');
                updateAutoRefresh();
            });
        
            // Set up Show All Coins toggle
            $('#show-all-coins-toggle').on('change', function() {
                showAllCoins = $(this).prop('checked');
                console.log('Show all coins:', showAllCoins);
                
                // Update URL with show_all parameter without page reload
                const url = new URL(window.location);
                if (showAllCoins) {
                    url.searchParams.set('show_all', '1');
                } else {
                    url.searchParams.delete('show_all');
                }
                window.history.pushState({}, '', url);
                
                // Update the toggle label
                const label = $(this).next('label');
                label.text(showAllCoins ? 'Show All Coins (All)' : 'Show All Coins (Filtered)');
                
                // Refresh data with the new filter
                fetchAndUpdateData();
            });
        
            // Initialize showAllCoins from URL parameter on page load
            const urlParams = new URLSearchParams(window.location.search);
            const showAllParam = urlParams.get('show_all');
            if (showAllParam === '1') {
                showAllCoins = true;
                $('#show-all-coins-toggle').prop('checked', true);
                const label = $('#show-all-coins-toggle').next('label');
                label.text('Show All Coins (All)');
            }
        
            // Refresh button handler
            $('#refresh-data').on('click', function(e) {
                e.preventDefault();
                console.log('Refresh button clicked');
                fetchAndUpdateData();
                updatePortfolioDisplay();
                return false;
            });
        
            // Also keep the generic refresh handler for any other refresh buttons
            $(document).on('click', '.refresh-btn, .refresh-data, button:contains("Refresh")', function(e) {
                e.preventDefault();
                console.log('Generic refresh button clicked');
                fetchAndUpdateData();
                updatePortfolioDisplay();
                return false;
            });
        
            // Initial data load
            fetchAndUpdateData();
            updatePortfolioDisplay();
        
            // End of document.ready function
        });
        } else {
            // jQuery isn't loaded yet, wait 100ms and try again
            setTimeout(checkJQuery, 100);
        }
    }
    
    // Start checking for jQuery
    checkJQuery();
})();

// Buy button click handler
$(document).on('click', '.btn-buy', function() {
    const $button = $(this);
    const coinId = $button.data('id');
    const symbol = $button.data('symbol');
    const price = $button.data('price');
    const $inputField = $button.closest('.input-group').find('.buy-amount');
    const amount = parseFloat($inputField.val());
    
    if (!amount || isNaN(amount) || amount <= 0) {
        showToast('Please enter a valid amount to buy', 'warning');
        return;
    }
    
    // Calculate total cost (removed confirmation popup)
    const totalCost = (amount * price).toFixed(2);
    // Proceed directly without confirmation
    {
        // Disable button to prevent double-clicks
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        // Call API to execute trade
        $.ajax({
            url: '/NS/api/execute-trade.php',
            method: 'POST',
            data: {
                action: 'buy',
                coin_id: coinId,
                symbol: symbol,
                amount: amount,
                price: price
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // showToast(`Successfully purchased ${amount} ${symbol}!`, 'success');
                    // Update portfolio display
                    updatePortfolioDisplay();
                    // Clear input field
                    $inputField.val('');
                } else {
                    // showToast(response.message || 'Trade failed', 'error');
                }
            },
            error: function() {
                // showToast('Server error while processing trade', 'error');
            },
            complete: function() {
                // Re-enable button
                $button.prop('disabled', false).html('Buy');
            }
        });
    }
});

// Sell button click handler
$(document).on('click', '.btn-sell', function() {
    const $button = $(this);
    const coinId = $button.data('id');
    const symbol = $button.data('symbol');
    const price = $button.data('price');
    const balance = parseFloat($button.data('balance'));
    const $inputField = $button.closest('.input-group').find('.sell-amount');
    const amount = parseFloat($inputField.val());
    
    if (!amount || isNaN(amount) || amount <= 0) {
        showToast('Please enter a valid amount to sell', 'warning');
        return;
    }
    
    if (amount > balance) {
        showToast(`You only have ${balance} ${symbol} available to sell`, 'warning');
        return;
    }
    
    // Calculate total value (removed confirmation popup)
    const totalValue = (amount * price).toFixed(2);
    // Proceed directly without confirmation
    {
        // Disable button to prevent double-clicks
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        // Call API to execute trade
        $.ajax({
            url: '/NS/api/execute-trade.php',
            method: 'POST',
            data: {
                action: 'sell',
                coin_id: coinId,
                symbol: symbol,
                amount: amount,
                price: price
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    //showToast(`Successfully sold ${amount} ${symbol}!`, 'success');
                    // Update portfolio display
                    updatePortfolioDisplay();
                    // Clear input field
                    $inputField.val('');
                } else {
                    // showToast(response.message || 'Trade failed', 'error');
                }
            },
            error: function() {
                showToast('Server error while processing trade', 'error');
            },
            complete: function() {
                // Re-enable button
                $button.prop('disabled', false).html('Sell');
            }
        });
    }
});

// Helper function for showing toast notifications
window.showToast = function(message, type = 'info') {
    if (typeof $.toast === 'function') {
        $.toast({
            text: message,
            heading: type.charAt(0).toUpperCase() + type.slice(1),
            icon: type,
            showHideTransition: 'fade',
            allowToastClose: true,
            hideAfter: 5000,
            stack: 5,
            position: 'top-right',
            textAlign: 'left',
            loader: true,
            loaderBg: '#9EC600'
        });
    } else {
        // Fallback if toast plugin not available
        alert(message);
    }
};

function processCoinData(data) {
    // Process coins
    data.forEach(coin => {
        const userBalance = coin.user_balance || 0;
        const canSell = userBalance > 0;
        
        if (window.coinsTable && typeof window.coinsTable.row.add === 'function') {
            window.coinsTable.row.add([
                window.formatCoinName(coin.name, coin.symbol),
                window.formatPrice(coin.current_price || coin.price || 0),
                window.formatPercentage(coin.price_change_24h || 0),
                window.formatLargeNumber(coin.volume_24h || 0),
                window.formatLargeNumber(coin.marketcap || 0),
                window.formatAge(coin.date_added || coin.last_updated),
                window.formatStatus(coin.is_trending, coin.volume_spike, coin.source || coin.data_source || ''),
                window.formatTradeButtons(coin.id, coin.symbol, coin.current_price || coin.price || 0, canSell, userBalance)
            ]);
        } else {
            const rowHtml = `
                <tr>
                    <td>${window.formatCoinName(coin.name, coin.symbol)}</td>
                    <td>${window.formatPrice(coin.current_price || coin.price || 0)}</td>
                    <td>${window.formatPercentage(coin.price_change_24h || 0)}</td>
                    <td>${window.formatLargeNumber(coin.volume_24h || 0)}</td>
                    <td>${window.formatLargeNumber(coin.marketcap || 0)}</td>
                    <td data-sort="${new Date(coin.date_added || coin.last_updated).getTime()}">${window.formatAge(coin.date_added || coin.last_updated)}</td>
                    <td>${window.formatStatus(coin.is_trending, coin.volume_spike, coin.source || coin.data_source || '')}</td>
                    <td>${window.formatTradeButtons(coin.id, coin.symbol, coin.current_price || coin.price || 0, canSell, userBalance)}</td>
                </tr>
            `;
            $('#coins-table tbody').append(rowHtml);
        }
    });
}