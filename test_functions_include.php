<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '/opt/lampp/htdocs/NS/includes/functions.php';

if (function_exists('log_message')) {
    echo "log_message function exists.\n";
} else {
    echo "log_message function DOES NOT exist.\n";
}

?>