import requests
import mysql.connector
from datetime import datetime

# --- CONFIGURATION ---
API_KEY = 'e2e746c1-169a-4778-90f7-a66458a6af00'
DB_CONFIG = {
    'host': 'localhost',
    'user': 'dimi',
    'password': '1304',
    'database': 'NS',
    'charset': 'utf8mb4',
    'unix_socket': '/opt/lampp/var/mysql/mysql.sock'
}
EXCHANGE_NAME = 'binance'

def get_binance_exchange_id(cursor):
    cursor.execute("SELECT id FROM exchanges WHERE exchange_name = %s", (EXCHANGE_NAME,))
    row = cursor.fetchone()
    if row:
        return row[0]
    else:
        raise ValueError(f"Exchange '{EXCHANGE_NAME}' not found in exchanges table.")

def main():
    headers = {'X-CMC_PRO_API_KEY': API_KEY}

    # 1. Fetch CMC listings
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
    print(f"Retrieved {len(all_coins)} cryptocurrencies from CMC.")

    # 2. Fetch Binance symbols
    print("Fetching Binance exchange info...")
    binance_url = 'https://api.binance.com/api/v3/exchangeInfo'
    binance_response = requests.get(binance_url)
    binance_response.raise_for_status()
    binance_data = binance_response.json()
    binance_coins = set()
    for symbol_data in binance_data['symbols']:
        base_asset = symbol_data['baseAsset']
        quote_asset = symbol_data['quoteAsset']
        if base_asset not in ['EUR', 'USD', 'USDT', 'BUSD']:
            binance_coins.add(base_asset)
        if quote_asset not in ['EUR', 'USD', 'USDT', 'BUSD']:
            binance_coins.add(quote_asset)
    print(f"Found {len(binance_coins)} unique coins on Binance.")

    # 3. Match CMC data to Binance coins
    symbol_to_data = {coin['symbol']: coin for coin in all_coins}
    matched_coins = []
    for symbol in binance_coins:
        if symbol in symbol_to_data:
            coin = symbol_to_data[symbol]
            matched_coins.append(coin)

    print(f"Matched {len(matched_coins)} Binance coins with CMC data.")

    # 4. Connect to MySQL and insert/update coins
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor()
    try:
        exchange_id = get_binance_exchange_id(cursor)
        for coin in matched_coins:
            sql = """
                INSERT INTO coins (
                    coin_name, symbol, currency, price, current_price, price_change_24h, marketcap, volume_24h,
                    last_updated, volume_spike, date_added, exchange_id
                ) VALUES (
                    %s, %s, %s, %s, %s, %s, %s, %s, %s, NULL, %s, %s
                )
                ON DUPLICATE KEY UPDATE
                    price = VALUES(price),
                    current_price = VALUES(current_price),
                    price_change_24h = VALUES(price_change_24h),
                    marketcap = VALUES(marketcap),
                    volume_24h = VALUES(volume_24h),
                    last_updated = VALUES(last_updated)
            """
            coin_name = coin['name']
            symbol = coin['symbol']
            currency = 'USD'
            current_price = coin['quote']['USD']['price']
            price_change_24h = coin['quote']['USD'].get('percent_change_24h', 0)
            marketcap = coin['quote']['USD'].get('market_cap')
            if marketcap is None:
                current_price = coin['quote']['USD']['price']
                circulating_supply = coin.get('circulating_supply')
                if current_price and circulating_supply:
                    marketcap = current_price * circulating_supply
                else:
                    # Skip this coin if we can't determine marketcap
                    continue
            volume_24h = coin['quote']['USD'].get('volume_24h', 0)
            last_updated = coin['last_updated'].replace('T', ' ').replace('Z', '')
            date_added = coin['date_added'].replace('T', ' ').replace('Z', '')
            # volume_spike is left as NULL for now

            values = (
                coin_name, symbol, currency, current_price, current_price, price_change_24h, marketcap, volume_24h,
                last_updated, date_added, exchange_id
            )
            cursor.execute(sql, values)
        conn.commit()
        print(f"Inserted/updated {len(matched_coins)} coins into the database.")
    finally:
        cursor.close()
        conn.close()

if __name__ == "__main__":
    main()