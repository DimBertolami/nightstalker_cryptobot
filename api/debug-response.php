<?php
// Buffer output to prevent any PHP errors from breaking JSON
ob_start();

// Turn off error display for JSON API
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear any output buffered so far to ensure clean JSON
ob_clean();
header('Content-Type: application/json');

// Check if any PHP files are outputting HTML content
$includedFiles = get_included_files();
$suspiciousFiles = [];

foreach ($includedFiles as $file) {
    if (strpos($file, '.php') !== false) {
        $content = file_get_contents($file);
        if (strpos($content, '<!DOCTYPE') !== false || 
            strpos($content, '<html') !== false || 
            strpos($content, '<body') !== false ||
            strpos($content, '<br') !== false) {
            $suspiciousFiles[] = $file;
        }
    }
}

// Return the result
echo json_encode([
    'success' => true,
    'included_files' => $includedFiles,
    'suspicious_files' => $suspiciousFiles,
    'timestamp' => time()
]);

// End output buffering and flush
ob_end_flush();
