/**
 * Exchange Configuration JavaScript
 * Handles CCXT integration and exchange management
 */

$(document).ready(function() {
    // Load exchange data when editing
    $(document).on('click', '.edit-exchange', function() {
        const exchangeId = $(this).data('exchange-id');
        
        // Show loading indicator
        $(this).html('<i class="fas fa-spinner fa-spin"></i>');
        
        // Get exchange data
        $.ajax({
            url: `${BASE_URL}/api/get-exchanges.php`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.exchanges[exchangeId]) {
                    const exchange = response.exchanges[exchangeId];
                    
                    // Populate modal fields
                    $('#exchangeSelect').val(exchangeId);
                    $('#exchangeSelect').prop('disabled', true); // Disable changing exchange type when editing
                    $('#apiKey').val(exchange.credentials.api_key || '');
                    $('#apiSecret').val(exchange.credentials.api_secret || '');
                    $('#testMode').prop('checked', exchange.credentials.test_mode || false);
                    
                    if (exchange.credentials.additional_params) {
                        let additionalParams = exchange.credentials.additional_params;
                        if (typeof additionalParams === 'object') {
                            additionalParams = JSON.stringify(additionalParams, null, 2);
                        }
                        $('#additionalParams').val(additionalParams);
                    } else {
                        $('#additionalParams').val('');
                    }
                    
                    // Change button text to indicate editing
                    $('#saveExchange').text('Update Exchange');
                    $('#saveExchange').data('edit-mode', true);
                    $('#saveExchange').data('exchange-id', exchangeId);
                    
                    // Show modal
                    $('#addExchangeModalLabel').text('Edit Exchange');
                    $('#addExchangeModal').modal('show');
                } else {
                    showAlert('Error loading exchange data', 'danger');
                }
            },
            error: function(xhr, status, error) {
                showAlert('Error connecting to server', 'danger');
                console.error('AJAX error:', error);
            },
            complete: function() {
                // Reset button
                $('.edit-exchange[data-exchange-id="' + exchangeId + '"]').html('<i class="fas fa-edit"></i>');
            }
        });
    });
    
    // Handle delete exchange button
    $(document).on('click', '.delete-exchange', function() {
        const exchangeId = $(this).data('exchange-id');
        const exchangeName = $(this).closest('.form-check').find('.form-check-label').text().trim();
        
        if (confirm(`Are you sure you want to delete the ${exchangeName} exchange? This cannot be undone.`)) {
            // Show loading indicator
            $(this).html('<i class="fas fa-spinner fa-spin"></i>');
            
            // Send delete request
            $.ajax({
                url: `${BASE_URL}/api/delete-exchange.php`,
                method: 'POST',
                data: JSON.stringify({ exchange_id: exchangeId }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Exchange deleted successfully', 'success');
                        
                        // Remove exchange from the list
                        $(`.form-check:has(#${exchangeId}Exchange)`).fadeOut(300, function() {
                            $(this).remove();
                            
                            // Remove from default exchange dropdown
                            $(`#defaultExchange option[value="${exchangeId}"]`).remove();
                            
                            // If no exchanges left, show message
                            if ($('.active-exchanges .form-check').length === 0) {
                                $('.active-exchanges').html('<div class="alert alert-warning">No exchanges configured yet. Add one below.</div>');
                            }
                        });
                    } else {
                        showAlert('Error: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    showAlert('Error connecting to server', 'danger');
                    console.error('AJAX error:', error);
                },
                complete: function() {
                    // Reset button
                    $('.delete-exchange[data-exchange-id="' + exchangeId + '"]').html('<i class="fas fa-trash"></i>');
                }
            });
        }
    });
    
    // Reset modal when closed
    $('#addExchangeModal').on('hidden.bs.modal', function() {
        $('#exchangeSelect').val('');
        $('#exchangeSelect').prop('disabled', false);
        $('#apiKey').val('');
        $('#apiSecret').val('');
        $('#additionalParams').val('');
        $('#testMode').prop('checked', false);
        $('#saveExchange').text('Add Exchange');
        $('#saveExchange').data('edit-mode', false);
        $('#saveExchange').removeData('exchange-id');
        $('#addExchangeModalLabel').text('Add New Exchange');
    });
    
    // Handle Add/Update Exchange button click
    $('#saveExchange').on('click', function() {
        const exchangeId = $('#exchangeSelect').val();
        const apiKey = $('#apiKey').val();
        const apiSecret = $('#apiSecret').val();
        const additionalParams = $('#additionalParams').val();
        const testMode = $('#testMode').is(':checked');
        const isEditMode = $(this).data('edit-mode');
        
        if (!exchangeId) {
            showAlert('Please select an exchange', 'danger');
            return;
        }
        
        if (!apiKey || !apiSecret) {
            showAlert('API Key and Secret are required', 'danger');
            return;
        }
        
        // Show loading indicator
        const originalButtonText = $(this).html();
        $(this).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        $(this).prop('disabled', true);
        
        // Prepare data for submission
        const exchangeData = {
            exchange_id: exchangeId,
            api_key: apiKey,
            api_secret: apiSecret,
            test_mode: testMode,
            additional_params: additionalParams
        };
        
        // Determine which endpoint to use based on edit mode
        const endpoint = isEditMode ? 'edit-exchange.php' : 'add-exchange.php';
        
        // Send data to server
        $.ajax({
            url: `${BASE_URL}/api/${endpoint}`,
            method: 'POST',
            data: JSON.stringify(exchangeData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const actionText = isEditMode ? 'updated' : 'added';
                    showAlert(`Exchange ${actionText} successfully!`, 'success');
                    $('#addExchangeModal').modal('hide');
                    
                    // Reload the page to show updated exchanges
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showAlert('Error: ' + response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                showAlert('Error connecting to server', 'danger');
                console.error('AJAX error:', error);
            },
            complete: function() {
                // Reset button
                $('#saveExchange').html(originalButtonText);
                $('#saveExchange').prop('disabled', false);
            }
        });
    });
    
    // Test exchange connection
    $('#testExchange').on('click', function() {
        const exchangeId = $('#exchangeSelect').val();
        const apiKey = $('#apiKey').val();
        const apiSecret = $('#apiSecret').val();
        const additionalParams = $('#additionalParams').val();
        const testMode = $('#testMode').is(':checked');
        
        if (!exchangeId) {
            showAlert('Please select an exchange', 'danger');
            return;
        }
        
        if (!apiKey || !apiSecret) {
            showAlert('API Key and Secret are required', 'danger');
            return;
        }
        
        // Show loading indicator
        const originalButtonText = $(this).html();
        $(this).html('<i class="fas fa-spinner fa-spin"></i> Testing...');
        $(this).prop('disabled', true);
        
        // Prepare data for submission
        const exchangeData = {
            exchange_id: exchangeId,
            api_key: apiKey,
            api_secret: apiSecret,
            test_mode: testMode,
            additional_params: additionalParams
        };
        
        // Send test request
        $.ajax({
            url: `${BASE_URL}/api/test-exchange.php`,
            method: 'POST',
            data: JSON.stringify(exchangeData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(`Connection successful! Found ${response.markets_count || 'multiple'} markets.`, 'success');
                } else {
                    showAlert('Connection failed: ' + response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                showAlert('Error connecting to server', 'danger');
                console.error('AJAX error:', error);
            },
            complete: function() {
                // Reset button
                $('#testExchange').html(originalButtonText);
                $('#testExchange').prop('disabled', false);
            }
        });
    });
    
    // Helper function to add exchange to the list
    function addExchangeToList(exchangeId, exchangeName) {
        const exchangeHtml = `
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="${exchangeId}Exchange" name="exchanges[${exchangeId}]" checked>
                <label class="form-check-label" for="${exchangeId}Exchange">
                    <img src="assets/images/exchanges/${exchangeId}.png" alt="${exchangeName}" width="20" class="me-2" onerror="this.src='assets/images/exchanges/generic.png';">
                    ${exchangeName}
                </label>
                <button type="button" class="btn btn-sm btn-outline-primary ms-2 edit-exchange" data-exchange-id="${exchangeId}">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger ms-1 delete-exchange" data-exchange-id="${exchangeId}">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        
        // Add to active exchanges list
        $('.active-exchanges').append(exchangeHtml);
        
        // Add to default exchange dropdown if not already there
        if ($(`#defaultExchange option[value="${exchangeId}"]`).length === 0) {
            $('#defaultExchange').append(`<option value="${exchangeId}">${exchangeName}</option>`);
        }
    }
    
    // Function to show alerts
    function showAlert(message, type) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        // Show alert at the top of the page
        $('#alerts-container').html(alertHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    }
    
    // Test connection button
    $('#testConnection').on('click', function() {
        const exchangeId = $('#exchangeSelect').val();
        const apiKey = $('#apiKey').val();
        const apiSecret = $('#apiSecret').val();
        const additionalParams = $('#additionalParams').val();
        const testMode = $('#testMode').is(':checked');
        
        if (!exchangeId || !apiKey || !apiSecret) {
            showAlert('Please fill in all required fields', 'danger');
            return;
        }
        
        // Show loading indicator
        $(this).html('<i class="fas fa-spinner fa-spin"></i> Testing...');
        $(this).prop('disabled', true);
        
        // Prepare data for test
        const testData = {
            exchange_id: exchangeId,
            api_key: apiKey,
            api_secret: apiSecret,
            test_mode: testMode,
            additional_params: additionalParams
        };
        
        // Send test request
        $.ajax({
            url: `${BASE_URL}/api/test-exchange.php`,
            method: 'POST',
            data: JSON.stringify(testData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('Connection successful!', 'success');
                } else {
                    showAlert('Connection failed: ' + response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                showAlert('Error testing connection', 'danger');
                console.error('AJAX error:', error);
            },
            complete: function() {
                // Reset button
                $('#testConnection').html('Test Connection');
                $('#testConnection').prop('disabled', false);
            }
        });
    });
});
