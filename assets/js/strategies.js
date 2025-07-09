/**
 * Strategies Management JavaScript
 * 
 * Handles the strategy configuration and execution for the Night Stalker cryptobot
 * Supports autonomous trading based on selected strategies
 */

(function($) {
    "use strict";
    
    // Global variables
    let strategiesTable;
    let currentStrategy = null;
    
    // Initialize when document is ready
    $(document).ready(function() {
        initStrategiesTable();
        bindEventHandlers();
        loadStrategies();
    });
    
    // Initialize DataTables for strategies
    function initStrategiesTable() {
        strategiesTable = $('#strategies-table').DataTable({
            responsive: true,
            order: [[0, 'asc']],
            columns: [
                { data: 'name' },
                { data: 'description' },
                { data: 'config', render: formatConfig },
                { data: 'is_active', render: formatStatus },
                { data: 'last_run', render: formatLastRun },
                { data: null, render: formatActions, orderable: false }
            ]
        });
    }
    
    // Bind event handlers
    function bindEventHandlers() {
        // Add strategy button
        $('#add-strategy-btn').on('click', function() {
            resetStrategyForm();
            $('#strategy-modal-label').text('Add New Strategy');
            $('#strategy-modal').modal('show');
        });
        
        // Strategy type radio buttons
        $('input[name="strategy-type"]').on('change', function() {
            toggleStrategyConfigSections();
        });
        
        // Save strategy button
        $('#save-strategy-btn').on('click', function() {
            saveStrategy();
        });
        
        // Run strategies button
        $('#run-strategies-btn').on('click', function() {
            runActiveStrategies();
        });
        
        // Edit strategy button (delegated)
        $('#strategies-table').on('click', '.btn-edit', function() {
            const strategyId = $(this).data('id');
            editStrategy(strategyId);
        });
        
        // Toggle active status button (delegated)
        $('#strategies-table').on('click', '.btn-toggle-active', function() {
            const strategyId = $(this).data('id');
            const isActive = $(this).data('active') === 1 ? 0 : 1;
            toggleStrategyActive(strategyId, isActive);
        });
        
        // Run single strategy button (delegated)
        $('#strategies-table').on('click', '.btn-run', function() {
            const strategyName = $(this).data('name');
            runStrategy(strategyName);
        });
        
        // Delete strategy button (delegated)
        $('#strategies-table').on('click', '.btn-delete', function() {
            const strategyId = $(this).data('id');
            if (confirm('Are you sure you want to delete this strategy?')) {
                deleteStrategy(strategyId);
            }
        });
    }
    
    // Toggle strategy configuration sections based on selected type
    function toggleStrategyConfigSections() {
        const selectedType = $('input[name="strategy-type"]:checked').val();
        
        if (selectedType === 'volume_spike') {
            $('#volume-spike-config').show();
            $('#trending-coins-config').hide();
        } else if (selectedType === 'trending_coins') {
            $('#volume-spike-config').hide();
            $('#trending-coins-config').show();
        }
    }
    
    // Reset strategy form
    function resetStrategyForm() {
        $('#strategy-form')[0].reset();
        $('#strategy-id').val('');
        $('#type-volume-spike').prop('checked', true);
        toggleStrategyConfigSections();
    }
    
    // Load strategies from the server
    function loadStrategies() {
        $.ajax({
            url: 'api/get-strategies.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    strategiesTable.clear();
                    if (response.data && response.data.length > 0) {
                        strategiesTable.rows.add(response.data);
                    }
                    strategiesTable.draw();
                } else {
                    showToast('error', 'Error', response.message);
                }
            },
            error: function(xhr, status, error) {
                showToast('error', 'Error', 'Failed to load strategies: ' + error);
            }
        });
    }
    
    // Format strategy configuration for display
    function formatConfig(config) {
        if (!config) return '';
        
        try {
            const configObj = typeof config === 'string' ? JSON.parse(config) : config;
            let html = '<ul class="list-unstyled mb-0">';
            
            for (const key in configObj) {
                if (configObj.hasOwnProperty(key)) {
                    const formattedKey = key.replace(/_/g, ' ');
                    html += `<li><strong>${formattedKey}:</strong> ${configObj[key]}</li>`;
                }
            }
            
            html += '</ul>';
            return html;
        } catch (e) {
            return 'Invalid configuration';
        }
    }
    
    // Format strategy status
    function formatStatus(isActive) {
        return isActive ? 
            '<span class="badge badge-success">Active</span>' : 
            '<span class="badge badge-secondary">Inactive</span>';
    }
    
    // Format last run timestamp
    function formatLastRun(lastRun) {
        if (!lastRun) return 'Never';
        
        const date = new Date(lastRun);
        return date.toLocaleString();
    }
    
    // Format action buttons
    function formatActions(data) {
        const id = data.id;
        const name = data.name;
        const isActive = data.is_active;
        
        return `
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-info btn-edit" data-id="${id}" title="Edit">
                    <i class="fa fa-edit"></i>
                </button>
                <button type="button" class="btn ${isActive ? 'btn-warning' : 'btn-success'} btn-toggle-active" 
                        data-id="${id}" data-active="${isActive}" title="${isActive ? 'Deactivate' : 'Activate'}">
                    <i class="fa ${isActive ? 'fa-pause' : 'fa-play'}"></i>
                </button>
                <button type="button" class="btn btn-primary btn-run" data-name="${name}" title="Run Now">
                    <i class="fa fa-bolt"></i>
                </button>
                <button type="button" class="btn btn-danger btn-delete" data-id="${id}" title="Delete">
                    <i class="fa fa-trash"></i>
                </button>
            </div>
        `;
    }
    
    // Save strategy
    function saveStrategy() {
        // Get form data
        const id = $('#strategy-id').val();
        const name = $('#strategy-name').val();
        const description = $('#strategy-description').val();
        const strategyType = $('input[name="strategy-type"]:checked').val();
        const isActive = $('#strategy-active').is(':checked') ? 1 : 0;
        
        // Build config object based on strategy type
        let config = {};
        
        if (strategyType === 'volume_spike') {
            config = {
                min_volume_increase: parseInt($('#vs-min-volume-increase').val()),
                timeframe: $('#vs-timeframe').val(),
                max_investment: parseInt($('#vs-max-investment').val()),
                stop_loss: parseInt($('#vs-stop-loss').val()),
                take_profit: parseInt($('#vs-take-profit').val())
            };
        } else if (strategyType === 'trending_coins') {
            config = {
                min_market_cap: parseInt($('#tc-min-market-cap').val()),
                max_age_hours: parseInt($('#tc-max-age-hours').val()),
                max_investment: parseInt($('#tc-max-investment').val()),
                stop_loss: parseInt($('#tc-stop-loss').val()),
                take_profit: parseInt($('#tc-take-profit').val())
            };
        }
        
        // Prepare data for API
        const data = {
            id: id,
            name: name,
            description: description,
            type: strategyType,
            config: JSON.stringify(config),
            is_active: isActive
        };
        
        // Send to server
        $.ajax({
            url: 'api/save-strategy.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#strategy-modal').modal('hide');
                    showToast('success', 'Success', response.message);
                    loadStrategies();
                } else {
                    showToast('error', 'Error', response.message);
                }
            },
            error: function(xhr, status, error) {
                showToast('error', 'Error', 'Failed to save strategy: ' + error);
            }
        });
    }
    
    // Edit strategy
    function editStrategy(id) {
        $.ajax({
            url: 'api/get-strategy.php',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const strategy = response.data;
                    
                    // Populate form
                    $('#strategy-id').val(strategy.id);
                    $('#strategy-name').val(strategy.name);
                    $('#strategy-description').val(strategy.description);
                    $('#strategy-active').prop('checked', strategy.is_active === 1);
                    
                    // Set strategy type
                    if (strategy.type === 'volume_spike') {
                        $('#type-volume-spike').prop('checked', true);
                    } else if (strategy.type === 'trending_coins') {
                        $('#type-trending-coins').prop('checked', true);
                    }
                    
                    toggleStrategyConfigSections();
                    
                    // Populate config fields
                    try {
                        const config = typeof strategy.config === 'string' ? 
                            JSON.parse(strategy.config) : strategy.config;
                        
                        if (strategy.type === 'volume_spike') {
                            $('#vs-min-volume-increase').val(config.min_volume_increase || 20);
                            $('#vs-timeframe').val(config.timeframe || '24h');
                            $('#vs-max-investment').val(config.max_investment || 100);
                            $('#vs-stop-loss').val(config.stop_loss || 5);
                            $('#vs-take-profit').val(config.take_profit || 10);
                        } else if (strategy.type === 'trending_coins') {
                            $('#tc-min-market-cap').val(config.min_market_cap || 1000000);
                            $('#tc-max-age-hours').val(config.max_age_hours || 24);
                            $('#tc-max-investment').val(config.max_investment || 50);
                            $('#tc-stop-loss').val(config.stop_loss || 7);
                            $('#tc-take-profit').val(config.take_profit || 15);
                        }
                    } catch (e) {
                        console.error('Error parsing config:', e);
                    }
                    
                    // Show modal
                    $('#strategy-modal-label').text('Edit Strategy');
                    $('#strategy-modal').modal('show');
                } else {
                    showToast('error', 'Error', response.message);
                }
            },
            error: function(xhr, status, error) {
                showToast('error', 'Error', 'Failed to load strategy: ' + error);
            }
        });
    }
    
    // Toggle strategy active status
    function toggleStrategyActive(id, isActive) {
        $.ajax({
            url: 'api/toggle-strategy.php',
            type: 'POST',
            data: { id: id, is_active: isActive },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('success', 'Success', response.message);
                    loadStrategies();
                } else {
                    showToast('error', 'Error', response.message);
                }
            },
            error: function(xhr, status, error) {
                showToast('error', 'Error', 'Failed to update strategy status: ' + error);
            }
        });
    }
    
    // Run a single strategy
    function runStrategy(strategyName) {
        $('#strategy-results').html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i><p class="mt-2">Running strategy...</p></div>');
        $('#results-modal').modal('show');
        
        $.ajax({
            url: 'api/auto-trade.php',
            type: 'POST',
            data: { strategy: strategyName },
            dataType: 'json',
            success: function(response) {
                let html = '';
                
                if (response.success) {
                    html += `<div class="alert alert-success">${response.message}</div>`;
                    
                    if (response.trades && response.trades.length > 0) {
                        html += '<h5>Executed Trades:</h5>';
                        html += '<table class="table table-striped">';
                        html += '<thead><tr><th>Coin</th><th>Action</th><th>Amount</th><th>Price</th><th>Total</th></tr></thead>';
                        html += '<tbody>';
                        
                        response.trades.forEach(function(trade) {
                            html += `<tr>
                                <td>${trade.symbol}</td>
                                <td>${trade.action.toUpperCase()}</td>
                                <td>${trade.amount}</td>
                                <td>$${parseFloat(trade.price).toFixed(2)}</td>
                                <td>$${parseFloat(trade.total).toFixed(2)}</td>
                            </tr>`;
                        });
                        
                        html += '</tbody></table>';
                    } else {
                        html += '<div class="alert alert-info">No trades were executed.</div>';
                    }
                } else {
                    html += `<div class="alert alert-danger">${response.message}</div>`;
                }
                
                $('#strategy-results').html(html);
            },
            error: function(xhr, status, error) {
                $('#strategy-results').html(`<div class="alert alert-danger">Failed to run strategy: ${error}</div>`);
            }
        });
    }
    
    // Run all active strategies
    function runActiveStrategies() {
        $('#strategy-results').html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i><p class="mt-2">Running all active strategies...</p></div>');
        $('#results-modal').modal('show');
        
        $.ajax({
            url: 'api/run-all-strategies.php',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                let html = '';
                
                if (response.success) {
                    html += `<div class="alert alert-success">${response.message}</div>`;
                    
                    if (response.results && response.results.length > 0) {
                        response.results.forEach(function(result) {
                            html += `<h5>Strategy: ${result.strategy}</h5>`;
                            
                            if (result.success) {
                                html += `<div class="alert alert-success">${result.message}</div>`;
                                
                                if (result.trades && result.trades.length > 0) {
                                    html += '<table class="table table-striped mb-4">';
                                    html += '<thead><tr><th>Coin</th><th>Action</th><th>Amount</th><th>Price</th><th>Total</th></tr></thead>';
                                    html += '<tbody>';
                                    
                                    result.trades.forEach(function(trade) {
                                        html += `<tr>
                                            <td>${trade.symbol}</td>
                                            <td>${trade.action.toUpperCase()}</td>
                                            <td>${trade.amount}</td>
                                            <td>$${parseFloat(trade.price).toFixed(2)}</td>
                                            <td>$${parseFloat(trade.total).toFixed(2)}</td>
                                        </tr>`;
                                    });
                                    
                                    html += '</tbody></table>';
                                } else {
                                    html += '<div class="alert alert-info mb-4">No trades were executed for this strategy.</div>';
                                }
                            } else {
                                html += `<div class="alert alert-danger mb-4">${result.message}</div>`;
                            }
                        });
                    } else {
                        html += '<div class="alert alert-info">No active strategies found.</div>';
                    }
                } else {
                    html += `<div class="alert alert-danger">${response.message}</div>`;
                }
                
                $('#strategy-results').html(html);
            },
            error: function(xhr, status, error) {
                $('#strategy-results').html(`<div class="alert alert-danger">Failed to run strategies: ${error}</div>`);
            }
        });
    }
    
    // Delete strategy
    function deleteStrategy(id) {
        $.ajax({
            url: 'api/delete-strategy.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('success', 'Success', response.message);
                    loadStrategies();
                } else {
                    showToast('error', 'Error', response.message);
                }
            },
            error: function(xhr, status, error) {
                showToast('error', 'Error', 'Failed to delete strategy: ' + error);
            }
        });
    }
    
    // Show toast notification
    function showToast(type, title, message) {
        if (typeof $.toast === 'function') {
            $.toast({
                heading: title,
                text: message,
                showHideTransition: 'slide',
                icon: type,
                position: 'top-right',
                hideAfter: 5000
            });
        } else {
            alert(`${title}: ${message}`);
        }
    }
    
})(jQuery);
