import requests
import json
import sys
import os
import time
import random
import argparse
from datetime import datetime, timedelta
import pickle

# Cache settings
CACHE_FILE = os.path.expanduser("~/.coingecko_cache.pkl")
CACHE_EXPIRY_HOURS = 3  # How long to keep cached coin details

# Constants for API
API_DELAY_MIN = 1.5  # Minimum delay between API calls in seconds
API_DELAY_MAX = 3.0  # Maximum delay between API calls in seconds
MAX_COINS_TO_CHECK = 2500  # Number of coins to check from /coins/list
MAX_DETAILED_CHECKS = 100   # Max number of detailed coin checks

# File to store the known coins list
KNOWN_COINS_FILE = os.path.expanduser("~/.coingecko_known_coins.json")

def api_request(url, params=None, retry_count=3, api_key=None):
    """
    Make an API request with proper rate limiting and retries
    """
    headers = {}
    if api_key:
        headers['x-cg-pro-api-key'] = api_key
    
    for attempt in range(retry_count):
        try:
            # Add a random delay to avoid rate limiting
            time.sleep(random.uniform(API_DELAY_MIN, API_DELAY_MAX))
            
            response = requests.get(url, params=params, headers=headers, timeout=15)
            
            # Handle rate limiting
            if response.status_code == 429:  # Too Many Requests
                wait_time = int(response.headers.get('retry-after', 60))
                print(f"Rate limited, waiting {wait_time} seconds...")
                time.sleep(wait_time)
                continue
                
            # Handle unauthorized (need API key)
            if response.status_code == 401:
                print("Unauthorized - this endpoint requires a CoinGecko API key")
                return None
                
            response.raise_for_status()
            return response.json()
            
        except requests.exceptions.RequestException as e:
            print(f"API request error (attempt {attempt+1}/{retry_count}): {str(e)}")
            if attempt == retry_count - 1:
                raise
            time.sleep(5)  # Wait before retrying
    
    return None

def load_known_coins():
    """
    Load the list of previously known coins
    """
    if os.path.exists(KNOWN_COINS_FILE):
        try:
            with open(KNOWN_COINS_FILE, 'r') as f:
                return json.load(f)
        except (IOError, json.JSONDecodeError) as e:
            print(f"Error loading known coins: {str(e)}")
    return {}

def save_known_coins(known_coins):
    """
    Save the list of known coins
    """
    try:
        with open(KNOWN_COINS_FILE, 'w') as f:
            json.dump(known_coins, f)
    except IOError as e:
        print(f"Error saving known coins: {str(e)}")

def get_first_seen_date(coin_data, cache=None):
    """
    Determine when a coin was first seen based on various data points
    """
    if cache is None:
        cache = load_cache()
    
    coin_id = coin_data['id']
    
    # Check cache first
    if coin_id in cache and datetime.now().timestamp() - cache[coin_id]['timestamp'] < CACHE_EXPIRY_HOURS * 3600:
        return cache[coin_id]['first_seen'], True
    
    # If we have detailed coin data, use it directly
    if isinstance(coin_data, dict) and 'detail_fetched' in coin_data and coin_data['detail_fetched']:
        genesis_date = coin_data.get('genesis_date')
        first_seen = None
        
        if genesis_date and genesis_date != "":
            try:
                first_seen = datetime.strptime(genesis_date, "%Y-%m-%d")
            except ValueError:
                pass
        
        if not first_seen:
            public_interest_stats = coin_data.get('public_interest_stats', {})
            if isinstance(public_interest_stats, dict):
                rank_date = public_interest_stats.get('coingecko_rank_date')
                if rank_date:
                    try:
                        first_seen = datetime.strptime(rank_date, "%Y-%m-%dT%H:%M:%S.%fZ")
                    except ValueError:
                        try:
                            first_seen = datetime.strptime(rank_date, "%Y-%m-%dT%H:%M:%SZ")
                        except ValueError:
                            pass
        
        # Use the coin's first seen timestamp from our tracking
        if not first_seen and 'first_tracked' in coin_data:
            try:
                first_seen = datetime.fromisoformat(coin_data['first_tracked'])
            except ValueError:
                pass
        
        # Still no date, must be a new discovery - use current time
        if not first_seen:
            first_seen = datetime.now()
        
        # Update cache
        cache[coin_id] = {
            'first_seen': first_seen,
            'timestamp': datetime.now().timestamp()
        }
        save_cache(cache)
        
        return first_seen, True
    
    # We don't have enough information
    return datetime.now(), False

