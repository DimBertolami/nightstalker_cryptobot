#!/usr/bin/env python3
"""
Coin Metadata Retrieval Functions

This script provides functions to retrieve detailed metadata about specific coins
from Binance or Bitvavo exchanges using the CoinMarketCap API.
"""
from datetime import datetime
import time
age_filter=700
mcap_filter=1500000
vol_filter=1500000
large_csv="_largelist_"
small_csv="_smalllist_"

import requests
import pandas as pd # type: ignore

import json
from typing import Dict, Any, List, Optional, Union

timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')

def get_binance_coin_metadata(coin_symbol: str) -> Dict[str, Any]:
    """
    Retrieve detailed metadata for a specific coin listed on Binance.
    
    Args:
        coin_symbol: The symbol of the coin (e.g., 'BTC', 'ETH')
        
    Returns:
        Dictionary containing all available metadata for the coin
    """
# --- CONFIGURATION ---
# SPARE KEY 1: a36ab379-15a0-409b-99ec-85ab7f2836ea
# SPARE KEY 2: 1758e18b-1744-4ad6-a2a9-908af2f33c8a
# SPARE KEY 3: 2b0c6f1d-4e7a-4f5c-8b9d-0f8c1e2b3a4b
# used up this month: API_KEY = 'e2e746c1-169a-4778-90f7-a66458a6af00'
    API_KEY= '1758e18b-1744-4ad6-a2a9-908af2f33c8a'
    headers = {'X-CMC_PRO_API_KEY': API_KEY}
    
    try:
        # First, get the coin's CMC ID using the cryptocurrency/map endpoint
        map_url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/map'
        map_params = {'symbol': coin_symbol}
        
        map_response = requests.get(map_url, headers=headers, params=map_params)
        map_response.raise_for_status()
        map_data = map_response.json()
        
        if not map_data.get('data'):
            print(f"Coin {coin_symbol} not found.")
            return {}
        
        # Get the first matching coin's ID
        coin_id = map_data['data'][0]['id']
        
        # Now get detailed metadata using the cryptocurrency/info endpoint
        info_url = 'https://pro-api.coinmarketcap.com/v2/cryptocurrency/info'
        info_params = {'id': coin_id}
        
        info_response = requests.get(info_url, headers=headers, params=info_params)
        info_response.raise_for_status()
        info_data = info_response.json()
        
        if str(coin_id) not in info_data.get('data', {}):
            print(f"{coin_symbol} (ID: {coin_id}) not found.")
            return {}
        
        coin_info = info_data['data'][str(coin_id)]
        
        # Get Binance-specific information
        # Check if the coin is listed on Binance
        is_on_binance = False
        binance_listing_date = None
        
        if 'urls' in coin_info and 'markets' in coin_info['urls']:
            for market_url in coin_info['urls']['markets']:
                if 'binance.com' in market_url:
                    is_on_binance = True
                    break
        
        # Get the first historical data point from CMC to estimate listing date
        if is_on_binance:
            try:
                # Use the quotes/historical endpoint to get the earliest data
                historical_url = f'https://pro-api.coinmarketcap.com/v3/cryptocurrency/quotes/historical'
                historical_params = {
                    'id': coin_id,
                    'count': 1,
                    'interval': 'daily',
                    'convert': 'USD'
                }
                
                historical_response = requests.get(historical_url, headers=headers, params=historical_params)
                if historical_response.status_code == 200:
                    historical_data = historical_response.json()
                    if 'data' in historical_data and 'quotes' in historical_data['data']:
                        earliest_quote = historical_data['data']['quotes'][0]
                        binance_listing_date = earliest_quote['timestamp']
            except Exception as e:
                print(f"Error fetching historical data: {e}")
        
        # Add Binance-specific information to the coin info
        coin_info['is_on_binance'] = is_on_binance
        coin_info['binance_listing_date'] = binance_listing_date
        
        # Get available metadata fields (column names)
        metadata_fields = list_metadata_fields(coin_info)
        coin_info['available_fields'] = metadata_fields
        
        return coin_info
    
    except requests.exceptions.RequestException as e:
        print(f"API Error: {e}")
        return {}

