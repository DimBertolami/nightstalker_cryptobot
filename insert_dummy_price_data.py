import mysql.connector
from mysql.connector import Error
from datetime import datetime, timedelta, timezone
import random

# Database Configuration (from includes/config.php)
DB_CONFIG = {
    'host': 'localhost',
    'database': 'NS',
    'user': 'root',
    'password': '1304',
    'raise_on_warnings': True
}

# Dummy data to insert
dummy_data = []

# Simulate price data for WLD with a drop
coin_id_to_simulate = 'WLD'
initial_price = 0.86700000

# Define simulation timeline using UTC
simulation_duration_minutes = 5
simulation_start_time = datetime.now(timezone.utc) - timedelta(minutes=simulation_duration_minutes) # Start X minutes in the past

# Apex and drop start times relative to simulation_start_time
apex_offset_minutes = 2 # Apex 2 minutes into simulation
drop_start_offset_seconds = 120 # Drop starts 120 seconds (2 minutes) after apex

apex_time = simulation_start_time + timedelta(minutes=apex_offset_minutes)
drop_start_time = apex_time + timedelta(seconds=drop_start_offset_seconds)

# Format these for database insertion
apex_time_str = apex_time.strftime('%Y-%m-%d %H:%M:%S')
drop_start_time_str = drop_start_time.strftime('%Y-%m-%d %H:%M:%S')

current_sim_time = simulation_start_time
price = initial_price
simulated_apex_price = initial_price

print(f"Simulated Apex Time: {apex_time_str}")
print(f"Simulated Drop Start Time: {drop_start_time_str}")

for _ in range(simulation_duration_minutes * 60): # Generate data for the full simulation duration
    if current_sim_time < apex_time:
        # Price increases steadily before the apex
        price += 0.0001 # Consistent increase
    elif current_sim_time >= apex_time and current_sim_time < drop_start_time:
        # Price holds steady or slightly increases after apex, before drop
        price += random.uniform(0.00001, 0.00002)
    elif current_sim_time >= drop_start_time and current_sim_time < drop_start_time + timedelta(seconds=60):
        # Price drops significantly for 60 seconds
        price -= 0.005 # Consistent and larger decrease
        if price < 0.70000000: # Lower floor to ensure visible drop
            price = 0.70000000
    else:
        # Price stabilizes after the drop
        price += random.uniform(-0.00001, 0.00001) # Small random fluctuations

    dummy_data.append({
        'coin_id': coin_id_to_simulate,
        'price': price,
        'recorded_at': current_sim_time.strftime('%Y-%m-%d %H:%M:%S')
    })

    # Update simulated apex price if current price is higher
    if price > simulated_apex_price:
        simulated_apex_price = price

    current_sim_time += timedelta(seconds=1)

# Add other coins with stable prices, aligned with the same simulation time
other_coins = [
    {'coin_id': 'TRX', 'price': 0.28434000},
    {'coin_id': 'LTC', 'price': 92.31000000},
    {'coin_id': 'IMX', 'price': 0.44618000},
    {'coin_id': 'JTO', 'price': 1.45670000},
]

for coin in other_coins:
    current_price = coin['price']
    current_sim_time_other = simulation_start_time
    for _ in range(simulation_duration_minutes * 60):
        dummy_data.append({
            'coin_id': coin['coin_id'],
            'price': current_price + random.uniform(-0.00001, 0.00001),
            'recorded_at': current_sim_time_other.strftime('%Y-%m-%d %H:%M:%S')
        })
        current_sim_time_other += timedelta(seconds=1)


def insert_dummy_data():
    conn = None
    cursor = None
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        if conn.is_connected():
            cursor = conn.cursor()
            
            # SQL to insert data into price_history table
            insert_sql = """
            INSERT INTO price_history 
            (coin_id, price, recorded_at)
            VALUES (%s, %s, %s)
            """
            
            records_to_insert = []
            for record in dummy_data:
                coin_id = record['coin_id']
                price = f"{record['price']:.8f}"
                recorded_at = record['recorded_at']
                records_to_insert.append((coin_id, price, recorded_at))
            
            cursor.executemany(insert_sql, records_to_insert)
            conn.commit()
            print(f"Successfully inserted {cursor.rowcount} records into price_history table.")

            # Update coin_apex_prices for the simulated coin
            update_apex_sql = """
            INSERT INTO coin_apex_prices (coin_id, apex_price, apex_timestamp, drop_start_timestamp, status, last_checked)
            VALUES (%s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
            apex_price = VALUES(apex_price),
            apex_timestamp = VALUES(apex_timestamp),
            drop_start_timestamp = VALUES(drop_start_timestamp),
            status = VALUES(status),
            last_checked = VALUES(last_checked)
            """
            cursor.execute(update_apex_sql, (coin_id_to_simulate, f"{simulated_apex_price:.12f}", apex_time_str, drop_start_time_str, 'dropping', datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')))
            conn.commit()
            print(f"Successfully updated coin_apex_prices for {coin_id_to_simulate}.")

            # Insert dummy portfolio entry for the simulated coin
            insert_portfolio_sql = """
            INSERT INTO portfolio (user_id, coin_id, amount, avg_buy_price, last_updated)
            VALUES (%s, %s, %s, %s, NOW())
            ON DUPLICATE KEY UPDATE
            amount = VALUES(amount),
            avg_buy_price = VALUES(avg_buy_price),
            last_updated = NOW()
            """
            cursor.execute(insert_portfolio_sql, (1, coin_id_to_simulate, 10.0, initial_price))
            conn.commit()
            print(f"Successfully inserted/updated portfolio entry for {coin_id_to_simulate}.")

    except Error as e:
        
    finally:
        if cursor:
            cursor.close()
        if conn and conn.is_connected():
            conn.close()

if __name__ == "__main__":
    insert_dummy_data()