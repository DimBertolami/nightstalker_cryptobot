<?php
// Start session
session_start();

// Include configuration
require_once 'includes/config.php';

// Include header
$pageTitle = "Multi-Signature Wallet Management";
include 'includes/header.php';
?>

<div class="container my-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-shield-lock me-2"></i>
                        Multi-Signature Wallet Management
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-info-circle fs-4 me-2"></i>
                            <h5 class="mb-0">What is a Multi-Signature Wallet?</h5>
                        </div>
                        <p>A multi-signature wallet requires multiple approvals before a transaction can be executed. This adds an extra layer of security for high-value transactions and shared funds.</p>
                        <p><strong>Example:</strong> A 2-of-3 multi-signature wallet requires any 2 out of 3 designated signers to approve a transaction before it can be processed.</p>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Your Multi-Signature Wallets</h5>
                        <button class="btn btn-primary" id="create-multisig-wallet">
                            <i class="bi bi-plus-circle me-2"></i>
                            Create New Multi-Signature Wallet
                        </button>
                    </div>
                    
                    <!-- Multi-signature wallets container -->
                    <div id="multisig-wallets-container">
                        <!-- Wallets will be added here dynamically -->
                        <div class="alert alert-warning" id="no-multisig-wallets">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            You don't have any multi-signature wallets yet. Create one to get started.
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history me-2"></i>
                        Pending Transactions
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Pending transactions container -->
                    <div id="pending-transactions-container">
                        <!-- Pending transactions will be added here dynamically -->
                        <div class="alert alert-info" id="no-pending-transactions">
                            <i class="bi bi-info-circle me-2"></i>
                            No pending transactions at this time.
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-check-circle me-2"></i>
                        Completed Transactions
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Completed transactions container -->
                    <div id="completed-transactions-container">
                        <!-- Completed transactions will be added here dynamically -->
                        <div class="alert alert-info" id="no-completed-transactions">
                            <i class="bi bi-info-circle me-2"></i>
                            No completed transactions yet.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Multi-Signature Wallet Modal -->
<div class="modal fade" id="createMultisigModal" tabindex="-1" aria-labelledby="createMultisigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="createMultisigModalLabel">
                    <i class="bi bi-shield-lock me-2"></i>
                    Create Multi-Signature Wallet
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="multisig-wallet-form">
                    <div class="mb-3">
                        <label for="wallet-name" class="form-label">Wallet Name</label>
                        <input type="text" class="form-control" id="wallet-name" name="wallet_name" placeholder="Enter a name for this wallet" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="required-signatures" class="form-label">Required Signatures</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="required-signatures" name="required_signatures" min="2" max="2" value="2" required>
                            <span class="input-group-text">of</span>
                            <span class="input-group-text" id="total-signers">2</span>
                            <span class="input-group-text">signers</span>
                        </div>
                        <div class="form-text">At least 2 signatures are required for security.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span>Signers</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="add-signer">
                                <i class="bi bi-plus-circle me-1"></i>
                                Add Signer
                            </button>
                        </label>
                        
                        <div id="signers-container">
                            <!-- Initial two signer inputs -->
                            <div class="mb-3 signer-input">
                                <label class="form-label">Signer 1 Address</label>
                                <input type="text" class="form-control" name="signer[]" placeholder="Enter wallet address" required>
                            </div>
                            <div class="mb-3 signer-input">
                                <label class="form-label">Signer 2 Address</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="signer[]" placeholder="Enter wallet address" required>
                                    <button type="button" class="btn btn-outline-danger remove-signer">
                                        <i class="bi bi-dash-circle"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Make sure all signer addresses are correct. Once created, the signers cannot be changed.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="create-multisig-submit" form="multisig-wallet-form">Create Wallet</button>
            </div>
        </div>
    </div>
</div>

<!-- Propose Transaction Modal -->
<div class="modal fade" id="proposeTransactionModal" tabindex="-1" aria-labelledby="proposeTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="proposeTransactionModalLabel">
                    <i class="bi bi-send me-2"></i>
                    Propose Transaction
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="propose-transaction-form">
                    <input type="hidden" id="transaction-wallet-id" name="wallet_id">
                    <input type="hidden" id="user-experience-level" value="beginner">
                    
                    <div class="mb-3">
                        <label for="transaction-amount" class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="transaction-amount" name="amount" min="0.01" step="0.01" placeholder="Enter amount" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="transaction-recipient" class="form-label">Recipient Address</label>
                        <input type="text" class="form-control" id="transaction-recipient" name="recipient" placeholder="Enter recipient wallet address" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        This transaction will require <strong id="required-signatures-count">2</strong> signatures before it can be executed.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="propose-transaction-submit" form="propose-transaction-form">Propose Transaction</button>
            </div>
        </div>
    </div>
</div>

<!-- Safety Warning Modal -->
<div class="modal fade" id="safetyWarningModal" tabindex="-1" aria-labelledby="safetyWarningModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="safetyWarningModalLabel">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Safety Warning
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <p><strong>High Value Transaction Detected!</strong></p>
                    <p>You are attempting to send <strong>$<span id="safety-warning-amount">0</span></strong>, which exceeds the recommended limit of <strong>$<span id="safety-threshold">0</span></strong> for beginner users.</p>
                    <p>Are you sure you want to proceed with this transaction?</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-unsafe-transaction">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Yes, I Understand the Risk
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Execute Transaction Modal -->
<div class="modal fade" id="executeTransactionModal" tabindex="-1" aria-labelledby="executeTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="executeTransactionModalLabel">
                    <i class="bi bi-check-circle me-2"></i>
                    Execute Transaction
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="execute-transaction-id">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> Once executed, this transaction cannot be reversed. Are you sure you want to proceed?
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirm-execute-transaction">
                    <i class="bi bi-check-circle me-2"></i>
                    Confirm Execution
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>

<!-- Include wallet management script -->
<script src="assets/js/wallet-management.js"></script>
