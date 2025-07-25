#!/usr/bin/env python3

# This script places a market buy order on the Binance Testnet.
#
# To run this script, you need to install the python-binance library:
# pip install python-binance
#
# You also need to set your Binance API Key and Secret as environment variables:
# export BINANCE_API_KEY='your_api_key'
# export BINANCE_API_SECRET='your_api_secret'

from python-binance.client import Client
import os
import sys

# IMPORTANT: Require Binance API Key and Secret from environment variables
API_KEY = os.environ.get('BINANCE_API_KEY')
API_SECRET = os.environ.get('BINANCE_API_SECRET')

if not API_KEY or not API_SECRET:
    print("ERROR: Binance API key and secret must be set in environment variables BINANCE_API_KEY and BINANCE_API_SECRET.")
    sys.exit(1)

# Initialize the client for Binance Testnet
client = Client(API_KEY, API_SECRET, testnet=True)

# Order parameters for a market buy order
symbol = 'BTCUSDT'
side = 'BUY'
order_type = 'MARKET'
quantity = 0.001 # The amount of BTC to buy for testing

print(f"Attempting to place a {side} {order_type} order for {quantity} {symbol}...")

try:
    # For a market buy, you only specify symbol, side, type, and quantity
    order = client.create_order(
        symbol=symbol,
        side=side,
        type=order_type,
        quantity=quantity
    )
    print("Successfully placed order:")
    print(order)
except Exception as e:
    print(f"An error occurred: {e}")
    sys.exit(1)

