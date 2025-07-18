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
    let indicators = [];
    if (isTrending) indicators.push('Trending');
    if (volumeSpike) indicators.push('Volume Spike');
    if (indicators.length > 0) {
        statusHtml += `<div class="coin-indicators">${indicators.join(' • ')}</div>`;
    }
    return statusHtml || 'Normal';
};

window.formatSource = function(source, exchangeName) {
    const displaySource = exchangeName || source || 'Local';
    let sourceHtml = '';
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
        alert(message);
    }
};

(function() {
    function checkJQuery() {
        if (window.jQuery) {
            // jQuery is loaded, initialize everything
            $(document).ready(function() {
                const urlParams = new URLSearchParams(window.location.search);
                if (typeof BASE_URL === 'undefined') {
                    BASE_URL = '';
                }

                const filters = {
                    age: { enabled: true, value: 24 },
                    marketCap: { enabled: true, value: 1500000 },
                    volume: { enabled: true, value: 1500000 },
                    autoRefresh: true
                };

                let isLoading = false;
                let filterTimeout = null;
                let lastRefreshTime = null;
                let autoRefreshInterval;
                let isAutoRefreshEnabled = false;
                let showAllCoins = false;
                let defaultExchangeId = '';
                let coinsTable;
                var isProcessingTrade = false;
                var portfolioData = {};

                function updateUrlParams(params) {
                    const url = new URL(window.location);
                    Object.keys(params).forEach(key => {
                        if (params[key] !== null && params[key] !== undefined) {
                            url.searchParams.set(key, params[key]);
                        } else {
                            url.searchParams.delete(key);
                        }
                    });
                    window.history.pushState({}, '', url);
                }

                function updateFiltersFromUrl() {
                    let anyParam = urlParams.has('max_age') || urlParams.has('min_marketcap') || urlParams.has('min_volume');
                    if (!anyParam) {
                        filters.age.value = 24;
                        filters.marketCap.value = 1500000;
                        filters.volume.value = 1500000;
                    }
                    showAllCoins = urlParams.get('show_all') === '1';
                    if (urlParams.has('max_age')) {
                        filters.age.value = parseInt(urlParams.get('max_age'));
                    }
                    if (urlParams.has('min_marketcap')) {
                        filters.marketCap.value = parseInt(urlParams.get('min_marketcap'));
                    }
                    if (urlParams.has('min_volume')) {
                        filters.volume.value = parseInt(urlParams.get('min_volume'));
                    }
                    $('#show-all-coins-toggle').prop('checked', showAllCoins);
                }

                updateFiltersFromUrl();

                window.addEventListener('popstate', function() {
                    updateFiltersFromUrl();
                    fetchAndUpdateData();
                });

                function applyFilters() {
                    const $loading = $('#loading');
                    $loading.show();
                    const params = {
                        show_all: showAllCoins ? '1' : '0'
                    };
                    if ($('#filter-age').is(':checked')) {
                        params.max_age = 24;
                    } else {
                        params.max_age = null;
                    }
                    if ($('#filter-marketcap-toggle').is(':checked')) {
                        const marketCapVal = parseFloat($('#filter-marketcap').val().replace(/[^0-9.]/g, ''));
                        if (!isNaN(marketCapVal) && marketCapVal > 0) {
                            filters.marketCap.value = marketCapVal;
                            params.min_marketcap = marketCapVal;
                        }
                    } else {
                        params.min_marketcap = null;
                    }
                    if ($('#filter-volume-toggle').is(':checked')) {
                        const volumeVal = parseFloat($('#filter-volume').val().replace(/[^0-9.]/g, ''));
                        if (!isNaN(volumeVal) && volumeVal > 0) {
                            filters.volume.value = volumeVal;
                            params.min_volume = volumeVal;
                        }
                    } else {
                        params.min_volume = null;
                    }
                    updateUrlParams(params);
                    if (typeof coinsTable !== 'undefined' && $.fn.DataTable.isDataTable('#coins-table')) {
                        coinsTable.clear().draw();
                    }
                    fetchAndUpdateData(true);
                }

                $('#show-all-coins-toggle').on('change', function() {
                    showAllCoins = $(this).is(':checked');
                    applyFilters();
                });

                $('.filter-toggle').on('change', function() {
                    const target = $(this).data('target');
                    const $input = $(`#filter-${target}`);
                    if (target === 'age') {
                        applyFilters();
                    } else {
                        if ($(this).is(':checked')) {
                            $input.prop('disabled', false).focus();
                            if (!$input.val()) {
                                $input.val(target === 'marketcap' ? '1000000' : '1000000');
                            }
                            applyFilters();
                        } else {
                            $input.prop('disabled', true).val('');
                            applyFilters();
                        }
                    }
                });

                function handleFilterChange() {
                    if (filterTimeout) {
                        clearTimeout(filterTimeout);
                    }
                    filterTimeout = setTimeout(applyFilters, 500);
                }

                $('.filter-input').on('input', handleFilterChange);

                if (urlParams.get('show_all') === '1') {
                    $('#show-all-coins-toggle').prop('checked', true);
                    showAllCoins = true;
                }

                function fetchDefaultExchange() {
                    $.ajax({
                        url: `/NS/api/get-exchanges.php`,
                        method: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success && response.default_exchange) {
                                defaultExchangeId = response.default_exchange;
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error fetching default exchange:', { status: status, error: error });
                            showToast('Failed to load exchange settings', 'error');
                        }
                    });
                }

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
                        $('#coins-table').addClass('table table-striped');
                    }
                } else {
                    $('#coins-table').addClass('table table-striped');
                }

                function fetchAndUpdateData(forceRefresh = true) {
                    const $loading = $('#loading');
                    $loading.show();
                    const url = new URL('/NS/api/get-coins.php', window.location.origin);
                    url.searchParams.append('show_all', showAllCoins ? '1' : '0');
                    if (forceRefresh) {
                        url.searchParams.append('t', new Date().getTime());
                    }
                    const currentUrlParams = new URLSearchParams(window.location.search);
                    const excludedParams = ['show_all', 't'];
                    for (const [key, value] of currentUrlParams.entries()) {
                        if (!excludedParams.includes(key)) {
                            url.searchParams.append(key, value);
                        }
                    }
                    $.ajax({
                        url: url.toString(),
                        method: 'GET',
                        dataType: 'json',
                        cache: false,
                        success: function(response) {
                            if (response.success && response.data) {
                                if (window.coinsTable && typeof window.coinsTable.clear === 'function') {
                                    window.coinsTable.clear().draw();
                                } else {
                                    $('#coins-table tbody').empty();
                                }
                                processCoinData(response.data);
                                if (window.coinsTable && typeof window.coinsTable.draw === 'function') {
                                    window.coinsTable.draw();
                                }
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Failed to fetch market data:', { status: status, error: error });
                            showToast('Failed to load market data', 'error');
                        },
                        complete: function() {
                            $loading.hide();
                        }
                    });
                }

window.updatePortfolioDisplay = function() {
    const $portfolio = $('#portfolio');
    const $totalValue = $('#total-portfolio-value');
    if ($portfolio.length === 0 || $totalValue.length === 0) {
        return;
    }
    $portfolio.html('<div class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>Loading portfolio...</div>');
    $totalValue.html('Total: $0.00');
    $.ajax({
        url: '/NS/api/get-portfolio.php',
        method: 'GET',
        dataType: 'json',
        cache: false,
        success: function(response) {
            $portfolio.empty();
            if (!response.success || !response.portfolio || response.portfolio.length === 0) {
                $portfolio.html('<div class="text-muted">No coins in portfolio.</div>');
                return;
            }
            let totalValue = 0;
            const validCoins = response.portfolio.filter(coin => {
                const amount = parseFloat(coin.amount || 0);
                if (amount > 0) {
                    const priceUsd = parseFloat(coin.current_price_usd || 0);
                    totalValue += amount * priceUsd;
                    return true;
                }
                return false;
            });
            $totalValue.html(`Total: $${totalValue.toFixed(2)}`);
            if (validCoins.length === 0) {
                $portfolio.html('<div class="text-muted">No coins with balance found.</div>');
                return;
            }
            validCoins.sort((a, b) => parseFloat(b.amount || 0) - parseFloat(a.amount || 0));
            const widgetSettings = JSON.parse(localStorage.getItem('cryptoWidgetSettings') || '{}');
            const theme = widgetSettings.theme || 'blue';
            const showChange = widgetSettings.showChange !== false;
            const roundDecimals = parseInt(widgetSettings.roundDecimals || 2);
            validCoins.forEach(coin => {
                const symbol = (coin.symbol || coin.coin_id.replace('COIN_', '')).trim();
                const name = coin.name || symbol;
                const amount = parseFloat(coin.amount || 0);
                const coinId = coin.coin_id;
                const priceUsd = parseFloat(coin.current_price || 0);
                const totalCoinValue = amount * priceUsd;
                const priceChange = parseFloat(coin.price_change_percentage_24h || 0);
                const iconLetter = symbol.charAt(0).toUpperCase();
            const widgetHtml = `
                <div class="card portfolio-item" data-symbol="${symbol}" data-coin="${coinId}" data-purchase-price="${coin.avg_buy_price}">
                    <div class="card-header">
                        <div class="crypto-widget-header-inline">
                            ${amount.toFixed(2)} ${name} at $${priceUsd.toFixed(2)}
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="crypto-widget-price">new-price: $${priceUsd.toFixed(2)}</div>
                        <div class="crypto-widget-amount-label">potential profit: $${(priceUsd * amount * 10).toFixed(2)}</div>
                        <button class="crypto-widget-action sell sell-portfolio-btn" data-coin="${coinId}" data-symbol="${symbol}" data-price="${priceUsd}" data-amount="${amount}">
                            Sell
                        </button>
                    </div>
                </div>
            `;
                $portfolio.append(widgetHtml);
            });
        },
        error: function(xhr, status, error) {
            console.error('Failed to load portfolio:', { status: status, error: error });
            $portfolio.html('<div class="alert alert-danger">Failed to load portfolio data.</div>');
            showToast('Failed to load portfolio data', 'error');
        }
    });
}

                function buyCoin(coinId, amount) {
                    if (isProcessingTrade) {
                        alert('A trade is already in progress. Please wait for it to complete.');
                        return;
                    }
                    isProcessingTrade = true;
                    $('.buy-btn, .sell-btn').prop('disabled', true);
                    fetch('/NS/api/trade.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'buy', coinId: coinId.toString(), amount: amount })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
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
                        isProcessingTrade = false;
                        $('.buy-btn, .sell-btn').prop('disabled', false);
                    });
                }

                function sellCoin(coinId, amount, price, symbol) {
                    if (isProcessingTrade) {
                        alert('A trade is already in progress. Please wait for it to complete.');
                        return;
                    }
                    isProcessingTrade = true;
                    $('.buy-btn, .sell-btn').prop('disabled', true);
                    $.ajax({
                        url: '/NS/api/execute-trade.php',
                        type: 'POST',
                        data: { action: 'sell', coin_id: coinId.toString(), symbol: symbol, amount: amount, price: price },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                showToast(`Successfully sold ${amount} ${symbol || coinId}`, 'success');
                                fetchAndUpdateData();
                                updatePortfolioDisplay();
                            } else {
                                showToast(`Error: ${response.message || 'Unknown error'}`, 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Trade error:', xhr, status, error);
                            showToast('Trade failed: ' + error, 'error');
                        },
                        complete: function() {
                            isProcessingTrade = false;
                            $('.buy-btn, .sell-btn').prop('disabled', false);
                        }
                    });
                }

                $(document).on('click', '.btn-sell, .crypto-widget-action.sell', function() {
                    const $button = $(this);
                    const coinId = $button.data('id') || $button.data('coin');
                    const symbol = $button.data('symbol');
                    const price = $button.data('price');
                    let amount;
                    if ($button.hasClass('crypto-widget-action')) {
                        amount = parseFloat(prompt(`Enter amount of ${symbol} to sell:`));
                    } else {
                        const $inputField = $button.closest('.input-group').find('.sell-amount');
                        amount = parseFloat($inputField.val());
                    }
                    if (!amount || isNaN(amount) || amount <= 0) {
                        showToast('Please enter a valid amount to sell', 'warning');
                        return;
                    }
                    sellCoin(coinId, amount, price, symbol);
                });

                function updateAutoRefresh() {
                    clearInterval(autoRefreshInterval);
                    if (isAutoRefreshEnabled) {
                        autoRefreshInterval = setInterval(function() {
                            fetchAndUpdateData();
                            updatePortfolioDisplay();
                        }, 30000); // 30 seconds
                    }
                }

                isAutoRefreshEnabled = $('#auto-refresh-toggle').is(':checked');
                updateAutoRefresh();

                $('#auto-refresh-toggle').on('change', function() {
                    isAutoRefreshEnabled = $(this).is(':checked');
                    updateAutoRefresh();
                });

                $('#show-all-coins-toggle').on('change', function() {
                    showAllCoins = $(this).prop('checked');
                    const url = new URL(window.location);
                    if (showAllCoins) {
                        url.searchParams.set('show_all', '1');
                    } else {
                        url.searchParams.delete('show_all');
                    }
                    window.history.pushState({}, '', url);
                    const label = $(this).next('label');
                    label.text(showAllCoins ? 'Show All Coins (All)' : 'Show All Coins (Filtered)');
                    fetchAndUpdateData();
                });

                const showAllParam = urlParams.get('show_all');
                if (showAllParam === '1') {
                    showAllCoins = true;
                    $('#show-all-coins-toggle').prop('checked', true);
                    const label = $('#show-all-coins-toggle').next('label');
                    label.text('Show All Coins (All)');
                }

                $('#refresh-data').on('click', function(e) {
                    e.preventDefault();
                    fetchAndUpdateData();
                    updatePortfolioDisplay();
                    return false;
                });

                $(document).on('click', '.refresh-btn, .refresh-data, button:contains("Refresh")', function(e) {
                    e.preventDefault();
                    fetchAndUpdateData();
                    updatePortfolioDisplay();
                    return false;
                });

                fetchAndUpdateData();
                updatePortfolioDisplay();

                $(document).on('click', '.sell-portfolio-btn', function() {
                    const $button = $(this);
                    const coinId = $button.data('coin');
                    const symbol = $button.data('symbol');
                    const price = $button.data('price');
                    const amount = $button.data('amount');

                    if (confirm(`Are you sure you want to sell all ${amount} ${symbol}?`)) {
                        sellCoin(coinId, amount, price, symbol);
                    }
                });
            });
        } else {
            setTimeout(checkJQuery, 100);
        }
    }
    checkJQuery();
})();

