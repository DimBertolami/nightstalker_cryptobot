
import os
import sys
import random
from datetime import datetime, timedelta
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, Integer, String, Float, DateTime, Boolean
from sqlalchemy.orm import sessionmaker
from sqlalchemy.ext.declarative import declarative_base

# Load environment variables
load_dotenv()

# Add backend to path to find models if needed, though we define them here for simplicity
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), 'backend')))

# Define the models directly in the script to ensure it's self-contained
Base = declarative_base()

class LearningMetric(Base):
    __tablename__ = "learning_metrics"
    id = Column(Integer, primary_key=True, autoincrement=True)
    timestamp = Column(DateTime, nullable=False)
    model_id = Column(String(255), nullable=False)
    accuracy = Column(Float)
    precision = Column(Float)
    recall = Column(Float)
    f1_score = Column(Float)
    profit_factor = Column(Float)
    sharpe_ratio = Column(Float)
    win_rate = Column(Float)
    dataset_size = Column(Integer)
    training_duration = Column(Float)

class TradingPerformance(Base):
    __tablename__ = "trading_performance"
    id = Column(Integer, primary_key=True, autoincrement=True)
    timestamp = Column(DateTime, nullable=False)
    symbol = Column(String(255), nullable=False)
    initial_price = Column(Float, nullable=False)
    final_price = Column(Float, nullable=False)
    profit_loss = Column(Float, nullable=False)
    signal = Column(String(255), nullable=False)
    confidence = Column(Float, nullable=False)
    duration_minutes = Column(Integer)
    success = Column(Boolean)

def populate_data():
    """Connects to the database and inserts sample data."""
    db_user = 'dimi'
    db_pass = '1304'
    db_host = 'localhost'
    db_name = 'NS'

    if not all([db_user, db_pass, db_host, db_name]):
        print("Database environment variables are not set.")
        return

    database_url = f"mysql+pymysql://{db_user}:{db_pass}@{db_host}/{db_name}"
    
    try:
        engine = create_engine(database_url)
        Session = sessionmaker(bind=engine)
        Session = sessionmaker(bind=engine)
        Session = sessionmaker(bind=engine)
        session = Session()

        # Drop tables if they exist to ensure a clean slate
        Base.metadata.drop_all(engine, tables=[LearningMetric.__table__, TradingPerformance.__table__])
        Base.metadata.create_all(engine) # Create tables
        print("Successfully connected to the database, dropped and ensured tables exist.")

        # --- Populate learning_metrics ---
        print("Populating learning_metrics...")
        for i in range(10):
            metric = LearningMetric(
                timestamp=datetime.now() - timedelta(days=i),
                model_id=f"model_v{random.choice([1,2])}",
                accuracy=random.uniform(0.6, 0.9),
                precision=random.uniform(0.6, 0.9),
                recall=random.uniform(0.5, 0.85),
                f1_score=random.uniform(0.6, 0.9),
                profit_factor=random.uniform(1.1, 2.5),
                sharpe_ratio=random.uniform(0.8, 2.0),
                win_rate=random.uniform(0.55, 0.8),
                dataset_size=10000 + i * 100,
                training_duration=random.uniform(300, 1200)
            )
            session.add(metric)

        # --- Populate trading_performance ---
        print("Populating trading_performance...")
        for i in range(20):
            initial_price = random.uniform(40000, 50000)
            profit = random.uniform(-500, 750)
            performance = TradingPerformance(
                timestamp=datetime.now() - timedelta(hours=i*2),
                symbol="BTC-USD",
                initial_price=initial_price,
                final_price=initial_price + profit,
                profit_loss=profit,
                signal="BUY" if profit > 0 else "SELL",
                confidence=random.uniform(0.7, 0.98),
                duration_minutes=random.randint(30, 240),
                success=profit > 0
            )
            session.add(performance)

        session.commit()
        print("Successfully added sample data to the database.")

    except Exception as e:
        
    finally:
        session.close()

if __name__ == "__main__":
    populate_data()
