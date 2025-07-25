#!/usr/bin/env bash

# Set up authentication:
API_KEY="8dKaFuWjQYIp6CFuKfkWWGC0rjAcwQSJY6Z44n9uvFdfcQgZfwIQgfFfTH27kmRu"
PRIVATE_KEY_PATH="test-prv-key.pem"

# Set up the request:
API_METHOD="POST"
API_CALL="api/v3/order"
API_PARAMS="symbol=BTCUSDT&side=SELL&type=LIMIT&timeInForce=GTC&quantity=1&price=0.2"

# Sign the request:
timestamp=$(date +%s000)
api_params_with_timestamp="$API_PARAMS&timestamp=$timestamp"
signature=$(echo -n "$api_params_with_timestamp" \
            | openssl dgst -sha256 -sign "$PRIVATE_KEY_PATH" \
            | openssl enc -base64 -A)

# Send the request:
curl -H "X-MBX-APIKEY: $API_KEY" -X "$API_METHOD" \
    "https://testnet.binance.vision/$API_CALL?$api_params_with_timestamp" \
    --data-urlencode "signature=$signature"
