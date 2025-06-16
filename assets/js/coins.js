// DataTables Initialization
$(document).ready(function() {
    // Check if BASE_URL is defined, if not set a default
    if (typeof BASE_URL === 'undefined') {
        BASE_URL = '';
    }
    
    // Global variables
    let autoRefreshInterval;
    let isAutoRefreshEnabled = true;
    let showAllCoins = false; // Default: apply filters
    let defaultExchangeId = ''; // New variable to store default exchange ID

    // Function to fetch default exchange
    function fetchDefaultExchange() {
        $.ajax({
            url: `${BASE_URL}/api/get-exchanges.php`,
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
    let coinsTable = $('#coins-table').DataTable({
        responsive: true,
        pageLength: 25,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search coins..."
        }
    });
    
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
            data-max-amount="${portfolioAmount}">
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
    
    // Function to fetch data and update table
    function fetchAndUpdateData() {
        $.ajax({
            url: `${BASE_URL}/api/get-coins.php`,
            method: 'GET',
            data: { show_all: showAllCoins ? 1 : 0 },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update last updated timestamp
                    $('#last-updated').text('Last updated: ' + new Date().toLocaleTimeString());
                    
                    // Clear existing table data
                    coinsTable.clear();
                    
                    // Add new data
                    response.data.forEach(function(coin) {
                        const userBalance = coin.user_balance || 0;
                        const canSell = userBalance > 0;
                        
                        coinsTable.row.add([
                            formatCoinName(coin.name, coin.symbol),
                            formatPrice(coin.price),
                            formatPercentage(coin.price_change_24h),
                            formatLargeNumber(coin.volume || 0),
                            formatLargeNumber(coin.market_cap),
                            formatAge(coin.date_added),
                            formatStatus(coin.is_trending, coin.volume_spike, coin.data_source),
                            formatTradeButtons(coin.id, coin.symbol, coin.price, canSell, userBalance)
                        ]);
                    });
                    
                    // Redraw the table
                    coinsTable.draw();
                    

                    // Add highlight class to rows for animation
                    $('#coins-table tbody tr').addClass('highlight-update');
                    
                    // Remove highlight class after animation completes
                    setTimeout(function() {
                        $('#coins-table tbody tr').removeClass('highlight-update');
                    }, 1500);
                } else {
                    console.error('Error fetching coin data:', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
            }
        });
    }
    
    // Buy coin via AJAX
    function buyCoin(coinId, amount) {
        console.log(`Buying ${amount} of ${coinId}`);
        fetch('/NS/api/trade.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'buy',
                coinId: coinId,
                amount: amount
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Successfully bought ${amount} ${coinId}`);
                location.reload();
            } else {
                alert(`Error: ${data.message}`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Trade failed');
        });
    }

    // Sell coin via AJAX
    function sellCoin(coinId, amount) {
        console.log(`Selling ${amount} of ${coinId}`);
        fetch('/NS/api/trade.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'sell',
                coinId: coinId,
                amount: amount
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Successfully sold ${amount} ${coinId}`);
                location.reload();
            } else {
                alert(`Error: ${data.message}`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Trade failed');
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
    
});
