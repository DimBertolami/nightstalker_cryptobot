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
        console.log("Chart Data from API:", chartData);

        const ctx = document.getElementById('bot-performance-main-chart').getContext('2d');
        console.log("Chart Context:", ctx);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Decision Accuracy',
                        data: chartData.decision_accuracy,
                        borderColor: '#8e44ad', // Purple
                        backgroundColor: 'rgba(142, 68, 173, 0.2)', // Purple with transparency
                        fill: 'origin', // Fill to origin
                        tension: 0.4,
                        yAxisID: 'y',
                        pointRadius: 3, // Show points
                    },
                    {
                        label: 'Cumulative Profit',
                        data: chartData.cumulative_profit,
                        borderColor: '#28a745', // Green
                        backgroundColor: 'rgba(40, 167, 69, 0.2)', // Green with transparency
                        fill: false, // No fill for this line
                        tension: 0.4,
                        yAxisID: 'y1',
                        pointRadius: 3, // Show points
                    },
                    {
                        label: 'Bot Confidence',
                        data: chartData.bot_confidence,
                        borderColor: '#ffc107', // Yellow
                        borderDash: [5, 5],
                        tension: 0.4,
                        yAxisID: 'y',
                        pointRadius: 3, // Show points
                    },
                    // {
                    //     label: 'Total Trades',
                    //     data: chartData.total_trades, // Assuming this data exists
                    //     type: 'bar', // Bar chart for total trades
                    //     backgroundColor: 'rgba(108, 117, 125, 0.5)', // Gray with transparency
                    //     yAxisID: 'y2', // New Y-axis for bar charts
                    //     barPercentage: 0.7,
                    //     categoryPercentage: 0.8,
                    // },
                    // {
                    //     label: 'Significant Trades',
                    //     data: chartData.significant_trades, // Assuming this data exists
                    //     type: 'bar', // Bar chart for significant trades
                    //     backgroundColor: 'rgba(220, 53, 69, 0.5)', // Red with transparency
                    //     yAxisID: 'y2', // New Y-axis for bar charts
                    //     barPercentage: 0.7,
                    //     categoryPercentage: 0.8,
                    // }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                height: 400, // Set a fixed height for the chart
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                stacked: false,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            tooltipFormat: 'MMM d',
                            displayFormats: {
                                day: 'M/d'
                            },
                            parser: 'MM/dd' // Explicitly define the parser for the date format
                        },
                        ticks: {
                            color: '#b0b3b8', // X-axis tick color
                        },
                        grid: {
                            color: '#4f545c', // X-axis grid line color
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: false, // Hide title
                        },
                        ticks: {
                            color: '#b0b3b8', // Y-axis tick color
                            callback: function(value) {
                                return value + '%'; // Add percentage sign
                            }
                        },
                        grid: {
                            color: '#4f545c', // Y-axis grid line color
                        },
                        min: 0,
                        max: 100
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: false, // Hide title
                        },
                        ticks: {
                            color: '#b0b3b8', // Y1-axis tick color
                            callback: function(value) {
                                return '$' + value.toLocaleString(); // Add dollar sign and format
                            }
                        },
                        grid: {
                            drawOnChartArea: false, // Only draw grid for y
                            color: '#4f545c',
                        },
                        min: -10,
                        max: 200
                    }
                }
            }
        });

    } catch (error) {
        console.error("Could not render bot performance charts:", error);
    }
}

// Call the function to render charts when the DOM is loaded
document.addEventListener('DOMContentLoaded', renderBotPerformanceCharts);