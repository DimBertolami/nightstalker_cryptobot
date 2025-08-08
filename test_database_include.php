<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '/opt/lampp/htdocs/NS/includes/database.php';

if (function_exists('getDBConnection')) {
    echo "getDBConnection function exists.\n";
} else {
    echo "getDBConnection function DOES NOT exist.\n";
}

// Try to call it to see if it throws an error
try {
    $db = getDBConnection();
    if ($db) {
        echo "Successfully called getDBConnection.\n";
    } else {
        echo "getDBConnection returned false.\n";
    }
} catch (Exception $e) {
    echo "Error calling getDBConnection: " . $e->getMessage() . "\n";
}

?>