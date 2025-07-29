/**
 * New Coin Charts Component
 * For Night Stalker Cryptobot - nightstalker.php
 * Generates sparkline charts for new coins based on their price history.
 */

/**
 * Fetches new coin price history data and renders sparklines.
 */
function loadNewCoinCharts() {
    fetch(`/NS/api/get-new-coin-price-history.php`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                renderNewCoinSparklines(data.data);
            } else {
                console.error('Failed to load new coin price history:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching new coin price history:', error);
        });
}

    document.addEventListener('DOMContentLoaded', function() {
        loadNewCoinCharts();
        
        // Auto-refresh new coin charts every 5 minutes (adjust as needed)
        setInterval(loadNewCoinCharts, 3000);
    });

/**
 * Renders sparkline charts for new coins.
 * @param {Array} coinsData - Array of new coin data including price_points.
 */
function renderNewCoinSparklines(coinsData) {
    coinsData.forEach(coin => {
        const chartContainer = document.getElementById(`new-coin-sparkline-${coin.symbol}`);
        if (chartContainer) {
            // Clear previous chart if any
            chartContainer.innerHTML = '';
            
            const values = coin.price_points.map(Number);
            
            // Determine trend class based on the last change (e.g., change_5 from API)
            // Assuming change_5 is the overall change for the sparkline period
            const trendClass = coin.change_5 >= 0 ? 'positive-trend' : 'negative-trend';
            
            // Create sparkline using SVG
            const width = chartContainer.offsetWidth || 150; // Use container width or default
            const height = 40; // Fixed height for sparklines
            const padding = 2;
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('width', width);
            svg.setAttribute('height', height);
            svg.setAttribute('class', 'sparkline');
            
            // Calculate min and max for scaling
            const min = Math.min(...values);
            const max = Math.max(...values);
            const range = max - min || 1; // Avoid division by zero
            
            // Create polyline points
            const points = values.map((value, index) => {
                const x = padding + (index / (values.length - 1)) * (width - 2 * padding);
                const y = height - padding - ((value - min) / range) * (height - 2 * padding);
                return `${x},${y}`;
            }).join(' ');
            
            // Create polyline element
            const polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
            polyline.setAttribute('points', points);
            polyline.setAttribute('fill', 'none');
            polyline.setAttribute('stroke', trendClass === 'positive-trend' ? '#28a745' : '#dc3545');
            polyline.setAttribute('stroke-width', '1.5');
            
            svg.appendChild(polyline);
            chartContainer.appendChild(svg);
            
            // Add a small label for the overall change
            const changeLabel = document.createElement('div');
            changeLabel.classList.add('sparkline-change-label');
            changeLabel.style.fontSize = '0.8em';
            changeLabel.style.textAlign = 'center';
            changeLabel.style.marginTop = '5px';
            changeLabel.style.color = trendClass === 'positive-trend' ? '#28a745' : '#dc3545';
            changeLabel.textContent = `${coin.change_5 >= 0 ? '+' : ''}${coin.change_5.toFixed(2)}%`;
            chartContainer.appendChild(changeLabel);
        }
    });
}
