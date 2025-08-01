import time
import requests
import json
import os
import signal
import sys
from datetime import datetime
import mysql.connector
from mysql.connector import Error

# Configuration
COINMARKETCAP_API_KEY = 'a36ab379-15a0-409b-99ec-85ab7f2836ea'
DB_CONFIG = {
    'unix_socket': '/opt/lampp/var/mysql/mysql.sock',
    'host': 'localhost',
    'database': 'NS',
    'user': 'root',
    'password': '1304',
    'raise_on_warnings': True
}

# For clean exit
running = True

def signal_handler(sig, frame):
    global running
    print("\nShutting down gracefully...")
    running = False
    print("Backend price update script stopped.")
    sys.exit(0)

signal.signal(signal.SIGINT, signal_handler)

def create_price_history_table():

    """Create price_history table if it doesn't exist"""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()
        
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS price_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                coin_id VARCHAR(50) NOT NULL,
                symbol VARCHAR(20) NOT NULL,
                price DECIMAL(20, 8) NOT NULL,
                volume_24h DECIMAL(20, 2) DEFAULT NULL,
                market_cap DECIMAL(20, 2) DEFAULT NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_coin_id (coin_id),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        """)
        conn.commit()
        print("Price history table is ready")
        
    except Error as e:
        print(f"Error creating price_history table: {e}")
        raise
    finally:
        if 'conn' in locals() and conn.is_connected():
            cursor.close()
            conn.close()

def get_active_trades():
    """Fetch all active trades from the database"""
    conn = None
    cursor = None
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT id, user_id, coin_id, amount, avg_buy_price, last_updated 
            FROM portfolio 
            WHERE amount > 0
        """)
        return cursor.fetchall()
    except Error as e:
        print(f"Database error in get_active_trades: {e}")
        return []
    finally:
        if cursor:
            cursor.close()
        if conn and conn.is_connected():
            conn.close()

def get_cmc_id(symbol):
    conn = None
    cursor = None
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT id FROM cryptocurrencies WHERE symbol = %s", (symbol,))
        result = cursor.fetchone()
        if result:
            return result['id']
        return None
    except Error as e:
        print(f"Database error in get_cmc_id: {e}")
        return None
    finally:
        if cursor:
            cursor.close()
        if conn and conn.is_connected():
            conn.close()

def fetch_coinmarketcap_prices(coin_symbols):
    """Fetch latest prices from CoinMarketCap for multiple symbols"""
    url = 'https://pro-api.coinmarketcap.com/v2/cryptocurrency/quotes/latest'
    headers = {
        'X-CMC_PRO_API_KEY': COINMARKETCAP_API_KEY,
        'Accepts': 'application/json'
    }
    
    # Convert symbols to CMC IDs (you'll need to implement this mapping)
    cmc_ids = [str(get_cmc_id(symbol)) for symbol in coin_symbols if get_cmc_id(symbol)]
    
    if not cmc_ids:
        print("No valid CMC IDs found for the symbols")
        return None
    
    params = {
        'id': ','.join(cmc_ids),
        'convert': 'EUR'
    }
    
    try:
        response = requests.get(url, headers=headers, params=params, timeout=10)
        response.raise_for_status()
        return response.json().get('data', {})
    except Exception as e:
        print(f"Error fetching prices from CMC: {e}")
        return None

def update_price_history(coin_data):
    """Update price history in the database"""
    if not coin_data:
        return
    
    conn = None
    cursor = None
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()
        
        for coin_id, data in coin_data.items():
            symbol = data['symbol']
            price = data['quote']['EUR']['price']
            volume_24h = data['quote']['EUR'].get('volume_24h')
            market_cap = data['quote']['EUR'].get('market_cap')
            
            cursor.execute("""
                INSERT INTO price_history 
                (coin_id, symbol, price, volume_24h, market_cap)
                VALUES (%s, %s, %s, %s, %s)
            """, (
                coin_id,
                symbol,
                price,
                volume_24h,
                market_cap
            ))
        
        conn.commit()
        print(f"Updated price history at {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        
    except Error as e:
        print(f"Error updating price history: {e}")
        if conn:
            conn.rollback()
    finally:
        if cursor:
            cursor.close()
        if conn and conn.is_connected():
            conn.close()

def test():
    import mysql.connector

    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        print("Successfully connected to the database!")
        conn.close()
    except Exception as e:
        print(f"Connection failed: {e}")

def main():
    print("Backend price update script started.")
    # Ensure price_history table exists
    create_price_history_table()
    
    print("Starting price monitor... (Press Ctrl+C to stop)")
    
    while running:
        try:
            # Get active trades
            active_trades = get_active_trades()
            
            if not active_trades:
                print(f"{datetime.now()}: No active trades found")
                time.sleep(3)
                continue
            
            # Extract coin symbols
            coin_symbols = [trade['coin_id'] for trade in active_trades]
            print(f"Monitoring coins: {', '.join(coin_symbols)}")
            
            # Fetch prices
            prices = fetch_coinmarketcap_prices(coin_symbols)
            
            if prices:
                # Update price history
                update_price_history(prices)
            
            # Wait for the next interval
            time.sleep(3)
            
        except Exception as e:
            print(f"Error in main loop: {e}")
            time.sleep(10)  # Wait longer if there's an error

if __name__ == "__main__":
    main()