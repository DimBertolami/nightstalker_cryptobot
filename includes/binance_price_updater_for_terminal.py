import json
import time
import mysql.connector # type: ignore
import requests
import logging
import signal
import sys

# For clean exit
running = True

def signal_handler(sig, frame):
    global running
    print("Shutting down gracefully...")
    print("Backend price update script stopped.")
    running = False
    sys.exit(0)

signal.signal(signal.SIGINT, signal_handler)

def get_portfolio_symbols():
    """Fetches coin symbols from the portfolio in the database."""
    try:
        connection = mysql.connector.connect(
            unix_socket="/opt/lampp/var/mysql/mysql.sock",
            host="127.0.0.1",
            user="root",
            password="1304",
            database="NS",
            port=3307
        )
        cursor = connection.cursor()
        cursor.execute("SELECT coin_id FROM portfolio;")
        symbols = cursor.fetchall()
        cursor.close()
        connection.close()
        return [f"{symbol[0].upper()}EUR" for symbol in symbols]  # Binance uses symbols like ETHEUR without dash
    except mysql.connector.Error as err:
        print(f"Database error in get_portfolio_symbols: {err}")
        return []
    except Exception as e:
        print(f"An unexpected error occurred in get_portfolio_symbols: {e}")
        return []

def fetch_binance_prices(symbols):
    """Fetches current prices for given symbols from Binance API."""
    prices = {}
    try:
        url = "https://api.binance.com/api/v3/ticker/price"
        response = requests.get(url)
        response.raise_for_status()
        data = response.json()
        price_map = {item['symbol']: item['price'] for item in data}
        for symbol in symbols:
            if symbol in price_map:
                prices[symbol] = float(price_map[symbol])
    except Exception as e:
        print(f"Error fetching prices from Binance API: {e}")
    return prices

def log_and_save_portfolio_prices():
    symbols_to_track = get_portfolio_symbols()
    if not symbols_to_track:
        print("No symbols to track from portfolio.")
        return False

    prices = fetch_binance_prices(symbols_to_track)

    try:
        connection = mysql.connector.connect(
            unix_socket="/opt/lampp/var/mysql/mysql.sock",
            host="127.0.0.1",
            user="root",
            password="1304",
            database="NS",
            port=3307
        )
        cursor = connection.cursor()

        for symbol_eur in symbols_to_track:
            if symbol_eur in prices:
                price = prices[symbol_eur]
                symbol = symbol_eur.replace('EUR', '')

                print(f"{symbol_eur}:{price}")

                insert_query = "INSERT INTO price_history (coin_id, price) VALUES (%s, %s)"
                cursor.execute(insert_query, (symbol, price))
                connection.commit()
                print(f"Successfully inserted price for {symbol}: {price}")

        cursor.close()
        connection.close()

    except mysql.connector.Error as err:
        print(f"Database error during price insertion: {err}")
    except Exception as e:
        print(f"An error occurred during database operation: {e}")

    return True

if __name__ == '__main__':
    print("Backend price update script started.")
    print("--- Script starting ---")
    while running:
        if not log_and_save_portfolio_prices():
            print("No portfolio coins found. Shutting down.")
            break
        time.sleep(3)
    print("--- Script finished ---")
