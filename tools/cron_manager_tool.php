<?php
/**
 * Night Stalker Cron Manager Tool
 * 
 * This tool provides an interface to manage cron jobs for the Night Stalker application.
 * It allows scheduling and management of various automated tasks.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/pdo_functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/cron_manager.php';

// Set up error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly
ini_set('display_startup_errors', 0);

// Check if we're running from command line or system-tools
$isSystemTools = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'system-tools') !== false;

// Start output buffering if we're running from system-tools
if ($isSystemTools) {
    ob_start();
}

// Process any actions
$action = $_GET['action'] ?? '';
$interval = isset($_GET['interval']) ? (int)$_GET['interval'] : 30;
$enabled = isset($_GET['enabled']) ? ($_GET['enabled'] === 'true') : true;

$result = [
    'status' => 'success',
    'message' => '',
    'details' => []
];

// Get current status
$currentStatus = get_fetch_coins_cron_status();

if ($action === 'update') {
    // Update cron settings
    $success = schedule_fetch_coins_cron($interval, $enabled);
    
    if ($success) {
        $result['message'] = $enabled 
            ? "Cron job scheduled successfully with {$interval} minute interval" 
            : "Cron job disabled successfully";
    } else {
        $result['status'] = 'error';
        $result['message'] = "Failed to update cron job";
    }
    
    // Get updated status
    $currentStatus = get_fetch_coins_cron_status();
}

// Add current status to result
$result['details']['fetch_coins'] = [
    'enabled' => $currentStatus['enabled'],
    'interval' => $currentStatus['interval'],
    'next_run' => $currentStatus['enabled'] 
        ? date('Y-m-d H:i:s', strtotime('+' . $currentStatus['interval'] . ' minutes')) 
        : 'Not scheduled'
];

// Output function - can output either HTML or plain text depending on context
function outputResults($results) {
    global $isSystemTools;
    
    if ($isSystemTools) {
        // Plain text output for system-tools
        echo "=== NIGHT STALKER CRON MANAGER ===\n\n";
        
        echo "=== STATUS ===\n";
        echo "Operation: {$results['status']}\n";
        echo "Message: {$results['message']}\n\n";
        
        echo "=== CRON JOBS ===\n";
        foreach ($results['details'] as $job => $details) {
            $status = $details['enabled'] ? "✓ ENABLED" : "✗ DISABLED";
            echo "{$job}: {$status}\n";
            echo "  Interval: {$details['interval']} minutes\n";
            echo "  Next run: {$details['next_run']}\n";
        }
    } else {
        // HTML output for direct browser access
        ?><!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Night Stalker Cron Manager</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body {
                    background-color: #1a1a2e;
                    color: #e6e6e6;
                }
                .card {
                    background-color: #16213e;
                    border: 1px solid #0f3460;
                    margin-bottom: 20px;
                }
                .card-header {
                    background-color: #0f3460;
                    color: #e6e6e6;
                }
                .success {
                    color: #4cd137;
                }
                .warning {
                    color: #fbc531;
                }
                .error {
                    color: #e84118;
                }
                .cron-item {
                    padding: 8px;
                    border-bottom: 1px solid #2c3e50;
                }
            </style>
        </head>
        <body>
            <div class="container mt-4">
                <h1 class="mb-4">Night Stalker Cron Manager</h1>
                
                <?php if (!empty($results['message'])): ?>
                <div class="alert alert-<?= $results['status'] === 'success' ? 'success' : 'danger' ?>">
                    <?= $results['status'] === 'success' ? '<i class="fas fa-check-circle me-2"></i>' : '<i class="fas fa-exclamation-triangle me-2"></i>' ?>
                    <?= htmlspecialchars($results['message']) ?>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3>Cron Jobs</h3>
                            </div>
                            <div class="card-body">
                                <?php foreach ($results['details'] as $job => $details): ?>
                                    <div class="cron-item">
                                        <h4>
                                            <?php if ($details['enabled']): ?>
                                                <span class="success">✓</span>
                                            <?php else: ?>
                                                <span class="error">✗</span>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($job) ?>
                                        </h4>
                                        <p><strong>Status:</strong> <?= $details['enabled'] ? '<span class="success">Enabled</span>' : '<span class="error">Disabled</span>' ?></p>
                                        <p><strong>Interval:</strong> <?= $details['interval'] ?> minutes</p>
                                        <p><strong>Next run:</strong> <?= $details['next_run'] ?></p>
                                        
                                        <form class="mt-3">
                                            <div class="row g-3 align-items-center">
                                                <div class="col-auto">
                                                    <label for="interval" class="col-form-label">Interval (minutes):</label>
                                                </div>
                                                <div class="col-auto">
                                                    <input type="number" id="interval" name="interval" class="form-control" value="<?= $details['interval'] ?>" min="5" max="1440">
                                                </div>
                                                <div class="col-auto">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" <?= $details['enabled'] ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="enabled">Enabled</label>
                                                    </div>
                                                </div>
                                                <div class="col-auto">
                                                    <button type="submit" class="btn btn-primary">Update</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <a href="/" class="btn btn-primary">Return to Dashboard</a>
                    </div>
                </div>
            </div>
            
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
    }
}

// Output results
outputResults($result);

// End output buffering if we're running from system-tools
if ($isSystemTools) {
    $output = ob_get_clean();
    echo $output;
    
    // Save to log file if running from system-tools
    $logDir = '/opt/lampp/htdocs/NS/system-tools/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Ymd_His');
    $logFile = "{$logDir}/cron_manager_{$timestamp}.log";
    file_put_contents($logFile, $output);
}