def load_cache():
    """Load cached coin data"""
    if os.path.exists(CACHE_FILE):
        try:
            with open(CACHE_FILE, 'rb') as f:
                return pickle.load(f)
        except (IOError, pickle.PickleError):
            pass
    return {}

def save_cache(cache):
    """Save cached coin data"""
    try:
        with open(CACHE_FILE, 'wb') as f:
            pickle.dump(cache, f)
    except (IOError, pickle.PickleError) as e:
        print(f"Error saving cache: {str(e)}")

def get_new_coins(max_age_hours=24, sort_by='volume', top_n=50, api_key=None):
    """
    Fetch newly listed coins from CoinGecko using a coin tracking approach
    
    Parameters:
    - max_age_hours: Maximum age of coins in hours (default: 24)
    - sort_by: Sorting criteria ('volume' or 'marketcap')
    - top_n: Number of top coins to return
    - api_key: Optional CoinGecko API key
    
    Returns:
    - List of dictionaries containing coin data, sorted by specified criteria
    """
    try:
        print(f"Looking for coins less than {max_age_hours} hours old...")
        
        # Load our two tracking systems
        cache = load_cache()  # For coin details we've seen
        known_coins = load_known_coins()  # To track which coins we've seen before
        
        # Get a timestamp for when we run this
        current_time = datetime.now()
        current_time_iso = current_time.isoformat()
        
        # First, get the full coin list from CoinGecko (this endpoint doesn't need API key)
        print("Fetching full coin list...")
        
        # Try to use API key if provided
        if api_key:
            print("Using provided CoinGecko API key")
        
        coins_list_url = "https://api.coingecko.com/api/v3/coins/list"
        coins_list = api_request(coins_list_url, api_key=api_key)
        
        if not coins_list:
            print("Failed to fetch coin list!")
            return []
        
        print(f"Fetched {len(coins_list)} coins from CoinGecko")
        
        # Get the IDs of coins we've never seen before
        new_coin_ids = []
        for coin in coins_list[:MAX_COINS_TO_CHECK]:  # Limit how many we process
            if coin['id'] not in known_coins:
                new_coin_ids.append(coin['id'])
                # Mark this as first time we've seen this coin
                known_coins[coin['id']] = {
                    'first_seen': current_time_iso,
                    'symbol': coin['symbol'].upper(),
                    'name': coin['name']
                }
        
        # Save our updated known coins list
        save_known_coins(known_coins)
        
        if new_coin_ids:
            print(f"Found {len(new_coin_ids)} previously unseen coins")
        else:
            print("No new coins found in the list")
        
        # For coins we can check (rate limits), get market data
        potential_new_coins = []
        checked_count = 0
        
        # Get data in batches to respect API limits (max 100 coins per call)
        # Focus on the most recently discovered coins first
        coins_to_check = sorted(new_coin_ids, key=lambda x: known_coins[x]['first_seen'], reverse=True)
        
        # For testing/limited API access, only check a reasonable number
        if len(coins_to_check) > MAX_DETAILED_CHECKS:
            print(f"Limiting check to {MAX_DETAILED_CHECKS} coins due to API constraints")
            coins_to_check = coins_to_check[:MAX_DETAILED_CHECKS]
        
        # Process in batches of 50 to respect API limits
        batch_size = 50
        for i in range(0, len(coins_to_check), batch_size):
            batch_ids = coins_to_check[i:i+batch_size]
            id_param = ','.join(batch_ids)
            
            # Get market data for this batch
            markets_url = "https://api.coingecko.com/api/v3/coins/markets"
            params = {
                'vs_currency': 'usd',
                'ids': id_param,
                'order': 'volume_desc',
                'per_page': str(batch_size),
                'page': '1',
                'sparkline': 'false',
                'price_change_percentage': '24h'
            }
            
            market_data = api_request(markets_url, params=params, api_key=api_key)
            if not market_data:
                print(f"Failed to fetch market data for batch {i//batch_size + 1}")
                continue
                
            print(f"Processing {len(market_data)} coins from batch {i//batch_size + 1}...")
            
            for coin in market_data:
                checked_count += 1
                
                if not coin['id']:  # Skip if missing ID
                    continue
                
                # Get when we first saw this coin
                first_seen_str = known_coins.get(coin['id'], {}).get('first_seen')
                
                if first_seen_str:
                    try:
                        first_seen_date = datetime.fromisoformat(first_seen_str)
                        age_hours = (current_time - first_seen_date).total_seconds() / 3600
                        
                        # Get additional coin details for a few of the newest coins
                        coin_with_details = coin.copy()
                        
                        # If this coin is new enough for our criteria
                        if age_hours <= max_age_hours:
                            # Format the data
                            new_coin_data = {
                                'id': coin['id'],
                                'name': coin['name'],
                                'symbol': coin['symbol'].upper(),
                                'age_hours': age_hours,
                                'volume': coin.get('total_volume') or 0,
                                'market_cap': coin.get('market_cap') or 0,
                                'price': coin.get('current_price') or 0,
                                'price_change_24h': coin.get('price_change_percentage_24h_in_currency') or 0,
                                'first_seen': first_seen_str,
                                'is_truly_new': True,
                                'coingecko_rank': coin.get('coingecko_rank'),
                                'image': coin.get('image')
                            }
                            potential_new_coins.append(new_coin_data)
                    except (ValueError, TypeError) as e:
                        print(f"Error processing date for {coin['id']}: {e}")
        
        # Check for any coins we might have seen before but are still new
        # Use the existing coin market data endpoint which has good performance
        print("Checking market data for additional new coins...")
        recent_coins_url = "https://api.coingecko.com/api/v3/coins/markets"
        recent_params = {
            'vs_currency': 'usd',
            'order': 'market_cap_desc',  # Valid order parameter
            'per_page': 250,
            'page': 1,
            'sparkline': 'false'  # API expects string 'false', not boolean
        }
        
        recent_market_data = api_request(recent_coins_url, params=recent_params, api_key=api_key)
        if recent_market_data:
            # Process each coin
            for coin in recent_market_data:
                # Skip if we already added this coin
                if coin['id'] in [c['id'] for c in potential_new_coins]:
                    continue
                    
                # Check if this coin might be new
                if coin['id'] in known_coins:
                    first_seen_str = known_coins[coin['id']].get('first_seen')
                    if first_seen_str:
                        try:
                            first_seen_date = datetime.fromisoformat(first_seen_str)
                            age_hours = (current_time - first_seen_date).total_seconds() / 3600
                            
                            # If new enough, add to our potential list
                            if age_hours <= max_age_hours:
                                new_coin_data = {
                                    'id': coin['id'],
                                    'name': coin['name'],
                                    'symbol': coin['symbol'].upper(),
                                    'age_hours': age_hours,
                                    'volume': coin.get('total_volume') or 0,
                                    'market_cap': coin.get('market_cap') or 0,
                                    'price': coin.get('current_price') or 0,
                                    'price_change_24h': coin.get('price_change_percentage_24h_in_currency') or 0,
                                    'first_seen': first_seen_str,
                                    'is_truly_new': True,
                                    'coingecko_rank': coin.get('coingecko_rank'),
                                    'image': coin.get('image')
                                }
                                potential_new_coins.append(new_coin_data)
                        except (ValueError, TypeError) as e:
                            pass  # Silently skip date parsing issues
                    
        # Sort the results
        if sort_by == 'volume':
            potential_new_coins.sort(key=lambda x: x['volume'] if x['volume'] is not None else 0, reverse=True)
        elif sort_by == 'marketcap':
            potential_new_coins.sort(key=lambda x: x['market_cap'] if x['market_cap'] is not None else 0, reverse=True)
        else:  # default to age (newest first)
            potential_new_coins.sort(key=lambda x: x['age_hours'] if x['age_hours'] is not None else float('inf'))
        
        # Take only the top N results
        result_coins = potential_new_coins[:top_n]
        
        print(f"Found {len(potential_new_coins)} genuinely new coins less than {max_age_hours} hours old")
        print(f"Returning top {len(result_coins)} coins based on {sort_by}")
        
        return result_coins
    
    except Exception as e:
        print(f"Error in get_new_coins: {str(e)}")
        import traceback
        traceback.print_exc()
        return []

