/**
 * Handles the refresh button click for the Bot Performance Card.
 * Fetches updated data from the API and updates the DOM.
 */
async function handleBotPerformanceRefresh() {
    const refreshButton = document.getElementById('bot-performance-refresh-button');
    const lastUpdateElement = document.getElementById('bot-performance-last-update');

    // Disable the button to prevent multiple clicks
    refreshButton.disabled = true;
    refreshButton.textContent = 'Refreshing...';

    try {
        // --- Replace with your actual API endpoint ---
        const response = await fetch('/NS/api/bot/performance/index.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        // Update the DOM with the new data
        
        document.getElementById('bot-performance-win-rate').textContent = data.win_rate;
        document.getElementById('bot-performance-avg-profit').textContent = data.avg_profit;
        document.getElementById('bot-performance-drawdown').textContent = data.drawdown;

        // Update the last updated time
        lastUpdateElement.textContent = `Last update: ${new Date().toLocaleTimeString()}`;

    } catch (error) {
        console.error("Could not fetch bot performance data:", error);
        lastUpdateElement.textContent = "Error updating data.";
    } finally {
        // Re-enable the button
        refreshButton.disabled = false;
        refreshButton.textContent = 'Refresh';
    }
}
