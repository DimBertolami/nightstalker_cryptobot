import time
import hmac
import hashlib
import urllib.parse

API_KEY = 'K5JaS3MyNCevPcBNWAgvjp139sUFkS290Cjq0D6hLukkZogNaL2HqEzekO1Zb72n'
SECRET_KEY = '1k1wkTro8uGxH6ifk2MTV46YBiJJh8Ivs0tDYLTUcEcFmA10Xgdmk3HPYOlFZvyl'

timestamp = int(time.time() * 1000)

params = {
    'symbol': 'LTCBTC',
    'side': 'BUY',
    'type': 'LIMIT',
    'timeInForce': 'GTC',
    'quantity': '1',
    'price': '0.1',
    'recvWindow': '5000',
    'timestamp': str(timestamp)
}

query_string = urllib.parse.urlencode(params)
signature = hmac.new(SECRET_KEY.encode('utf-8'), query_string.encode('utf-8'), hashlib.sha256).hexdigest()

curl_command = f"curl -H \"X-MBX-APIKEY: {API_KEY}\" -X POST 'https://testnet.binance.vision/api/v3/order' -d '{query_string}&signature={signature}'"


