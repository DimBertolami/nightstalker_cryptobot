<?php
/**
 * Bitvavo Refresh Controls
 * 
 * This template provides controls for refreshing Bitvavo data,
 * including an auto-refresh toggle switch and manual refresh button.
 */
?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-sync-alt me-2"></i> Bitvavo Data Refresh Controls
        </h5>
    </div>
    <div class="card-body">
        <div class="auto-refresh-container">
            <div class="d-flex align-items-center">
                <label for="auto-refresh-switch" class="me-3">Auto-Refresh:</label>
                <label class="switch">
                    <input type="checkbox" id="auto-refresh-switch">
                    <span class="slider"></span>
                </label>
                
                <button id="refresh-data-btn" class="btn btn-primary ms-4">
                    <i class="fas fa-sync-alt"></i> Refresh Data
                </button>
                
                <span class="refresh-status ms-3">
                    <?php
                    // Get last refresh time from database or file
                    $lastRefreshTime = null;
                    $refreshLogFile = __DIR__ . '/../logs/bitvavo_refresh.log';
                    
                    if (file_exists($refreshLogFile)) {
                        $lastLine = exec('tail -n 1 ' . escapeshellarg($refreshLogFile));
                        if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $lastLine, $matches)) {
                            $lastRefreshTime = $matches[1];
                        }
                    }
                    
                    if ($lastRefreshTime) {
                        echo 'Last refreshed: <span class="refresh-timestamp">' . htmlspecialchars($lastRefreshTime) . '</span>';
                    } else {
                        echo 'No recent refresh data';
                    }
                    ?>
                </span>
            </div>
            
            <div class="mt-3 small text-muted">
                <p class="mb-0">
                    <i class="fas fa-info-circle me-1"></i> 
                    Auto-refresh will purge the coins table and fetch fresh data from Bitvavo every 5 minutes.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Include the required CSS and JS files -->
<link rel="stylesheet" href="/NS/assets/css/bitvavo-refresh.css">
<script src="/NS/assets/js/bitvavo-refresh.js"></script>
