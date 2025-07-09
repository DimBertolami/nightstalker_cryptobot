<?php
/**
 * Strategy Management Page
 * 
 * Allows users to configure and activate trading strategies
 * for the Night Stalker cryptobot platform
 */

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Trading Strategies</h4>
                    <p class="card-category">Configure and activate autonomous trading strategies</p>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <button id="add-strategy-btn" class="btn btn-primary">
                                <i class="fa fa-plus"></i> Add New Strategy
                            </button>
                            <button id="run-strategies-btn" class="btn btn-success">
                                <i class="fa fa-play"></i> Run Active Strategies
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table id="strategies-table" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Strategy Name</th>
                                    <th>Description</th>
                                    <th>Configuration</th>
                                    <th>Status</th>
                                    <th>Last Run</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Strategies will be loaded here via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Strategy Configuration Modal -->
    <div class="modal fade" id="strategy-modal" tabindex="-1" role="dialog" aria-labelledby="strategy-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="strategy-modal-label">Configure Strategy</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="strategy-form">
                        <input type="hidden" id="strategy-id" name="id">
                        
                        <div class="form-group">
                            <label for="strategy-name">Strategy Name</label>
                            <input type="text" class="form-control" id="strategy-name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="strategy-description">Description</label>
                            <textarea class="form-control" id="strategy-description" name="description" rows="2"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Strategy Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="strategy-type" id="type-volume-spike" value="volume_spike" checked>
                                <label class="form-check-label" for="type-volume-spike">
                                    Volume Spike
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="strategy-type" id="type-trending-coins" value="trending_coins">
                                <label class="form-check-label" for="type-trending-coins">
                                    Trending Coins
                                </label>
                            </div>
                        </div>
                        
                        <!-- Volume Spike Strategy Config -->
                        <div id="volume-spike-config" class="strategy-config">
                            <h5>Volume Spike Configuration</h5>
                            
                            <div class="form-group">
                                <label for="vs-min-volume-increase">Minimum Volume Increase (%)</label>
                                <input type="number" class="form-control" id="vs-min-volume-increase" name="vs-min-volume-increase" value="20" min="1" max="1000">
                                <small class="form-text text-muted">Minimum percentage increase in volume to trigger a buy</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="vs-timeframe">Timeframe</label>
                                <select class="form-control" id="vs-timeframe" name="vs-timeframe">
                                    <option value="1h">1 Hour</option>
                                    <option value="4h">4 Hours</option>
                                    <option value="12h">12 Hours</option>
                                    <option value="24h" selected>24 Hours</option>
                                </select>
                                <small class="form-text text-muted">Timeframe to measure volume increase</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="vs-max-investment">Maximum Investment per Trade ($)</label>
                                <input type="number" class="form-control" id="vs-max-investment" name="vs-max-investment" value="100" min="1" max="10000">
                                <small class="form-text text-muted">Maximum dollar amount to invest in a single trade</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="vs-stop-loss">Stop Loss (%)</label>
                                <input type="number" class="form-control" id="vs-stop-loss" name="vs-stop-loss" value="5" min="1" max="50">
                                <small class="form-text text-muted">Percentage drop to trigger automatic sell</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="vs-take-profit">Take Profit (%)</label>
                                <input type="number" class="form-control" id="vs-take-profit" name="vs-take-profit" value="10" min="1" max="1000">
                                <small class="form-text text-muted">Percentage gain to trigger automatic sell</small>
                            </div>
                        </div>
                        
                        <!-- Trending Coins Strategy Config -->
                        <div id="trending-coins-config" class="strategy-config" style="display:none;">
                            <h5>Trending Coins Configuration</h5>
                            
                            <div class="form-group">
                                <label for="tc-min-market-cap">Minimum Market Cap ($)</label>
                                <input type="number" class="form-control" id="tc-min-market-cap" name="tc-min-market-cap" value="1000000" min="1000" max="10000000000">
                                <small class="form-text text-muted">Minimum market capitalization in USD</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="tc-max-age-hours">Maximum Coin Age (hours)</label>
                                <input type="number" class="form-control" id="tc-max-age-hours" name="tc-max-age-hours" value="24" min="1" max="720">
                                <small class="form-text text-muted">Only consider coins added in the last X hours</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="tc-max-investment">Maximum Investment per Trade ($)</label>
                                <input type="number" class="form-control" id="tc-max-investment" name="tc-max-investment" value="50" min="1" max="10000">
                                <small class="form-text text-muted">Maximum dollar amount to invest in a single trade</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="tc-stop-loss">Stop Loss (%)</label>
                                <input type="number" class="form-control" id="tc-stop-loss" name="tc-stop-loss" value="7" min="1" max="50">
                                <small class="form-text text-muted">Percentage drop to trigger automatic sell</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="tc-take-profit">Take Profit (%)</label>
                                <input type="number" class="form-control" id="tc-take-profit" name="tc-take-profit" value="15" min="1" max="1000">
                                <small class="form-text text-muted">Percentage gain to trigger automatic sell</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="strategy-active" name="is_active" checked>
                                <label class="custom-control-label" for="strategy-active">Active</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-strategy-btn">Save Strategy</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Strategy Results Modal -->
    <div class="modal fade" id="results-modal" tabindex="-1" role="dialog" aria-labelledby="results-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="results-modal-label">Strategy Execution Results</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="strategy-results">
                        <!-- Results will be displayed here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/strategies.js"></script>

<?php
// Include footer
include_once 'includes/footer.php';
?>
