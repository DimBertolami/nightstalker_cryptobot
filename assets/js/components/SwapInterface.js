// Jupiter Terminal Swap Interface
Vue.component('swap-interface', {
    created() {
        // Load swap history from localStorage
        try {
            const savedHistory = localStorage.getItem('jupiterSwapHistory');
            if (savedHistory) {
                this.swapHistory = JSON.parse(savedHistory);
            }
        } catch (e) {
            console.error('Failed to load swap history:', e);
        }
    },
    template: `
    <div class="swap-interface">
        <!-- Price Chart Section -->
        <div class="mb-4">
            <price-chart 
                :input-token="inputToken" 
                :output-token="outputToken"
                :input-symbol="getTokenSymbol(inputToken)"
                :output-symbol="getTokenSymbol(outputToken)"
            ></price-chart>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>From</label>
                    <select v-model="inputToken" class="form-select">
                        <option v-for="token in tokens" :key="'input-'+token.mint" :value="token.mint">{{ token.symbol }} - {{ token.name }}</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>To</label>
                    <select v-model="outputToken" class="form-select">
                        <option v-for="token in tokens" :key="'output-'+token.mint" :value="token.mint">{{ token.symbol }} - {{ token.name }}</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="form-group mt-3">
            <label>Amount</label>
            <input type="number" v-model="amount" class="form-control" placeholder="1.0">
        </div>
        
        <button @click="getQuote" class="btn btn-primary mt-3" :disabled="loading">
            <span v-if="loading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            {{ loading ? 'Getting Quote...' : 'Get Quote' }}
        </button>
        
        <div v-if="quote" class="mt-4">
            <h5>Quote Details</h5>
            <div v-if="error" class="alert alert-danger">{{ error }}</div>
            <div class="card">
                <div class="card-body">
                    <p><strong>Input:</strong> {{ formatAmount(amount) }} {{ getTokenSymbol(inputToken) }}</p>
                    <p><strong>Output:</strong> {{ formatOutputAmount() }} {{ getTokenSymbol(outputToken) }}</p>
                    <p><strong>Price Impact:</strong> {{ quote.priceImpactPct ? (quote.priceImpactPct * 100).toFixed(2) + '%' : 'N/A' }}</p>
                    <p><strong>Route:</strong> {{ quote.routePlan ? quote.routePlan.length + ' steps' : 'Direct' }}</p>
                </div>
            </div>
            <button @click="executeSwap" class="btn btn-success mt-3">Execute Swap</button>
        </div>
        
        <!-- Swap History Section -->
        <div class="mt-5">
            <h5>Swap History</h5>
            <div v-if="swapHistory.length === 0" class="alert alert-info">
                No swap history yet. Execute a swap to see it here.
            </div>
            <div v-else class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(swap, index) in swapHistory" :key="index">
                            <td>{{ new Date(swap.timestamp).toLocaleString() }}</td>
                            <td>{{ swap.inputTokenSymbol }}</td>
                            <td>{{ swap.outputTokenSymbol }}</td>
                            <td>{{ swap.inputAmount }} → {{ swap.outputAmount }}</td>
                            <td>
                                <span class="badge bg-success">Success</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    `,
    data() {
        return {
            inputToken: 'So11111111111111111111111111111111111111112',
            outputToken: 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            amount: 1,
            quote: null,
            error: null,
            loading: false,
            tokens: [
                { mint: 'So11111111111111111111111111111111111111112', symbol: 'SOL', name: 'Solana', decimals: 9 },
                { mint: 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v', symbol: 'USDC', name: 'USD Coin', decimals: 6 },
                { mint: 'Es9vMFrzaCERmJfrF4H2FYD4KCoNkY11McCe8BenwNYB', symbol: 'USDT', name: 'Tether USD', decimals: 6 },
                { mint: 'mSoLzYCxHdYgdzU16g5QSh3i5K3z3KZK7ytfqcJm7So', symbol: 'mSOL', name: 'Marinade Staked SOL', decimals: 9 },
                { mint: '7dHbWXmci3dT8UFYWYZweBLXgycu7Y3iL6trKn1Y7ARj', symbol: 'BONK', name: 'Bonk', decimals: 5 },
                { mint: 'DezXAZ8z7PnrnRJjz3wXBoRgixCa6xjnB7YaB1pPB263', symbol: 'BONK', name: 'Bonk', decimals: 5 },
                { mint: 'DUSTawucrTsGU8hcqRdHDCbuYhCPADMLM2VcCb8VnFnQ', symbol: 'DUST', name: 'DUST Protocol', decimals: 9 },
                { mint: 'JUPyiwrYJFskUPiHa7hkeR8VUtAeFoSYbKedZNsDvCN', symbol: 'JUP', name: 'Jupiter', decimals: 6 }
            ],
            swapHistory: []
        }
    },
    methods: {
        async getQuote() {
            this.loading = true;
            this.error = null;
            try {
                // Get input token decimals for proper amount formatting
                const inputTokenInfo = this.findToken(this.inputToken);
                if (!inputTokenInfo) {
                    throw new Error(`Token information not found for ${this.inputToken}`);
                }
                
                // Convert amount to smallest unit (e.g., lamports for SOL)
                // This is critical - the frontend should handle the conversion to match backend expectations
                const amountInSmallestUnit = parseFloat(this.amount) * Math.pow(10, inputTokenInfo.decimals);
                // Ensure we're sending a whole number as a string (no decimals)
                const amountString = Math.floor(amountInSmallestUnit).toString();
                
                console.log(`Getting quote for ${this.amount} ${inputTokenInfo.symbol} (${amountInSmallestUnit} smallest units) to ${this.getTokenSymbol(this.outputToken)}`);
                
                const response = await axios.post('/NS/dashboard/settings.php?page=jupiter_swap', {
                    action: 'get_quote',
                    inputToken: this.inputToken,
                    outputToken: this.outputToken,
                    amount: amountString
                });
                
                console.log('Raw quote response:', response.data);
                
                // Validate the response
                if (!response.data || typeof response.data !== 'object') {
                    throw new Error('Invalid response format from server');
                }
                
                this.quote = response.data;
                
                // Detailed validation and debugging
                if (!this.quote.outAmount) {
                    console.error('Missing outAmount in quote response', this.quote);
                    this.error = 'Invalid quote response: Missing output amount';
                } else {
                    console.log(`Quote successful: ${this.formatOutputAmount()} ${this.getTokenSymbol(this.outputToken)}`);
                }
            } catch (error) {
                console.error('Quote error:', error);
                this.error = error.response?.data?.error || error.message || 'Failed to get quote';
            } finally {
                this.loading = false;
            }
        },
        async executeSwap() {
            if (!this.quote) return;
            
            this.loading = true;
            try {
                const response = await axios.post('/NS/dashboard/settings.php?page=jupiter_swap', {
                    action: 'execute_swap',
                    quote: this.quote
                });
                
                // Record the swap in history
                this.recordSwapHistory(response.data);
                
                alert('Swap executed successfully!');
            } catch (error) {
                console.error('Swap error:', error);
                alert('Swap failed: ' + (error.response?.data?.error || error.message));
            } finally {
                this.loading = false;
            }
        },
        formatAmount(amount) {
            return parseFloat(amount).toFixed(6);
        },
        formatOutputAmount() {
            if (!this.quote || !this.quote.outAmount) return 'N/A';
            
            // Debug output amount format
            console.log('Output amount type:', typeof this.quote.outAmount);
            console.log('Output amount value:', this.quote.outAmount);
            
            // Handle different response formats
            let outAmount;
            if (typeof this.quote.outAmount === 'string') {
                outAmount = parseFloat(this.quote.outAmount);
            } else if (typeof this.quote.outAmount === 'number') {
                outAmount = this.quote.outAmount;
            } else {
                console.error('Unexpected outAmount format:', this.quote.outAmount);
                return 'N/A';
            }
            
            // Get decimals from the response if available, otherwise from token info
            let decimals;
            if (this.quote.outputDecimals) {
                decimals = this.quote.outputDecimals;
                console.log(`Using decimals from API response: ${decimals}`);
            } else {
                // Find the token and get its decimals
                const outputTokenInfo = this.findToken(this.outputToken);
                if (!outputTokenInfo) {
                    console.error('Token info not found for:', this.outputToken);
                    return 'N/A';
                }
                decimals = outputTokenInfo.decimals;
                console.log(`Using decimals from token info: ${decimals}`);
            }
            
            // Ensure we have a valid number
            if (isNaN(outAmount) || outAmount < 0) {
                console.error('Invalid outAmount value:', outAmount);
                return 'N/A';
            }
            
            const formattedAmount = (outAmount / Math.pow(10, decimals)).toFixed(6);
            console.log('Formatted amount:', formattedAmount);
            return formattedAmount;
        },
        
        findToken(mint) {
            return this.tokens.find(token => token.mint === mint);
        },
        
        getTokenSymbol(mint) {
            const token = this.findToken(mint);
            return token ? token.symbol : mint.substring(0, 5) + '...';
        },
        
        recordSwapHistory(swap) {
            // Add to history with timestamp
            const historyItem = {
                ...swap,
                timestamp: new Date().toISOString(),
                inputTokenSymbol: this.getTokenSymbol(this.inputToken),
                outputTokenSymbol: this.getTokenSymbol(this.outputToken),
                inputAmount: this.formatAmount(this.amount),
                outputAmount: this.formatOutputAmount()
            };
            
            this.swapHistory.unshift(historyItem);
            
            // Keep only last 10 swaps
            if (this.swapHistory.length > 10) {
                this.swapHistory = this.swapHistory.slice(0, 10);
            }
            
            // Save to localStorage
            try {
                localStorage.setItem('jupiterSwapHistory', JSON.stringify(this.swapHistory));
            } catch (e) {
                console.error('Failed to save swap history:', e);
            }
        }
    }
});
