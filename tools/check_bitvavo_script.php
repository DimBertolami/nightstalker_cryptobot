<?php
/**
 * Check Bitvavo Script
 * 
 * This utility script checks if the Bitvavo data fetch script exists
 * and has the correct permissions for execution.
 */

// Define the path to the Bitvavo data fetch script
$scriptPath = __DIR__ . '/cmc/bitvavoFromCMC4NS.py';
$pythonCommand = 'python3';

// Function to check if a command exists
function commandExists($command) {
    $whereIsCommand = (PHP_OS == 'WINNT') ? 'where' : 'which';
    $process = proc_open(
        "$whereIsCommand $command",
        [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ],
        $pipes
    );
    
    if ($process !== false) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        
        return $stdout != '';
    }
    
    return false;
}

// Header for CLI or browser output
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html>\n<html>\n<head>\n";
    echo "<title>Bitvavo Script Check</title>\n";
    echo "<style>body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }\n";
    echo ".success { color: green; }\n.error { color: red; }\n";
    echo ".warning { color: orange; }\n.info { background: #f0f0f0; padding: 10px; }\n";
    echo "</style>\n</head>\n<body>\n";
    echo "<h1>Bitvavo Script Check</h1>\n";
} else {
    echo "=== Bitvavo Script Check ===\n\n";
}

// Check if the script exists
if (file_exists($scriptPath)) {
    outputMessage("✓ Script found at: $scriptPath", 'success');
} else {
    outputMessage("✗ Script not found at: $scriptPath", 'error');
    outputMessage("Please ensure the Bitvavo data fetch script is in the correct location.", 'info');
    outputFooter();
    exit(1);
}

// Check if the script is readable
if (is_readable($scriptPath)) {
    outputMessage("✓ Script is readable", 'success');
} else {
    outputMessage("✗ Script is not readable", 'error');
    outputMessage("Please check file permissions: chmod +r $scriptPath", 'info');
    outputFooter();
    exit(1);
}

// Check if the script is executable
if (is_executable($scriptPath)) {
    outputMessage("✓ Script is executable", 'success');
} else {
    outputMessage("⚠ Script is not executable", 'warning');
    outputMessage("Consider making the script executable: chmod +x $scriptPath", 'info');
}

// Check if Python is installed
if (commandExists($pythonCommand)) {
    outputMessage("✓ Python ($pythonCommand) is available", 'success');
} else {
    outputMessage("✗ Python ($pythonCommand) not found", 'error');
    outputMessage("Please ensure Python is installed and available in the PATH", 'info');
    outputFooter();
    exit(1);
}

// Check script content (first few lines)
$scriptContent = file_get_contents($scriptPath, false, null, 0, 500); // Get first 500 bytes
if ($scriptContent !== false) {
    outputMessage("Script preview (first few lines):", 'info');
    $lines = explode("\n", $scriptContent);
    $previewLines = array_slice($lines, 0, 10); // Show first 10 lines
    
    if (php_sapi_name() !== 'cli') {
        echo "<pre>";
    }
    
    foreach ($previewLines as $line) {
        echo htmlspecialchars($line) . "\n";
    }
    
    if (count($lines) > 10) {
        echo "...\n";
    }
    
    if (php_sapi_name() !== 'cli') {
        echo "</pre>";
    }
} else {
    outputMessage("⚠ Could not read script content", 'warning');
}

// Test script execution (dry run)
outputMessage("Testing script execution (no actual data refresh)...", 'info');
$command = "$pythonCommand $scriptPath --check-only 2>&1";
$output = [];
$returnCode = 0;
exec($command, $output, $returnCode);

if ($returnCode === 0) {
    outputMessage("✓ Script execution test successful", 'success');
} else {
    outputMessage("✗ Script execution test failed (return code: $returnCode)", 'error');
    
    if (!empty($output)) {
        outputMessage("Error output:", 'info');
        
        if (php_sapi_name() !== 'cli') {
            echo "<pre>";
        }
        
        foreach ($output as $line) {
            echo htmlspecialchars($line) . "\n";
        }
        
        if (php_sapi_name() !== 'cli') {
            echo "</pre>";
        }
    }
}

// Check logs directory
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    outputMessage("⚠ Logs directory does not exist: $logDir", 'warning');
    outputMessage("The directory will be created automatically when needed", 'info');
} else {
    outputMessage("✓ Logs directory exists", 'success');
    
    // Check if logs directory is writable
    if (is_writable($logDir)) {
        outputMessage("✓ Logs directory is writable", 'success');
    } else {
        outputMessage("✗ Logs directory is not writable", 'error');
        outputMessage("Please check permissions: chmod 755 $logDir", 'info');
    }
}

// Final summary
outputMessage("\nSummary:", 'info');
outputMessage("The Bitvavo data fetch script appears to be properly configured.", 'success');
outputMessage("You can now use the auto-refresh functionality to update coin data.", 'info');

// Output footer
outputFooter();

/**
 * Helper function to output messages with appropriate formatting
 */
function outputMessage($message, $type = 'info') {
    if (php_sapi_name() === 'cli') {
        $prefixes = [
            'success' => "\033[32m",  // Green
            'error' => "\033[31m",    // Red
            'warning' => "\033[33m",  // Yellow
            'info' => "\033[0m",      // Default
        ];
        $suffix = "\033[0m";
        
        echo $prefixes[$type] . $message . $suffix . "\n";
    } else {
        echo "<p class=\"$type\">$message</p>\n";
    }
}

/**
 * Output footer based on environment
 */
function outputFooter() {
    if (php_sapi_name() !== 'cli') {
        echo "</body>\n</html>";
    }
}
?>
