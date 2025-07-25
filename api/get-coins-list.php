<?php
header('Content-Type: application/json');

// Database connection details - Use the same as get-chart-data.php
$servername = "localhost";
$username = "dimi";
$password = "1304";
$dbname = "NS";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

$coins = [];

// Fetch coin_id from coin_apex_prices (portfolio items) and their names from cryptocurrencies table
$sql = "SELECT cap.coin_id, c.name FROM coin_apex_prices cap JOIN cryptocurrencies c ON cap.coin_id = c.id ORDER BY c.name ASC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $coins[] = [
            "id" => $row['coin_id'],
            "name" => $row['name']
        ];
    }
} else {
    echo json_encode(["error" => "Error fetching coins: " . $conn->error]);
    $conn->close();
    exit();
}

$conn->close();

echo json_encode($coins);

?>