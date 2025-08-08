<?php
// File: /opt/lampp/htdocs/NS/system-tools/vph.php

// Assuming your config and header/footer files are in the 'includes' directory.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php'; 
require_once __DIR__ . '/../includes/functions.php';

// Set page title
$title = 'Portfolio Price History';

// It's good practice to include a header file for consistent layout
if (file_exists(__DIR__ . '/../includes/header.php')) {
    include __DIR__ . '/../includes/header.php';
} else {
    // Fallback basic header
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Price History</title>';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">';
    echo '<style>
        body { background-color: #212529; color: #f8f9fa; }
        .table-responsive table {
            table-layout: fixed; /* Use fixed layout for better control over column widths */
            min-width: 768px; /* Ensure table is at least this wide */
        }
        .table-responsive td, .table-responsive th {
            word-break: break-word;
            font-size: 0.85em;
            padding: 0.5em; /* Slightly more padding for readability */
        }
        /* Specific widths for price_history table columns */
        /* ID */
        .table-responsive table.table-sm th:nth-child(1),
        .table-responsive table.table-sm td:nth-child(1) { width: 10%; }
        /* Coin Symbol */
        .table-responsive table.table-sm th:nth-child(2),
        .table-responsive table.table-sm td:nth-child(2) { width: 20%; }
        /* Price */
        .table-responsive table.table-sm th:nth-child(3),
        .table-responsive table.table-sm td:nth-child(3) { width: 30%; }
        /* Timestamp */
        .table-responsive table.table-sm th:nth-child(4),
        .table-responsive table.table-sm td:nth-child(4) { width: 40%; }
    </style>';
    echo '</head><body><div class="container-fluid pt-4">';
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-chart-line me-2"></i>Portfolio Price History</h1>
            <p class="lead">View the price evolution for coins currently in your portfolio.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card bg-dark border-secondary">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Price Evolution</h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $db = getDBConnection();

                        if (!$db) {
                            echo "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle me-2'></i>Database connection failed. Please check your configuration.</div>";
                        } else {
                            // 1. Get the list of coin_ids from the portfolio
                            $portfolioStmt = $db->query("SELECT DISTINCT coin_id FROM portfolio WHERE amount > 0");
                            $portfolioCoinIds = $portfolioStmt->fetchAll(PDO::FETCH_COLUMN);

                            if (empty($portfolioCoinIds)) {
                                echo "<div class='alert alert-info'><i class='fas fa-info-circle me-2'></i>No coins found in your portfolio.</div>";
                            } else {
                                // --- Display Coin Apex Prices ---
                                $apexStmt = $db->prepare("SELECT coin_id, apex_price, apex_timestamp, drop_start_timestamp, status FROM coin_apex_prices WHERE coin_id IN (" . implode(',', array_fill(0, count($portfolioCoinIds), '?')) . ") ORDER BY coin_id");
                                $apexStmt->execute($portfolioCoinIds);
                                $apexPrices = $apexStmt->fetchAll(PDO::FETCH_ASSOC);

                                if (!empty($apexPrices)) {
                    ?>
                                    <h5 class="mt-3 mb-3"><i class="fas fa-chart-area me-2"></i>Coin Apex Prices</h5>
                                    <div class="table-responsive mb-4">
                                        <table class="table table-dark table-striped table-hover table-bordered table-sm">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th><i class="fas fa-coins me-1"></i>Coin Symbol</th>
                                                    <th><i class="fas fa-arrow-up me-1"></i>Apex Price</th>
                                                    <th><i class="far fa-clock me-1"></i>Apex Timestamp</th>
                                                    <th><i class="fas fa-hourglass-start me-1"></i>Drop Start</th>
                                                    <th><i class="fas fa-info-circle me-1"></i>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($apexPrices as $apexRecord): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($apexRecord['coin_id']); ?></td>
                                                    <td>&euro; <?php echo htmlspecialchars(rtrim(rtrim(number_format((float)$apexRecord['apex_price'], 8, '.', ''), '0'), '.')); ?></td>
                                                    <td><?php echo htmlspecialchars($apexRecord['apex_timestamp']); ?></td>
                                                    <td><?php echo htmlspecialchars($apexRecord['drop_start_timestamp'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars(ucfirst($apexRecord['status'])); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                    <?php
                                }

                                // --- Display Price History ---
                                $placeholders = implode(',', array_fill(0, count($portfolioCoinIds), '?'));
                                $historyStmt = $db->prepare("SELECT id, coin_id, price, recorded_at FROM price_history WHERE coin_id IN ($placeholders) ORDER BY recorded_at DESC LIMIT 1000");
                                $historyStmt->execute($portfolioCoinIds);
                                $priceHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

                                if (empty($priceHistory)) {
                                    echo "<div class='alert alert-info'><i class='fas fa-info-circle me-2'></i>No price history data found for the coins in your portfolio.</div>";
                                } else {
                    ?>
                                    <h5 class="mt-3 mb-3"><i class="fas fa-chart-bar me-2"></i>Detailed Price History</h5>
                                    <p class="text-muted">
                                        Showing the last <strong><?php echo count($priceHistory); ?></strong> price updates for your portfolio coins.
                                    </p>
                                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                        <table class="table table-dark table-striped table-hover table-bordered table-sm">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th><i class="fas fa-hashtag me-1"></i>ID</th>
                                                    <th><i class="fas fa-coins me-1"></i>Coin Symbol</th>
                                                    <th><i class="fas fa-euro-sign me-1"></i>Price</th>
                                                    <th><i class="far fa-clock me-1"></i>Timestamp</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($priceHistory as $record): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($record['id']); ?></td>
                                                    <td><?php echo htmlspecialchars($record['coin_id']); ?></td>
                                                    <td>&euro; <?php echo htmlspecialchars(rtrim(rtrim(number_format((float)$record['price'], 8, '.', ''), '0'), '.')); ?></td>
                                                    <td><?php echo htmlspecialchars($record['recorded_at']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                    <?php
                                }
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Database Error in vph.php: " . $e->getMessage());
                        echo "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle me-2'></i>An error occurred while fetching data. Please check the logs for details.</div>";
                    } catch (Exception $e) {
                        error_log("General Error in vph.php: " . $e->getMessage());
                        echo "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle me-2'></i>An unexpected error occurred.</div>";
                    }
                    ?>
                </div>
                <div class="card-footer text-end">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to System Tools
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include a footer file for consistent layout
if (file_exists(__DIR__ . '/../includes/footer.php')) {
    include __DIR__ . '/../includes/footer.php';
} else {
    // Fallback basic footer
    echo '</div><script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script></body></html>';
}
?>