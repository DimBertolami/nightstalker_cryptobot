<?php
// Test file to check write permissions
$config_dir = __DIR__ . '/config';
$test_file = $config_dir . '/test_write.txt';

echo "Testing write permissions to config directory...<br>";
echo "Config directory: $config_dir<br>";

// Check if directory exists and is writable
if (!file_exists($config_dir)) {
    echo "Config directory does not exist!<br>";
    exit;
}

if (!is_writable($config_dir)) {
    echo "Config directory is not writable!<br>";
    echo "Directory permissions: " . substr(sprintf('%o', fileperms($config_dir)), -4) . "<br>";
    echo "Directory owner: " . posix_getpwuid(fileowner($config_dir))['name'] . "<br>";
    echo "Directory group: " . posix_getgrgid(filegroup($config_dir))['name'] . "<br>";
    exit;
}

echo "Config directory is writable.<br>";

// Try to write a test file
$content = "Test write at " . date('Y-m-d H:i:s');
$result = file_put_contents($test_file, $content);

if ($result === false) {
    echo "Failed to write test file!<br>";
    echo "Error: " . error_get_last()['message'] . "<br>";
} else {
    echo "Successfully wrote $result bytes to test file.<br>";
    echo "File content: " . file_get_contents($test_file) . "<br>";
    
    // Try to delete the file
    if (unlink($test_file)) {
        echo "Successfully deleted test file.<br>";
    } else {
        echo "Failed to delete test file.<br>";
    }
}

// Check exchanges.json specifically
$exchanges_file = $config_dir . '/exchanges.json';
echo "<br>Testing exchanges.json file...<br>";

if (file_exists($exchanges_file)) {
    echo "exchanges.json exists.<br>";
    echo "File permissions: " . substr(sprintf('%o', fileperms($exchanges_file)), -4) . "<br>";
    echo "File owner: " . posix_getpwuid(fileowner($exchanges_file))['name'] . "<br>";
    echo "File group: " . posix_getgrgid(filegroup($exchanges_file))['name'] . "<br>";
    
    if (is_writable($exchanges_file)) {
        echo "exchanges.json is writable.<br>";
    } else {
        echo "exchanges.json is NOT writable!<br>";
    }
} else {
    echo "exchanges.json does not exist.<br>";
}
?>
