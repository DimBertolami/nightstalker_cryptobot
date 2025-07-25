<?php
header('Content-Type: application/json');

// Database connection details
$servername = "localhost";
$username = "dimi";
$password = "1304";
$dbname = "NS";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

$coin_id = $_GET['coin_id'] ?? '';

if (empty($coin_id)) {
    die(json_encode(["error" => "coin_id parameter is required."]));
}

$data = [];
$apex_data = null;
$purchase_time = null;
$latest_recorded_time = null;
$coin_status = null;
$drop_start_timestamp = null;

// Fetch price history
$stmt_history = $conn->prepare("SELECT recorded_at, price FROM price_history WHERE coin_id = ? ORDER BY recorded_at ASC");
$stmt_history->bind_param("s", $coin_id);
$stmt_history->execute();
$result_history = $stmt_history->get_result();

while ($row = $result_history->fetch_assoc()) {
    $timestamp_ms = strtotime($row['recorded_at']) * 1000;
    if ($purchase_time === null) {
        $purchase_time = $timestamp_ms; // First recorded_at is purchase time
    }
    $latest_recorded_time = $timestamp_ms; // Keep updating to get the latest
    $data[] = [
        "time" => $timestamp_ms,
        "price" => (float)$row['price']
    ];
}
$stmt_history->close();

// Fetch apex price data and coin status
$stmt_apex = $conn->prepare("SELECT apex_price, apex_timestamp, status, drop_start_timestamp FROM coin_apex_prices WHERE coin_id = ?");
$stmt_apex->bind_param("s", $coin_id);
$stmt_apex->execute();
$result_apex = $stmt_apex->get_result();

if ($result_apex->num_rows > 0) {
    $row_apex = $result_apex->fetch_assoc();
    $apex_data = [
        "price" => (float)$row_apex['apex_price'],
        "timestamp" => strtotime($row_apex['apex_timestamp']) * 1000 // Convert to milliseconds
    ];
    $coin_status = $row_apex['status'];
    if ($row_apex['drop_start_timestamp']) {
        $drop_start_timestamp = strtotime($row_apex['drop_start_timestamp']) * 1000;
    }
}
$stmt_apex->close();

$conn->close();

echo json_encode([
    "history" => $data,
    "apex" => $apex_data,
    "purchase_time" => $purchase_time,
    "latest_recorded_time" => $latest_recorded_time,
    "coin_status" => $coin_status,
    "drop_start_timestamp" => $drop_start_timestamp
]);

?>