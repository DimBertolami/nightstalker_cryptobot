document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM Content Loaded.');
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
        console.log('Attempting to populate coin select.');
        try {
            const response = await fetch('api/get-portfolio-coins.php');
            console.log('Response from get-portfolio-coins.php:', response);
            const coins = await response.json();
            console.log('Coins data:', coins);

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

    // Function to load and render the chart with multiple technical indicators
    async function loadChart(coinId) {
        if (!coinId) return;

        // For testing: use sample data instead of fetching from API
        const sampleHistory = [
            { time: '2023-07-01T00:00:00Z', price: 30000, sma: 29900, ema: 29850, rsi: 45 },
            { time: '2023-07-01T01:00:00Z', price: 30100, sma: 29950, ema: 29900, rsi: 50 },
            { time: '2023-07-01T02:00:00Z', price: 30200, sma: 30000, ema: 29950, rsi: 55 },
            { time: '2023-07-01T03:00:00Z', price: 30300, sma: 30050, ema: 30000, rsi: 60 },
            { time: '2023-07-01T04:00:00Z', price: 30400, sma: 30100, ema: 30050, rsi: 65 },
            { time: '2023-07-01T05:00:00Z', price: 30500, sma: 30150, ema: 30100, rsi: 70 }
        ];

        try {
            const response = await fetch(`api/get-chart-data-dev.php?coin_id=${coinId}`);
            const data = await response.json();

            if (data.error) {
                console.error('Error fetching chart data:', data.error);
                return;
            }

            const history = data.history;

            // Prepare datasets for price and indicators
            const datasets = [
                {
                    label: 'Price',
                    data: history.map(point => ({ x: new Date(point.time), y: point.price })),
                    borderColor: 'rgb(255, 255, 0)', // Bright yellow
                    tension: 0.1,
                    fill: false,
                    pointRadius: 0,
                },
                {
                    label: 'SMA',
                    data: history.map(point => ({ x: new Date(point.time), y: point.sma })),
                    borderColor: 'rgb(0, 128, 255)', // Blue
                    borderDash: [5, 5],
                    tension: 0.1,
                    fill: false,
                    pointRadius: 0,
                },
                {
                    label: 'EMA',
                    data: history.map(point => ({ x: new Date(point.time), y: point.ema })),
                    borderColor: 'rgb(255, 0, 0)', // Red
                    borderDash: [10, 5],
                    tension: 0.1,
                    fill: false,
                    pointRadius: 0,
                },
                {
                    label: 'RSI',
                    data: history.map(point => ({ x: new Date(point.time), y: point.rsi })),
                    borderColor: 'rgb(0, 255, 0)', // Green
                    tension: 0.1,
                    fill: false,
                    pointRadius: 0,
                    yAxisID: 'rsi-axis', // Use a separate y-axis for RSI
                }
            ];

            // Chart options with additional y-axis for RSI
            const chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'minute',
                        },
                        title: {
                            display: true,
                            text: 'Date',
                        },
                    },
                    y: {
                        type: 'linear',
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Price (USD)',
                        },
                    },
                    'rsi-axis': {
                        type: 'linear',
                        position: 'right',
                        min: 0,
                        max: 100,
                        grid: {
                            drawOnChartArea: false,
                        },
                        title: {
                            display: true,
                            text: 'RSI',
                        },
                    },
                },
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    },
                    legend: {
                        display: true,
                        position: 'top',
                    },
                },
            };

            if (priceChart) {
                priceChart.data.labels = history.map(point => new Date(point.time));
                priceChart.data.datasets = datasets;
                priceChart.options = chartOptions;
                priceChart.update();
            } else {
                priceChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: history.map(point => new Date(point.time)),
                        datasets: datasets,
                    },
                    options: chartOptions,
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
        console.log('populateCoinSelect finished.');
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
    }, 2000);
});
