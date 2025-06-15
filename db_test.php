<?php
$db = new mysqli('localhost', 'username', 'password', 'night_stalker');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

echo "Connected successfully. Tables:\n";

$result = $db->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    echo $row[0]."\n";
}

$db->close();
