/**
 * Settings JavaScript
 * 
 * Handles settings page functionality, including the masterFetchToggle
 * checkbox that controls coin fetching and price monitoring.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize masterFetchToggle checkbox handler
    initMasterFetchToggle();
});

/**
 * Initialize the master fetch toggle checkbox
 */
function initMasterFetchToggle() {
    const masterFetchToggle = document.getElementById('masterFetchToggle');
    
    if (!masterFetchToggle) {
        console.error('masterFetchToggle element not found');
        return;
    }
    
    // Add event listener for checkbox changes
    masterFetchToggle.addEventListener('change', function() {
        const isChecked = this.checked;
        
        // Show loading indicator
        showAlert('info', 'Updating setting...', 'settings-alert');
        
        // Update setting via API
        updateSetting('masterFetchToggle', isChecked)
            .then(response => {
                if (response.success) {
                    // Show success message with explanation of what this means
                    const message = isChecked ? 
                        'Coin fetching enabled. Price monitoring disabled.' : 
                        'Coin fetching disabled. Price monitoring enabled - prices of portfolio coins will be monitored every 3 seconds.';
                    
                    showAlert('success', message, 'settings-alert');
                } else {
                    // Show error and revert checkbox
                    showAlert('danger', 'Failed to update setting: ' + response.message, 'settings-alert');
                    masterFetchToggle.checked = !isChecked;
                }
            })
            .catch(error => {
                console.error('Error updating setting:', error);
                showAlert('danger', 'Error updating setting: ' + error.message, 'settings-alert');
                masterFetchToggle.checked = !isChecked;
            });
    });
    
    // Load initial setting value from the database
    loadSetting('masterFetchToggle')
        .then(value => {
            if (value !== null) {
                masterFetchToggle.checked = value === '1' || value === true;
            }
        })
        .catch(error => {
            console.error('Error loading setting:', error);
        });
}

/**
 * Update a setting via the API
 * 
 * @param {string} setting Setting name
 * @param {any} value Setting value
 * @returns {Promise} Promise that resolves with the API response
 */
function updateSetting(setting, value) {
    return fetch('/NS/api/update-settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            setting: setting,
            value: value
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    });
}

/**
 * Load a setting value from the API
 * 
 * @param {string} setting Setting name
 * @returns {Promise} Promise that resolves with the setting value
 */
function loadSetting(setting) {
    return fetch(`/NS/api/get-settings.php?setting=${setting}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.data && data.data.value !== undefined) {
                return data.data.value;
            }
            return null;
        });
}

/**
 * Show an alert message
 * 
 * @param {string} type Alert type (success, danger, warning, info)
 * @param {string} message Alert message
 * @param {string} id ID to use for the alert element
 */
function showAlert(type, message, id = 'alert') {
    // Find or create alert container
    let alertContainer = document.getElementById('alerts-container');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.id = 'alerts-container';
        document.querySelector('.container-fluid').prepend(alertContainer);
    }
    
    // Create alert element
    const alertElement = document.createElement('div');
    alertElement.id = id;
    alertElement.className = `alert alert-${type} alert-dismissible fade show`;
    alertElement.role = 'alert';
    
    // Add message
    alertElement.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Remove existing alert with same ID
    const existingAlert = document.getElementById(id);
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // Add alert to container
    alertContainer.appendChild(alertElement);
    
    // Auto-dismiss after 5 seconds for success messages
    if (type === 'success') {
        setTimeout(() => {
            const alert = document.getElementById(id);
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    }
}
