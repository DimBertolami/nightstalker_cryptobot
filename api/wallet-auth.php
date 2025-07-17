<?php
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Validate required fields
if (empty($data['provider']) || empty($data['publicKey'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing provider or publicKey']);
    exit;
}

$provider = $data['provider'];
$publicKey = $data['publicKey'];

// TODO: Implement actual wallet linking logic here, e.g., save to database, session, etc.
// For now, simulate success

// Example: Save wallet info to session
session_start();
$_SESSION['walletProvider'] = $provider;
$_SESSION['walletAddress'] = $publicKey;

echo json_encode(['success' => true, 'message' => 'Wallet linked successfully']);
exit;
?>
