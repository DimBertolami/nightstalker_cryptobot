import requests
import pandas as pd
from datetime import datetime
import time
import mysql.connector

# --- CONFIGURATION ---
DB_CONFIG = {
    'host': 'localhost',
    'user': 'dimi',
    'password': '1304',
    'database': 'NS',
    'charset': 'utf8mb4',
    'unix_socket': '/opt/lampp/var/mysql/mysql.sock'
}

def get_bitvavo_coins_from_cmc():
    """
    Fetch all coins listed on Bitvavo exchange using CoinMarketCap API and Bitvavo API
    """
    API_KEY = '1758e18b-1744-4ad6-a2a9-908af2f33c8a'  # Using your existing API key
    headers = {'X-CMC_PRO_API_KEY': API_KEY}
    try:
        # First, get Bitvavo's supported symbols directly from Bitvavo API
        print("Fetching Bitvavo exchange info...")
        bitvavo_url = 'https://api.bitvavo.com/v2/markets'
        bitvavo_response = requests.get(bitvavo_url)
        bitvavo_response.raise_for_status()
        bitvavo_data = bitvavo_response.json()
        bitvavo_coins = set()
        for market_data in bitvavo_data:
            base_asset = market_data['base']
            quote_asset = market_data['quote']
            if base_asset not in ['EUR', 'USD', 'USDT', 'BUSD']:
                bitvavo_coins.add(base_asset)
            if quote_asset not in ['EUR', 'USD', 'USDT', 'BUSD']:
                bitvavo_coins.add(quote_asset)
        print(f"Found {len(bitvavo_coins)} unique coins on Bitvavo")
        coin_list = sorted(list(bitvavo_coins))
        print(f"Processing {len(coin_list)} Bitvavo coins...")
        print("Fetching cryptocurrency listings from CoinMarketCap...")
        listings_url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest'
        listings_params = {
            'limit': 5000,
            'sort': 'market_cap',
            'sort_dir': 'desc',
            'convert': 'USD'
        }
        listings_response = requests.get(listings_url, headers=headers, params=listings_params)
        listings_response.raise_for_status()
        listings_data = listings_response.json()
        all_coins = listings_data['data']
        print(f"Retrieved {len(all_coins)} cryptocurrencies from CMC")
        symbol_to_data = {coin['symbol']: coin for coin in all_coins}
        coin_details = []
        matched_count = 0
        for symbol in coin_list:
            if symbol in symbol_to_data:
                matched_count += 1
                coin_data = symbol_to_data[symbol]
                price = coin_data['quote']['USD']['price']
                market_cap = coin_data['quote']['USD'].get('market_cap')
                circulating_supply = coin_data.get('circulating_supply')
                if market_cap is None:
                    if price and circulating_supply:
                        market_cap = price * circulating_supply
                    else:
                        # Skip this coin if we can't determine market_cap
                        continue
                coin_details.append({
                    'symbol': symbol,
                    'name': coin_data['name'],
                    'price': price,
                    'market_cap': market_cap,
                    'volume_24h': coin_data['quote']['USD'].get('volume_24h', 0),
                    'percent_change_24h': coin_data['quote']['USD'].get('percent_change_24h', 0)
                })
        print(f"Matched {matched_count} out of {len(coin_list)} Bitvavo coins with CMC data")
        unmatched_coins = [symbol for symbol in coin_list if symbol not in symbol_to_data]
        if unmatched_coins:
            print(f"Fetching data for {len(unmatched_coins)} unmatched coins...")
            batch_size = 100
            for i in range(0, len(unmatched_coins), batch_size):
                batch = unmatched_coins[i:i+batch_size]
                symbols_str = ','.join(batch)
                quotes_url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest'
                quotes_params = {'symbol': symbols_str, 'convert': 'USD'}
                quotes_response = requests.get(quotes_url, headers=headers, params=quotes_params)
                if quotes_response.status_code == 200:
                    quotes_data = quotes_response.json()['data']
                    for symbol in batch:
                        if symbol in quotes_data:
                            coin_data = quotes_data[symbol]
                            price = coin_data['quote']['USD']['price']
                            market_cap = coin_data['quote']['USD'].get('market_cap')
                            circulating_supply = coin_data.get('circulating_supply')
                            if market_cap is None:
                                if price is not None and circulating_supply is not None:
                                    market_cap = price * circulating_supply
                                else:
                                    market_cap = 0
                            coin_details.append({
                                'symbol': symbol,
                                'name': coin_data['name'],
                                'price': price,
                                'market_cap': market_cap,
                                'volume_24h': coin_data['quote']['USD'].get('volume_24h', 0),
                                'percent_change_24h': coin_data['quote']['USD'].get('percent_change_24h', 0)
                            })
        print(f"Found {len(coin_details)} coins on Bitvavo")
        df = pd.DataFrame(coin_details)
        # Ensure 'marketcap' column exists and is correct
        if 'market_cap' in df.columns:
            df['marketcap'] = df['market_cap']
            df = df.drop(columns=['market_cap'])
        elif 'marketcap' not in df.columns:
            df['marketcap'] = 0
        # Optionally, set current_price if required by DB
        if 'price' in df.columns:
            df['current_price'] = df['price']
        return df
    except requests.exceptions.RequestException as e:
        print(f"API Error: {e}")
        return None


