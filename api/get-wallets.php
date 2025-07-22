<?php
// api/get-wallets.php
header('Content-Type: application/json');
session_start();

if (isset($_SESSION['connected_wallets']) && !empty($_SESSION['connected_wallets'])) {
    echo json_encode([
        'success' => true,
        'wallets' => $_SESSION['connected_wallets']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'wallets' => []
    ]);
}
