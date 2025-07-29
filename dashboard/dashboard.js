/**
 * Night Stalker Trading Dashboard JavaScript
 * Handles real-time updates and interactive features
 */

let priceChart = null; // Chart.js instance
let priceData = { labels: [], prices: [] }; // Data for the price chart
let currentMonitoredSymbol = null; // To track the currently monitored symbol
let activeTradeInterval = null; // To store the interval ID for active trade updates

document.addEventListener('DOMContentLoaded', function() {
    // Handle strategy selection
    const strategyForm = document.getElementById('strategy-form');
    const strategySelect = document.getElementById('strategy-select');
    
    if (strategySelect) {
        // Store original strategy value to detect changes
        let originalStrategy = strategySelect.value;
        
        // Listen for changes to the strategy
        strategySelect.addEventListener('change', function() {
            if (this.value !== originalStrategy) {
                showToast('Switching to ' + this.options[this.selectedIndex].text);
                strategyForm.submit();
            }
        });
    }
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Event filtering for Recent Trading Activity
    const filterButtons = document.querySelectorAll('[data-filter]');
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Update active button
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            const filterValue = this.getAttribute('data-filter');
            const eventRows = document.querySelectorAll('#recent-events-table tbody tr');
            
            eventRows.forEach(row => {
                if (filterValue === 'all') {
                    row.style.display = '';
                } else {
                    const eventType = row.getAttribute('data-event-type');
                    row.style.display = (eventType === filterValue) ? '' : 'none';
                }
            });
        });
    });
    
    // Set 'All' as default active filter
    const allFilterButton = document.querySelector('[data-filter="all"]');
    if (allFilterButton) {
        allFilterButton.classList.add('active');
    }
    
    // Price chart data and auto-refresh functionality
    const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
    let autoRefreshInterval;

    if (autoRefreshToggle) {
        autoRefreshToggle.addEventListener('change', function() {
            if (this.checked) {
                // Refresh every 30 seconds
                autoRefreshInterval = setInterval(function() {
                    updateActiveTrade();
                    updateRecentEvents();
                }, 30000);
                showToast('Auto-refresh enabled (30s)');
            } else {
                clearInterval(autoRefreshInterval);
                showToast('Auto-refresh disabled');
            }
        });
    }

    // Update active trade information
    function updateActiveTrade() {
        const activeTradeSection = document.getElementById('active-trade-section');
        if (!activeTradeSection) return;

        const currentPriceElement = document.getElementById('current-price');
        const profitPercentageElement = document.getElementById('profit-percentage');
        const holdingTimeElement = document.getElementById('holding-time');
        const priceUpdatesElement = document.getElementById('price-updates');

        if (currentPriceElement) {
            currentPriceElement.innerHTML = '<div class="loading-spinner"></div>';
        }

        fetch('get_active_trade_data.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.symbol !== 'N/A') {
                    // If the monitored symbol changes, reset the chart
                    if (currentMonitoredSymbol !== data.symbol) {
                        currentMonitoredSymbol = data.symbol;
                        priceData = { labels: [], prices: [] }; // Clear old data
                        if (priceChart) {
                            priceChart.destroy(); // Destroy old chart instance
                            priceChart = null;
                        }
                        initPriceChart(); // Re-initialize chart for new symbol
                    }

                    if (currentPriceElement) {
                        currentPriceElement.textContent = data.current_price;
                    }
                    
                    if (profitPercentageElement) {
                        profitPercentageElement.textContent = data.profit_percentage;
                        profitPercentageElement.className = 
                            parseFloat(data.raw_profit_percentage) > 0 ? 'stat-value positive' : 'stat-value negative';
                    }
                    
                    if (holdingTimeElement) {
                        holdingTimeElement.textContent = data.holding_time;
                    }
                    
                    if (priceUpdatesElement) {
                        priceUpdatesElement.textContent = 'Updated: ' + data.last_update;
                    }

                    // Update price chart if it exists
                    updatePriceChart(data.current_price, data.symbol, data.apex_price, data.drop_start_timestamp);

                    // Ensure interval is running if there's an active trade
                    if (!activeTradeInterval) {
                        activeTradeInterval = setInterval(updateActiveTrade, 3000); // Update every 3 seconds
                    }

                } else {
                    // No active trade or error, clear chart and stop updates
                    if (priceChart) {
                        priceChart.destroy();
                        priceChart = null;
                    }
                    priceData = { labels: [], prices: [] };
                    currentMonitoredSymbol = null;
                    if (activeTradeInterval) {
                        clearInterval(activeTradeInterval);
                        activeTradeInterval = null;
                    }

                    if (currentPriceElement) {
                        currentPriceElement.textContent = 'N/A';
                    }
                    if (profitPercentageElement) {
                        profitPercentageElement.textContent = '0.00%';
                        profitPercentageElement.className = 'stat-value';
                    }
                    if (holdingTimeElement) {
                        holdingTimeElement.textContent = '0s';
                    }
                    if (priceUpdatesElement) {
                        priceUpdatesElement.textContent = 'Updated: N/A';
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching active trade data:', error);
                if (currentPriceElement) {
                    currentPriceElement.textContent = 'Error';
                }
                // Also stop updates on error
                if (activeTradeInterval) {
                    clearInterval(activeTradeInterval);
                    activeTradeInterval = null;
                }
            });
    }

    // Update recent events
    function updateRecentEvents() {
        const recentEventsTable = document.getElementById('recent-events-table');
        if (!recentEventsTable) return;

        fetch('get_recent_events.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.events) {
                    const tbody = recentEventsTable.querySelector('tbody');
                    if (tbody) {
                        tbody.innerHTML = '';
                        
                        if (data.events.length === 0) {
                            const tr = document.createElement('tr');
                            tr.innerHTML = '<td colspan="4" class="text-center">No trading activity yet</td>';
                            tbody.appendChild(tr);
                        } else {
                            data.events.forEach(event => {
                                const tr = document.createElement('tr');
                                tr.className = 'event-' + event.event_type;
                                
                                let badgeClass = 'bg-secondary';
                                switch (event.event_type) {
                                    case 'buy': badgeClass = 'bg-success'; break;
                                    case 'sell': badgeClass = 'bg-danger'; break;
                                    case 'monitor': badgeClass = 'bg-warning'; break;
                                    case 'error': badgeClass = 'bg-dark'; break;
                                }
                                
                                let details = '';
                                const eventData = event.event_data;
                                
                                if (event.event_type === 'buy') {
                                    details = `Bought at ${formatCurrency(eventData.price, 8)} 
                                        (Amount: ${eventData.amount}, 
                                        Cost: ${formatCurrency(eventData.cost, 2)} ${eventData.currency})`;
                                } else if (event.event_type === 'sell') {
                                    const profitClass = eventData.profit > 0 ? 'positive' : 'negative';
                                    details = `Sold at ${formatCurrency(eventData.sell_price, 8)} 
                                        (Profit: <span class="${profitClass}">
                                            ${formatCurrency(eventData.profit, 2)} ${eventData.currency} 
                                            (${formatPercentage(eventData.profit_percentage)})
                                        </span>)`;
                                } else if (event.event_type === 'monitor') {
                                    const changeClass = (eventData.price_change || 0) > 0 ? 'positive' : 'negative';
                                    details = `Price: ${formatCurrency(eventData.current_price, 8)}, 
                                        Change: <span class="${changeClass}">
                                            ${formatPercentage(eventData.price_change || 0)}
                                        </span>`;
                                } else {
                                    details = JSON.stringify(eventData);
                                }
                                
                                tr.innerHTML = `
                                    <td>${formatDateTime(event.event_time)}</td>
                                    <td><span class="badge ${badgeClass}">${event.event_type.toUpperCase()}</span></td>
                                    <td>${eventData.symbol || 'N/A'}</td>
                                    <td>${details}</td>
                                `;
                                
                                tbody.appendChild(tr);
                            });
                        }
                    }
                }
            })
            .catch(error => console.error('Error fetching recent events:', error));
    }

    // Initialize price chart
    function initPriceChart() {
        const priceChartCanvas = document.getElementById('price-chart');
        if (!priceChartCanvas) return;

        priceChart = new Chart(priceChartCanvas, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Price',
                    data: [],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false
                    },
                    x: {
                        ticks: {
                            maxTicksLimit: 8
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                interaction: {
                    mode: 'nearest',
                    intersect: false
                }
            }
        });
    }

    // Update price chart with new data
    function updatePriceChart(currentPrice, symbol, apexPrice, dropStartTimestamp) {
        if (!priceChart) {
            initPriceChart();
            if (!priceChart) return;
        }

        // Update chart label with the symbol
        priceChart.data.datasets[0].label = `${symbol} Price`;

        // Add new data point
        const now = new Date();
        const timeString = now.getHours().toString().padStart(2, '0') + ':' + 
                          now.getMinutes().toString().padStart(2, '0') + ':' + 
                          now.getSeconds().toString().padStart(2, '0');
        
        priceData.labels.push(timeString);
        priceData.prices.push(parseFloat(currentPrice.replace(/,/g, '')));
        
        // Keep only the last 50 data points
        if (priceData.labels.length > 50) {
            priceData.labels.shift();
            priceData.prices.shift();
        }
        
        // Add Apex Price and Drop Start Timestamp as new datasets or annotations
        // For simplicity, let's add them as annotations if they exist
        const annotations = {};

        if (apexPrice && apexPrice !== 'N/A') {
            annotations.apexLine = {
                type: 'line',
                yMin: parseFloat(apexPrice.replace(/,/g, '')),
                yMax: parseFloat(apexPrice.replace(/,/g, '')),
                borderColor: 'rgb(255, 99, 132)', // Red
                borderWidth: 2,
                borderDash: [5, 5],
                label: {
                    content: `Apex: ${apexPrice}`,
                    enabled: true,
                    position: 'end'
                }
            };
        }

        if (dropStartTimestamp && dropStartTimestamp !== 'N/A') {
            // Assuming dropStartTimestamp is a valid date string
            const dropDate = new Date(dropStartTimestamp);
            annotations.dropLine = {
                type: 'line',
                xMin: dropDate,
                xMax: dropDate,
                borderColor: 'rgb(255, 159, 64)', // Orange
                borderWidth: 2,
                borderDash: [5, 5],
                label: {
                    content: 'Drop Start',
                    enabled: true,
                    position: 'top'
                }
            };
        }

        // Update chart options with new annotations
        priceChart.options.plugins.annotation = { annotations: annotations };

        // Update chart
        priceChart.data.labels = priceData.labels;
        priceChart.data.datasets[0].data = priceData.prices;
        priceChart.update();
    }

    // Helper functions
    function formatCurrency(value, decimals = 2) {
        return parseFloat(value).toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function formatPercentage(value) {
        return parseFloat(value).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + '%';
    }

    function formatDateTime(dateTimeStr) {
        const date = new Date(dateTimeStr);
        return date.toLocaleString();
    }

    function showToast(message) {
        const toastContainer = document.getElementById('toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = 'toast show';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="toast-header">
                <strong class="me-auto">Night Stalker</strong>
                <small>Just now</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        `;
        
        toastContainer.appendChild(toast);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toastContainer.removeChild(toast);
            }, 300);
        }, 3000);
    }

    // Initialize active trade updates and price chart if there's an active trade
    if (document.getElementById('active-trade-section')) {
        // Initial update
        updateActiveTrade();
        
        // The interval is now managed within updateActiveTrade
    }
    
    // Performance chart functionality
    let performanceChart = null;
    let chartMode = 'profit'; // 'profit' or 'cumulative'
    
    // Initialize performance chart
    function initPerformanceChart() {
        const performanceChartCanvas = document.getElementById('performance-chart');
        if (!performanceChartCanvas) return;
        
        fetch('get_performance_data.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.performance_data) {
                    const chartData = processPerformanceData(data.performance_data, chartMode);
                    
                    performanceChart = new Chart(performanceChartCanvas, {
                        type: 'bar',
                        data: {
                            labels: chartData.labels,
                            datasets: [{
                                label: chartMode === 'profit' ? 'Profit/Loss per Trade' : 'Cumulative Profit/Loss',
                                data: chartData.values,
                                backgroundColor: chartData.colors,
                                borderColor: chartData.borderColors,
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: chartMode === 'profit'
                                }
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const dataPoint = data.performance_data[context.dataIndex];
                                            if (chartMode === 'profit') {
                                                return [
                                                    `Symbol: ${dataPoint.symbol}`,
                                                    `Profit: ${dataPoint.profit} EUR (${dataPoint.profit_percentage}%)`
                                                ];
                                            } else {
                                                return `Cumulative Profit: ${dataPoint.cumulative_profit} EUR`;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            })
            .catch(error => console.error('Error fetching performance data:', error));
    }
    
    // Process performance data for chart display
    function processPerformanceData(data, mode) {
        const labels = [];
        const values = [];
        const colors = [];
        const borderColors = [];
        
        data.forEach(item => {
            labels.push(item.date);
            
            if (mode === 'profit') {
                values.push(item.profit);
                
                // Set color based on profit value
                if (item.profit > 0) {
                    colors.push('rgba(40, 167, 69, 0.5)');
                    borderColors.push('rgb(40, 167, 69)');
                } else {
                    colors.push('rgba(220, 53, 69, 0.5)');
                    borderColors.push('rgb(220, 53, 69)');
                }
            } else { // cumulative mode
                values.push(item.cumulative_profit);
                
                // Set color based on cumulative profit value
                if (item.cumulative_profit >= 0) {
                    colors.push('rgba(0, 123, 255, 0.5)');
                    borderColors.push('rgb(0, 123, 255)');
                } else {
                    colors.push('rgba(108, 117, 125, 0.5)');
                    borderColors.push('rgb(108, 117, 125)');
                }
            }
        });
        
        return { labels, values, colors, borderColors };
    }
    
    // Toggle between profit and cumulative chart views
    const profitChartBtn = document.getElementById('view-profit-chart');
    const cumulativeChartBtn = document.getElementById('view-cumulative-chart');
    
    if (profitChartBtn && cumulativeChartBtn) {
        profitChartBtn.addEventListener('click', function() {
            if (chartMode !== 'profit') {
                chartMode = 'profit';
                updateChartMode();
                
                // Update button states
                profitChartBtn.classList.add('active');
                cumulativeChartBtn.classList.remove('active');
            }
        });
        
        cumulativeChartBtn.addEventListener('click', function() {
            if (chartMode !== 'cumulative') {
                chartMode = 'cumulative';
                updateChartMode();
                
                // Update button states
                cumulativeChartBtn.classList.add('active');
                profitChartBtn.classList.remove('active');
            }
        });
        
        // Set default active button
        profitChartBtn.classList.add('active');
    }
    
    // Update chart mode
    function updateChartMode() {
        if (performanceChart) {
            performanceChart.destroy();
        }
        initPerformanceChart();
    }
    
    // Initialize performance chart
    initPerformanceChart();
    
    // Add refresh button functionality
    const refreshBtn = document.getElementById('refresh-btn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            location.reload();
        });
    }
});
