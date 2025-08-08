import requests
import time
from datetime import datetime, timedelta

def get_new_coins(max_age_hours=24, sort_by='volume'):
    """
    Fetch newly listed coins from CoinGecko and sort by volume or market cap
    
    Parameters:
    - max_age_hours: Maximum age of coins in hours (default: 2)
    - sort_by: Sorting criteria ('volume' or 'marketcap')
    
    Returns:
    - List of dictionaries containing coin data, sorted by specified criteria
    """
    
    # Get all coins list from CoinGecko
    url = "https://api.coingecko.com/api/v3/coins/list"
    response = requests.get(url)
    coins_list = response.json()
    
    # Calculate timestamp for max_age_hours ago
    cutoff_time = datetime.now() - timedelta(hours=max_age_hours)
    
    new_coins = []
    
    # Loop through all coins (this might take a while)
    for coin in coins_list:
        try:
            # Get detailed info for each coin
            coin_url = f"https://api.coingecko.com/api/v3/coins/{coin['id']}"
            coin_data = requests.get(coin_url).json()
            
            # Check if the coin has listing date
            if 'genesis_date' in coin_data or 'public_notice' in coin_data:
                # Some coins don't have proper dates, so we'll skip them
                continue
                
            # Get the last updated timestamp
            last_updated = coin_data.get('last_updated')
            if not last_updated:
                continue
                
            # Convert to datetime
            last_updated_dt = datetime.strptime(last_updated, "%Y-%m-%dT%H:%M:%S.%fZ")
            
            # Check if the coin is new enough
            if last_updated_dt >= cutoff_time:
                # Get market data
                market_data = coin_data.get('market_data', {})
                
                # Add to our list
                new_coins.append({
                    'id': coin['id'],
                    'name': coin_data.get('name', ''),
                    'symbol': coin_data.get('symbol', '').upper(),
                    'age_hours': (datetime.now() - last_updated_dt).total_seconds() / 3600,
                    'volume': market_data.get('total_volume', {}).get('usd', 0),
                    'market_cap': market_data.get('market_cap', {}).get('usd', 0),
                    'price': market_data.get('current_price', {}).get('usd', 0),
                    'price_change_24h': market_data.get('price_change_percentage_24h', 0),
                    'last_updated': last_updated
                })
                
        except Exception as e:
            print(f"Error processing {coin['id']}: {str(e)}")
            continue
    
    # Sort by the specified criteria
    if sort_by == 'volume':
        new_coins.sort(key=lambda x: x['volume'], reverse=True)
    elif sort_by == 'marketcap':
        new_coins.sort(key=lambda x: x['market_cap'], reverse=True)
    
    return new_coins

if __name__ == "__main__":
    print("Fetching newly listed coins (<2 hours old)...")
    new_coins = get_new_coins(max_age_hours=2, sort_by='volume')
    
    print("\nTop New Coins by Trading Volume:")
    print("{:<25} {:<8} {:<15} {:<15} {:<15} {:<10}".format(
        "Name", "Symbol", "Volume (USD)", "Market Cap", "Price (USD)", "Age (hrs)"))
    
    for coin in new_coins[:20]:  # Display top 20
        print("{:<25} {:<8} ${:<15,.0f} ${:<15,.0f} ${:<15,.4f} {:<10.2f}".format(
            coin['name'],
            coin['symbol'],
            coin['volume'],
            coin['market_cap'],
            coin['price'],
            coin['age_hours']))