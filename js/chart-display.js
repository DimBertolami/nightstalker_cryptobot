document.addEventListener('DOMContentLoaded', () => {
    const coinSelect = document.getElementById('coinSelect');
    const loadChartButton = document.getElementById('loadChartButton');
    const ctx = document.getElementById('priceChart').getContext('2d');
    let priceChart = null;
    let countdownInterval = null;

    // Function to create and style the countdown timer
    function createCountdownTimer() {
        const countdownContainer = document.getElementById('countdown-timer-container');
        if (!document.getElementById('countdown-timer')) {
            const timerElement = document.createElement('div');
            timerElement.id = 'countdown-timer';
            timerElement.style.position = 'absolute';
            timerElement.style.top = '50%';
            timerElement.style.left = '50%';
            timerElement.style.transform = 'translate(-50%, -50%)';
            timerElement.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
            timerElement.style.color = '#ff0000';
            timerElement.style.padding = '20px';
            timerElement.style.borderRadius = '10px';
            timerElement.style.fontSize = '48px';
            timerElement.style.fontWeight = 'bold';
            timerElement.style.zIndex = '10';
            timerElement.innerHTML = '<span id="countdown-time">30</span>s';
            countdownContainer.appendChild(timerElement);
        }
    }

    // Function to update the countdown timer
    function updateCountdown(endTime) {
        const now = new Date().getTime();
        const distance = endTime - now;
        const countdownTimer = document.getElementById('countdown-timer');

        if (distance <= 0) {
            clearInterval(countdownInterval);
            countdownInterval = null;
            if (countdownTimer) {
                countdownTimer.style.display = 'none';
            }
            return;
        }

        const seconds = Math.ceil(distance / 1000);
        if (countdownTimer) {
            const timeSpan = countdownTimer.querySelector('#countdown-time');
            if(timeSpan) {
                timeSpan.textContent = seconds;
            }
            if (countdownTimer.style.display !== 'block') {
                countdownTimer.style.display = 'block';
            }
        }
    }

    // Function to fetch and populate coin list
    function createOrUpdateChart(coinId) {
        loadChart(coinId);
    }

    // Listen for localStorage events to trigger chart refresh
    window.addEventListener('storage', (event) => {
        if (event.key === 'nightstalker_chart_refresh') {
            const selectedCoinId = coinSelect.value;
            if (selectedCoinId) {
                createOrUpdateChart(selectedCoinId);
            }
        }
    });

    // Function to fetch and populate coin list
    async function populateCoinSelect() {
        try {
            const response = await fetch('api/get-portfolio-coins.php');
            const coins = await response.json();

            if (coins.error) {
                console.error('Error fetching coins:', coins.error);
                return;
            }

            coinSelect.innerHTML = ''; // Clear existing options
            coins.forEach(coin => {
                const option = document.createElement('option');
                option.value = coin.id;
                option.textContent = coin.name;
                coinSelect.appendChild(option);
            });

            // Load chart for the first coin by default if available
            if (coins.length > 0) {
                loadChart(coins[0].id);
            }

        } catch (error) {
            console.error('Failed to fetch coin list:', error);
        }
    }

    // Function to load and render the chart
    async function loadChart(coinId) {
        if (!coinId) return;

        try {
            const response = await fetch(`api/get-chart-data.php?coin_id=${coinId}`);
            const data = await response.json();

            if (data.error) {
                console.error('Error fetching chart data:', data.error);
                return;
            }

            const history = data.history;
            const apex = data.apex;
            const purchaseTime = data.purchase_time;
            const latestRecordedTime = data.latest_recorded_time;
            const coinStatus = data.coin_status;
            const dropStartTimestamp = data.drop_start_timestamp;

            if (coinStatus === 'dropping' && dropStartTimestamp) {
                if (!countdownInterval) { // Only start if not already running
                    const endTime = dropStartTimestamp + 30000; // 30 seconds after drop starts
                    createCountdownTimer();
                    countdownInterval = setInterval(() => updateCountdown(endTime), 1000);
                }
            } else {
                if (countdownInterval) {
                    clearInterval(countdownInterval);
                    countdownInterval = null;
                }
                const countdownTimer = document.getElementById('countdown-timer');
                if (countdownTimer) {
                    countdownTimer.style.display = 'none';
                }
            }

            const chartData = {
                labels: history.map(point => new Date(point.time)),
                datasets: [
                    {
                        label: 'Price',
                        data: history.map(point => point.price),
                        borderColor: 'rgb(255, 255, 0)', // Bright yellow
                        tension: 0.1,
                        fill: false,
                        pointRadius: 0, // Hide points for line segments
                        hoverBorderColor: 'rgb(255, 255, 255)', // White for highlight
                        hoverBorderWidth: 3 // Thicker line on hover
                    }
                ]
            };

            const annotations = {};

            // Purchase Time Annotation
            if (purchaseTime) {
                annotations.purchaseLine = {
                    type: 'line',
                    xMin: new Date(purchaseTime),
                    xMax: new Date(purchaseTime),
                    borderColor: 'rgb(0, 128, 0)', // Green for purchase
                    borderWidth: 2,
                    borderDash: [5, 5],
                    label: {
                        content: 'Purchase',
                        enabled: true,
                        position: 'top'
                    }
                };
            }

            if (apex) {
                annotations.apexLine = {
                    type: 'line',
                    xMin: new Date(apex.timestamp),
                    xMax: new Date(apex.timestamp),
                    borderColor: 'rgb(255, 99, 132)',
                    borderWidth: 2,
                    label: {
                        content: `Apex Price: ${apex.price.toFixed(2)}`,
                        enabled: true,
                        position: 'top'
                    }
                };
                annotations.apexPoint = {
                    type: 'point',
                    xValue: new Date(apex.timestamp),
                    yValue: apex.price,
                    backgroundColor: 'rgb(255, 99, 132)',
                    borderColor: 'rgb(255, 99, 132)',
                    radius: 5,
                    pointStyle: 'star',
                    hoverRadius: 7
                };
            }

            // Determine x-axis min and max
            let xMin = purchaseTime;
            let xMax = latestRecordedTime;

            // If coin is sold, extend xMax slightly beyond drop_start_timestamp
            if (coinStatus === 'sold' && dropStartTimestamp) {
                // Add a buffer (e.g., 60 seconds) after the drop_start_timestamp
                xMax = dropStartTimestamp + (60 * 1000); // 60 seconds buffer
            } else if (latestRecordedTime) {
                // If not sold, extend xMax slightly beyond the last recorded price
                xMax = latestRecordedTime + (60 * 1000); // 60 seconds buffer
            }

            // Calculate time range in milliseconds
            const timeRange = xMax && xMin ? new Date(xMax) - new Date(xMin) : 0;

            // Determine appropriate time unit and stepSize based on timeRange
            let timeUnit = 'second';
            let stepSize = 3;

            if (timeRange > 1000 * 60 * 60 * 24) { // More than 1 day
                timeUnit = 'hour';
                stepSize = 1;
            } else if (timeRange > 1000 * 60 * 60) { // More than 1 hour
                timeUnit = 'minute';
                stepSize = 5;
            } else if (timeRange > 1000 * 60) { // More than 1 minute
                timeUnit = 'second';
                stepSize = 10;
            }

            // Validate xMin and xMax to prevent too large range errors
            if (timeRange > 1000 * 60 * 60 * 24 * 7) { // More than 7 days
                console.warn('Time range too large for chart, adjusting max to min + 7 days');
                xMax = new Date(new Date(xMin).getTime() + 1000 * 60 * 60 * 24 * 7);
            }

            const chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: timeUnit,
                            stepSize: stepSize,
                            displayFormats: {
                                second: 'h:mm:ss a',
                                minute: 'h:mm a',
                                hour: 'h:mm a',
                                day: 'MMM d',
                                week: 'MMM d',
                                month: 'MMM yyyy',
                            }
                        },
                        min: xMin ? new Date(xMin) : undefined,
                        max: xMax ? new Date(xMax) : undefined,
                        title: {
                            display: true,
                            text: 'Date',
                            color: '#FFFFFF', // White color for title
                            font: { size: 14 }
                        },
                        ticks: {
                            source: 'auto',
                            maxRotation: 0,
                            minRotation: 0,
                            autoSkipPadding: 10, // Add padding between labels
                            color: '#E0E0E0', // Light gray for tick labels
                            font: { size: 10 },
                            callback: function(value, index, values) {
                                // Only show time for smaller units, date for larger
                                if (timeUnit === 'second' || timeUnit === 'minute' || timeUnit === 'hour') {
                                    return new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                                } else {
                                    return new Date(value).toLocaleDateString();
                                }
                            }
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Price (USD)',
                            color: '#FFFFFF', // White color for title
                            font: { size: 14 }
                        },
                        beginAtZero: false,
                        ticks: {
                            color: '#E0E0E0', // Light gray for tick labels
                            font: { size: 10 },
                            callback: function(value, index, values) {
                                // Format price with appropriate precision
                                return new Intl.NumberFormat('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 8 // Adjust as needed for coin prices
                                }).format(value);
                            }
                        }
                    }
                },
                plugins: {
                    annotation: {
                        annotations: annotations
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: 'USD',
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 8 // Consistent with Y-axis ticks
                                    }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                }
            };

            if (priceChart) {
                priceChart.data.labels = chartData.labels;
                priceChart.data.datasets[0].data = chartData.datasets[0].data;
                priceChart.options.scales.x.min = xMin;
                priceChart.options.scales.x.max = xMax;
                priceChart.options.scales.x.time.unit = timeUnit;
                priceChart.options.scales.x.time.stepSize = stepSize;
                priceChart.options.plugins.annotation.annotations = annotations;
                priceChart.update({
                    duration: 800,
                    easing: 'easeOutQuart'
                });
            } else {
                priceChart = new Chart(ctx, {
                    type: 'line',
                    data: chartData,
                    options: chartOptions
                });
            }
        } catch (error) {
            console.error('Failed to load chart:', error);
        }
    }

    // Event Listeners
    loadChartButton.addEventListener('click', () => {
        const selectedCoinId = coinSelect.value;
        createOrUpdateChart(selectedCoinId);
    });

    // Initial population of coin select dropdown
    populateCoinSelect().then(() => {
        // Load chart for first coin immediately after population
        const initialCoinId = coinSelect.value;
        if (initialCoinId) {
            createOrUpdateChart(initialCoinId);
        }
    });

    // Auto-refresh chart
    setInterval(() => {
        const selectedCoinId = coinSelect.value;
        if (selectedCoinId) {
            loadChart(selectedCoinId);
        }
    }, 1500);
});
