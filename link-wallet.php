<?php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$title = "Link Crypto Wallet";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card">
                <div class="card-header">
                    <h4>Link Your Wallet</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <button id="link-phantom" class="btn btn-outline-primary">
                            <i class="fas fa-wallet"></i> Connect Phantom
                        </button>
                    </div>
                    <div id="wallet-status" class="alert alert-info d-none"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('link-phantom').addEventListener('click', async () => {
    if (window?.phantom?.solana?.isPhantom) {
        try {
            const response = await window.phantom.solana.connect();
            const publicKey = response.publicKey.toString();
            
            const link = await fetch('wallet-auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    provider: 'phantom',
                    publicKey: publicKey
                })
            });
            
            const result = await link.json();
            
            const statusEl = document.getElementById('wallet-status');
            statusEl.classList.remove('d-none');
            
            if (result.success) {
                statusEl.classList.remove('alert-info');
                statusEl.classList.add('alert-success');
                statusEl.textContent = 'Wallet linked successfully!';
            } else {
                statusEl.classList.remove('alert-info');
                statusEl.classList.add('alert-danger');
                statusEl.textContent = result.message || 'Linking failed';
            }
        } catch (err) {
            console.error(err);
            alert("Connection failed");
        }
    } else {
        alert("Please install Phantom Wallet first");
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
