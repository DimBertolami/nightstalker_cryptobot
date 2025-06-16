<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/TradingLogger.php';

// Initialize the trading logger
$logger = new TradingLogger();

// Get filter parameters
$eventType = isset($_GET['event_type']) ? $_GET['event_type'] : '';
$symbol = isset($_GET['symbol']) ? $_GET['symbol'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$strategy = isset($_GET['strategy']) ? $_GET['strategy'] : 'new_coin_strategy';

// Get events with filters
$events = $logger->getFilteredEvents($strategy, $eventType, $symbol, $dateFrom, $dateTo, $limit);

// Get unique symbols for the filter dropdown
$symbols = $logger->getUniqueSymbols($strategy);

// Check if we're in test mode
$testMode = true; // Default to test mode for safety
$configFile = __DIR__ . '/../crons/execute_new_coin_strategy.php';
if (file_exists($configFile)) {
    $configContent = file_get_contents($configFile);
    if (preg_match('/\$testMode\s*=\s*(true|false)/', $configContent, $matches)) {
        $testMode = $matches[1] === 'true';
    }
}

// Helper function to format event data
function formatEventData($event) {
    $eventData = $event['event_data'];
    $output = '';
    
    switch ($event['event_type']) {
        case 'buy':
            $output = 'Bought at ' . formatCurrency($eventData['price'], 8) . 
                     '<br>Amount: ' . $eventData['amount'] . 
                     '<br>Cost: ' . formatCurrency($eventData['cost'], 2) . ' ' . ($eventData['currency'] ?? 'EUR');
            
            if (isset($eventData['market_cap'])) {
                $output .= '<br>Market Cap: $' . formatCurrency($eventData['market_cap'], 0);
            }
            if (isset($eventData['volume'])) {
                $output .= '<br>Volume: $' . formatCurrency($eventData['volume'], 0);
            }
            if (isset($eventData['age_hours'])) {
                $output .= '<br>Age: ' . round($eventData['age_hours'], 1) . ' hours';
            }
            break;
            
        case 'sell':
            $profitClass = $eventData['profit'] > 0 ? 'text-success' : 'text-danger';
            $output = 'Sold at ' . formatCurrency($eventData['sell_price'], 8) . 
                     '<br>Buy Price: ' . formatCurrency($eventData['buy_price'], 8) . 
                     '<br>Profit: <span class="' . $profitClass . '">' . 
                     formatCurrency($eventData['profit'], 2) . ' ' . ($eventData['currency'] ?? 'EUR') . 
                     ' (' . formatPercentage($eventData['profit_percentage']) . ')</span>';
            
            if (isset($eventData['holding_time_seconds'])) {
                $output .= '<br>Holding Time: ' . formatDuration($eventData['holding_time_seconds']);
            }
            break;
            
        case 'monitor':
            $changeClass = ($eventData['price_change'] ?? 0) > 0 ? 'text-success' : 'text-danger';
            $output = 'Price: ' . formatCurrency($eventData['current_price'], 8) . 
                     '<br>Change: <span class="' . $changeClass . '">' . 
                     formatPercentage($eventData['price_change'] ?? 0) . '</span>';
            break;
            
        default:
            $output = json_encode($eventData);
    }
    
    return $output;
}

// Helper function to get badge class for event type
function getEventBadgeClass($eventType) {
    switch ($eventType) {
        case 'buy': return 'bg-success';
        case 'sell': return 'bg-danger';
        case 'monitor': return 'bg-warning';
        case 'error': return 'bg-danger';
        default: return 'bg-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Logs - Night Stalker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
    <?php include_once('nav.php'); ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Trading Logs</h2>
            <div>
                <span class="badge bg-secondary">Last updated: <?php echo date('Y-m-d H:i:s'); ?></span>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-funnel"></i> Filters
            </div>
            <div class="card-body">
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label for="event_type" class="form-label">Event Type</label>
                        <select name="event_type" id="event_type" class="form-select">
                            <option value="">All</option>
                            <option value="buy" <?php echo $eventType === 'buy' ? 'selected' : ''; ?>>Buy</option>
                            <option value="sell" <?php echo $eventType === 'sell' ? 'selected' : ''; ?>>Sell</option>
                            <option value="monitor" <?php echo $eventType === 'monitor' ? 'selected' : ''; ?>>Monitor</option>
                            <option value="error" <?php echo $eventType === 'error' ? 'selected' : ''; ?>>Error</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="symbol" class="form-label">Symbol</label>
                        <select name="symbol" id="symbol" class="form-select">
                            <option value="">All</option>
                            <?php foreach ($symbols as $sym): ?>
                                <option value="<?php echo $sym; ?>" <?php echo $symbol === $sym ? 'selected' : ''; ?>>
                                    <?php echo $sym; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="text" class="form-control date-picker" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="text" class="form-control date-picker" id="date_to" name="date_to" value="<?php echo $dateTo; ?>" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="col-md-2">
                        <label for="limit" class="form-label">Limit</label>
                        <select name="limit" id="limit" class="form-select">
                            <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
                            <option value="200" <?php echo $limit === 200 ? 'selected' : ''; ?>>200</option>
                            <option value="500" <?php echo $limit === 500 ? 'selected' : ''; ?>>500</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Logs Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-journal-text"></i> Trading Events
                </div>
                <div>
                    <span class="badge bg-info"><?php echo count($events); ?> events found</span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Time</th>
                                <th>Event</th>
                                <th>Symbol</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($events)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No events found matching your criteria</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($events as $event): ?>
                                    <?php 
                                        $eventData = $event['event_data'];
                                        $badgeClass = getEventBadgeClass($event['event_type']);
                                    ?>
                                    <tr>
                                        <td><?php echo $event['id']; ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($event['event_time'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo strtoupper($event['event_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $eventData['symbol'] ?? 'N/A'; ?></td>
                                        <td><?php echo formatEventData($event); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize date pickers
            flatpickr(".date-picker", {
                dateFormat: "Y-m-d",
                allowInput: true
            });
        });
    </script>
</body>
</html>
