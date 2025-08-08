<?php

require_once __DIR__ . '/vendor/autoload.php';

use ccxt\binance;

error_reporting(E_ALL);
ini_set('display_errors', 1);

$apiKey = 'K5JaS3MyNCevPcBNWAgvjp139sUFkS290Cjq0D6hLukkZogNaL2HqEzekO1Zb72n';
$secret = '1k1wkTro8uGxH6ifk2MTV46YBiJJh8Ivs0tDYLTUcEcFmA10Xgdmk3HPYOlFZvyl';

$exchange = new binance(array(
    'apiKey' => $apiKey,
    'secret' => $secret,
    'enableRateLimit' => true,
    'options' => [
        'defaultType' => 'spot',
    ],
    'urls' => [
        'api' => 'https://testnet.binance.vision',
        'www' => 'https://testnet.binance.vision',
    ],
));

try {
    $balance = $exchange->fetch_balance();
    echo "<pre>";
    print_r($balance);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

?>