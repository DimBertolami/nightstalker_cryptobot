import requests
import pandas as pd
from datetime import datetime
import time

def get_binance_coins_from_cmc():
    """
    Fetch all coins listed on Binance exchange using CoinMarketCap API
    """
    # Your CoinMarketCap API key : 
    API_KEY = 'e2e746c1-169a-4778-90f7-a66458a6af00'  # Using your existing API key
    headers = {'X-CMC_PRO_API_KEY': API_KEY}
    
    try:
        # Instead of using the exchange-specific endpoints (which might be restricted),
        # we'll use the cryptocurrency listings endpoint and then filter for coins available on Binance
        
        # First, get a list of all cryptocurrencies
        print("Fetching cryptocurrency listings...")
        listings_url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest'
        listings_params = {
            'limit': 5000,  # Maximum allowed
            'sort': 'market_cap',
            'sort_dir': 'desc',
            'convert': 'USD'
        }
        
        listings_response = requests.get(listings_url, headers=headers, params=listings_params)
        listings_response.raise_for_status()
        listings_data = listings_response.json()
        
        all_coins = listings_data['data']
        print(f"Retrieved {len(all_coins)} cryptocurrencies from CMC")
        
        # Now, we'll use the exchange info endpoint to get Binance's supported symbols
        # This is a direct API call to Binance, not through CMC
        print("Fetching Binance exchange info...")
        binance_url = 'https://api.binance.com/api/v3/exchangeInfo'
        binance_response = requests.get(binance_url)
        binance_response.raise_for_status()
        binance_data = binance_response.json()
        
        # Extract all unique base currencies (coins) from Binance
        binance_coins = set()
        for symbol_data in binance_data['symbols']:
            base_asset = symbol_data['baseAsset']
            quote_asset = symbol_data['quoteAsset']
            
            # Add base currency (typically the crypto)
            if base_asset != 'EUR' and base_asset != 'USD' and base_asset != 'USDT' and base_asset != 'BUSD':
                binance_coins.add(base_asset)
            
            # Sometimes the quote can also be a crypto
            if quote_asset != 'EUR' and quote_asset != 'USD' and quote_asset != 'USDT' and quote_asset != 'BUSD':
                binance_coins.add(quote_asset)
                
        print(f"Found {len(binance_coins)} unique coins on Binance")
        
        # Convert to list and sort
        coin_list = sorted(list(binance_coins))
        print(f"Processing {len(coin_list)} Binance coins...")
        
        # Get additional details for each coin by matching with the listings we already have
        coin_details = []
        
        # Create a mapping of symbol to coin data from our listings
        symbol_to_data = {coin['symbol']: coin for coin in all_coins}
        
        # Match Binance coins with our listings data
        matched_count = 0
        for symbol in coin_list:
            # Try to find the coin in our listings data
            if symbol in symbol_to_data:
                matched_count += 1
                coin_data = symbol_to_data[symbol]
                coin_details.append({
                    'symbol': symbol,
                    'name': coin_data['name'],
                    'price': coin_data['quote']['USD']['price'],
                    'market_cap': coin_data['quote']['USD'].get('market_cap', 0),
                    'volume_24h': coin_data['quote']['USD'].get('volume_24h', 0),
                    'percent_change_24h': coin_data['quote']['USD'].get('percent_change_24h', 0)
                })
        
        print(f"Matched {matched_count} out of {len(coin_list)} Binance coins with CMC data")
        
        # For coins that weren't matched, we can try to get their data directly
        unmatched_coins = [symbol for symbol in coin_list if symbol not in symbol_to_data]
        if unmatched_coins:
            print(f"Fetching data for {len(unmatched_coins)} unmatched coins...")
            
            # Use the cryptocurrency/quotes/latest endpoint to get details in batches
            batch_size = 100
            for i in range(0, len(unmatched_coins), batch_size):
                batch = unmatched_coins[i:i+batch_size]
                symbols_str = ','.join(batch)
                
                quotes_url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest'
                quotes_params = {
                    'symbol': symbols_str,
                    'convert': 'USD'
                }
                
                quotes_response = requests.get(quotes_url, headers=headers, params=quotes_params)
                if quotes_response.status_code == 200:
                    quotes_data = quotes_response.json()
                    
                    for symbol in batch:
                        if symbol in quotes_data.get('data', {}):
                            coin_data = quotes_data['data'][symbol]
                            coin_details.append({
                                'symbol': symbol,
                                'name': coin_data['name'],
                                'price': coin_data['quote']['USD']['price'],
                                'market_cap': coin_data['quote']['USD'].get('market_cap', 0),
                                'volume_24h': coin_data['quote']['USD'].get('volume_24h', 0),
                                'percent_change_24h': coin_data['quote']['USD'].get('percent_change_24h', 0)
                            })
                else:
                    print(f"Error fetching details for unmatched coins: {quotes_response.status_code}")
                
                # Respect API rate limits
                time.sleep(1)
        
        # Create a DataFrame for better viewing
        df = pd.DataFrame(coin_details)
        
        print(f"Successfully processed {len(coin_details)} coins from Binance")
        return df
    
    except requests.exceptions.RequestException as e:
        print(f"API Error: {e}")
        return None

