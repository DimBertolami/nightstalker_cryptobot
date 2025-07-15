/**
 * Wallet Management JavaScript
 * 
 * Handles wallet connection, disconnection, and management functionality
 * with safety guardrails and user-friendly feedback.
 */

$(document).ready(function() {
    // Handle disconnect wallet button clicks
    $('.disconnect-wallet-btn').on('click', function() {
        const walletId = $(this).data('wallet-id');
        const walletAddress = $(this).data('wallet-address');
        
        // Set values in the disconnect form
        $('#disconnect-wallet-id').val(walletId);
        $('#disconnect-wallet-address').val(walletAddress);
        
        // Show the confirmation modal
        $('#disconnectWalletModal').modal('show');
    });
    
    // Handle wallet disconnection confirmation
    $('#confirm-disconnect-wallet').on('click', function() {
        // Show loading state
        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Disconnecting...');
        
        // Get form data
        const formData = $('#disconnect-wallet-form').serialize();
        
        // Send disconnect request to API
        $.ajax({
            url: '/NS/api/disconnect-wallet.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showToast('Wallet disconnected successfully', 'success');
                    
                    // Close the modal
                    $('#disconnectWalletModal').modal('hide');
                    
                    // Reload the page to update wallet status
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error message
                    showToast('Error: ' + response.message, 'danger');
                    
                    // Reset button state
                    $('#confirm-disconnect-wallet').prop('disabled', false).html('<i class="bi bi-x-circle me-2"></i> Disconnect Wallet');
                }
            },
            error: function(xhr, status, error) {
                // Show error message
                showToast('Error disconnecting wallet: ' + error, 'danger');
                
                // Reset button state
                $('#confirm-disconnect-wallet').prop('disabled', false).html('<i class="bi bi-x-circle me-2"></i> Disconnect Wallet');
            }
        });
    });
    
    // Function to show toast notifications
    function showToast(message, type = 'info') {
        // Create toast element if it doesn't exist
        if ($('#toast-container').length === 0) {
            $('body').append('<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3"></div>');
        }
        
        // Create unique ID for this toast
        const toastId = 'toast-' + Date.now();
        
        // Create toast HTML
        const toast = `
            <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        // Add toast to container
        $('#toast-container').append(toast);
        
        // Initialize and show toast
        const toastElement = new bootstrap.Toast(document.getElementById(toastId), {
            delay: 5000
        });
        toastElement.show();
        
        // Remove toast from DOM after it's hidden
        $(`#${toastId}`).on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
    
    // Function to refresh wallet balances
    function refreshWalletBalances() {
        // Show loading indicator
        $('#balances-container').hide();
        $('#loading-balances').show();
        
        // Get selected exchange
        const exchange = $('#exchange-select').val();
        
        // Fetch balances from API
        $.ajax({
            url: '/NS/api/trading/balance.php',
            type: 'GET',
            data: { exchange: exchange },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Clear existing balances
                    $('#balances-container').empty();
                    
                    // Check if wallet is connected
                    if (response.wallet_connected === false) {
                        // No wallet connected
                        $('#balances-container').html(`
                            <div class="alert alert-info m-3">
                                <i class="bi bi-wallet me-2"></i>
                                No wallet connected. <a href="/NS/link-wallet.php" class="alert-link">Connect a wallet</a> to view your balances.
                            </div>
                        `);
                    }
                    // Check if we have balances and it's not demo data (unless demo data is explicitly requested)
                    else if (response.balances && Object.keys(response.balances).length > 0 && (!response.demo_data || window.location.href.includes('demo=1'))) {
                        // Loop through balances and create balance cards
                        $.each(response.balances, function(currency, balance) {
                            // Skip zero balances
                            if (parseFloat(balance.total) <= 0) return;
                            
                            // Create balance card
                            const balanceCard = `
                                <div class="list-group-item balance-item" data-currency="${currency}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <div class="currency-icon ${currency.toLowerCase()}">
                                                ${currency.substring(0, 1)}
                                            </div>
                                            <div class="ms-3">
                                                <h6 class="mb-0">${currency}</h6>
                                                <small class="text-muted">Available: ${parseFloat(balance.free).toFixed(8)}</small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="balance-amount">${parseFloat(balance.total).toFixed(8)}</div>
                                            <small class="text-muted balance-value">$0.00</small>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            // Add balance card to container
                            $('#balances-container').append(balanceCard);
                        });
                        
                        // Update USD values for balances
                        updateBalanceValues();
                    } else {
                        // No balances found or demo data that shouldn't be shown
                        $('#balances-container').html(`
                            <div class="alert alert-info m-3">
                                <i class="bi bi-info-circle me-2"></i>
                                No balances found for this exchange. Please connect a wallet or deposit funds.
                            </div>
                        `);
                    }
                } else {
                    // Error fetching balances
                    $('#balances-container').html(`
                        <div class="alert alert-danger m-3">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Error fetching balances: ${response.message}
                        </div>
                    `);
                }
                
                // Hide loading indicator and show balances
                $('#loading-balances').hide();
                $('#balances-container').show();
            },
            error: function(xhr, status, error) {
                // Error fetching balances
                $('#balances-container').html(`
                    <div class="alert alert-danger m-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Error fetching balances: ${error}
                    </div>
                `);
                
                // Hide loading indicator and show balances
                $('#loading-balances').hide();
                $('#balances-container').show();
            }
        });
    }
    
    // Function to update balance values in USD
    function updateBalanceValues() {
        // Get all currencies
        const currencies = [];
        $('.balance-item').each(function() {
            currencies.push($(this).data('currency'));
        });
        
        // If no currencies, return
        if (currencies.length === 0) return;
        
        // Fetch prices for currencies
        $.ajax({
            url: '/NS/api/trading/price.php',
            type: 'GET',
            data: { symbols: currencies.join(',') },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.prices) {
                    // Update each balance value
                    $('.balance-item').each(function() {
                        const currency = $(this).data('currency');
                        const amount = parseFloat($(this).find('.balance-amount').text());
                        const price = response.prices[currency] ? parseFloat(response.prices[currency].price) : 0;
                        const value = amount * price;
                        
                        // Update value display
                        $(this).find('.balance-value').text('$' + value.toFixed(2));
                    });
                }
            }
        });
    }
    
    // Handle refresh balances button click
    $('#refresh-balances').on('click', function() {
        refreshWalletBalances();
    });
    
    // Handle exchange select change
    $('#exchange-select').on('change', function() {
        refreshWalletBalances();
    });
    
    // Initial load of balances
    refreshWalletBalances();
});
