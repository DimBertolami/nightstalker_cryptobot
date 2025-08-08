from python_bitvavo_api.bitvavo import Bitvavo
import json
import os

bitvavo = Bitvavo()
response = bitvavo.markets({
    # Get all markets
    # 'all': True
    # 'tradable': True
})

market_symbols = [market['market'] for market in response]

# Define output path for JSON file (adjust path as needed)
output_path = '/opt/lampp/htdocs/NS/assets/js/bitvavo_markets.json'

# Write market symbols to JSON file
with open(output_path, 'w') as f:
    json.dump(market_symbols, f, indent=2)

print(f"Market symbols written to {output_path}")
