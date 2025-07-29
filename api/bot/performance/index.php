<?php

// Set the content type to JSON
header('Content-Type: application/json');

/**
 * Generates random performance data for the bot.
 * In a real application, you would fetch this data from a database or a trading bot's state.
 */
function get_bot_performance_data() {
    // Simulate some random data for demonstration purposes
    $confidence_score = rand(70, 95) . '%';
    $overall_profit = '+' . (rand(50, 200) / 10) . '%';
    $learning_curve = rand(80, 98) . '%';
    $win_rate = rand(75, 90) . '%';
    $avg_profit = (rand(10, 30) / 10) . '%';
    $drawdown = '-' . (rand(1, 9) / 10) . '%';

    return [
        'confidence_score' => $confidence_score,
        'overall_profit' => $overall_profit,
        'learning_curve' => $learning_curve,
        'win_rate' => $win_rate,
        'avg_profit' => $avg_profit,
        'drawdown' => $drawdown,
    ];
}

/**
 * Generates simulated chart data.
 * In a real application, this would come from actual historical performance.
 */
function get_chart_data() {
    $data = [];
    $start_value = 1000;
    for ($i = 0; $i < 30; $i++) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $value = $start_value + (rand(-50, 50));
        $start_value = $value; // Make the next value relative to the current one
        $data[] = ['date' => $date, 'value' => $value];
    }
    // Sort data by date in ascending order
    usort($data, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    return $data;
}

// Determine which data to return based on the request URI
$request_uri = $_SERVER['REQUEST_URI'];
if (strpos($request_uri, 'chart_data') !== false) {
    $response_data = get_chart_data();
} else {
    $response_data = get_bot_performance_data();
}

// Encode the data as JSON and output it
echo json_encode($response_data);

?>