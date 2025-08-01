import mysql.connector
from mysql.connector import Error
from datetime import datetime

# Database Configuration (from includes/config.php)
DB_CONFIG = {
    'host': 'localhost',
    'database': 'NS',
    'user': 'root',
    'password': '1304',
    'raise_on_warnings': True
}

# Dummy data to insert
# Note: 'symbol' is inferred from 'coin_id', and 'volume_24h', 'market_cap' are set to NULL
dummy_data = [
    {'coin_id': 'TRX', 'price': 0.28434000, 'recorded_at': '2025-08-01 02:15:06'},
    {'coin_id': 'LTC', 'price': 92.31000000, 'recorded_at': '2025-08-01 02:15:06'},
    {'coin_id': 'WLD', 'price': 0.86570000, 'recorded_at': '2025-08-01 02:15:06'},
    {'coin_id': 'IMX', 'price': 0.44618000, 'recorded_at': '2025-08-01 02:15:06'},
    {'coin_id': 'JTO', 'price': 1.45670000, 'recorded_at': '2025-08-01 02:15:06'},
    {'coin_id': 'TRX', 'price': 0.28434000, 'recorded_at': '2025-08-01 02:15:07'},
    {'coin_id': 'LTC', 'price': 92.31000000, 'recorded_at': '2025-08-01 02:15:07'},
    {'coin_id': 'WLD', 'price': 0.86570000, 'recorded_at': '2025-08-01 02:15:07'},
    {'coin_id': 'IMX', 'price': 0.44618000, 'recorded_at': '2025-08-01 02:15:07'},
    {'coin_id': 'JTO', 'price': 1.45670000, 'recorded_at': '2025-08-01 02:15:07'},
    {'coin_id': 'TRX', 'price': 0.28434000, 'recorded_at': '2025-08-01 02:15:07'},
    {'coin_id': 'LTC', 'price': 92.31000000, 'recorded_at': '2025-08-01 02:15:07'},
    {'coin_id': 'WLD', 'price': 0.86570000, 'recorded_at': '2025-08-01 02:15:07'},
    {'coin_id': 'IMX', 'price': 0.44574000, 'recorded_at': '2025-08-01 02:15:07'},
    {'coin_id': 'JTO', 'price': 1.45640000, 'recorded_at': '2025-08-01 02:15:07'},
    {'coin_id': 'TRX', 'price': 0.28434000, 'recorded_at': '2025-08-01 02:15:09'},
    {'coin_id': 'LTC', 'price': 92.37600000, 'recorded_at': '2025-08-01 02:15:09'},
    {'coin_id': 'WLD', 'price': 0.86622000, 'recorded_at': '2025-08-01 02:15:09'},
    {'coin_id': 'IMX', 'price': 0.44574000, 'recorded_at': '2025-08-01 02:15:09'},
    {'coin_id': 'TRX', 'price': 0.28434000, 'recorded_at': '2025-08-01 02:15:10'},
    {'coin_id': 'LTC', 'price': 92.37600000, 'recorded_at': '2025-08-01 02:15:10'},
    {'coin_id': 'WLD', 'price': 0.86622000, 'recorded_at': '2025-08-01 02:15:10'},
    {'coin_id': 'IMX', 'price': 0.44574000, 'recorded_at': '2025-08-01 02:15:10'},
    {'coin_id': 'TRX', 'price': 0.28434000, 'recorded_at': '2025-08-01 02:15:11'},
    {'coin_id': 'LTC', 'price': 92.37600000, 'recorded_at': '2025-08-01 02:15:11'},
    {'coin_id': 'WLD', 'price': 0.86622000, 'recorded_at': '2025-08-01 02:15:11'},
    {'coin_id': 'IMX', 'price': 0.44574000, 'recorded_at': '2025-08-01 02:15:11'},
    {'coin_id': 'TRX', 'price': 0.28440000, 'recorded_at': '2025-08-01 02:15:13'},
    {'coin_id': 'LTC', 'price': 92.37600000, 'recorded_at': '2025-08-01 02:15:13'},
    {'coin_id': 'WLD', 'price': 0.86659000, 'recorded_at': '2025-08-01 02:15:13'},
    {'coin_id': 'IMX', 'price': 0.44576000, 'recorded_at': '2025-08-01 02:15:13'},
    {'coin_id': 'TRX', 'price': 0.28440000, 'recorded_at': '2025-08-01 02:15:13'},
    {'coin_id': 'LTC', 'price': 92.37600000, 'recorded_at': '2025-08-01 02:15:13'},
    {'coin_id': 'WLD', 'price': 0.86659000, 'recorded_at': '2025-08-01 02:15:13'},
    {'coin_id': 'IMX', 'price': 0.44576000, 'recorded_at': '2025-08-01 02:15:13'},
    {'coin_id': 'LTC', 'price': 92.40600000, 'recorded_at': '2025-08-01 02:15:14'},
    {'coin_id': 'WLD', 'price': 0.86659000, 'recorded_at': '2025-08-01 02:15:14'},
    {'coin_id': 'IMX', 'price': 0.44576000, 'recorded_at': '2025-08-01 02:15:14'},
    {'coin_id': 'LTC', 'price': 92.40600000, 'recorded_at': '2025-08-01 02:15:16'},
    {'coin_id': 'WLD', 'price': 0.86659000, 'recorded_at': '2025-08-01 02:15:16'},
    {'coin_id': 'IMX', 'price': 0.44576000, 'recorded_at': '2025-08-01 02:15:16'},
    {'coin_id': 'LTC', 'price': 92.40600000, 'recorded_at': '2025-08-01 02:15:16'},
    {'coin_id': 'WLD', 'price': 0.86659000, 'recorded_at': '2025-08-01 02:15:16'},
    {'coin_id': 'IMX', 'price': 0.44576000, 'recorded_at': '2025-08-01 02:15:16'},
    {'coin_id': 'LTC', 'price': 92.40600000, 'recorded_at': '2025-08-01 02:15:17'},
    {'coin_id': 'WLD', 'price': 0.86659000, 'recorded_at': '2025-08-01 02:15:17'},
    {'coin_id': 'LTC', 'price': 92.44700000, 'recorded_at': '2025-08-01 02:15:19'},
    {'coin_id': 'WLD', 'price': 0.86725000, 'recorded_at': '2025-08-01 02:15:19'},
    {'coin_id': 'LTC', 'price': 92.44700000, 'recorded_at': '2025-08-01 02:15:19'},
    {'coin_id': 'WLD', 'price': 0.86725000, 'recorded_at': '2025-08-01 02:15:19'},
    {'coin_id': 'LTC', 'price': 92.44700000, 'recorded_at': '2025-08-01 02:15:20'},
    {'coin_id': 'WLD', 'price': 0.86725000, 'recorded_at': '2025-08-01 02:15:20'},
    {'coin_id': 'WLD', 'price': 0.86725000, 'recorded_at': '2025-08-01 02:15:22'},
    {'coin_id': 'WLD', 'price': 0.86536000, 'recorded_at': '2025-08-01 02:15:22'},
    {'coin_id': 'WLD', 'price': 0.86536000, 'recorded_at': '2025-08-01 02:15:23'},
    {'coin_id': 'WLD', 'price': 0.86536000, 'recorded_at': '2025-08-01 02:15:25'},
    {'coin_id': 'WLD', 'price': 0.86536000, 'recorded_at': '2025-08-01 02:15:25'},
    {'coin_id': 'WLD', 'price': 0.86536000, 'recorded_at': '2025-08-01 02:15:25'},
    {'coin_id': 'WLD', 'price': 0.86536000, 'recorded_at': '2025-08-01 02:15:26'},
    {'coin_id': 'WLD', 'price': 0.86680000, 'recorded_at': '2025-08-01 02:15:28'},
    {'coin_id': 'WLD', 'price': 0.86680000, 'recorded_at': '2025-08-01 02:15:29'},
    {'coin_id': 'WLD', 'price': 0.86680000, 'recorded_at': '2025-08-01 02:15:29'},
    {'coin_id': 'WLD', 'price': 0.86680000, 'recorded_at': '2025-08-01 02:15:29'},
    {'coin_id': 'WLD', 'price': 0.86680000, 'recorded_at': '2025-08-01 02:15:31'},
    {'coin_id': 'WLD', 'price': 0.86680000, 'recorded_at': '2025-08-01 02:15:32'},
    {'coin_id': 'WLD', 'price': 0.86680000, 'recorded_at': '2025-08-01 02:15:32'},
    {'coin_id': 'WLD', 'price': 0.86571000, 'recorded_at': '2025-08-01 02:15:33'},
    {'coin_id': 'WLD', 'price': 0.86571000, 'recorded_at': '2025-08-01 02:15:35'},
    {'coin_id': 'WLD', 'price': 0.86571000, 'recorded_at': '2025-08-01 02:15:35'},
    {'coin_id': 'WLD', 'price': 0.86571000, 'recorded_at': '2025-08-01 02:15:35'},
    {'coin_id': 'WLD', 'price': 0.86571000, 'recorded_at': '2025-08-01 02:15:36'},
    {'coin_id': 'WLD', 'price': 0.86598000, 'recorded_at': '2025-08-01 02:15:38'},
    {'coin_id': 'WLD', 'price': 0.86598000, 'recorded_at': '2025-08-01 02:15:38'},
    {'coin_id': 'WLD', 'price': 0.86598000, 'recorded_at': '2025-08-01 02:15:38'},
    {'coin_id': 'WLD', 'price': 0.86598000, 'recorded_at': '2025-08-01 02:15:39'},
    {'coin_id': 'WLD', 'price': 0.86598000, 'recorded_at': '2025-08-01 02:15:39'},
    {'coin_id': 'WLD', 'price': 0.86598000, 'recorded_at': '2025-08-01 02:15:41'},
]

def insert_dummy_data():
    conn = None
    cursor = None
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        if conn.is_connected():
            cursor = conn.cursor()
            
            # SQL to insert data into price_history table
            # Assuming 'symbol' column can be derived from 'coin_id' for dummy data
            # 'volume_24h' and 'market_cap' are set to NULL as they are not provided
            insert_sql = """
            INSERT INTO price_history 
            (coin_id, symbol, price, recorded_at, volume_24h, market_cap)
            VALUES (%s, %s, %s, %s, NULL, NULL)
            """
            
            records_to_insert = []
            for record in dummy_data:
                coin_id = record['coin_id']
                price = record['price']
                recorded_at = record['recorded_at']
                symbol = coin_id # Assuming coin_id can be used as symbol for dummy data
                records_to_insert.append((coin_id, symbol, price, recorded_at))
            
            cursor.executemany(insert_sql, records_to_insert)
            conn.commit()
            print(f"Successfully inserted {cursor.rowcount} records into price_history table.")

    except Error as e:
        print(f"Error inserting data: {e}")
    finally:
        if cursor:
            cursor.close()
        if conn and conn.is_connected():
            conn.close()

if __name__ == "__main__":
    insert_dummy_data()
