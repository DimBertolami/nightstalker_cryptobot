"""
repair_date_added_from_cmc.py

Bulk repairs the date_added field in your coins database using correct values from CoinMarketCap.
- Finds coins with suspicious date_added (e.g., last 12 hours)
- Fetches correct date_added from CMC API
- Updates your DB with the correct value

Usage: python repair_date_added_from_cmc.py
"""

import os
import mysql.connector
import requests
from datetime import datetime, timedelta

# --- CONFIG ---
# TODO: Fill in your real database credentials below:
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',      # <-- PUT YOUR DB USERNAME HERE
    'password': '1304',  # <-- PUT YOUR DB PASSWORD HERE
    'database': 'NS',  # <-- PUT YOUR DB NAME HERE
}
# TODO: Set your CoinMarketCap API key below or via environment variable:
CMC_API_KEY = os.environ.get('CMC_API_KEY') or 'e2e746c1-169a-4778-90f7-a66458a6af00'
CMC_LISTINGS_URL = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest'

# --- PARAMETERS ---
SUSPICIOUS_HOURS = 12  # Coins with date_added in last N hours will be checked
BATCH_SIZE = 100

# --- DB CONNECT ---
def get_db_conn():
    return mysql.connector.connect(**DB_CONFIG)

def get_suspicious_coins():
    conn = get_db_conn()
    cursor = conn.cursor(dictionary=True)
    cutoff = (datetime.utcnow() - timedelta(hours=SUSPICIOUS_HOURS)).strftime('%Y-%m-%d %H:%M:%S')
    cursor.execute("SELECT symbol, coin_name, date_added FROM coins WHERE date_added > %s", (cutoff,))
    coins = cursor.fetchall()
    cursor.close()
    conn.close()
    return coins

def fetch_cmc_data():
    headers = {'X-CMC_PRO_API_KEY': CMC_API_KEY}
    params = {'limit': 5000, 'sort': 'market_cap', 'sort_dir': 'desc', 'convert': 'USD'}
    resp = requests.get(CMC_LISTINGS_URL, headers=headers, params=params)
    resp.raise_for_status()
    data = resp.json()['data']
    return {coin['symbol']: coin['date_added'].replace('T', ' ').replace('Z', '') for coin in data}

def repair_dates():
    print('Fetching coins with suspicious date_added...')
    coins = get_suspicious_coins()
    print(f'Found {len(coins)} coins to check.')
    if not coins:
        print('No coins need fixing.')
        return
    cmc_dates = fetch_cmc_data()
    updates = []
    for coin in coins:
        symbol = coin['symbol']
        if symbol in cmc_dates:
            correct_date = cmc_dates[symbol]
            if coin['date_added'] != correct_date:
                updates.append((correct_date, symbol))
    print(f'Prepared {len(updates)} update statements.')
    if not updates:
        print('All coins already have correct date_added.')
        return
    # Update DB
    conn = get_db_conn()
    cursor = conn.cursor()
    for correct_date, symbol in updates:
        cursor.execute("UPDATE coins SET date_added = %s WHERE symbol = %s", (correct_date, symbol))
    conn.commit()
    cursor.close()
    conn.close()
    print(f'Updated {len(updates)} coins in the DB.')

if __name__ == '__main__':
    repair_dates()