def get_bitvavo_coin_metadata(coin_symbol: str) -> Dict[str, Any]:
    """
    Retrieve detailed metadata for a specific coin listed on Bitvavo.
    
    Args:
        coin_symbol: The symbol of the coin (e.g., 'BTC', 'ETH')
        
    Returns:
        Dictionary containing all available metadata for the coin
    """
    # CoinMarketCap API key
    API_KEY = 'a36ab379-15a0-409b-99ec-85ab7f2836ea'
    headers = {'X-CMC_PRO_API_KEY': API_KEY}
    
    try:
        # First, check if the coin is available on Bitvavo
        bitvavo_url = 'https://api.bitvavo.com/v2/markets'
        bitvavo_response = requests.get(bitvavo_url)
        bitvavo_response.raise_for_status()
        bitvavo_data = bitvavo_response.json()
        
        # Check if the coin is listed on Bitvavo
        is_on_bitvavo = False
        for market in bitvavo_data:
            if market['base'] == coin_symbol:
                is_on_bitvavo = True
                break
        
        if not is_on_bitvavo:
            print(f"Coin {coin_symbol} not found on Bitvavo.")
            return {}
        
        # Now get the coin's CMC ID using the cryptocurrency/map endpoint
        map_url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/map'
        map_params = {'symbol': coin_symbol}
        
        map_response = requests.get(map_url, headers=headers, params=map_params)
        map_response.raise_for_status()
        map_data = map_response.json()
        
        if not map_data.get('data'):
            print(f"Coin {coin_symbol} not found in CoinMarketCap database.")
            return {}
        
        # Get the first matching coin's ID
        coin_id = map_data['data'][0]['id']
        
        # Now get detailed metadata using the cryptocurrency/info endpoint
        info_url = 'https://pro-api.coinmarketcap.com/v2/cryptocurrency/info'
        info_params = {'id': coin_id}
        
        info_response = requests.get(info_url, headers=headers, params=info_params)
        info_response.raise_for_status()
        info_data = info_response.json()
        
        if str(coin_id) not in info_data.get('data', {}):
            print(f"Detailed info for {coin_symbol} (ID: {coin_id}) not found.")
            return {}
        
        coin_info = info_data['data'][str(coin_id)]
        
        # Get Bitvavo-specific information
        bitvavo_listing_date = None
        
        # Try to get the first historical data point from CMC to estimate listing date
        try:
            # Use the quotes/historical endpoint to get the earliest data
            historical_url = f'https://pro-api.coinmarketcap.com/v3/cryptocurrency/quotes/historical'
            historical_params = {
                'id': coin_id,
                'count': 1,
                'interval': 'daily',
                'convert': 'USD'
            }
            
            historical_response = requests.get(historical_url, headers=headers, params=historical_params)
            if historical_response.status_code == 200:
                historical_data = historical_response.json()
                if 'data' in historical_data and 'quotes' in historical_data['data']:
                    earliest_quote = historical_data['data']['quotes'][0]
                    bitvavo_listing_date = earliest_quote['timestamp']
        except Exception as e:
            print(f"Error fetching historical data: {e}")
        
        # Add Bitvavo-specific information to the coin info
        coin_info['is_on_bitvavo'] = is_on_bitvavo
        coin_info['bitvavo_listing_date'] = bitvavo_listing_date
        
        # Get available metadata fields (column names)
        metadata_fields = list_metadata_fields(coin_info)
        coin_info['available_fields'] = metadata_fields
        
        return coin_info
    
    except requests.exceptions.RequestException as e:
        print(f"API Error: {e}")
        return {}

def list_metadata_fields(data: Dict[str, Any], prefix: str = '') -> List[str]:
    """
    Recursively extract all field names (keys) from a nested dictionary.
    
    Args:
        data: The dictionary to extract field names from
        prefix: Prefix for nested fields
        
    Returns:
        List of all field names in the dictionary
    """
    fields = []
    
    for key, value in data.items():
        field_name = f"{prefix}.{key}" if prefix else key
        
        # Add the current field
        fields.append(field_name)
        
        # Recursively process nested dictionaries
        if isinstance(value, dict):
            nested_fields = list_metadata_fields(value, field_name)
            fields.extend(nested_fields)
        # Handle lists of dictionaries
        elif isinstance(value, list) and value and isinstance(value[0], dict):
            # Just add the list field name with [0] to indicate it's a list
            fields.append(f"{field_name}[0]")
    
    return fields

