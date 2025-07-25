<?php
require_once 'pdo_functions.php';

if (syncPortfolioCoinsToCryptocurrenciesPDO()) {
    echo "Portfolio coins synced successfully.\n";
    exit(0);
} else {
    echo "Failed to sync portfolio coins.\n";
    exit(1);
}
