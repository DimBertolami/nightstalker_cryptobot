/**
 * Renders the main Chart.js graph for the Bot Performance Dashboard.
 */
async function renderBotPerformanceCharts() {
    try {
        const response = await fetch('http://localhost:5000/api/bot/performance/chart_data'); 
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const chartData = await response.json();

        const ctx = document.getElementById('bot-performance-main-chart').getContext('2d');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Decision Accuracy',
                        data: chartData.decision_accuracy,
                        borderColor: 'rgb(147, 197, 253)', // Light blue
                        backgroundColor: 'rgba(147, 197, 253, 0.2)',
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Cumulative Profit',
                        data: chartData.cumulative_profit,
                        borderColor: 'rgb(52, 211, 153)', // Green
                        backgroundColor: 'rgba(52, 211, 153, 0.2)',
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y1',
                    },
                    {
                        label: 'Bot Confidence',
                        data: chartData.bot_confidence,
                        borderColor: 'rgb(251, 191, 36)', // Yellow
                        borderDash: [5, 5],
                        tension: 0.4,
                        yAxisID: 'y',
                    },
                    // Add more datasets as needed for Total Trades, Significant Trades
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                stacked: false,
                plugins: {
                    title: {
                        display: false,
                    },
                    annotation: {
                        annotations: {
                            accuracyLine: {
                                type: 'line',
                                yMin: 75,
                                yMax: 75,
                                borderColor: 'rgb(255, 99, 132)',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                label: {
                                    display: true,
                                    content: 'Accuracy 75%',
                                    position: 'start'
                                }
                            },
                            breakEvenLine: {
                                type: 'line',
                                yMin: 0,
                                yMax: 0,
                                borderColor: 'rgb(255, 99, 132)',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                label: {
                                    display: true,
                                    content: 'Break-even',
                                    position: 'start'
                                },
                                yAxisID: 'y1' // Associate with the profit axis
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'category',
                        labels: chartData.labels,
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Percentage'
                        },
                        min: 0,
                        max: 100
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Profit'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                }
            }
        });

    } catch (error) {
        console.error("Could not render bot performance charts:", error);
    }
}

// Call the function to render charts when the DOM is loaded
document.addEventListener('DOMContentLoaded', renderBotPerformanceCharts);