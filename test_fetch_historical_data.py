#!/usr/bin/env python3
"""
Test script for the improved fetch_historical_data.py functionality
Tests both the market cap calculation and the async fetch functionality
"""

import asyncio
import pandas as pd
from fetch_historical_data import (
    fetch_historical_data_for_backtesting,
    fetch_market_cap_from_coingecko,
    estimate_market_cap_from_volume,
    SYMBOL_TO_COINGECKO
)

async def test_market_cap_fetching():
    """Test the market cap fetching functionality"""
    print("=== Testing Market Cap Fetching ===")
    
    # Test CoinGecko API
    print("Testing CoinGecko API...")
    market_cap = await fetch_market_cap_from_coingecko('bitcoin', '01-01-2024')
    print(f"Bitcoin market cap on 2024-01-01: ${market_cap:,.2f}")
    
    # Test fallback volume estimation
    print("\nTesting volume-based estimation...")
    test_df = pd.DataFrame({
        'close': [50000, 51000, 52000],
        'volume': [1000000, 1200000, 1100000]
    })
    estimated_df = await estimate_market_cap_from_volume(test_df, 'BTC-EUR')
    print(f"Estimated market caps: {estimated_df['market_cap'].tolist()}")

async def test_full_data_fetch():
    """Test the complete data fetching process"""
    print("\n=== Testing Full Data Fetch ===")
    
    # Test with a small subset
    test_symbols = ['BTC-EUR', 'ETH-EUR']
    
    try:
        df = await fetch_historical_data_for_backtesting(test_symbols, days=7)
        
        if not df.empty:
            print(f"✅ Successfully fetched data for {len(df)} records")
            print(f"Columns: {list(df.columns)}")
            print(f"Symbols: {df['symbol'].unique()}")
            print(f"Date range: {df['timestamp'].min()} to {df['timestamp'].max()}")
            
            # Check market cap values
            print("\nMarket cap statistics:")
            for symbol in test_symbols:
                symbol_data = df[df['symbol'] == symbol]
                if not symbol_data.empty:
                    avg_market_cap = symbol_data['market_cap'].mean()
                    print(f"{symbol}: Average market cap = ${avg_market_cap:,.2f}")
            
            # Save sample for frontend testing
            df.to_csv('test_historical_data.csv', index=False)
            print("\n📊 Sample data saved to 'test_historical_data.csv'")
            
            return True
        else:
            print("❌ No data fetched")
            return False
            
    except Exception as e:
        print(f"❌ Error during fetch: {e}")
        return False

async def run_all_tests():
    """Run all tests"""
    print("🧪 Starting comprehensive tests...\n")
    
    # Test market cap functionality
    await test_market_cap_fetching()
    
    # Test full data fetch
    success = await test_full_data_fetch()
    
    if success:
        print("\n✅ All tests completed successfully!")
        print("\nNext steps:")
        print("1. Check 'test_historical_data.csv' for sample data")
        print("2. Run the frontend test page (see test_frontend.html)")
        print("3. Verify market cap values look reasonable")
    else:
        print("\n❌ Some tests failed - check error messages above")

if __name__ == "__main__":
    asyncio.run(run_all_tests())
