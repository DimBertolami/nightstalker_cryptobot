const fs = require('fs');

// Read the file
const filePath = '/opt/lampp/htdocs/NS/assets/js/coins.js';
const content = fs.readFileSync(filePath, 'utf8');

// Create a backup
fs.writeFileSync(`${filePath}.final_backup`, content);
console.log(`Created backup at ${filePath}.final_backup`);

// Fix the buy button click handler
let fixed = content.replace(
  /\$\(document\)\.on\('click', '\.buy-button, \.crypto-widget-action\.buy', function\(\) \{[\s\S]*?complete: function\(\) \{[\s\S]*?\$button\.html\('Buy'\);[\s\S]*?\}\s*\}\s*\}\s*\)\s*;\s*\}\s*\}\s*\);/,
  `$(document).on('click', '.buy-button, .crypto-widget-action.buy', function() {
    const $button = $(this);
    const coinId = $button.data('id') || $button.data('coin');
    const symbol = $button.data('symbol');
    const price = $button.data('price');
    let amount;
    
    // Check if this is a crypto-widget button or regular button
    if ($button.hasClass('crypto-widget-action')) {
        // For widget button, get amount from data attribute
        amount = parseFloat($button.data('amount') || 0);
    } else {
        // For regular button, get amount from input field
        const $inputField = $button.closest('.input-group').find('.buy-amount');
        amount = parseFloat($inputField.val());
    }
    
    if (!amount || isNaN(amount) || amount <= 0) {
        showToast('Please enter a valid amount to buy', 'warning');
        return;
    }
    
    // Calculate total cost
    const totalCost = (amount * price).toFixed(2);

    // Show confirmation with coin name/symbol
    if (confirm(\`Confirm purchase of \${amount} \${symbol} at $\${price} per coin?\\nTotal cost: $\${totalCost}\`)) {
        // Disable button to prevent double-clicks
        $button.prop('disabled', true).html(\`<i class="fas fa-spinner fa-spin"></i> Buying \${symbol}...\`);
        
        // Call API to execute trade
        $.ajax({
            url: '/NS/api/execute-trade.php',
            method: 'POST',
            data: {
                coinId: coinId,
                symbol: symbol,
                amount: amount,
                price: price,
                action: 'buy',
                total: totalCost
            },
            dataType: 'json',
            success: function(response) {
                console.log('Trade response:', response);
                
                // In the success callback function
                if (response.success) {
                    showToast(\`Successfully bought \${amount} \${symbol}\`, 'success');
                    
                    // Clear input field only if it exists (for non-widget buttons)
                    if (!$button.hasClass('crypto-widget-action')) {
                        const $inputField = $button.closest('.input-group').find('.buy-amount');
                        $inputField.val('');
                    }
                } else {
                    showToast(response.message || \`Failed to buy \${symbol}\`, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Trade error:', xhr, status, error);
                
                // Try to parse the error response
                let errorMessage = \`Failed to buy \${symbol}: \${error}\`;
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    console.error('Error parsing error response:', e);
                }
                
                showToast(errorMessage, 'error');
            },
            complete: function() {
                // Re-enable button with appropriate text
                $button.prop('disabled', false);
                if ($button.hasClass('crypto-widget-action')) {
                    $button.html('Buy');
                } else {
                    $button.html('Buy');
                }
            }
        });
    }
});`
);

// Fix the processCoinData function
fixed = fixed.replace(
  /function processCoinData\(data\) \{[\s\S]*?\$\('#coins-table tbody'\)\.append\(rowHtml\);[\s\S]*?\}\s*\}\s*\}\}/,
  `function processCoinData(data) {
    // Process coins
    data.forEach(coin => {
        const userBalance = coin.user_balance || 0;
        const canSell = userBalance > 0;
        
        if (window.coinsTable && typeof window.coinsTable.row.add === 'function') {
            window.coinsTable.row.add([
                window.formatCoinName(coin.name, coin.symbol),
                window.formatPrice(coin.current_price || coin.price || 0),
                window.formatPercentage(coin.price_change_24h || 0),
                window.formatLargeNumber(coin.volume_24h || 0),
                window.formatLargeNumber(coin.marketcap || 0),
                window.formatAge(coin.date_added || coin.last_updated),
                window.formatStatus(coin.is_trending, coin.volume_spike),
                window.formatSource(coin.source || coin.data_source || '', coin.exchange_name),
                window.formatTradeButtons(coin.id, coin.symbol, coin.current_price || coin.price || 0, canSell, userBalance)
            ]);
        } else {
            const rowHtml = \`
                <tr>
                    <td>\${window.formatCoinName(coin.name, coin.symbol)}</td>
                    <td>\${window.formatPrice(coin.current_price || coin.price || 0)}</td>
                    <td>\${window.formatPercentage(coin.price_change_24h || 0)}</td>
                    <td>\${window.formatLargeNumber(coin.volume_24h || 0)}</td>
                    <td>\${window.formatLargeNumber(coin.marketcap || 0)}</td>
                    <td data-sort="\${new Date(coin.date_added || coin.last_updated).getTime()}">\${window.formatAge(coin.date_added || coin.last_updated)}</td>
                    <td>\${window.formatStatus(coin.is_trending, coin.volume_spike)}</td>
                    <td>\${window.formatSource(coin.source || coin.data_source || '', coin.exchange_name)}</td>
                    <td>\${window.formatTradeButtons(coin.id, coin.symbol, coin.current_price || coin.price || 0, canSell, userBalance)}</td>
                </tr>
            \`;
            $('#coins-table tbody').append(rowHtml);
        }
    });
}`
);

// Write the fixed content back to the file
fs.writeFileSync(filePath, fixed);
console.log('Fixed JavaScript syntax errors in ' + filePath);
