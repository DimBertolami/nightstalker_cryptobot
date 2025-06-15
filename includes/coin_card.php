<div class="col-md-3 mb-4">
    <div class="card h-100 coin-card" data-coin-id="<?php echo $coin['id']; ?>">
        <div class="card-header <?php echo $coin['is_trending'] ? 'bg-warning text-dark' : 'bg-primary text-white'; ?>">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><?php echo $coin['name']; ?></h5>
                <span class="badge bg-<?php echo $coin['price_change_24h'] >= 0 ? 'success' : 'danger'; ?>">
                    <?php echo number_format($coin['price_change_24h'], 2); ?>%
                </span>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-6">
                    <p class="mb-1"><strong>Price:</strong></p>
                    <p class="mb-1"><strong>Volume:</strong></p>
                    <p class="mb-1"><strong>Market Cap:</strong></p>
                </div>
                <div class="col-6 text-end">
                    <p class="mb-1">$<?php echo number_format($coin['price'], 4); ?></p>
                    <p class="mb-1">$<?php echo number_format($coin['volume']); ?></p>
                    <p class="mb-1">$<?php echo number_format($coin['market_cap']); ?></p>
                </div>
            </div>
        </div>
        <div class="card-footer bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted"><?php echo $coin['symbol']; ?></small>
                <small class="text-muted"><?php echo $coin['age_hours']; ?>h old</small>
            </div>
        </div>
    </div>
</div>
