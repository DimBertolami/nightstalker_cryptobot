/**
 * Price History Table Component
 * For Night Stalker Cryptobot
 * Shows price history of portfolio coins at different intervals
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the price history table
    initPriceHistoryTable();
    
    // Refresh button event listener
    document.getElementById('refresh-price-history').addEventListener('click', function() {
        loadPriceHistoryData();
    });
    
    // Auto-refresh every 5 minutes
    setInterval(loadPriceHistoryData, 300000);
});

/**
 * Initialize the price history table
 */
function initPriceHistoryTable() {
    loadPriceHistoryData();
}

/**
 * Load price history data from API
 */
function loadPriceHistoryData() {
    const loadingElement = document.getElementById('loading-price-history');
    const tableElement = document.getElementById('price-history-table');
    
    if (loadingElement) loadingElement.style.display = 'flex';
    if (tableElement) tableElement.style.display = 'none';
    
    // Get selected exchange
    const exchange = document.getElementById('exchange-select').value;
    
    // Fetch price history data
    fetch(`/NS/api/trading/price-history.php?exchange=${exchange}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderPriceHistoryTable(data.data);
            } else {
                showToast(data.message || 'Failed to load price history', 'error');
            }
        })
        .catch(error => {
            console.error('Error fetching price history:', error);
            showToast('Error loading price history data', 'error');
        })
        .finally(() => {
            if (loadingElement) loadingElement.style.display = 'none';
            if (tableElement) tableElement.style.display = 'table';
        });
}

/**
 * Render the price history table with data
 * @param {Array} data - Price history data
 */
function renderPriceHistoryTable(data) {
    const tableBody = document.getElementById('price-history-tbody');
    const tableHead = document.querySelector('#price-history-table thead tr');
    if (!tableBody || !tableHead) return;
    
    // Clear existing rows
    tableBody.innerHTML = '';
    
    // If no data, show message
    if (!data.length) {
        const emptyRow = document.createElement('tr');
        emptyRow.innerHTML = `<td colspan="8" class="text-center py-4">No price history data available</td>`;
        tableBody.appendChild(emptyRow);
        return;
    }
    
    // Get interval labels from the first coin
    const intervalLabels = data[0].interval_labels;
    
    // Update table headers with dynamic interval labels
    tableHead.innerHTML = `
        <th>Coin</th>
        <th class="text-end">Current Price</th>
        <th>${intervalLabels.i1}</th>
        <th>${intervalLabels.i2}</th>
        <th>${intervalLabels.i3}</th>
        <th>${intervalLabels.i4}</th>
        <th>${intervalLabels.i5}</th>
        <th>Price Trend</th>
    `;
    
    // Add rows for each coin
    data.forEach(coin => {
        const row = document.createElement('tr');
        
        // Highlight row if in portfolio
        if (coin.in_portfolio) {
            row.classList.add('table-active');
        }
        
        // Add sparkline chart class if positive/negative
        const trendClass = coin.change_5 >= 0 ? 'positive-trend' : 'negative-trend';
        
        // Format price with appropriate precision
        const formatPrice = (price) => {
            if (price < 0.001) return price.toFixed(8);
            if (price < 0.01) return price.toFixed(6);
            if (price < 1) return price.toFixed(4);
            if (price < 1000) return price.toFixed(2);
            return price.toFixed(2);
        };
        
        // Format percent change
        const formatChange = (change) => {
            const prefix = change >= 0 ? '+' : '';
            return `${prefix}${change.toFixed(2)}%`;
        };
        
        // Create change cell with appropriate color
        const createChangeCell = (change) => {
            const cellClass = change >= 0 ? 'text-success' : 'text-danger';
            return `<td class="${cellClass}">${formatChange(change)}</td>`;
        };
        
        // Build row HTML
        row.innerHTML = `
            <td>
                <div class="d-flex align-items-center">
                    <div class="coin-icon me-2">
                        <img src="/NS/assets/img/crypto-icons/generic.png" 
                             width="24" height="24" alt="${coin.symbol}">
                    </div>
                    <div>
                        <div class="fw-bold">${coin.symbol}</div>
                        <div class="text-muted small">${coin.name}</div>
                        ${coin.in_portfolio ? '<span class="badge bg-primary ms-1">Portfolio</span>' : ''}
                    </div>
                </div>
            </td>
            <td class="text-end">$${formatPrice(coin.current_price)}</td>
            ${createChangeCell(coin.change_1)}
            ${createChangeCell(coin.change_2)}
            ${createChangeCell(coin.change_3)}
            ${createChangeCell(coin.change_4)}
            ${createChangeCell(coin.change_5)}
            <td>
                <div class="sparkline-chart ${trendClass}" data-values="${coin.price_points.join(',')}"></div>
            </td>
        `;
        
        tableBody.appendChild(row);
    });
    
    // Initialize sparkline charts
    initSparklines();
}

/**
 * Initialize sparkline charts
 */
function initSparklines() {
    document.querySelectorAll('.sparkline-chart').forEach(chart => {
        const values = chart.getAttribute('data-values').split(',').map(Number);
        
        // Create sparkline using SVG
        const width = 100;
        const height = 30;
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
        polyline.setAttribute('stroke', chart.classList.contains('positive-trend') ? '#28a745' : '#dc3545');
        polyline.setAttribute('stroke-width', '1.5');
        
        svg.appendChild(polyline);
        chart.appendChild(svg);
    });
}
