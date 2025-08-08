
import requests
import mysql.connector # type: ignore
from datetime import datetime
import time

# --- CONFIGURATION ---
# SPARE KEY 1: a36ab379-15a0-409b-99ec-85ab7f2836ea
# SPARE KEY 2: 1758e18b-1744-4ad6-a2a9-908af2f33c8a
# SPARE KEY 3: 2b0c6f1d-4e7a-4f5c-8b9d-0f8c1e2b3a4b
# used up this month: API_KEY = 'e2e746c1-169a-4778-90f7-a66458a6af00'
API_KEY= "a36ab379-15a0-409b-99ec-85ab7f2836ea"
DB_CONFIG = {
    'host': 'localhost',
    'user': 'dimi',
    'password': '1304',
    'database': 'NS',
    'charset': 'utf8mb4',
    'unix_socket': '/opt/lampp/var/mysql/mysql.sock'
}
EXCHANGE_NAME = 'bitvavo'

def get_exchange_id(cursor, exchange_name):
    cursor.execute("SELECT id FROM exchanges WHERE exchange_name = %s", (exchange_name,))
    row = cursor.fetchone()
    if row:
        return row[0]
    else:
        raise ValueError(f"Exchange '{exchange_name}' not found in exchanges table.")

def get_first_coin_exchange_id(cursor):
    cursor.execute("SELECT exchange_id FROM coins LIMIT 1")
    row = cursor.fetchone()
    return row[0] if row else None

def fetch_cmc_data_for_symbols(symbols, headers):
    """Fetch data from CMC for a given list of symbols in batches."""
    all_coin_data = {}
    batch_size = 100  # CMC API recommends batching symbols
    for i in range(0, len(symbols), batch_size):
        batch = symbols[i:i+batch_size]
        symbols_str = ','.join(batch)
        quotes_url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest'
        quotes_params = {'symbol': symbols_str, 'convert': 'USD'}
        
        print(f"Fetching CMC data for batch: {symbols_str[:70]}...")
        quotes_response = requests.get(quotes_url, headers=headers, params=quotes_params)
        
        if quotes_response.status_code == 429:
            print("Rate limit hit, waiting for 60 seconds...")
            time.sleep(60)
            quotes_response = requests.get(quotes_url, headers=headers, params=quotes_params) # Retry once

        quotes_response.raise_for_status()
        quotes_data = quotes_response.json()['data']
        all_coin_data.update(quotes_data)
        time.sleep(2) # Be respectful to the API
    return all_coin_data

def main():
    print("Backend price update script started.")
    headers = {'X-CMC_PRO_API_KEY': API_KEY}
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor()

    try:
        bitvavo_exchange_id = get_exchange_id(cursor, 'bitvavo')
        first_coin_exchange_id = get_first_coin_exchange_id(cursor)

        # 1. Fetch Bitvavo symbols
        print("Fetching Bitvavo exchange info...")
        bitvavo_url = 'https://api.bitvavo.com/v2/markets'
        bitvavo_response = requests.get(bitvavo_url)
        bitvavo_response.raise_for_status()
        bitvavo_data = bitvavo_response.json()
        bitvavo_symbols = set()
        for market_data in bitvavo_data:
            base_asset = market_data['base']
            if base_asset not in ['EUR', 'USD', 'USDT', 'BUSD']:
                bitvavo_symbols.add(base_asset)
        print(f"Found {len(bitvavo_symbols)} unique symbols on Bitvavo.")

        # 2. Fetch data for these symbols from CMC
        cmc_data = fetch_cmc_data_for_symbols(list(bitvavo_symbols), headers)
        print(f"Retrieved data for {len(cmc_data)} symbols from CMC.")

        # 3. Prepare matched coins
        matched_coins = []
        for symbol, coin_data in cmc_data.items():
            if coin_data: # Ensure there is data for the symbol
                matched_coins.append(coin_data)

        print(f"Matched {len(matched_coins)} Bitvavo coins with CMC data.")

        # 4. Connect to MySQL and insert/update coins
        if first_coin_exchange_id == 2:  # If the coins are from Binance
            print("Data is from Binance, purging existing coin data...")
            cursor.execute("DELETE FROM coins")
            conn.commit()
            print("Purge complete.")

        for coin in matched_coins:
            sql = '''
                INSERT INTO coins (
                    coin_name, symbol, currency, price, current_price, price_change_24h, marketcap, volume_24h,
                    last_updated, date_added, exchange_id
                ) VALUES (
                    %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
                )
                ON DUPLICATE KEY UPDATE
                    price = VALUES(price),
                    current_price = VALUES(current_price),
                    price_change_24h = VALUES(price_change_24h),
                    marketcap = VALUES(marketcap),
                    volume_24h = VALUES(volume_24h),
                    last_updated = VALUES(last_updated)
            '''
            quote = coin.get('quote', {}).get('USD', {})
            coin_name = coin.get('name')
            symbol = coin.get('symbol')
            currency = 'USD'
            current_price = quote.get('price')
            price_change_24h = quote.get('percent_change_24h')
            marketcap = quote.get('market_cap')
            volume_24h = quote.get('volume_24h')
            last_updated = quote.get('last_updated', '').replace('T', ' ').replace('Z', '')
            date_added = coin.get('date_added', '').replace('T', ' ').replace('Z', '')

            # Skip if essential data is missing
            if not all([coin_name, symbol, current_price, last_updated, date_added]):
                print(f"Skipping coin with missing data: {symbol}")
                continue

            values = (
                coin_name, symbol, currency, current_price, current_price, price_change_24h, marketcap, volume_24h,
                last_updated, date_added, bitvavo_exchange_id
            )
            cursor.execute(sql, values)
        conn.commit()
        print(f"Inserted/updated {len(matched_coins)} coins into the database.")
    finally:
        cursor.close()
        conn.close()
        print("Backend price update script stopped.")

if __name__ == "__main__":
    main()