$(document).on('click', '.buy-button, .crypto-widget-action.buy', function() {
    const $button = $(this);
    const coinId = $button.data('id') || $button.data('coin');
    const symbol = $button.data('symbol');
    const price = $button.data('price');
    let amount;
    if ($button.hasClass('crypto-widget-action')) {
        amount = parseFloat(prompt(`Enter amount of ${symbol} to buy:`));
    } else {
        const $inputField = $button.closest('.input-group').find('.buy-amount');
        amount = parseFloat($inputField.val());
    }
    if (!amount || isNaN(amount) || amount <= 0) {
        showToast('Please enter a valid amount to buy', 'warning');
        return;
    }
    const totalCost = (amount * price).toFixed(2);
    if (confirm(`Confirm purchase of ${amount} ${symbol} at $${price} per coin?`)) {
        $button.prop('disabled', true).html(`<i class="fas fa-spinner fa-spin"></i> Buying ${symbol}...`);
        $.ajax({
            url: '/NS/api/execute-trade.php',
            method: 'POST',
            data: { action: 'buy', coin_id: coinId.toString(), symbol: symbol, amount: amount, price: price },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast(`Successfully purchased ${amount} ${symbol}!`, 'success');
                    updatePortfolioDisplay();
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
                } catch (e) {}
                showToast(errorMessage, 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                if ($button.hasClass('crypto-widget-action')) {
                    $button.html('Buy');
                } else {
                    $button.html('Buy');
                }
            }
        });
    }
});

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
    {
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $.ajax({
            url: '/NS/api/execute-trade.php',
            method: 'POST',
            data: { action: 'sell', coin_id: coinId.toString(), symbol: symbol, amount: amount, price: price },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast(`Successfully sold ${amount} ${symbol}!`, 'success');
                    updatePortfolioDisplay();
                    $inputField.val('');
                } else {
                    showToast(response.message || 'Trade failed', 'error');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Trade failed: ' + error;
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {}
                showToast(errorMessage, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).html('Sell');
            }
        });
    }
});

function processCoinData(data) {
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
                window.formatStatus(coin.is_trending, coin.volume_spike),
                window.formatSource(coin.source || coin.data_source || '', coin.exchange_name),
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
                    <td>${window.formatStatus(coin.is_trending, coin.volume_spike)}</td>
                    <td>${window.formatSource(coin.source || coin.data_source || '', coin.exchange_name)}</td>
                    <td>${window.formatTradeButtons(coin.id, coin.symbol, coin.current_price || coin.price || 0, canSell, userBalance)}</td>
                </tr>
            `;
            $('#coins-table tbody').append(rowHtml);
        }
    });
}
