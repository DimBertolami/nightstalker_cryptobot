import os
import sys
from dotenv import load_dotenv
from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker

# Load environment variables
load_dotenv()

def check_data():
    """Connects to the database and checks for data in the relevant tables."""
    db_user = os.getenv('DB_USER')
    db_pass = os.getenv('DB_PASS')
    db_host = os.getenv('DB_HOST')
    db_name = os.getenv('DB_NAME')

    if not all([db_user, db_pass, db_host, db_name]):
        print("Database environment variables are not set. Please check your .env file.")
        return

    database_url = f"mysql+pymysql://{db_user}:{db_pass}@{db_host}/{db_name}"
    
    try:
        engine = create_engine(database_url)
        with engine.connect() as connection:
            print("Successfully connected to the database.")

            # Check for learning_metrics
            metrics_count = connection.execute(text("SELECT COUNT(*) FROM learning_metrics")).scalar_one()
            print(f"Found {metrics_count} rows in 'learning_metrics'.")

            # Check for trading_performance
            performance_count = connection.execute(text("SELECT COUNT(*) FROM trading_performance")).scalar_one()
            print(f"Found {performance_count} rows in 'trading_performance'.")

            if metrics_count == 0 and performance_count == 0:
                print("\nConclusion: The database tables are empty. This is why the charts are not appearing.")
                print("You need to run your trading bot or simulation to generate performance data.")
            else:
                print("\nConclusion: There is data in the database. The issue is likely in the API endpoint logic or the frontend JavaScript.")

    except Exception as e:
        

if __name__ == "__main__":
    check_data()
