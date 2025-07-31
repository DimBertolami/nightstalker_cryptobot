<?php

/**
 * Renders the Bot Performance Card component.
 *
 * @param array $data An associative array containing the performance metrics.
 *        Expected keys:
 *        - last_update (string)
 *        - confidence_score (string)
 *        - overall_profit (string)
 *        - learning_curve (string)
 *        - win_rate (string)
 *        - avg_profit (string)
 *        - drawdown (string)
 * @param bool $loading Whether the component is in a loading state.
 * @return string The HTML content for the component.
 */
function renderBotPerformanceCard(
    $data = [
        'last_update' => null,
        'confidence_score' => '82%',
        'overall_profit' => '+12.8%',
        'learning_curve' => '87%',
        'win_rate' => '85%',
        'avg_profit' => '2.3%',
        'drawdown' => '-0.8%',
    ],
    $loading = false
) {
    // Set default for last_update if not provided
    $last_update = $data['last_update'] ?? date('H:i:s');
    
    // Determine button state
    $button_text = $loading ? 'Refreshing...' : 'Refresh';
    $button_disabled = $loading ? 'disabled' : '';

    // Start output buffering to capture HTML
    ob_start();
?>

<div class="dashboard-container" style="background-color: #1a1d21; color: #ffffff; font-family: 'Arial', sans-serif; padding: 20px; border-radius: 10px;">
    <div class="dashboard-header" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 20px; border-bottom: 1px solid #333;">
        <h2 style="color: #ffffff; margin: 0; font-size: 1.8em;"><i class="fas fa-robot" style="margin-right: 10px; color: #007bff;"></i> Bot Learning & Performance Dashboard</h2>
        <span style="color: #b0b3b8; font-size: 1.2em;">lstm_gru_ensemble</span>
    </div>

    <div class="info-cards-container" style="display: flex; justify-content: space-around; margin-top: 20px; flex-wrap: wrap;">
        <!-- Profit Tracker Card -->
        <div class="info-card" style="background-color: #2c2f33; border-radius: 8px; padding: 20px; text-align: center; margin: 10px; flex: 1; min-width: 280px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
            <h4 style="color: #b0b3b8; margin-bottom: 10px; font-size: 1.1em;">Profit Tracker</h4>
            <p style="font-size: 2.2em; font-weight: bold; color: #28a745; margin-bottom: 5px;">$2068.69 <span style="font-size: 0.6em; color: #28a745;">+206.87%</span></p>
            <div class="progress-bar-container" style="height: 10px; background-color: #4f545c; border-radius: 5px; margin-top: 15px;">
                <div class="progress-bar-fill" style="width: 70%; background-color: #28a745; height: 100%; border-radius: 5px;"></div>
            </div>
            <p style="color: #b0b3b8; font-size: 0.8em; margin-top: 10px;">4/2/2025</p>
        </div>

        <!-- Decision Accuracy Card -->
        <div class="info-card" style="background-color: #2c2f33; border-radius: 8px; padding: 20px; text-align: center; margin: 10px; flex: 1; min-width: 280px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
            <h4 style="color: #b0b3b8; margin-bottom: 10px; font-size: 1.1em;">Decision Accuracy</h4>
            <p style="font-size: 2.2em; font-weight: bold; color: #8e44ad; margin-bottom: 5px;">74%</p>
            <div class="progress-bar-container" style="height: 10px; background-color: #4f545c; border-radius: 5px; margin-top: 15px;">
                <div class="progress-bar-fill" style="width: 74%; background-color: #8e44ad; height: 100%; border-radius: 5px;"></div>
            </div>
        </div>

        <!-- Learning Progress Card -->
        <div class="info-card" style="background-color: #2c2f33; border-radius: 8px; padding: 20px; text-align: center; margin: 10px; flex: 1; min-width: 280px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
            <h4 style="color: #b0b3b8; margin-bottom: 10px; font-size: 1.1em;">Learning Progress</h4>
            <div class="progress-bar-container" style="height: 20px; background-color: #4f545c; border-radius: 10px; margin-top: 20px;">
                <div class="progress-bar-fill" style="width: 85%; background: linear-gradient(to right, #007bff, #00c6ff); height: 100%; border-radius: 10px;"></div>
            </div>
        </div>
    </div>

    <!-- Chart Section -->
    <div class="chart-section" style="background-color: #2c2f33; border-radius: 8px; padding: 20px; margin-top: 20px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); max-height: 440px; overflow-y: hidden;">
        <div class="legend" style="text-align: center; margin-bottom: 20px; font-size: 0.9em;">
            <span style="color: #8e44ad; margin-right: 15px;"><i class="fas fa-circle" style="font-size: 0.7em;"></i> Decision Accuracy</span>
            <span style="color: #28a745; margin-right: 15px;"><i class="fas fa-circle" style="font-size: 0.7em;"></i> Cumulative Profit</span>
            <span style="color: #ffc107; margin-right: 15px;"><i class="fas fa-circle" style="font-size: 0.7em;"></i> Bot Confidence</span>
            <span style="color: #6c757d; margin-right: 15px;"><i class="fas fa-circle" style="font-size: 0.7em;"></i> Total Trades</span>
            <span style="color: #dc3545; margin-right: 15px;"><i class="fas fa-circle" style="font-size: 0.7em;"></i> Significant Trades</span>
        </div>
        <canvas id="bot-performance-main-chart" style="height: 400px;"></canvas>
    </div>
</div>

<?php
    // Return the buffered HTML
    return ob_get_clean();
}

?>
