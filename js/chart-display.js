document.addEventListener('DOMContentLoaded', () => {
    const coinSelect = document.getElementById('coinSelect');
    const loadChartButton = document.getElementById('loadChartButton');
    const ctx = document.getElementById('priceChart').getContext('2d');
    let priceChart = null;

    // Function to fetch and populate coin list
    async function populateCoinSelect() {
        try {
            // You'll need to create a PHP endpoint (e.g., api/get-coins-list.php)
            // that returns a JSON array of { id: 'bitcoin', name: 'Bitcoin' } objects.
            const response = await fetch('api/get-coins-list.php'); // TODO: Implement this PHP endpoint
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

            const chartData = {
                labels: history.map(point => new Date(point.time)),
                datasets: [
                    {
                        label: 'Price',
                        data: history.map(point => point.price),
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1,
                        fill: false,
                        pointRadius: 0 // Hide points for line segments
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

            const chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'second',
                            stepSize: 3 // Force labels every 3 seconds
                        },
                        min: xMin ? new Date(xMin) : undefined,
                        max: xMax ? new Date(xMax) : undefined,
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Price (USD)'
                        },
                        beginAtZero: false
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
                                        maximumFractionDigits: 6
                                    }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                }
            };

            if (priceChart) {
                priceChart.destroy(); // Destroy existing chart before creating a new one
            }
            priceChart = new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: chartOptions
            });

        } catch (error) {
            console.error('Failed to load chart:', error);
        }
    }

    // Event Listeners
    loadChartButton.addEventListener('click', () => {
        const selectedCoinId = coinSelect.value;
        loadChart(selectedCoinId);
    });

    // Initial population of coin select dropdown
    populateCoinSelect();
});