if __name__ == "__main__":
    # Parse command line arguments
    parser = argparse.ArgumentParser(description='Track newly listed coins on CoinGecko')
    parser.add_argument('--max-age', type=int, default=24, help='Maximum age in hours for a coin to be considered new (default: 24)')
    parser.add_argument('--sort', choices=['volume', 'marketcap', 'age'], default='volume', help='Sort results by (default: volume)')
    parser.add_argument('--limit', type=int, default=50, help='Limit number of results (default: 50)')
    parser.add_argument('--json', action='store_true', help='Output in JSON format for machine consumption')
    parser.add_argument('--api-key', type=str, help='CoinGecko API key for accessing premium endpoints')
    parser.add_argument('--reset-tracking', action='store_true', help='Reset the tracking database for new coin detection')
    
    args = parser.parse_args()
    
    # Reset tracking if requested
    if args.reset_tracking:
        if os.path.exists(KNOWN_COINS_FILE):
            os.remove(KNOWN_COINS_FILE)
            print(f"Reset coin tracking database: {KNOWN_COINS_FILE}")
    
    # Get new coins
    new_coins = get_new_coins(
        max_age_hours=args.max_age, 
        sort_by=args.sort, 
        top_n=args.limit,
        api_key=args.api_key
    )
    
    if args.json:
        # Format the output for JSON consumption (used by automation)
        output_coins = []
        
        # Create temporary file for JSON output
        import tempfile
        temp_output_file = None
        
        try:
            # Create temp file to store JSON output
            temp_output_file = tempfile.NamedTemporaryFile(mode='w+', delete=False, suffix='.json')
            temp_filename = temp_output_file.name
            
            for coin in new_coins:
                # Create a clean copy with properly formatted values
                output_coin = {
                    'id': coin['id'],
                    'name': coin['name'],
                    'symbol': coin['symbol'],
                    'age_hours': float(coin.get('age_hours', 0) or 0),
                    'volume': float(coin.get('volume', 0) or 0),
                    'market_cap': float(coin.get('market_cap', 0) or 0),
                    'price': float(coin.get('price', 0) or 0),
                    'price_change_24h': float(coin.get('price_change_24h', 0) or 0),
                    'first_seen': coin.get('first_seen'),
                    'is_truly_new': coin.get('is_truly_new', True),
                    'coingecko_rank': coin.get('coingecko_rank'),
                    'image': coin.get('image')
                }
                output_coins.append(output_coin)
            
            # Write JSON to temp file
            json.dump(output_coins, temp_output_file)
            temp_output_file.close()
            
            # Print just the filename to stdout for the PHP script to read
            print(f"JSON_FILE:{temp_filename}")            
            
        except Exception as e:
            print(f"Error creating JSON output: {str(e)}", file=sys.stderr)
    else:
        # Format the output for human consumption
        print("\nNEWLY LISTED COINS (Age < {} hours):\n".format(args.max_age))
        print("{:<8} {:<20} {:<12} {:<15} {:<15} {:<10} {:<8}".format(
            "Symbol", "Name", "Age (hours)", "Volume ($)", "Market Cap ($)", "Price ($)", "Change %"
        ))
        print("-" * 95)
        
        for coin in new_coins:
            try:
                age_str = "{:.1f}".format(float(coin.get('age_hours', 0) or 0))
                volume_str = "${:,.0f}".format(float(coin.get('volume', 0) or 0))
                mktcap_str = "${:,.0f}".format(float(coin.get('market_cap', 0) or 0))
                price_str = "${:.8f}".format(float(coin.get('price', 0) or 0))
                change_str = "{:.1f}%".format(float(coin.get('price_change_24h', 0) or 0))
                
                # Truncate name if too long
                name = coin['name'][:19] if len(coin['name']) > 19 else coin['name']
                
                print("{:<8} {:<20} {:<12} {:<15} {:<15} {:<10} {:<8}".format(
                    coin['symbol'],
                    name,
                    age_str,
                    volume_str,
                    mktcap_str,
                    price_str,
                    change_str
                ))
            except (TypeError, ValueError) as e:
                print(f"Error formatting coin data for {coin.get('symbol', 'Unknown')}: {e}")
        
        # Add summary statistics
        print("\nTotal: {} coins found".format(len(new_coins)))
        
        if len(new_coins) > 0:
            avg_age = sum(float(c.get('age_hours', 0) or 0) for c in new_coins) / len(new_coins)
            print(f"Average age: {avg_age:.1f} hours")
            
            # Count very new coins (< 6 hours)
            very_new = sum(1 for c in new_coins if float(c.get('age_hours', 0) or 0) < 6)
            if very_new > 0:
                print(f"Hot new coins (< 6 hours old): {very_new}")
        
        print("")
