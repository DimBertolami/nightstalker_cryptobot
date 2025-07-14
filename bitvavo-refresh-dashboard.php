<?php
/**
 * Bitvavo Refresh Dashboard
 * 
 * This page provides controls for managing Bitvavo data refresh operations,
 * including auto-refresh functionality and manual refresh options.
 */

// Include required files
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

// Ensure user is logged in
checkLogin();

// Page title
$pageTitle = "Bitvavo Data Refresh";
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-sync-alt me-2"></i> Bitvavo Data Refresh Dashboard
        </h1>
    </div>

    <div class="row">
        <!-- Refresh Controls Card -->
        <div class="col-lg-12 mb-4">
            <?php include 'includes/bitvavo_refresh_controls.php'; ?>
        </div>
    </div>
    
    <div class="row">
        <!-- Refresh History Card -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history me-2"></i> Refresh History
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    // Get refresh history from log file
                    $logFile = __DIR__ . '/logs/bitvavo_refresh.log';
                    $history = [];
                    
                    if (file_exists($logFile)) {
                        $logContent = file_get_contents($logFile);
                        $lines = explode("\n", $logContent);
                        $lines = array_filter($lines); // Remove empty lines
                        $lines = array_slice($lines, -20); // Get last 20 entries
                        
                        foreach ($lines as $line) {
                            if (preg_match('/\[(.*?)\] \[(.*?)\] (.*)/', $line, $matches)) {
                                $history[] = [
                                    'timestamp' => $matches[1],
                                    'status' => $matches[2],
                                    'message' => $matches[3]
                                ];
                            }
                        }
                        
                        // Reverse to show newest first
                        $history = array_reverse($history);
                    }
                    
                    if (!empty($history)) {
                        echo '<div class="table-responsive">';
                        echo '<table class="table table-bordered" width="100%" cellspacing="0">';
                        echo '<thead>';
                        echo '<tr>';
                        echo '<th>Time</th>';
                        echo '<th>Status</th>';
                        echo '<th>Message</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';
                        
                        foreach ($history as $entry) {
                            $statusClass = ($entry['status'] === 'SUCCESS') ? 'text-success' : 'text-danger';
                            
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($entry['timestamp']) . '</td>';
                            echo '<td><span class="' . $statusClass . '">' . htmlspecialchars($entry['status']) . '</span></td>';
                            echo '<td>' . htmlspecialchars($entry['message']) . '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>';
                    } else {
                        echo '<div class="alert alert-info">';
                        echo '<i class="fas fa-info-circle me-2"></i> No refresh history available yet.';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Coins Table Summary Card -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-coins me-2"></i> Coins Table Summary
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    // Get summary of coins table
                    $db = getDBConnection();
                    
                    if ($db) {
                        try {
                            // Count total coins
                            $stmt = $db->query("SELECT COUNT(*) as total FROM coins");
                            $totalCoins = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                            
                            // Get newest coin (by timestamp if available)
                            $stmt = $db->query("SELECT symbol, name, price FROM coins ORDER BY id DESC LIMIT 1");
                            $newestCoin = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            // Get highest priced coin
                            $stmt = $db->query("SELECT symbol, name, price FROM coins ORDER BY price DESC LIMIT 1");
                            $highestPriceCoin = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            // Display summary
                            echo '<div class="row">';
                            echo '<div class="col-md-6 mb-4">';
                            echo '<div class="card bg-primary text-white shadow">';
                            echo '<div class="card-body">';
                            echo 'Total Coins';
                            echo '<div class="h3 mb-0">' . number_format($totalCoins) . '</div>';
                            echo '</div>';
                            echo '</div>';
                            echo '</div>';
                            
                            if ($newestCoin) {
                                echo '<div class="col-md-6 mb-4">';
                                echo '<div class="card bg-info text-white shadow">';
                                echo '<div class="card-body">';
                                echo 'Newest Coin';
                                echo '<div class="h5 mb-0">' . htmlspecialchars($newestCoin['symbol']) . ' - ' . htmlspecialchars($newestCoin['name']) . '</div>';
                                echo '<small>Price: ' . number_format($newestCoin['price'], 8) . '</small>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                            
                            if ($highestPriceCoin) {
                                echo '<div class="col-md-6 mb-4">';
                                echo '<div class="card bg-success text-white shadow">';
                                echo '<div class="card-body">';
                                echo 'Highest Price';
                                echo '<div class="h5 mb-0">' . htmlspecialchars($highestPriceCoin['symbol']) . ' - ' . htmlspecialchars($highestPriceCoin['name']) . '</div>';
                                echo '<small>Price: ' . number_format($highestPriceCoin['price'], 2) . '</small>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                            
                            // Link to check script
                            echo '<div class="col-md-6 mb-4">';
                            echo '<div class="card bg-secondary text-white shadow">';
                            echo '<div class="card-body">';
                            echo 'Script Status';
                            echo '<div class="mt-2">';
                            echo '<a href="tools/check_bitvavo_script.php" class="btn btn-light btn-sm" target="_blank">';
                            echo '<i class="fas fa-check-circle me-1"></i> Check Script Status';
                            echo '</a>';
                            echo '</div>';
                            echo '</div>';
                            echo '</div>';
                            echo '</div>';
                            
                            echo '</div>'; // End row
                            
                        } catch (PDOException $e) {
                            echo '<div class="alert alert-danger">';
                            echo '<i class="fas fa-exclamation-circle me-2"></i> Error querying database: ' . htmlspecialchars($e->getMessage());
                            echo '</div>';
                        }
                    } else {
                        echo '<div class="alert alert-danger">';
                        echo '<i class="fas fa-exclamation-circle me-2"></i> Database connection failed.';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Instructions Card -->
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle me-2"></i> Instructions
                    </h6>
                </div>
                <div class="card-body">
                    <h5>About the Bitvavo Data Refresh</h5>
                    <p>
                        This dashboard allows you to manage the refresh of coin data from Bitvavo. The auto-refresh functionality 
                        will periodically purge the coins table and fetch fresh data from Bitvavo using the Python script.
                    </p>
                    
                    <h5>How to Use</h5>
                    <ol>
                        <li>Toggle the <strong>Auto-Refresh</strong> switch to enable/disable automatic refreshes every 5 minutes.</li>
                        <li>Click the <strong>Refresh Data</strong> button to manually trigger a data refresh.</li>
                        <li>View the <strong>Refresh History</strong> to see past refresh operations and their status.</li>
                        <li>Check the <strong>Coins Table Summary</strong> to verify that data is being properly imported.</li>
                    </ol>
                    
                    <h5>Troubleshooting</h5>
                    <p>
                        If you encounter issues with the data refresh:
                    </p>
                    <ul>
                        <li>Use the <strong>Check Script Status</strong> button to verify that the Python script is properly configured.</li>
                        <li>Check the refresh history for any error messages.</li>
                        <li>Ensure that Python is installed and available on the server.</li>
                        <li>Verify that the necessary permissions are set for the script and logs directory.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
