import os
import sys

# Add the current directory to Python path
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from flask import Flask, jsonify, request
from flask_cors import CORS, cross_origin
from database import engine, SessionLocal, Base
from trading_db import init_db, Trade, TradingSignal, Position
import os
from datetime import datetime, timedelta
import pandas as pd
import numpy as np
import requests
from cachetools import TTLCache
import logging

# Set up logging
logging.basicConfig(
    filename='backend.log',
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)

app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}}, supports_credentials=True)

# Cache configuration (100 items, 1 hour TTL)
CACHE = TTLCache(maxsize=100, ttl=3600)

# Binance API configuration
BINANCE_API_BASE = "https://api.binance.com/api/v3"

# Interval mapping
INTERVAL_MAP = {
    '1m': '1m',
    '5m': '5m',
    '10m': '10m',
    '15m': '15m',
    '30m': '30m',
    '1h': '1h',
    '4h': '4h',
    '1d': '1d',
    '1w': '1w'
}

# Initialize database
try:
    init_db()
    logging.info("Database initialized successfully")
except Exception as e:
    logging.error(f"Failed to initialize database: {str(e)}")

def get_binance_klines(symbol, interval, limit=500):
    """Fetch klines data from Binance"""
    try:
        # Convert symbol to Binance format (remove /USDT)
        binance_symbol = symbol.replace('/', '')
        
        # Get data from Binance
        url = f"{BINANCE_API_BASE}/klines"
        params = {
            'symbol': binance_symbol,
            'interval': interval,
            'limit': limit
        }
        
        response = requests.get(url, params=params)
        response.raise_for_status()
        
        # Convert response to pandas DataFrame
        df = pd.DataFrame(response.json(), columns=[
            'timestamp', 'open', 'high', 'low', 'close', 'volume',
            'close_time', 'quote_volume', 'trades', 'taker_buy_base',
            'taker_buy_quote', 'ignored'
        ])
        
        # Convert timestamp to datetime
        df['timestamp'] = pd.to_datetime(df['timestamp'], unit='ms')
        
        # Convert price columns to float
        df['close'] = df['close'].astype(float)
        
        return df
    except Exception as e:
        logging.error(f"Error fetching Binance data: {str(e)}")
        raise

def calculate_technical_indicators(df):
    """Calculate technical indicators for the given DataFrame"""
    # Calculate SMA 20
    df['sma20'] = df['close'].rolling(window=20).mean()
    
    # Calculate SMA 50
    df['sma50'] = df['close'].rolling(window=50).mean()
    
    # Calculate RSI
    delta = df['close'].diff()
    gain = (delta.where(delta > 0, 0)).rolling(window=14).mean()
    loss = (-delta.where(delta < 0, 0)).rolling(window=14).mean()
    rs = gain / loss
    df['rsi'] = 100 - (100 / (1 + rs))
    
    # Calculate MACD
    df['ema12'] = df['close'].ewm(span=12, adjust=False).mean()
    df['ema26'] = df['close'].ewm(span=26, adjust=False).mean()
    df['macd'] = df['ema12'] - df['ema26']
    df['macd_signal'] = df['macd'].ewm(span=9, adjust=False).mean()
    
    # Calculate Bollinger Bands
    df['bb_middle'] = df['close'].rolling(window=20).mean()
    df['bb_std'] = df['close'].rolling(window=20).std()
    df['bb_upper'] = df['bb_middle'] + (df['bb_std'] * 2)
    df['bb_lower'] = df['bb_middle'] - (df['bb_std'] * 2)
    
    return df

@app.route('/api/data/<symbol>/<interval>', methods=['GET'])
async def get_chart_data(symbol, interval):
    """Get chart data for a specific symbol and interval"""
    try:
        # Check cache first
        cache_key = f"{symbol}_{interval}"
        if cache_key in CACHE:
            return jsonify(CACHE[cache_key])

        # Fetch data from Binance
        df = get_binance_klines(symbol, INTERVAL_MAP[interval])
        
        # Calculate technical indicators
        df = calculate_technical_indicators(df)
        
        # Prepare response data
        response_data = {
            'time': df['timestamp'].dt.strftime('%Y-%m-%d %H:%M:%S').tolist(),
            'price': df['close'].tolist(),
            'sma20': df['sma20'].tolist(),
            'sma50': df['sma50'].tolist(),
            'rsi': df['rsi'].tolist(),
            'macd': df['macd'].tolist(),
            'macd_signal': df['macd_signal'].tolist(),
            'bb_upper': df['bb_upper'].tolist(),
            'bb_lower': df['bb_lower'].tolist()
        }

        # Cache the data
        CACHE[cache_key] = response_data

        return jsonify(response_data)
    except Exception as e:
        logging.error(f"Error in get_chart_data: {str(e)}")
        return jsonify({'error': str(e)}), 500

@app.route('/api/health', methods=['GET'])
async def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'healthy',
        'timestamp': datetime.now().isoformat()
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)
