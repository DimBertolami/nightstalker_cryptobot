// Wait for jQuery to be available before executing code
(function checkJQuery() {
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
            let isAutoRefreshEnabled = true;
            let showAllCoins = false; // Default: show filtered coins
            let isProcessingTrade = false; // Track if a trade is in progress
            let defaultExchangeId = ''; // New variable to store default exchange ID
            let app = {}; // Main application object to hold methods
            
            // Handle show all coins toggle
            $('#show-all-coins-toggle').on('change', function() {
                showAllCoins = $(this).is(':checked');
                console.log('Show all coins:', showAllCoins);
                
                // Show loading indicator
                const $loading = $('#loading');
                $loading.show();
                
                // Clear the current table data
                if ($.fn.DataTable.isDataTable('#coins-table')) {
                    coinsTable.clear().draw();
                }
                
                // Fetch and update data with the new filter
                fetchAndUpdateData();
            });
            
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
                        console.error('AJAX error fetching default exchange:', error);
                    }
                });
            }
            // Initialize DataTable with existing data
            let coinsTable;
            
            // Only initialize DataTable if it doesn't already exist
            if (!$.fn.DataTable.isDataTable('#coins-table')) {
                coinsTable = $('#coins-table').DataTable({
                    responsive: true,
                    pageLength: 25,
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search coins..."
                    }
                });
            } else {
                coinsTable = $('#coins-table').DataTable();
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
            
            // Format functions
            function formatPrice(price) {
                return '$' + (price >= 1 ? price.toFixed(2) : price.toFixed(8));
            }
            
            function formatLargeNumber(num) {
                return '$' + num.toLocaleString();
            }
            
            function formatPercentage(percent) {
                const isPositive = percent >= 0;
                const icon = isPositive ? '<i class="fas fa-caret-up"></i>' : '<i class="fas fa-caret-down"></i>';
                return `<span class="${isPositive ? 'price-up' : 'price-down'}">${icon} ${Math.abs(percent).toFixed(2)}%</span>`;
            }
            
            function formatAge(dateAdded) {
                if (!dateAdded) return 'Unknown';
                
                const added = new Date(dateAdded);
                const now = new Date();
                const diffTime = Math.abs(now - added);
                const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays > 0) {
                    return `${diffDays} days`;
                } else {
                    const diffHours = Math.floor(diffTime / (1000 * 60 * 60));
                    const isNew = diffHours < 24;
                    return `${diffHours} hours${isNew ? ' <span class="badge bg-danger">NEW</span>' : ''}`;
                }
            }
            
            function formatCoinName(name, symbol) {
                return `
                    <div class="d-flex align-items-center">
                        <div>
                            <div class="fw-bold">${name}</div>
                            <div class="text-muted">${symbol}</div>
                        </div>
                    </div>
                `;
            }
            
            function formatStatus(isTrending, volumeSpike, dataSource) {
                let badges = [];
                
                // Always show the data source
                if (dataSource) {
                    badges.push(`<span class="badge bg-info">Source: ${dataSource}</span>`);
                }
                
                // Add other status badges
                if (isTrending) {
                    badges.push('<span class="badge badge-trending">Trending</span>');
                }
                if (volumeSpike) {
                    badges.push('<span class="badge badge-volume">Volume Spike</span>');
                }
                
                return badges.length > 0 ? badges.join(' ') : '<span class="badge bg-secondary">Normal</span>';
            }
            
            function formatTradeButtons(coinId, symbol, price, canSell, portfolioAmount = 0) {
            // Only show sell button if user has coins in portfolio
            const sellButton = canSell ? 
                `<button type="button" class="btn btn-danger sell-btn" 
                    data-coin-id="${coinId}" 
                    data-symbol="${symbol}" 
                    data-price="${price}"
                    data-max-amount="${portfolioAmount}"
                    style="opacity: 1 !important;">
                    <i class="fas fa-money-bill-wave"></i> Sell
                </button>` : '';
            
            // Show portfolio amount if available
            const portfolioInfo = portfolioAmount > 0 ? 
                `<small class="text-muted d-block">You own: ${portfolioAmount} ${symbol}</small>` : '';
            
            return `
                <div class="d-flex align-items-center">
                    <div class="input-group input-group-sm me-2" style="width: 120px;">
                        <input type="number" class="form-control trade-amount" 
                            placeholder="Amount" step="0.01" min="0.01">
                        ${portfolioInfo}
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-success buy-btn" 
                            data-coin-id="${coinId}" 
                            data-symbol="${symbol}" 
                            data-price="${price}">
                            <i class="fas fa-shopping-cart"></i> Buy
                        </button>
                        ${sellButton}
                    </div>
                </div>
            `;
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
                        
                        // Hide loading indicator
                        $loading.hide();
                        
                        if (!response) {
                            throw new Error('Empty response from server');
                        }
                        
                        console.log('Response data:', response);
                        
                        if (response.success !== true) {
                            throw new Error(response.message || 'API request failed');
                        }
                        
                        if (!response.data || !Array.isArray(response.data)) {
                            throw new Error('Invalid data format received from server');
                        }
                        
                        const coins = response.data;
                        console.log(`Received ${coins.length} coins`);
                        
                        if (coins.length > 0) {
                            // 1. Log sample first
                            console.log('First coin sample:', coins[0]);
                            
                            // 2. Clear table immediately
                            console.log('Clearing existing table data');
                            coinsTable.clear().draw(false);
                            
                            // 3. Update timestamp
                            const updateTime = new Date().toLocaleTimeString();
                            console.log('Updating table at:', updateTime);
                            $('#last-updated').text('Last updated: ' + updateTime);
                            
                            try {
                                // 4. Process coins
                                coins.forEach(coin => {
                                    const userBalance = coin.user_balance || 0;
                                    const canSell = userBalance > 0;
                                    
                                    coinsTable.row.add([
                                        formatCoinName(coin.name, coin.symbol),
                                        formatPrice(coin.current_price || coin.price || 0),
                                        formatPercentage(coin.price_change_24h || 0),
                                        formatLargeNumber(coin.volume_24h || 0),
                                        formatLargeNumber(coin.market_cap || 0),
                                        formatAge(coin.date_added || coin.last_updated),
                                        formatStatus(coin.is_trending, coin.volume_spike, coin.source || coin.data_source || ''),
                                        formatTradeButtons(coin.id, coin.symbol, coin.current_price || coin.price || 0, canSell, userBalance)
                                    ]);
                                });
                                
                                // 5. Draw and complete
                                coinsTable.draw(true);
                                console.log('Table update complete - added', coins.length, 'coins');
                                
                                // Update the portfolio display after a short delay to avoid race conditions
                                console.log('Scheduling portfolio update');
                                setTimeout(() => {
                                    console.log('Starting portfolio update');
                                    updatePortfolioDisplay();
                                }, 100);
                                
                            } catch (error) {
                                console.error('Error updating table:', error);
                                showToast('Error updating table: ' + (error.message || 'Unknown error'), 'error');
                                throw error; // Re-throw to be caught by the error handler
                            }
                            
                            // Add highlight class to rows for animation
                            $('#coins-table tbody tr').addClass('highlight-update');
                            
                            // Remove highlight class after animation completes
                            setTimeout(function() {
                                $('#coins-table tbody tr').removeClass('highlight-update');
                            }, 1500);
                        } else {
                            console.warn('No coins data available');
                            // Clear the table if no data
                            coinsTable.clear().draw();
                            
                            // Show a message to the user
                            const message = 'No coins data available. Try refreshing the page.';
                            coinsTable.row.add({
                                symbol: 'No Data',
                                name: message,
                                price: '',
                                price_change_24h: '',
                                volume_24h: '',
                                market_cap: ''
                            }).draw();
                            
                            //showToast(message, 'warning');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', {
                            status: status,
                            error: error,
                            response: xhr.responseText,
                            statusCode: xhr.status,
                            statusText: xhr.statusText
                        });
                        
                        let errorMessage = 'Failed to fetch data';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response && response.message) {
                                errorMessage = response.message;
                            } else if (xhr.status === 0) {
                                errorMessage = 'Network error: Could not connect to server';
                            } else if (xhr.status === 500) {
                                errorMessage = 'Server error: Please try again later';
                            }
                        } catch (e) {
                            console.error('Error parsing error response:', e);
                        }
                        
                        showToast(errorMessage, 'error');
                        $loading.hide();
                        
                        // If we have a 401 Unauthorized, redirect to login
                        if (xhr.status === 401) {
                            window.location.href = '/login.php';
                        }
                    },
                    complete: function() {
                        // Always hide loading indicator when the request is complete
                        $loading.hide();
                    }
                });
            }
            
            // Function to update the portfolio display with sell buttons
            function updatePortfolioDisplay() {
                console.log('Updating portfolio display...');
                
                // Find the portfolio list container
                const $portfolioList = $('#portfolioList');
                if ($portfolioList.length === 0) {
                    console.error('portfolioList element not found!');
                    return;
                }
                
                // Show loading state
                $portfolioList.html('<div class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>Loading portfolio...</div>');
                
                // Fetch portfolio data from the server
                $.ajax({
                    url: '/NS/api/get-portfolio.php',
                    method: 'GET',
                    dataType: 'json',
                    cache: false,
                    success: function(response) {
                        console.log('Portfolio API Response:', response);
                        
                        // Clear previous content
                        $portfolioList.empty();
                        
                        if (!response.success || !response.portfolio || response.portfolio.length === 0) {
                            $portfolioList.html('<div class="text-muted p-2">No coins in portfolio.</div>');
                            return;
                        }
                        
                        // Filter out coins with zero or negative balance
                        const validCoins = response.portfolio.filter(coin => {
                            return parseFloat(coin.amount || 0) > 0;
                        });
                        
                        if (validCoins.length === 0) {
                            $portfolioList.html('<div class="text-muted p-2">No coins with balance found.</div>');
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
                            
                            const $button = $(`
                                <button class="btn btn-sm btn-outline-danger me-2 mb-2 sell-portfolio-btn" 
                                        data-coin="${coinId}"
                                        data-symbol="${symbol}">
                                    ${symbol} (${amount})
                                </button>
                            `);
                            
                            $button.on('click', function(e) {
                                e.preventDefault();
                                console.log(`Sell button clicked: ${symbol} (${amount})`);
                                if (confirm(`Sell all ${amount} ${symbol}?`)) {
                                    console.log(`Initiating sell for ${amount} ${symbol}`);
                                    sellCoin(coinId, 'all');
                                }
                            });
                            
                            $portfolioList.append($button);
                        });
                        
                        console.log('Finished rendering portfolio buttons');
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading portfolio:', status, error);
                        $portfolioList.html(`
                            <div class="alert alert-danger">
                                Error loading portfolio: ${status}<br>
                                <button class="btn btn-sm btn-outline-secondary mt-2" onclick="updatePortfolioDisplay()">
                                    <i class="fas fa-sync-alt me-1"></i> Retry
                                </button>
                            </div>
                        `);
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
                        showToast(`Successfully bought ${amount} ${coinId}`, 'success');
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
        
        // Initialize auto-refresh
        isAutoRefreshEnabled = true; // Start with auto-refresh enabled
        updateAutoRefresh();
        
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
})();