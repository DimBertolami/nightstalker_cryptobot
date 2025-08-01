<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Log incoming request for debugging
error_log('wallet-auth.php request: ' . file_get_contents('php://input'));

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

// Check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_log('wallet-auth.php session user_id: ' . ($_SESSION['user_id'] ?? 'not set'));
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$userId = $_SESSION['user_id'];
$provider = $data['provider'];
$publicKey = $data['publicKey'];

try {
    $pdo = getDbConnection();

    // Check if wallet already linked
    $stmt = $pdo->prepare("SELECT id FROM user_wallets WHERE user_id = :user_id AND provider = :provider AND wallet_id = :wallet_id");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':provider', $provider, PDO::PARAM_STR);
    $stmt->bindParam(':wallet_id', $publicKey, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->fetch()) {
        // Wallet already linked, fetch all wallets for the user and store them in session
        $stmt = $pdo->prepare("SELECT id, provider, wallet_id FROM user_wallets WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $connectedWallets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $_SESSION['connected_wallets'] = $connectedWallets;

        echo json_encode([
            'success' => true,
            'message' => 'Wallet already linked',
            'connectedWallets' => $connectedWallets
        ]);
        exit;
    }

    // Insert new wallet link
    $stmt = $pdo->prepare("INSERT INTO user_wallets (user_id, provider, wallet_id, created_at) VALUES (:user_id, :provider, :wallet_id, NOW())");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':provider', $provider, PDO::PARAM_STR);
    $stmt->bindParam(':wallet_id', $publicKey, PDO::PARAM_STR);
    $stmt->execute();

    // After linking, fetch all wallets for the user and store them in session
    $stmt = $pdo->prepare("SELECT id, provider, wallet_id FROM user_wallets WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $connectedWallets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $_SESSION['connected_wallets'] = $connectedWallets;

    echo json_encode([
        'success' => true,
        'message' => 'Wallet linked successfully',
        'connectedWallets' => $connectedWallets
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    error_log('Wallet auth error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
    exit;
}
?>
