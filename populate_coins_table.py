
from pycoingecko import CoinGeckoAPI
import pymysql

# Initialize CoinGecko API
cg = CoinGeckoAPI()

# Database Configuration
DB_CONFIG = {
    'host': 'localhost',
    'database': 'NS',
    'user': 'dimi',
    'password': '1304',
}

def get_db_connection():
    """Establishes a connection to the MySQL database."""
    try:
        connection = pymysql.connect(**DB_CONFIG, cursorclass=pymysql.cursors.DictCursor)
        return connection
    except pymysql.Error as e:
        print(f"Error connecting to MySQL: {e}")
        return None

def populate_coins_table():
    """Fetches a list of coins from CoinGecko and populates the coins table."""
    try:
        coins_list = cg.get_coins_list(include_platform=False)
        conn = get_db_connection()
        if conn:
            try:
                with conn.cursor() as cursor:
                    # Truncate the table to start fresh
                    cursor.execute("TRUNCATE TABLE coins")
                    conn.commit()

                    insert_sql = """
                    INSERT INTO coins
                    (coin_name, symbol, currency, price, price_change_24h, marketcap, volume_24h, last_updated, date_added, exchange_id, is_trending)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, NOW(), NOW(), 1, 0)
                    """
                    records_to_insert = []
                    for coin in coins_list:
                        # Get coin details
                        coin_data = cg.get_coin_by_id(coin['id'])
                        if 'market_data' in coin_data:
                            market_data = coin_data['market_data']
                            records_to_insert.append((
                                coin_data.get('name', ''),
                                coin_data.get('symbol', '').upper(),
                                'usd',
                                market_data.get('current_price', {}).get('usd', 0),
                                market_data.get('price_change_percentage_24h', 0),
                                market_data.get('market_cap', {}).get('usd', 0),
                                market_data.get('total_volume', {}).get('usd', 0),
                            ))

                    cursor.executemany(insert_sql, records_to_insert)
                    conn.commit()
                    print(f"Successfully inserted {len(records_to_insert)} coins into the database.")
            finally:
                conn.close()
    except Exception as e:
        

if __name__ == "__main__":
    populate_coins_table()
