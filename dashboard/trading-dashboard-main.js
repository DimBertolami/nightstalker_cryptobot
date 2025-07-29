safeJQuery(function($) {
$(document).ready(function() {
        // Initialize Select2 on all select2 elements
        function initializeSelect2() {
            try {
                // Check if jQuery is loaded
                if (typeof $ === 'undefined' || typeof $.fn === 'undefined') {
                    console.error('jQuery not loaded, cannot initialize Select2');
                    return;
                }
                
                // Check if Select2 is loaded
                if (typeof $.fn.select2 === 'function') {
                    // Safely initialize Select2
                    $('.select2').each(function() {
                        try {
                            // Check if this element already has Select2 initialized
                            if (!$(this).data('select2')) {
                                $(this).select2({
                                    theme: 'bootstrap-5',
                                    width: '100%',
                                    dropdownParent: $(this).closest('.modal').length ? $(this).closest('.modal') : $('body')
                                });
                            }
                        } catch (elementError) {
                            console.warn('Error initializing Select2 on element:', $(this).attr('id') || 'unknown', elementError);
                        }
                    });
                    console.log('Select2 initialized successfully');
                } else {
                    console.warn('Select2 library not loaded properly. Attempting to load dynamically...');
                    
                    // Try to load Select2 dynamically if not available
                    const select2Css = document.createElement('link');
                    select2Css.rel = 'stylesheet';
                    select2Css.href = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
                    document.head.appendChild(select2Css);
                    
                    const select2ThemeCss = document.createElement('link');
                    select2ThemeCss.rel = 'stylesheet';
                    select2ThemeCss.href = 'https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css';
                    document.head.appendChild(select2ThemeCss);
                    
                    const select2Script = document.createElement('script');
                    select2Script.src = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
                    select2Script.onload = function() {
                        console.log('Select2 loaded dynamically, initializing...');
                        setTimeout(initializeSelect2, 500); // Try again after a delay
                    };
                    document.body.appendChild(select2Script);
                }
            } catch (e) {
                console.error('Error initializing Select2:', e);
            }
        }
        
        // Initialize on page load with a slight delay to ensure DOM is ready
        setTimeout(initializeSelect2, 300);
        
        // Re-initialize Select2 after tab changes
        $('.nav-tabs a').on('shown.bs.tab', function() {
            try {
                // First destroy any existing instances to prevent duplicates
                $('.select2').each(function() {
                    try {
                        if ($(this).data('select2')) {
                            $(this).select2('destroy');
                        }
                    } catch (e) {
                        // Ignore errors during destroy
                    }
                });
                
                // Then re-initialize
                initializeSelect2();
            } catch (e) {
                console.error('Error re-initializing Select2 after tab change:', e);
            }
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize toast
        var toastEl = document.getElementById('toast');
        var toast = new bootstrap.Toast(toastEl, { 
            autohide: true, 
            delay: 5000 
        });
        
        // Handle Select2 in modals
        $(document).on('select2:open', () => {
            document.querySelector('.select2-search__field').focus();
        });
        
        // Show toast message
        function showToast(message, type = 'success') {
            var toast = bootstrap.Toast.getOrCreateInstance(toastEl);
            var $toast = $(toastEl);
            $toast.removeClass('bg-success bg-danger bg-warning bg-info');
            $toast.addClass('bg-' + type);
            $toast.find('.toast-body').text(message);
            toast.show();
        }

        

        // Global variables for request handling
        let balanceRetryCount = 0;
        const MAX_RETRIES = 3;
        const RETRY_DELAY = 3000; // 3 seconds
        let balanceTimeout = null;
        let ordersTimeout = null;
        let isPageVisible = true;

        // Handle page visibility changes
        document.addEventListener('visibilitychange', function() {
            isPageVisible = !document.hidden;
            if (isPageVisible) {
                // Page became visible, refresh data
                loadBalances();
                loadOpenOrders();
            } else {
                // Page is hidden, clear timeouts
                clearTimeout(balanceTimeout);
                clearTimeout(ordersTimeout);
            }
        });

        // Load wallet balances with retry logic
        function loadBalances() {
            if (!isPageVisible) return;
            
            var exchange = $('#exchange-select').val();
            $('#loading-balances').show();
            
            // Clear any existing timeouts
            clearTimeout(balanceTimeout);
            
            $.ajax({
                url: '/NS/api/trading/balance.php',
                method: 'GET',
                data: { exchange: exchange },
                dataType: 'json',
                timeout: 10000, // 10 second timeout
                success: function(response) {
                    balanceRetryCount = 0; // Reset retry counter on success
                    
                    if (response.success && response.balances) {
                        var html = '';
                        var hasBalances = false;
                        
                        // Sort by balance value (highest first)
                        var sortedBalances = Object.entries(response.balances)
                            .sort((a, b) => parseFloat(b[1].total) - parseFloat(a[1].total));
                        
                        sortedBalances.forEach(([currency, balance]) => {
                            var total = parseFloat(balance.total || 0);
                            var available = parseFloat(balance.free || 0);
                            var inOrders = parseFloat(balance.used || 0);
                            
                            // Only show currencies with non-zero balance
                            if (total > 0) {
                                hasBalances = true;
                                var percentage = total > 0 ? (inOrders / total * 100).toFixed(1) : 0;
                                
                                html += `
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="fw-bold">${currency}</div>
                                            <div class="text-end">
                                                <div>${total.toFixed(8)}</div>
                                                <small class="text-muted">Available: ${available.toFixed(8)}</small>
                                            </div>
                                        </div>
                                        ${inOrders > 0 ? `
                                        <div class="mt-2">
                                            <div class="d-flex justify-content-between small text-muted mb-1">
                                                <span>In Orders: ${inOrders.toFixed(8)} (${percentage}%)</span>
                                            </div>
                                            <div class="progress" style="height: 4px;">
                                                <div class="progress-bar bg-warning" role="progressbar" 
                                                    style="width: ${percentage}%" 
                                                    aria-valuenow="${percentage}" 
                                                    aria-valuemin="0" 
                                                    aria-valuemax="100">
                                                </div>
                                            </div>
                                        </div>` : ''}
                                    </div>
                                `;
                            }
                        });
                        
                        if (!hasBalances) {
                            html = '<div class="list-group-item text-center text-muted py-3">No balances found</div>';
                        }
                        
                        $('#balances-container').html(html);
                    } else {
                        showToast(response.error || 'Failed to load balances', 'danger');
                    }
                    
                    // Schedule next refresh (30 seconds)
                    balanceTimeout = setTimeout(loadBalances, 30000);
                },
                error: function(xhr, status, error) {
                    balanceRetryCount++;
                    
                    if (balanceRetryCount <= MAX_RETRIES) {
                        // Exponential backoff: 3s, 6s, 12s
                        const delay = RETRY_DELAY * Math.pow(2, balanceRetryCount - 1);
                        showToast(`Error loading balances (${balanceRetryCount}/${MAX_RETRIES}). Retrying in ${delay/1000}s...`, 'warning');
                        balanceTimeout = setTimeout(loadBalances, delay);
                    } else {
                        showToast('Failed to load balances after ' + MAX_RETRIES + ' attempts. Please check your connection.', 'danger');
                        $('#loading-balances').hide();
                        // Schedule normal refresh after longer delay
                        balanceTimeout = setTimeout(loadBalances, 60000);
                    }
                },
                complete: function() {
                    // Don't hide if we're retrying
                    if (balanceRetryCount === 0) {
                        $('#loading-balances').hide();
                    }
                }
            });
        }

        // Load open orders with retry logic
        let ordersRetryCount = 0;
        
        function loadOpenOrders() {
            if (!isPageVisible) return;
            
            var exchange = $('#exchange-select').val();
            $('#loading-orders').show();
            
            // Clear any existing timeouts
            clearTimeout(ordersTimeout);
            
            // This would be replaced with an actual API call
            // For now, we'll simulate a response
            $.ajax({
                url: '/NS/api/trading/orders.php', // This endpoint needs to be implemented
                method: 'GET',
                data: { exchange: exchange },
                dataType: 'json',
                timeout: 10000, // 10 second timeout
                success: function(response) {
                    ordersRetryCount = 0; // Reset retry counter on success
                    
                    if (response.success && response.orders) {
                        var html = '';
                        
                        if (response.orders.length > 0) {
                            response.orders.forEach(function(order) {
                                var sideClass = order.side === 'buy' ? 'text-success' : 'text-danger';
                                var filledPercent = order.amount > 0 ? 
                                    (parseFloat(order.filled) / parseFloat(order.amount) * 100).toFixed(2) : 0;
                                
                                html += `
                                    <tr>
                                        <td>${new Date(order.created_at).toLocaleString()}</td>
                                        <td>${order.symbol}</td>
                                        <td><span class="badge bg-secondary">${order.type}</span></td>
                                        <td class="${sideClass}">${order.side.toUpperCase()}</td>
                                        <td>${parseFloat(order.price).toFixed(8)}</td>
                                        <td>${parseFloat(order.amount).toFixed(8)}</td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" 
                                                    style="width: ${filledPercent}%" 
                                                    aria-valuenow="${filledPercent}" 
                                                    aria-valuemin="0" 
                                                    aria-valuemax="100">
                                                    ${filledPercent}%
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-info">${order.status}</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger cancel-order" data-order-id="${order.id}">
                                                Cancel
                                            </button>
                                        </td>
                                    </tr>
                                `;
                            });
                        } else {
                            html = '<tr><td colspan="9" class="text-center text-muted py-3">No open orders</td></tr>';
                        }
                        
                        $('#orders-container').html(html);
                    } else {
                        showToast(response.error || 'Failed to load orders', 'danger');
                    }
                    
                    // Schedule next refresh (30 seconds)
                    ordersTimeout = setTimeout(loadOpenOrders, 30000);
                },
                error: function(xhr, status, error) {
                    ordersRetryCount++;
                    
                    if (ordersRetryCount <= MAX_RETRIES) {
                        // Exponential backoff: 3s, 6s, 12s
                        const delay = RETRY_DELAY * Math.pow(2, ordersRetryCount - 1);
                        showToast(`Error loading orders (${ordersRetryCount}/${MAX_RETRIES}). Retrying in ${delay/1000}s...`, 'warning');
                        ordersTimeout = setTimeout(loadOpenOrders, delay);
                    } else {
                        showToast('Failed to load orders after ' + MAX_RETRIES + ' attempts. Please check your connection.', 'danger');
                        $('#loading-orders').hide();
                        // Schedule normal refresh after longer delay
                        ordersTimeout = setTimeout(loadOpenOrders, 60000);
                    }
                },
                complete: function() {
                    // Don't hide if we're retrying
                    if (ordersRetryCount === 0) {
                        $('#loading-orders').hide();
                    }
                }
            });
        }

        // Submit order form
        function submitOrderForm(form, orderType) {
            var formData = $(form).serializeArray().reduce(function(obj, item) {
                obj[item.name] = item.value;
                return obj;
            }, {});
            
            // Add exchange
            formData.exchange = $('#exchange-select').val();
            
            // Show loading state
            var $submitBtn = $(form).find('button[type="submit"]');
            var originalText = $submitBtn.html();
            $submitBtn.prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Placing Order...'
            );
            
            // Submit order
            $.ajax({
                url: '/NS/api/trading/order.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(formData),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast(`${orderType} order placed successfully!`, 'success');
                        $(form)[0].reset();
                        loadOpenOrders();
                        loadBalances();

                        // If it was a buy order, load the chart for the purchased coin
                        if (orderType === 'Market' || orderType === 'Limit') { // Assuming these are buy orders
                            if (response.coin_symbol && typeof loadChart === 'function') {
                                loadChart(response.coin_symbol);
                            }
                        }
                    } else {
                        showToast(response.error || 'Failed to place order', 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    showToast('Error: ' + (xhr.responseJSON?.error || error), 'danger');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).html(originalText);
                }
            });
            
            return false;
        }

        // Event Listeners
        $('#refresh-balances, #exchange-select').on('change click', function() {
            loadBalances();
        });
        
        $('#refresh-orders').on('click', function() {
            loadOpenOrders();
        });
        
        // Form submissions
        $('#market-order-form').on('submit', function(e) {
            e.preventDefault();
            return submitOrderForm(this, 'Market');
        });
        
        $('#limit-order-form').on('submit', function(e) {
            e.preventDefault();
            return submitOrderForm(this, 'Limit');
        });
        
        $('#stop-order-form').on('submit', function(e) {
            e.preventDefault();
            return submitOrderForm(this, 'Stop');
        });
        
        // Cancel order
        $(document).on('click', '.cancel-order', function() {
            if (!confirm('Are you sure you want to cancel this order?')) {
                return;
            }
            
            var orderId = $(this).data('order-id');
            var $btn = $(this);
            $btn.prop('disabled', true).html('Canceling...');
            
            // This would be an API call to cancel the order
            setTimeout(function() {
                showToast('Order canceled successfully', 'success');
                loadOpenOrders();
                loadBalances();
            }, 1000);
        });
        
        // Load initial data
        loadBalances();
        loadOpenOrders();
        
        // The data will auto-refresh via the setTimeout calls in the success/error handlers of the loadBalances and loadOpenOrders functions.
        // The setInterval calls have been removed to prevent duplicate polling.

        // Integrate CMC data into trading logic
        function getCMCGainersLosers() {
            console.log('getCMCGainersLosers function called but not fully implemented');
            return {
                then: function(callback) {
                    // Return empty arrays for gainers and losers
                    callback({
                        gainers: [],
                        losers: []
                    });
                    return this;
                },
                catch: function(errorCallback) {
                    return this;
                }
            };
        }
        
        // getCMCGainersLosers().then(function(cmcData) {
        //     if (!cmcData || !cmcData.gainers) {
        //         console.warn('No CMC gainers data available');
        //         return;
        //     }
            
        //     var topGainers = cmcData.gainers.slice(0, 5); // Top 5 gainers

        //     topGainers.forEach(function(coin) {
        //         var symbol = coin.symbol;
        //         var change = coin.quote.USD.percent_change_24h;
                
        //         // Example trading rule: if 24h change > 15%
        //         if (change > 15) {
        //             // Your existing trade execution logic here
        //             var tradeAmount = Math.min(maxPositionSize, change/100 * capital);
        //             executeTrade(symbol, 'BUY', tradeAmount);
        //         }
        //     });
        // }).catch(function(error) {
        //     console.error('Failed to get CMC data:', error);
        // });

        // Function to create tutorial images directory and placeholder images
        function createTutorialImages() {
            // First try to create the directory
            $.ajax({
                url: '/NS/api/system/create-tutorial-directory.php',
                type: 'GET',
                dataType: 'json',
                success: function(dirResponse) {
                    console.log('Directory creation response:', dirResponse);
                    
                    if (dirResponse.success) {
                        // Now try to create the images
                        $.ajax({
                            url: '/NS/api/system/create-tutorial-images.php',
                            type: 'GET',
                            dataType: 'json',
                            success: function(response) {
                                console.log('Image creation response:', response);
                                
                                if (response.success) {
                                    console.log('Tutorial images created successfully');
                                    // Force reload images with cache busting
                                    $('.carousel-item img').each(function() {
                                        const src = $(this).attr('src');
                                        $(this).attr('src', src + '?v=' + new Date().getTime());
                                    });
                                    alert('Tutorial images created successfully!');
                                } else {
                                    console.error('Failed to create tutorial images:', response.message);
                                    alert('Failed to create tutorial images: ' + response.message);
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Error creating tutorial images:', error);
                                alert('Error creating tutorial images: ' + error);
                            }
                        });
                    } else {
                        console.error('Failed to create tutorial directory:', dirResponse.message);
                        alert('Failed to create tutorial directory: ' + dirResponse.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error creating tutorial directory:', error);
                    alert('Error creating tutorial directory: ' + error);
                }
            });
        }

        // Check if tutorial images exist, create them if not
        // Add click handler for the button
        $(document).on('click', '#create-tutorial-images', function() {
            createTutorialImages();
        });
        
        // Check if first tutorial image exists
        $.get('/NS/assets/images/tutorial/wallet-connect.png')
            .fail(function() {
                console.log('Tutorial images not found, creating them...');
                createTutorialImages();
            });
    });
});