def display_paginated_dataframe(df, items_per_page=100):
    """
    Display a DataFrame in the terminal with pagination
    """
    import os
    import shutil
    
    # Get terminal width or use a default if can't determine
    try:
        terminal_width = shutil.get_terminal_size().columns
    except:
        terminal_width = 120  # Default width
    
    total_items = len(df)
    total_pages = (total_items + items_per_page - 1) // items_per_page  # Ceiling division
    
    # Get list of columns for display
    columns = df.columns.tolist()
    
    # Create a formatted string for each column
    # Determine column widths based on data and headers
    col_widths = {}
    for col in columns:
        # Get max width of column header and values (limit to reasonable size)
        header_width = len(str(col))
        # Sample some values to determine width
        sample_width = max([len(str(val)) for val in df[col].sample(min(100, len(df))).values] + [0])
        # Use the larger of header or sample, but cap at reasonable size
        # Adjust column widths based on data type
        if col == 'symbol':
            max_width = 12
        elif col == 'name':
            max_width = 25
        elif col in ['price', 'market_cap', 'volume_24h']:
            max_width = 15
        else:
            max_width = 20
            
        col_widths[col] = min(max(header_width, sample_width) + 2, max_width)
    
    # Width for index column
    idx_width = 8
    
    for page in range(total_pages):
        start_idx = page * items_per_page
        end_idx = min(start_idx + items_per_page, total_items)
        
        print(f"\n--- Showing items {start_idx+1} to {end_idx} of {total_items} ---")
        
        # Print header
        header = "INDEX".ljust(idx_width)
        separator = "-" * (idx_width - 1) + " "
        
        for col in columns:
            header += str(col).ljust(col_widths[col])
            separator += "-" * (col_widths[col] - 1) + " "
        
        print(header)
        print(separator)
        
        # Print each row
        page_df = df.iloc[start_idx:end_idx]
        for i, (idx, row) in enumerate(page_df.iterrows()):
            # Format the row index
            row_str = str(start_idx + i).ljust(idx_width)
            
            # Format each column
            for col in columns:
                value = str(row[col])
                # Truncate if too long
                if len(value) > col_widths[col] - 1:
                    value = value[:col_widths[col] - 4] + "..."
                row_str += value.ljust(col_widths[col])
            
            print(row_str)
        
        if page < total_pages - 1:  # If not the last page
            user_input = input("\nPress Enter to continue, 'q' to quit: ")
            if user_input.lower() == 'q':
                print("Pagination stopped by user.")
                break

# Execute the function
if __name__ == "__main__":
    print("Fetching Binance coins from CoinMarketCap...")
    binance_coins = get_binance_coins_from_cmc()
    
    if binance_coins is not None:
        # Save to CSV
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        csv_filename = f'binance_coins_{timestamp}.csv'
        binance_coins.to_csv(csv_filename, index=False)
        print(f"\nSaved complete list of {len(binance_coins)} coins to {csv_filename}")
        
        # Display options
        print("\nDisplay options:")
        print("1. Show first 10 coins")
        print("2. Show complete list (paginated by 100 items)")
        choice = input("Enter your choice (1 or 2): ")
        
        if choice == "1":
            print("\nFirst 10 coins:")
            print(binance_coins.head(10))
        elif choice == "2":
            print("\nDisplaying complete list with pagination:")
            display_paginated_dataframe(binance_coins, 100)
