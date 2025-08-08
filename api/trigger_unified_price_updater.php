<?php
// API endpoint to trigger the unified_price_updater.py Python script

header('Content-Type: application/json');

$pythonScript = __DIR__ . '/../includes/unified_price_updater.py';

// Check if the Python script exists
if (!file_exists($pythonScript)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Python script not found']);
    exit;
}

ignore_user_abort(true);
set_time_limit(0);

$localSitePackages = '/home/dim/.local/lib/python3.11/site-packages';

// Execute the Python script asynchronously to avoid blocking, with PYTHONPATH set
$command = "export PYTHONPATH={$localSitePackages}:\$PYTHONPATH && python3 " . escapeshellarg($pythonScript) . " > /dev/null 2>&1 &";
exec($command);

echo json_encode(['success' => true, 'message' => 'Python script triggered asynchronously']);
?>