def filter_exchange_coins(exchange: str, age_filter: int = None, mcap_filter: float = None, vol_filter: float = None) -> pd.DataFrame:
    """
    Filter coins from a specific exchange based on age, market cap, and volume criteria.
    
    Args:
        exchange: The exchange to filter coins from ('binance' or 'bitvavo')
        age_filter: Maximum age in hours to filter coins (None = no filter)
        mcap_filter: Minimum market cap in USD to filter coins (None = no filter)
        vol_filter: Minimum 24h volume in USD to filter coins (None = no filter)
        
    Returns:
        DataFrame containing filtered coins with their metadata
    """
    # CoinMarketCap API key from config
    API_KEY = 'e2e746c1-169a-4778-90f7-a66458a6af00'
    headers = {'X-CMC_PRO_API_KEY': API_KEY}
    
    # Get current time for age calculation
    current_time = datetime.now()
    
    try:
        # Step 1: Get all cryptocurrencies from CMC
        listings_url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest'
        listings_params = {
            'limit': 5000,  # Get as many as possible
            'convert': 'USD'
        }
        
        print(f"Fetching cryptocurrency listings from CoinMarketCap...")
        listings_response = requests.get(listings_url, headers=headers, params=listings_params)
        listings_response.raise_for_status()
        listings_data = listings_response.json()
        
        all_coins = listings_data['data']
        print(f"Retrieved {len(all_coins)} cryptocurrencies from CMC")
        
        # Step 2: Get exchange-specific coins
        exchange_coins = set()
        
        if exchange.lower() == 'binance':
            # Get Binance coins
            binance_url = 'https://api.binance.com/api/v3/exchangeInfo'
            binance_response = requests.get(binance_url)
            binance_response.raise_for_status()
            binance_data = binance_response.json()
            
            # Extract symbols from Binance
            for symbol_info in binance_data['symbols']:
                if symbol_info['status'] == 'TRADING' and symbol_info['quoteAsset'] in ['USDT', 'BUSD', 'BTC', 'ETH']:
                    exchange_coins.add(symbol_info['baseAsset'])
                    
            print(f"Found {len(exchange_coins)} coins on Binance")
            
        elif exchange.lower() == 'bitvavo':
            # Get Bitvavo coins
            bitvavo_url = 'https://api.bitvavo.com/v2/markets'
            print("Fetching Bitvavo markets info...")
            bitvavo_response = requests.get(bitvavo_url)
            bitvavo_response.raise_for_status()
            bitvavo_data = bitvavo_response.json()
            
            # Extract symbols from Bitvavo
            for market in bitvavo_data:
                if market['status'] == 'trading':
                    exchange_coins.add(market['base'])
                    
            print(f"Found {len(exchange_coins)} coins on Bitvavo")
        
        else:
            print(f"Unsupported exchange: {exchange}.only 2 possibilities: binance, bitvavo")
            return pd.DataFrame()
        
        # Step 3: Match exchange coins with CMC data and apply filters
        filtered_coins = []
        
        for coin in all_coins:
            symbol = coin['symbol']
            
            # Check if coin is on the specified exchange
            if symbol not in exchange_coins:
                continue
                
            # Get required data for filtering
            date_added_str = coin.get('date_added')
            market_cap = coin['quote']['USD'].get('market_cap', 0)
            volume_24h = coin['quote']['USD'].get('volume_24h', 0)
            
            # Skip if missing required data
            if not date_added_str:
                continue
                
            # Calculate coin age in hours
            try:
                date_added = datetime.strptime(date_added_str, '%Y-%m-%dT%H:%M:%S.%fZ')
                age_hours = (current_time - date_added).total_seconds() / 3600
            except ValueError:
                try:
                    date_added = datetime.strptime(date_added_str, '%Y-%m-%dT%H:%M:%SZ')
                    age_hours = (current_time - date_added).total_seconds() / 3600
                except ValueError:
                    print(f"Could not parse date: {date_added_str} for {symbol}")
                    continue
            
            # Apply filters
            if age_filter is not None and age_hours > age_filter:
                continue
                
            if mcap_filter is not None and (market_cap is None or market_cap < mcap_filter):
                continue
                
            if vol_filter is not None and (volume_24h is None or volume_24h < vol_filter):
                continue
                
            # Add coin to filtered list with all relevant data
            filtered_coins.append({
                'symbol': symbol,
                'name': coin['name'],
                'slug': coin['slug'],
                'date_added': date_added_str,
                'age_hours': round(age_hours, 2),
                'price': coin['quote']['USD']['price'],
                'market_cap': market_cap,
                'volume_24h': volume_24h,
                'percent_change_24h': coin['quote']['USD'].get('percent_change_24h', 0),
                'percent_change_1h': coin['quote']['USD'].get('percent_change_1h', 0),
                'percent_change_7d': coin['quote']['USD'].get('percent_change_7d', 0),
                'exchange': exchange
            })
        
        # Create DataFrame from filtered coins
        df = pd.DataFrame(filtered_coins)
        
        # Sort by age (newest first)
        if not df.empty and 'age_hours' in df.columns:
            df = df.sort_values('age_hours')
            
        return df
        
    except requests.exceptions.RequestException as e:
        print(f"API Error: {e}")
        return pd.DataFrame()

