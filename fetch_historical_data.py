import requests
import pymysql
from datetime import datetime, timedelta
import time
import pandas as pd
import asyncio
import httpx
import numpy as np
from typing import List

# Database Configuration (not directly used for fetching, but kept for context)
DB_CONFIG = {
    'host': 'localhost',
    'database': 'NS',
    'user': 'dimi',
    'password': '1304',
}

# Bitvavo API Base URL
BITVAVO_API_BASE_URL = "https://api.bitvavo.com/v2"

# Coins to fetch historical data for (using Bitvavo format)
COINS_TO_FETCH = ['BTC-EUR', 'ETH-EUR', 'XRP-EUR', 'ADA-EUR', 'SOL-EUR']

async def fetch_bitvavo_candlestick_data(symbol: str, interval: str, start_time: int, end_time: int, limit: int = 1000) -> pd.DataFrame:
    """
    Fetch historical candlestick data from the Bitvavo API.

    Args:
        symbol (str): The market symbol (e.g., 'BTC-EUR').
        interval (str): The time interval for the candlestick data (e.g., '1h', '1d').
        start_time (int): The start time for the data fetch in milliseconds Unix timestamp.
        end_time (int): The end time for the data fetch in milliseconds Unix timestamp.
        limit (int): The maximum number of data points to return.

    Returns:
        pd.DataFrame: A DataFrame containing the fetched candlestick data.
                      Columns: [timestamp, open, high, low, close, volume]
    """
    url = f"{BITVAVO_API_BASE_URL}/markets/{symbol}/candles?interval={interval}&start={start_time}&end={end_time}&limit={limit}"
    
    async with httpx.AsyncClient() as client:
        response = await client.get(url)
        response.raise_for_status()  # Raise an error for bad responses
        data = response.json()
    
    # Bitvavo returns data as a list of lists: 
    # [timestamp, open, high, low, close, volume]
    df = pd.DataFrame(data, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
    
    # Convert timestamp to datetime and ensure numeric types
    df['timestamp'] = pd.to_datetime(df['timestamp'], unit='ms')
    for col in ['open', 'high', 'low', 'close', 'volume']:
        df[col] = pd.to_numeric(df[col], errors='coerce')
        
    return df

async def fetch_historical_data_for_backtesting(coin_symbols: List[str], days: int = 730) -> pd.DataFrame:
    """
    Fetches historical data for a list of coin symbols from Bitvavo and returns it as a single Pandas DataFrame.
    
    Args:
        coin_symbols (List[str]): A list of coin symbols (e.g., ['BTC-EUR', 'ETH-EUR']).
        days (int): The number of days of historical data to fetch.
        
    Returns:
        pd.DataFrame: A DataFrame containing historical data for all coins.
                      Columns should include: 'symbol', 'timestamp', 'open', 'high', 'low', 'close', 'volume', 'market_cap', 'date_added'.
    """
    all_historical_data = []
    end_time = datetime.now()
    start_time = end_time - timedelta(days=days)

    # Convert to milliseconds Unix timestamp
    start_timestamp_ms = int(start_time.timestamp() * 1000)
    end_timestamp_ms = int(end_time.timestamp() * 1000)

    for coin_symbol in coin_symbols:
        try:
            # Fetch daily candlestick data
            df_candlestick = await fetch_bitvavo_candlestick_data(
                symbol=coin_symbol,
                interval='1d', # Daily interval
                start_time=start_timestamp_ms,
                end_time=end_timestamp_ms
            )
            
            if not df_candlestick.empty:
                df_candlestick['symbol'] = coin_symbol
                
                # Fetch real market cap data from CoinGecko API
                coin_id = SYMBOL_TO_COINGECKO.get(coin_symbol, coin_symbol.split('-')[0].lower())
                date_str = df_candlestick['timestamp'].dt.strftime('%d-%m-%Y').iloc[0]
                
                # For each date, fetch market cap (in practice, you might want to batch this)
                market_caps = []
                for date in df_candlestick['timestamp'].dt.strftime('%d-%m-%Y'):
                    market_cap = await fetch_market_cap_from_coingecko(coin_id, date)
                    market_caps.append(market_cap)
                
                df_candlestick['market_cap'] = market_caps
                
                # If CoinGecko fails, use volume-based estimation as fallback
                if all(mc == 0 for mc in market_caps):
                    df_candlestick = await estimate_market_cap_from_volume(df_candlestick, coin_symbol)
                
                # Set date_added to the first timestamp for each coin
                df_candlestick['date_added'] = df_candlestick['timestamp'].min()
                
                all_historical_data.append(df_candlestick)
                print(f"Successfully fetched {df_candlestick.shape[0]} daily records for {coin_symbol}.")
            else:
                print(f"No daily historical data found for {coin_symbol}.")

        except httpx.HTTPStatusError as e:
            print(f"HTTP error fetching data for {coin_symbol}: {e.response.status_code} - {e.response.text}")
        except httpx.RequestError as e:
            print(f"Request error fetching data for {coin_symbol}: {e}")
        except Exception as e:
            print(f"An unexpected error occurred for {coin_symbol}: {e}")
        
        await asyncio.sleep(0.1) # Be kind to the API

    if not all_historical_data:
        print("No historical data fetched. Returning empty DataFrame.")
        return pd.DataFrame()

    df_combined = pd.concat(all_historical_data, ignore_index=True)
    
    # Ensure all necessary columns are present and correctly typed
    required_cols = ['symbol', 'timestamp', 'open', 'high', 'low', 'close', 'volume', 'market_cap', 'date_added']
    for col in required_cols:
        if col not in df_combined.columns:
            df_combined[col] = np.nan # Add missing columns as NaN
            
    # Fill any remaining NaN values that might result from coercion or missing data
    df_combined = df_combined.fillna(0) # Or use a more sophisticated imputation strategy

    return df_combined

if __name__ == "__main__":
    # Example Usage:
    async def main():
        historical_df = await fetch_historical_data_for_backtesting(COINS_TO_FETCH, days=30)
        if not historical_df.empty:
            print(f"Fetched historical data shape: {historical_df.shape}")
            print(historical_df.head())
        else:
            print("No data fetched.")

    asyncio.run(main())