/**
 * Night Stalker Trading - CoinMarketCap Utilities
 * JavaScript functions for fetching and processing CMC data
 */

// Global cache for CMC data to reduce API calls
let cmcDataCache = null;
let cmcLastFetch = 0;
const CMC_CACHE_TTL = 5 * 60 * 1000; // 5 minutes cache TTL

/**
 * Fetch gainers and losers data from CoinMarketCap
 * @param {boolean} forceRefresh - Force refresh cache
 * @returns {Promise} - Promise resolving to gainers/losers data
 */
function getCMCGainersLosers(forceRefresh = false) {
    return new Promise((resolve, reject) => {
        // Check if we have cached data that's still valid
        const now = Date.now();
        if (!forceRefresh && cmcDataCache && (now - cmcLastFetch < CMC_CACHE_TTL)) {
            console.log('Using cached CMC data');
            resolve(cmcDataCache);
            return;
        }
        
        // Show loading indicator if needed
        const $loading = $('#loading');
        if ($loading.length) {
            $loading.show();
        }
        
        // Fetch fresh data from API
        $.ajax({
            url: BASE_URL + '/api/trading/cmc_data.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    // Update cache
                    cmcDataCache = response.data;
                    cmcLastFetch = now;
                    
                    // Hide loading indicator
                    if ($loading.length) {
                        $loading.hide();
                    }
                    
                    resolve(response.data);
                } else {
                    console.error('Error fetching CMC data:', response.message || 'Unknown error');
                    reject(new Error(response.message || 'Failed to fetch CMC data'));
                }
            },
            error: function(xhr, status, error) {
                console.error('CMC API request failed:', status, error);
                
                // Hide loading indicator
                if ($loading.length) {
                    $loading.hide();
                }
                
                // Try to parse error response
                let errorMessage = 'Network error while fetching CMC data';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    // Use default error message
                }
                
                reject(new Error(errorMessage));
            }
        });
    });
}

/**
 * Get top gainers from CMC data
 * @param {number} limit - Maximum number of gainers to return
 * @returns {Promise} - Promise resolving to top gainers
 */
function getTopGainers(limit = 5) {
    return getCMCGainersLosers()
        .then(data => {
            if (data && data.gainers) {
                return data.gainers.slice(0, limit);
            }
            return [];
        });
}

/**
 * Get top losers from CMC data
 * @param {number} limit - Maximum number of losers to return
 * @returns {Promise} - Promise resolving to top losers
 */
function getTopLosers(limit = 5) {
    return getCMCGainersLosers()
        .then(data => {
            if (data && data.losers) {
                return data.losers.slice(0, limit);
            }
            return [];
        });
}

/**
 * Update UI with CMC gainers and losers
 * @param {string} gainersSelector - CSS selector for gainers container
 * @param {string} losersSelector - CSS selector for losers container
 */
function updateCMCUI(gainersSelector = '#cmc-gainers', losersSelector = '#cmc-losers') {
    getCMCGainersLosers()
        .then(data => {
            // Update gainers
            const $gainers = $(gainersSelector);
            if ($gainers.length && data.gainers && data.gainers.length) {
                $gainers.empty();
                data.gainers.slice(0, 5).forEach(coin => {
                    const change = coin.quote.USD.percent_change_24h.toFixed(2);
                    $gainers.append(`
                        <div class="cmc-coin">
                            <span class="coin-symbol">${coin.symbol}</span>
                            <span class="coin-change positive">+${change}%</span>
                        </div>
                    `);
                });
            }
            
            // Update losers
            const $losers = $(losersSelector);
            if ($losers.length && data.losers && data.losers.length) {
                $losers.empty();
                data.losers.slice(0, 5).forEach(coin => {
                    const change = coin.quote.USD.percent_change_24h.toFixed(2);
                    $losers.append(`
                        <div class="cmc-coin">
                            <span class="coin-symbol">${coin.symbol}</span>
                            <span class="coin-change negative">${change}%</span>
                        </div>
                    `);
                });
            }
        })
        .catch(error => {
            console.error('Failed to update CMC UI:', error);
        });
}
