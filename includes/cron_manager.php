<?php
/**
 * Cron Manager for Night Stalker
 * Handles scheduling and management of cron jobs
 */

/**
 * Schedule a cron job for fetch_coins.php
 * 
 * @param int $interval Interval in minutes
 * @param bool $enabled Whether the cron job should be enabled
 * @return bool True if successful, false otherwise
 */
function schedule_fetch_coins_cron($interval = 30, $enabled = true) {
    // Default path to cron file
    $cronFile = '/tmp/ns_crontab';
    
    // Get current crontab
    exec('crontab -l 2>/dev/null', $currentCrontab, $returnCode);
    
    // Filter out any existing fetch_coins.php entries
    $filteredCrontab = array_filter($currentCrontab, function($line) {
        return strpos($line, 'fetch_coins.php') === false;
    });
    
    // Add new entry if enabled
    if ($enabled) {
        // Calculate cron expression based on interval
        // For intervals less than 60 minutes, use */X format
        if ($interval < 60) {
            $cronExpression = "*/$interval * * * *";
        } else {
            // For intervals >= 60 minutes, convert to hours
            $hours = floor($interval / 60);
            if ($interval % 60 === 0) {
                // If it's a clean hour interval
                $cronExpression = "0 */$hours * * *";
            } else {
                // If it's not a clean hour interval, default to hourly
                $cronExpression = "0 * * * *";
            }
        }
        
        // Get the absolute path to fetch_coins.php
        $fetchCoinsPath = realpath(__DIR__ . '/../crons/fetch_coins.php');
        
        // Add PHP path and log output
        $logPath = realpath(__DIR__ . '/../logs') . '/cron_fetch_coins.log';
        $cronCommand = "$cronExpression /usr/bin/php $fetchCoinsPath >> $logPath 2>&1";
        
        // Add to filtered crontab
        $filteredCrontab[] = $cronCommand;
    }
    
    // Write new crontab to temporary file
    file_put_contents($cronFile, implode("\n", $filteredCrontab) . "\n");
    
    // Install new crontab
    exec("crontab $cronFile", $output, $returnCode);
    
    // Remove temporary file
    @unlink($cronFile);
    
    // Log the action
    $action = $enabled ? "scheduled with interval $interval minutes" : "disabled";
    logEvent("Fetch coins cron job $action", 'info');
    
    return $returnCode === 0;
}

/**
 * Get current fetch_coins cron job status
 * 
 * @return array Array with 'enabled' and 'interval' keys
 */
function get_fetch_coins_cron_status() {
    // Default return
    $status = [
        'enabled' => false,
        'interval' => 30 // Default interval
    ];
    
    // Get current crontab
    exec('crontab -l 2>/dev/null', $currentCrontab, $returnCode);
    
    if ($returnCode !== 0) {
        return $status;
    }
    
    // Look for fetch_coins.php entries
    foreach ($currentCrontab as $line) {
        if (strpos($line, 'fetch_coins.php') !== false) {
            $status['enabled'] = true;
            
            // Parse the cron expression to get the interval
            $parts = preg_split('/\s+/', trim($line), 6);
            
            // Check if it's a minute-based interval (*/X * * * *)
            if (preg_match('/^\*\/(\d+)\s/', $line, $matches)) {
                $status['interval'] = (int)$matches[1];
            } 
            // Check if it's an hour-based interval (0 */X * * *)
            else if (preg_match('/^0\s+\*\/(\d+)\s/', $line, $matches)) {
                $status['interval'] = (int)$matches[1] * 60;
            }
            
            break;
        }
    }
    
    return $status;
}
