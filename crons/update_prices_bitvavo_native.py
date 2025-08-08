
import requests
import mysql.connector
from datetime import datetime

# --- CONFIGURATION ---
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
    """Fetches the ID of the specified exchange from the database."""
    cursor.execute("SELECT id FROM exchanges WHERE exchange_name = %s", (exchange_name,))
    row = cursor.fetchone()
    if row:
        return row[0]
    else:
        raise ValueError(f"Exchange '{exchange_name}' not found in the exchanges table.")

def main():
    """Fetches latest ticker data from Bitvavo and updates the database."""
    print("Starting Bitvavo native price update script.")
    conn = None
    try:
        # 1. Fetch all 24h ticker data from Bitvavo
        print("Fetching ticker data from Bitvavo API...")
        bitvavo_url = 'https://api.bitvavo.com/v2/ticker/24h'
        response = requests.get(bitvavo_url)
        response.raise_for_status()  # Raises an exception for bad status codes
        ticker_data = response.json()
        print(f"Successfully fetched data for {len(ticker_data)} markets.")

        # 2. Connect to the database
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()
        bitvavo_exchange_id = get_exchange_id(cursor, EXCHANGE_NAME)

        update_count = 0
        # 3. Iterate through markets and update database
        for item in ticker_data:
            market = item.get('market')
            price = item.get('last')
            volume = item.get('volume')
            open_price = item.get('open')

            # Calculate 24h change
            price_change_24h = 0
            if open_price and float(open_price) != 0:
                price_change_24h = ((float(price) - float(open_price)) / float(open_price)) * 100

            # We are only interested in markets trading against EUR, USD, or USDT
            if '-' in market:
                symbol, currency = market.split('-')
            else:
                continue # Skip markets with unexpected format

            if not all([symbol, price, volume]):
                print(f"Skipping market with missing data: {market}")
                continue

            # Prepare and execute the SQL query
            sql = '''
                INSERT INTO coins (coin_name, symbol, currency, price, current_price, price_change_24h, volume_24h, last_updated, exchange_id)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    price = VALUES(price),
                    current_price = VALUES(current_price),
                    price_change_24h = VALUES(price_change_24h),
                    volume_24h = VALUES(volume_24h),
                    last_updated = VALUES(last_updated);
            '''
            # Note: We do not update marketcap or other CMC-specific fields.
            
            last_updated = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            
            values = (
                symbol, symbol, currency, price, price, price_change_24h, volume,
                last_updated, bitvavo_exchange_id
            )
            
            cursor.execute(sql, values)
            update_count += cursor.rowcount # Can be 1 for INSERT, 2 for UPDATE

        conn.commit()
        print(f"Successfully inserted/updated data for {len(ticker_data)} markets. Total rows affected: {update_count}.")

    except requests.exceptions.RequestException as e:
        print(f"Error fetching data from Bitvavo API: {e}")
    except mysql.connector.Error as err:
        print(f"Database error: {err}")
    except ValueError as e:
        print(f"Configuration error: {e}")
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()
            print("Database connection closed.")
        print("Bitvavo native price update script finished.")

if __name__ == "__main__":
    main()
