<?php
// /opt/lampp/htdocs/NS/test_config.php
require_once __DIR__ . '/includes/config.php';

echo "Configuration Test\n";
echo "================\n";
echo "API URL: " . (defined('CMC_API_URL') ? CMC_API_URL : 'NOT DEFINED') . "\n";
echo "API Key: " . (defined('CMC_API_KEY') ? 'SET' : 'NOT SET') . "\n";
