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
                    $('#apiUrl').val(exchange.credentials.api_url || ''); // Corrected: only one instance
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
                $('.edit-exchange[data-exchange-id="' + exchangeId + '"]').html('<i class="bi bi-pencil"></i>');
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
        $('#apiUrl').val(''); // Corrected: only one instance
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
        const apiUrl = $('#apiUrl').val(); // Corrected: read apiUrl
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
            api_url: apiUrl, // Corrected: include apiUrl
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
                        // location.reload();
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