<?php
header('Content-Type: application/json');
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth.php';

$response = ['status' => 'ok', 'timestamp' => time()];

// Check database connection
$db = db_connect();
if ($db === null) {
    $response['status'] = 'error';
    $response['message'] = 'Database connection failed';
    http_response_code(500);
} else {
    // Verify we can query the database
    $result = $db->query("SELECT 1");
    if (!$result) {
        $response['status'] = 'error';
        $response['message'] = 'Database query failed';
        $response['db_error'] = $db->error;
        http_response_code(500);
    }
    $db->close();
}

echo json_encode($response);