def generate_filtered_csvs(exchange: str):
    """
    Generate a CSV file with all the assets for binance or  bitvavo.
    Generate a 2nd CSV file with just the strongest assets that clearly show growth potential for binance or bitvavo.
    
    Args:
        exchange: The exchange to generate CSVs for ('binance' or 'bitvavo')
    """
    #print(f"\nGenerating CSV files for {exchange.capitalize()} ...")
    
    # Generate CSV with no filters (complete list)
    print("\n1. Generating large list...")
    all_coins_df = filter_exchange_coins(exchange)
    all_coins_csv = f"{exchange}{large_csv}{timestamp}.csv"
    all_coins_df.to_csv(all_coins_csv, index=False)
    #print(f"Saved {len(all_coins_df)} coins to {all_coins_csv}")
    
    # Generate CSV with combined filters (age + mcap + vol)
    #print("\n5. Generating list with combined filters (age < {age_filter}+ mcap 1.5M + vol 1.5M)...")
    combined_coins_df = filter_exchange_coins(exchange, age_filter=age_filter, mcap_filter=mcap_filter, vol_filter=vol_filter)
    combined_coins_csv = f"{exchange}{small_csv}{timestamp}.csv"
    combined_coins_df.to_csv(combined_coins_csv, index=False)
    #print(f"Saved {len(combined_coins_df)} coins to {combined_coins_csv}")
    
    #print(f"\nAll dataprocessing done for {exchange}!")
    return {
        'all': large_csv,
        'combined': small_csv
    }

