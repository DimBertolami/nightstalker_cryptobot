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

<div class="theme-card rounded-xl p-6 mt-8">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
            Bot Performance
        </h2>
        <div class="flex items-center space-x-2">
            <div id="bot-performance-last-update" class="text-sm text-gray-600 dark:text-gray-300">
                Last update: <?php echo htmlspecialchars($last_update); ?>
            </div>
            <button 
                id="bot-performance-refresh-button"
                onclick="handleBotPerformanceRefresh()"
                <?php echo $button_disabled; ?>
                class="px-3 py-1 text-sm rounded bg-blue-500 text-white hover:bg-blue-600 transition-colors disabled:opacity-50"
            >
                <?php echo htmlspecialchars($button_text); ?>
            </button>
        </div>
    </div>

    <div class="space-y-4 mt-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Overall Performance Chart</h3>
        <div class="h-96">
            <canvas id="bot-performance-main-chart"></canvas>
        </div>
    </div>

    <!-- Recent Performance Metrics -->
    <div class="space-y-4 col-span-2 mt-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Performance</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-green-50 dark:bg-green-900/10 rounded-lg">
                <div class="text-sm text-gray-600 dark:text-gray-300">Win Rate</div>
                <div id="bot-performance-win-rate" class="text-2xl font-bold text-green-600 dark:text-green-300">
                    <?php echo htmlspecialchars($data['win_rate']); ?>
                </div>
            </div>
            <div class="p-4 bg-blue-50 dark:bg-blue-900/10 rounded-lg">
                <div class="text-sm text-gray-600 dark:text-gray-300">Average Profit</div>
                <div id="bot-performance-avg-profit" class="text-2xl font-bold text-blue-600 dark:text-blue-300">
                    <?php echo htmlspecialchars($data['avg_profit']); ?>
                </div>
            </div>
            <div class="p-4 bg-purple-50 dark:bg-purple-900/10 rounded-lg">
                <div class="text-sm text-gray-600 dark:text-gray-300">Drawdown</div>
                <div id="bot-performance-drawdown" class="text-2xl font-bold text-purple-600 dark:text-purple-300">
                    <?php echo htmlspecialchars($data['drawdown']); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
    // Return the buffered HTML
    return ob_get_clean();
}

?>
