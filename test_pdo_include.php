<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '/opt/lampp/htdocs/NS/includes/pdo_functions.php';

if (function_exists('getLearningMetricsPDO')) {
    echo "getLearningMetricsPDO function exists.\n";
} else {
    echo "getLearningMetricsPDO function DOES NOT exist.\n";
}

// Try to call it to see if it throws an error
try {
    $metrics = getLearningMetricsPDO();
    echo "Successfully called getLearningMetricsPDO.\n";
    print_r($metrics);
} catch (Exception $e) {
    echo "Error calling getLearningMetricsPDO: " . $e->getMessage() . "\n";
}

?>