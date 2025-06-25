    </main>

    <footer class="footer mt-auto py-3 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <span class="text-muted">Night Stalker crypto trading system by D.Bertolami</span>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="text-muted italic small ">&copy; <?= date("M Y") ?></span>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    

    <!-- Web3/Wallet Integration -->
    <script>
    // Improved Phantom Wallet initialization - runs immediately
    (function() {
        // Make sure phantom is available globally
        window.phantom = window.phantom || {};
        window.phantom.solana = window.phantom.solana || {
            isPhantom: false,
            connect: () => Promise.reject('Phantom not available'),
            on: () => {},
            off: () => {}
        };
        
        // For compatibility with both API styles
        if (window.solana && window.solana.isPhantom && !window.phantom.solana.isPhantom) {
            window.phantom.solana = window.solana;
        }
        
        console.log("Phantom wallet initialization complete");
    })();
    
    // Add event listeners after DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Wallet detection
        if (window?.phantom?.solana?.isPhantom) {
            console.log("Phantom wallet detected");
            
            // Connect handler with error catching
            document.getElementById('connect-wallet')?.addEventListener('click', function() {
                window.phantom.solana.connect()
                    .then(() => console.log("Connected to Phantom wallet"))
                    .catch(err => console.warn("Connection rejected:", err));
            });
        } else {
            console.log("Phantom wallet not detected");
        }
    });
    
    <?php if($auto_refresh_enabled ?? false): ?>
    // Auto-refresh with activity monitoring
    let lastActivity = Date.now();
    const activityEvents = ['mousemove', 'keydown', 'scroll'];
    
    activityEvents.forEach(event => {
        document.addEventListener(event, () => lastActivity = Date.now());
    });

    // setInterval(() => {
    //     if (Date.now() - lastActivity > 2000) {
    //         location.reload();
    //     }
    // }, 3000);
    <?php endif; ?>
    </script>
<?php if(defined('BASE_URL')): ?>
<script src="<?= BASE_URL ?>/assets/js/script.js"></script>
<script src="<?= BASE_URL ?>/assets/js/coins.js"></script>
<?php endif; ?>
</body>
</html>