if __name__ == "__main__":
    # Parse command line arguments
    import sys
    import argparse
    
    parser = argparse.ArgumentParser(description='Cryptocurrency metadata and filtering tool')
    parser.add_argument('action', choices=['metadata', 'filter'], help='Action to perform: get metadata for a single coin or filter coins')
    parser.add_argument('exchange', choices=['binance', 'bitvavo'], help='Exchange to query')
    
    # For metadata action
    parser.add_argument('symbol', nargs='?', help='Coin symbol (required for metadata action)')
    
    # For filter action - positional filter arguments
    parser.add_argument('filters', nargs='*', help='Filters: "age" "mcap" "vol"')
    
    args = parser.parse_args()
    
    # Process the filters
    age_filter = None
    mcap_filter = None
    vol_filter = None
    
    if args.action == 'filter' and args.filters:
        if 'age' in args.filters:
            age_filter = 24  # Default to 24 hours
        if 'mcap' in args.filters:
            mcap_filter = 1500000  # Default to 1.5M
        if 'vol' in args.filters:
            vol_filter = 1500000  # Default to 1.5M
    
    if args.action == 'metadata':
        if not args.symbol:
            print("Error: symbol is required for metadata action")
            print("Usage: python coin_metadata.py metadata binance|bitvavo SYMBOL")
            print("Example: python coin_metadata.py metadata binance BTC")
            sys.exit(1)
            
        # Get metadata for a single coin
        if args.exchange == 'binance':
            metadata = get_binance_coin_metadata(args.symbol)
        else:  # bitvavo
            metadata = get_bitvavo_coin_metadata(args.symbol)
            
        if metadata:
            print(f"\n{args.exchange.capitalize()} Metadata for {args.symbol}:")
            print("=" * 50)
            
            # Print available fields
            print("\nAvailable Metadata Fields:")
            print("-" * 30)
            for field in metadata.get('available_fields', []):
                print(f"- {field}")
            
            # Print some key information
            print("\nKey Information:")
            print("-" * 30)
            print(f"Name: {metadata.get('name', 'N/A')}")
            print(f"Symbol: {metadata.get('symbol', 'N/A')}")
            print(f"Slug: {metadata.get('slug', 'N/A')}")
            print(f"Category: {metadata.get('category', 'N/A')}")
            print(f"Description: {metadata.get('description', 'N/A')[:100]}..." if metadata.get('description') else "Description: N/A")
            print(f"Date Added to CMC: {metadata.get('date_added', 'N/A')}")
            
            if args.exchange == 'binance':
                print(f"Listed on Binance: {metadata.get('is_on_binance', False)}")
                print(f"Binance Listing Date (est.): {metadata.get('binance_listing_date', 'N/A')}")
            else:
                print(f"Listed on Bitvavo: {metadata.get('is_on_bitvavo', False)}")
                print(f"Bitvavo Listing Date (est.): {metadata.get('bitvavo_listing_date', 'N/A')}")
            
            # Save full metadata to JSON file
            output_file = f"{args.symbol}_{args.exchange}_metadata.json"
            with open(output_file, 'w') as f:
                json.dump(metadata, f, indent=2)
            
            print(f"\nFull metadata saved to {output_file}")
        else:
            print(f"No metadata found for {args.symbol} on {args.exchange}.")
            
    elif args.action == 'filter':
        # Apply specified filters
        #print(f"Filtering {args.exchange} coins with specified criteria...")
        
        # Build filter description
        filter_desc = []
        if age_filter is not None:
            filter_desc.append(f"age <= {age_filter}h")
        if mcap_filter is not None:
            filter_desc.append(f"market cap >= ${mcap_filter:,.0f}")
        if vol_filter is not None:
            filter_desc.append(f"volume >= ${vol_filter:,.0f}")
            
        if filter_desc:
            print(f"Applying filters")
        else:
            print("showing all assets")
        
        # Get filtered coins
        filtered_df = filter_exchange_coins(
            args.exchange, 
            age_filter=age_filter, 
            mcap_filter=mcap_filter, 
            vol_filter=vol_filter
        )
        
        # Save to CSV
        #filters_text = '_'.join(filter_desc).replace(' ', '').replace('>=', 'min').replace('<=', 'max')
        #if not filters_text:
        #    filters_text = 'all'
            
        csv_filename = f"{args.exchange}{timestamp}.csv"
        filtered_df.to_csv(csv_filename, index=False)
        
        #print(f"\nFound {len(filtered_df)} coins matching your criteria")
        print(f"{csv_filename}")
        
        # Display first few results
        if not filtered_df.empty:
            #print("\nFirst 10 matching coins:")
            display_cols = ['symbol', 'name', 'age_hours', 'price', 'market_cap', 'volume_24h', 'percent_change_24h']
            #print(filtered_df[display_cols].head(10).to_string(index=False))
            
            #if len(filtered_df) > 10:
            #    print(f"\n... and {len(filtered_df) - 10} more coins in the CSV file")
    
    else:
        parser.print_help()
