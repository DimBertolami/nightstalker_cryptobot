/**
 * Bitvavo Data Auto-Refresh Functionality
 * 
 * This script handles the auto-refresh switch functionality for Bitvavo data.
 * It purges the coins table and fetches fresh data from Bitvavo when enabled.
 */

// Auto-refresh interval in milliseconds (default: 5 minutes)
const AUTO_REFRESH_INTERVAL = 5 * 60 * 1000;
let refreshIntervalId = null;
let isRefreshing = false;

/**
 * Initialize the auto-refresh functionality
 */
function initBitvavoAutoRefresh() {
    // Get the auto-refresh switch element
    const autoRefreshSwitch = document.getElementById('auto-refresh-switch');
    const refreshDataBtn = document.getElementById('refresh-data-btn');
    
    if (!autoRefreshSwitch) {
        console.error('Auto-refresh switch element not found');
        return;
    }
    
    // Check if auto-refresh was previously enabled (using localStorage)
    const autoRefreshEnabled = localStorage.getItem('bitvavo_auto_refresh') === 'true';
    autoRefreshSwitch.checked = autoRefreshEnabled;
    
    // Set up event listener for the switch
    autoRefreshSwitch.addEventListener('change', function() {
        const isEnabled = this.checked;
        
        // Save state to localStorage
        localStorage.setItem('bitvavo_auto_refresh', isEnabled);
        
        if (isEnabled) {
            // Start auto-refresh
            startAutoRefresh();
            showNotification('Auto-refresh enabled. Data will refresh every 5 minutes.');
        } else {
            // Stop auto-refresh
            stopAutoRefresh();
            showNotification('Auto-refresh disabled.');
        }
    });
    
    // Set up event listener for manual refresh button
    if (refreshDataBtn) {
        refreshDataBtn.addEventListener('click', function() {
            refreshBitvavoData();
        });
    }
    
    // Start auto-refresh if it was enabled
    if (autoRefreshEnabled) {
        startAutoRefresh();
    }
}

/**
 * Start the auto-refresh interval
 */
function startAutoRefresh() {
    // Clear any existing interval
    stopAutoRefresh();
    
    // Set new interval
    refreshIntervalId = setInterval(refreshBitvavoData, AUTO_REFRESH_INTERVAL);
    
    // Perform initial refresh
    refreshBitvavoData();
}

/**
 * Stop the auto-refresh interval
 */
function stopAutoRefresh() {
    if (refreshIntervalId) {
        clearInterval(refreshIntervalId);
        refreshIntervalId = null;
    }
}

/**
 * Refresh Bitvavo data by calling the API endpoint
 */
function refreshBitvavoData() {
    // Prevent multiple simultaneous refreshes
    if (isRefreshing) {
        console.log('Refresh already in progress, skipping');
        return;
    }
    
    isRefreshing = true;
    
    // Update UI to show refresh in progress
    const refreshBtn = document.getElementById('refresh-data-btn');
    if (refreshBtn) {
        refreshBtn.disabled = true;
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
    }
    
    // Show loading indicator
    showLoadingIndicator(true);
    
    // Call the API endpoint
    fetch('/NS/api/refresh_bitvavo_data.php')
        .then(response => response.json())
        .then(data => {
            isRefreshing = false;
            
            // Update UI
            if (refreshBtn) {
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh Data';
            }
            
            // Hide loading indicator
            showLoadingIndicator(false);
            
            // Handle response
            if (data.success) {
                showNotification('Data refreshed successfully!', 'success');
                
                // Reload the page to show updated data
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showNotification('Error refreshing data: ' + data.error, 'error');
                console.error('Error refreshing data:', data.error);
            }
        })
        .catch(error => {
            isRefreshing = false;
            
            // Update UI
            if (refreshBtn) {
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh Data';
            }
            
            // Hide loading indicator
            showLoadingIndicator(false);
            
            // Show error
            showNotification('Error refreshing data: ' + error.message, 'error');
            console.error('Error refreshing data:', error);
        });
}

/**
 * Show or hide a loading indicator
 */
function showLoadingIndicator(show) {
    // Check if loading indicator exists
    let loadingIndicator = document.getElementById('loading-indicator');
    
    // Create loading indicator if it doesn't exist
    if (!loadingIndicator && show) {
        loadingIndicator = document.createElement('div');
        loadingIndicator.id = 'loading-indicator';
        loadingIndicator.className = 'loading-overlay';
        loadingIndicator.innerHTML = `
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p>Refreshing Bitvavo Data...</p>
            </div>
        `;
        document.body.appendChild(loadingIndicator);
    }
    
    // Show or hide
    if (loadingIndicator) {
        loadingIndicator.style.display = show ? 'flex' : 'none';
    }
}

/**
 * Show a notification message
 */
function showNotification(message, type = 'info') {
    // Check if notification container exists
    let notificationContainer = document.getElementById('notification-container');
    
    // Create container if it doesn't exist
    if (!notificationContainer) {
        notificationContainer = document.createElement('div');
        notificationContainer.id = 'notification-container';
        notificationContainer.style.position = 'fixed';
        notificationContainer.style.top = '20px';
        notificationContainer.style.right = '20px';
        notificationContainer.style.zIndex = '9999';
        document.body.appendChild(notificationContainer);
    }
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.innerHTML = message;
    
    // Add to container
    notificationContainer.appendChild(notification);
    
    // Remove after delay
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', initBitvavoAutoRefresh);
