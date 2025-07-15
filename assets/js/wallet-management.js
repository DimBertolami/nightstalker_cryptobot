/**
 * Wallet Management JavaScript
 * 
 * Handles wallet connection, disconnection, and management functionality
 * with safety guardrails and user-friendly feedback.
 * 
 * Features:
 * - Wallet connection and disconnection
 * - Balance checking across all connected wallets
 * - Multi-signature wallet support
 * - Transaction safety guardrails
 * - Beginner-friendly warnings and confirmations
 * - Real-time balance updates
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
                            <div class="alert alert-warning m-3">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="bi bi-wallet2 fs-1 me-3"></i>
                                    <div>
                                        <h5>No Wallet Connected</h5>
                                        <p class="mb-0">Connect your wallet to view balances and start trading.</p>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <a href="/NS/link-wallet.php" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-2"></i> Connect Your Wallet
                                    </a>
                                </div>
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
    
    // ===== Multi-Signature Wallet Support =====
    
    // Handle create multi-signature wallet button
    $('#create-multisig-wallet').on('click', function() {
        $('#createMultisigModal').modal('show');
    });
    
    // Handle add signer button
    $('#add-signer').on('click', function() {
        const signerCount = $('.signer-input').length + 1;
        const newSignerHtml = `
            <div class="mb-3 signer-input">
                <label class="form-label">Signer ${signerCount} Address</label>
                <div class="input-group">
                    <input type="text" class="form-control" name="signer[]" placeholder="Enter wallet address" required>
                    <button type="button" class="btn btn-outline-danger remove-signer">
                        <i class="bi bi-dash-circle"></i>
                    </button>
                </div>
            </div>
        `;
        $('#signers-container').append(newSignerHtml);
        
        // Update required signatures max value
        $('#required-signatures').attr('max', signerCount);
    });
    
    // Handle remove signer button (delegated event)
    $('#signers-container').on('click', '.remove-signer', function() {
        $(this).closest('.signer-input').remove();
        
        // Renumber the remaining signers
        $('.signer-input').each(function(index) {
            $(this).find('label').text(`Signer ${index + 1} Address`);
        });
        
        // Update required signatures max value
        const signerCount = $('.signer-input').length;
        $('#required-signatures').attr('max', signerCount);
        
        // Ensure required signatures is not more than available signers
        const currentRequired = parseInt($('#required-signatures').val());
        if (currentRequired > signerCount) {
            $('#required-signatures').val(signerCount);
        }
    });
    
    // Handle create multi-signature wallet form submission
    $('#multisig-wallet-form').on('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        $('#create-multisig-submit').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating...');
        
        // Get form data
        const walletName = $('#wallet-name').val();
        const requiredSignatures = parseInt($('#required-signatures').val());
        const signers = [];
        
        $('.signer-input input').each(function() {
            signers.push($(this).val());
        });
        
        // Send create request to API
        $.ajax({
            url: '/NS/api/security/multi-signature.php',
            type: 'POST',
            data: JSON.stringify({
                action: 'create',
                wallet_name: walletName,
                required_signatures: requiredSignatures,
                signers: signers
            }),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showToast('Multi-signature wallet created successfully', 'success');
                    
                    // Close the modal
                    $('#createMultisigModal').modal('hide');
                    
                    // Add the new wallet to the list
                    addMultisigWalletToUI(response);
                } else {
                    // Show error message
                    showToast('Error: ' + response.message, 'danger');
                }
                
                // Reset button state
                $('#create-multisig-submit').prop('disabled', false).html('Create Wallet');
            },
            error: function(xhr, status, error) {
                // Show error message
                showToast('Error creating wallet: ' + error, 'danger');
                
                // Reset button state
                $('#create-multisig-submit').prop('disabled', false).html('Create Wallet');
            }
        });
    });
    
    // Function to add a multi-signature wallet to the UI
    function addMultisigWalletToUI(walletData) {
        const walletHtml = `
            <div class="card mb-3 multisig-wallet" data-wallet-id="${walletData.wallet_id}">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-shield-lock me-2"></i>
                        ${walletData.wallet_name}
                    </div>
                    <span class="badge bg-light text-dark">${walletData.required_signatures} of ${walletData.signers.length} signatures</span>
                </div>
                <div class="card-body">
                    <p class="card-text"><strong>Wallet ID:</strong> ${walletData.wallet_id}</p>
                    <p class="card-text"><strong>Signers:</strong></p>
                    <ul class="list-group mb-3">
                        ${walletData.signers.map(signer => `<li class="list-group-item">${signer}</li>`).join('')}
                    </ul>
                    <div class="d-flex justify-content-between">
                        <button class="btn btn-outline-primary propose-transaction" data-wallet-id="${walletData.wallet_id}">
                            <i class="bi bi-send me-2"></i>Propose Transaction
                        </button>
                        <button class="btn btn-outline-danger disconnect-multisig-btn" data-wallet-id="${walletData.wallet_id}">
                            <i class="bi bi-x-circle me-2"></i>Disconnect Wallet
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Add to multi-signature wallets container
        $('#multisig-wallets-container').append(walletHtml);
    }
    
    // Handle propose transaction button
    $(document).on('click', '.propose-transaction', function() {
        const walletId = $(this).data('wallet-id');
        $('#transaction-wallet-id').val(walletId);
        $('#proposeTransactionModal').modal('show');
    });
    
    // Handle propose transaction form submission
    $('#propose-transaction-form').on('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        $('#propose-transaction-submit').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Proposing...');
        
        // Get form data
        const walletId = $('#transaction-wallet-id').val();
        const amount = parseFloat($('#transaction-amount').val());
        const recipient = $('#transaction-recipient').val();
        
        // Check if amount exceeds safety threshold for beginners
        const isBeginner = $('#user-experience-level').val() === 'beginner';
        const safetyThreshold = 100; // $100 for beginners
        
        if (isBeginner && amount > safetyThreshold) {
            // Show safety warning
            $('#safety-warning-amount').text(amount);
            $('#safety-threshold').text(safetyThreshold);
            $('#safetyWarningModal').modal('show');
            
            // Reset button state
            $('#propose-transaction-submit').prop('disabled', false).html('Propose Transaction');
            return;
        }
        
        // Proceed with transaction proposal
        proposeTransaction(walletId, amount, recipient);
    });
    
    // Handle safety warning confirmation
    $('#confirm-unsafe-transaction').on('click', function() {
        // Get form data again
        const walletId = $('#transaction-wallet-id').val();
        const amount = parseFloat($('#transaction-amount').val());
        const recipient = $('#transaction-recipient').val();
        
        // Close safety warning modal
        $('#safetyWarningModal').modal('hide');
        
        // Proceed with transaction proposal
        proposeTransaction(walletId, amount, recipient);
    });
    
    // Function to propose a transaction
    function proposeTransaction(walletId, amount, recipient) {
        // Show loading state
        $('#propose-transaction-submit').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Proposing...');
        
        // Send propose transaction request to API
        $.ajax({
            url: '/NS/api/security/multi-signature.php',
            type: 'POST',
            data: JSON.stringify({
                action: 'propose_transaction',
                wallet_id: walletId,
                amount: amount,
                recipient: recipient
            }),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showToast('Transaction proposed successfully', 'success');
                    
                    // Close the modal
                    $('#proposeTransactionModal').modal('hide');
                    
                    // Add the transaction to pending transactions
                    addPendingTransactionToUI(response);
                } else {
                    // Show error message
                    showToast('Error: ' + response.message, 'danger');
                }
                
                // Reset button state
                $('#propose-transaction-submit').prop('disabled', false).html('Propose Transaction');
            },
            error: function(xhr, status, error) {
                // Show error message
                showToast('Error proposing transaction: ' + error, 'danger');
                
                // Reset button state
                $('#propose-transaction-submit').prop('disabled', false).html('Propose Transaction');
            }
        });
    }
    
    // Function to add a pending transaction to the UI
    function addPendingTransactionToUI(transactionData) {
        const transactionHtml = `
            <div class="card mb-3 pending-transaction" data-transaction-id="${transactionData.transaction_id}">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-clock-history me-2"></i>
                        Pending Transaction
                    </div>
                    <span class="badge bg-light text-dark">${transactionData.signatures} of ${transactionData.required_signatures} signatures</span>
                </div>
                <div class="card-body">
                    <p class="card-text"><strong>Transaction ID:</strong> ${transactionData.transaction_id}</p>
                    <p class="card-text"><strong>Amount:</strong> $${transactionData.amount}</p>
                    <p class="card-text"><strong>Recipient:</strong> ${transactionData.recipient}</p>
                    <p class="card-text"><strong>Status:</strong> <span class="badge bg-warning text-dark">Pending</span></p>
                    <div class="d-flex justify-content-between">
                        <button class="btn btn-outline-success sign-transaction" data-transaction-id="${transactionData.transaction_id}">
                            <i class="bi bi-pen me-2"></i>Sign Transaction
                        </button>
                        <button class="btn btn-outline-danger cancel-transaction" data-transaction-id="${transactionData.transaction_id}">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Add to pending transactions container
        $('#pending-transactions-container').append(transactionHtml);
    }
    
    // Handle sign transaction button
    $(document).on('click', '.sign-transaction', function() {
        const transactionId = $(this).data('transaction-id');
        
        // Show loading state
        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Signing...');
        
        // Send sign transaction request to API
        $.ajax({
            url: '/NS/api/security/multi-signature.php',
            type: 'POST',
            data: JSON.stringify({
                action: 'sign_transaction',
                transaction_id: transactionId,
                signer_id: 'current_user' // In a real implementation, this would be the current user's wallet ID
            }),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showToast('Transaction signed successfully', 'success');
                    
                    // Update the transaction in the UI
                    updateTransactionInUI(response);
                    
                    // If all signatures collected, show execute button
                    if (response.signatures >= response.required_signatures) {
                        const transactionCard = $(`.pending-transaction[data-transaction-id="${response.transaction_id}"]`);
                        transactionCard.find('.card-header').removeClass('bg-warning').addClass('bg-success text-white');
                        transactionCard.find('.card-header div').html('<i class="bi bi-check-circle me-2"></i>Ready to Execute');
                        transactionCard.find('.badge').removeClass('bg-light text-dark').addClass('bg-white text-success');
                        transactionCard.find('.sign-transaction').replaceWith(`
                            <button class="btn btn-success execute-transaction" data-transaction-id="${response.transaction_id}">
                                <i class="bi bi-check-circle me-2"></i>Execute Transaction
                            </button>
                        `);
                    }
                } else {
                    // Show error message
                    showToast('Error: ' + response.message, 'danger');
                }
                
                // Reset button state
                $('.sign-transaction[data-transaction-id="' + transactionId + '"]').prop('disabled', false).html('<i class="bi bi-pen me-2"></i>Sign Transaction');
            },
            error: function(xhr, status, error) {
                // Show error message
                showToast('Error signing transaction: ' + error, 'danger');
                
                // Reset button state
                $('.sign-transaction[data-transaction-id="' + transactionId + '"]').prop('disabled', false).html('<i class="bi bi-pen me-2"></i>Sign Transaction');
            }
        });
    });
    
    // Function to update a transaction in the UI
    function updateTransactionInUI(transactionData) {
        const transactionCard = $(`.pending-transaction[data-transaction-id="${transactionData.transaction_id}"]`);
        transactionCard.find('.badge').text(`${transactionData.signatures} of ${transactionData.required_signatures} signatures`);
    }
    
    // Handle execute transaction button
    $(document).on('click', '.execute-transaction', function() {
        const transactionId = $(this).data('transaction-id');
        
        // Show confirmation modal
        $('#execute-transaction-id').val(transactionId);
        $('#executeTransactionModal').modal('show');
    });
    
    // Handle execute transaction confirmation
    $('#confirm-execute-transaction').on('click', function() {
        // Show loading state
        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Executing...');
        
        // Get transaction ID
        const transactionId = $('#execute-transaction-id').val();
        
        // Send execute transaction request to API
        $.ajax({
            url: '/NS/api/security/multi-signature.php',
            type: 'POST',
            data: JSON.stringify({
                action: 'execute_transaction',
                transaction_id: transactionId
            }),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showToast('Transaction executed successfully', 'success');
                    
                    // Close the modal
                    $('#executeTransactionModal').modal('hide');
                    
                    // Remove the transaction from pending and add to completed
                    const transactionCard = $(`.pending-transaction[data-transaction-id="${transactionId}"]`);
                    transactionCard.fadeOut(500, function() {
                        $(this).remove();
                        
                        // Add to completed transactions
                        const completedHtml = `
                            <div class="card mb-3 completed-transaction">
                                <div class="card-header bg-success text-white">
                                    <i class="bi bi-check-circle me-2"></i>
                                    Completed Transaction
                                </div>
                                <div class="card-body">
                                    <p class="card-text"><strong>Transaction ID:</strong> ${transactionId}</p>
                                    <p class="card-text"><strong>Status:</strong> <span class="badge bg-success">Executed</span></p>
                                    <p class="card-text"><small class="text-muted">Completed on ${new Date().toLocaleString()}</small></p>
                                </div>
                            </div>
                        `;
                        $('#completed-transactions-container').prepend(completedHtml);
                    });
                    
                    // Refresh wallet balances
                    refreshWalletBalances();
                } else {
                    // Show error message
                    showToast('Error: ' + response.message, 'danger');
                }
                
                // Reset button state
                $('#confirm-execute-transaction').prop('disabled', false).html('Confirm Execution');
            },
            error: function(xhr, status, error) {
                // Show error message
                showToast('Error executing transaction: ' + error, 'danger');
                
                // Reset button state
                $('#confirm-execute-transaction').prop('disabled', false).html('Confirm Execution');
            }
        });
    });
    
    // ===== Safety Guardrails =====
    
    // Function to check transaction safety
    function checkTransactionSafety(amount, recipient) {
        // Get user experience level
        const experienceLevel = $('#user-experience-level').val() || 'beginner';
        
        // Define safety thresholds based on experience level
        const safetyThresholds = {
            beginner: 100,    // $100 for beginners
            intermediate: 500,  // $500 for intermediate users
            advanced: 1000     // $1000 for advanced users
        };
        
        const threshold = safetyThresholds[experienceLevel] || safetyThresholds.beginner;
        
        // Check if amount exceeds threshold
        if (amount > threshold) {
            return {
                safe: false,
                message: `This transaction exceeds the recommended limit of $${threshold} for ${experienceLevel} users.`
            };
        }
        
        // Check if recipient is in whitelist (in a real implementation)
        // This is a placeholder for demonstration
        const isWhitelisted = false; // Would check against a database
        
        if (!isWhitelisted) {
            return {
                safe: true,
                warning: 'This recipient is not in your whitelist. Double-check the address before proceeding.'
            };
        }
        
        return { safe: true };
    }
});