def insert_or_update_coins_in_db(df):
    """
    Insert or update Bitvavo coin data in the NS database
    """
    if df is None or df.empty:
        print("No coin data to insert/update.")
        return
        
    # Replace NaN values with None for SQL compatibility
    df = df.replace({pd.NA: None, float('nan'): None})
    df = df.where(pd.notnull(df), None)
    
    # Rename columns to match DB schema
    df = df.rename(columns={
        'name': 'coin_name',
        'price': 'price',
        'percent_change_24h': 'price_change_24h',
        'market_cap': 'marketcap'
    })
    
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()
        processed = 0
        
        for idx, row in df.iterrows():
            sql = ("""
                INSERT INTO coins (symbol, coin_name, currency, price, marketcap, volume_24h, price_change_24h, last_updated, exchange_id)
                VALUES (%s, %s, %s, %s, %s, %s, %s, NOW(), %s)
                ON DUPLICATE KEY UPDATE
                    coin_name=VALUES(coin_name),
                    price=VALUES(price),
                    marketcap=VALUES(marketcap),
                    volume_24h=VALUES(volume_24h),
                    price_change_24h=VALUES(price_change_24h),
                    last_updated=NOW()
            """)
            
            # Default values
            exchange_id = 1
            currency = 'USD'

            try:
                # Convert NaN/None to appropriate default values
                price = 0 if pd.isna(row['price']) else float(row['price'])
                marketcap = 0 if pd.isna(row['marketcap']) else float(row['marketcap'])
                volume_24h = 0 if pd.isna(row['volume_24h']) else float(row['volume_24h'])
                price_change_24h = 0 if pd.isna(row['price_change_24h']) else float(row['price_change_24h'])
                
                data = (
                    str(row['symbol']),
                    str(row['coin_name']),
                    currency,
                    price,
                    marketcap,
                    volume_24h,
                    price_change_24h,
                    exchange_id
                )
                
                cursor.execute(sql, data)
                processed += 1
                
                # Print progress every 50 coins
                if processed % 50 == 0:
                    print(f"Progress: {processed}/{len(df)} coins processed...")
                
            except (ValueError, TypeError) as e:
                print(f"Error processing {row['symbol']}: {e}")
                continue
            
        conn.commit()
        print(f"\nSuccessfully processed {processed} coins")
    except mysql.connector.Error as err:
        print(f"MySQL Error: {err}")
    finally:
        if 'cursor' in locals():
            cursor.close()
        if 'conn' in locals():
            conn.close()

def display_paginated_dataframe(df, items_per_page=100):
    """
    Display a DataFrame in the terminal with pagination
    """
    import os
    import shutil
    try:
        terminal_width = shutil.get_terminal_size().columns
    except:
        terminal_width = 120
    total_items = len(df)
    total_pages = (total_items + items_per_page - 1) // items_per_page
    columns = df.columns.tolist()
    col_widths = {}
    for col in columns:
        header_width = len(str(col))
        sample_width = max([len(str(val)) for val in df[col].sample(min(100, len(df))).values] + [0])
        if col == 'symbol':
            max_width = 12
        elif col == 'name':
            max_width = 25
        elif col in ['price', 'market_cap', 'volume_24h']:
            max_width = 15
        else:
            max_width = 20
        col_widths[col] = min(max(header_width, sample_width) + 2, max_width)
    idx_width = 8
    for page in range(total_pages):
        start_idx = page * items_per_page
        end_idx = min(start_idx + items_per_page, total_items)
        print(f"\n--- Showing items {start_idx+1} to {end_idx} of {total_items} ---")
        header = "INDEX".ljust(idx_width)
        separator = "-" * (idx_width - 1) + " "
        for col in columns:
            header += str(col).ljust(col_widths[col])
            separator += "-" * (col_widths[col] - 1) + " "
        print(header)
        print(separator)
        page_df = df.iloc[start_idx:end_idx]
        for i, (idx, row) in enumerate(page_df.iterrows()):
            row_str = str(start_idx + i).ljust(idx_width)
            for col in columns:
                value = str(row[col])
                if len(value) > col_widths[col] - 1:
                    value = value[:col_widths[col] - 4] + "..."
                row_str += value.ljust(col_widths[col])
            print(row_str)
        if page < total_pages - 1:
            user_input = input("\nPress Enter to continue, 'q' to quit: ")
            if user_input.lower() == 'q':
                print("Pagination stopped by user.")
                break

if __name__ == "__main__":
    print("Fetching Bitvavo coins from CoinMarketCap...")
    bitvavo_coins = get_bitvavo_coins_from_cmc()
    if bitvavo_coins is not None:
        insert_or_update_coins_in_db(bitvavo_coins)
        print(f"\nProcessed and inserted/updated {len(bitvavo_coins)} Bitvavo coins into the NS database.")
