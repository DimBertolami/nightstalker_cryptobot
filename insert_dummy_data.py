from sqlalchemy import create_engine, text
from datetime import datetime, timedelta
import random
import logging

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

DB_CONNECTION_STRING = "mysql+mysqlconnector://root:1304@localhost:3306/NS"

def insert_dummy_learning_metrics(engine, num_entries=10):
    logging.info(f"Inserting {num_entries} dummy entries into learning_metrics...")
    for i in range(num_entries):
        accuracy = round(random.uniform(0.7, 0.95), 4)
        model_precision = round(random.uniform(0.6, 0.9), 4)
        recall = round(random.uniform(0.65, 0.92), 4)
        f1_score = round(random.uniform(0.68, 0.93), 4)
        timestamp = datetime.now() - timedelta(days=random.randint(0, 30), hours=random.randint(0, 23))
        
        query = text("INSERT INTO learning_metrics (timestamp, accuracy, model_precision, recall, f1_score) VALUES (:timestamp, :accuracy, :model_precision, :recall, :f1_score)")
        
        with engine.connect() as connection:
            connection.execute(query, {
                "timestamp": timestamp,
                "accuracy": accuracy,
                "model_precision": model_precision,
                "recall": recall,
                "f1_score": f1_score
            })
            connection.commit()
    logging.info("Dummy learning_metrics data inserted.")

def insert_dummy_trading_signals(engine, num_entries=20):
    logging.info(f"Inserting {num_entries} dummy entries into trading_signals...")
    signals = ['buy', 'sell', 'hold']
    for i in range(num_entries):
        trade_signal = random.choice(signals)
        confidence = round(random.uniform(0.5, 0.99), 4)
        timestamp = datetime.now() - timedelta(days=random.randint(0, 30), hours=random.randint(0, 23))
        
        query = text("INSERT INTO trading_signals (timestamp, trade_signal, confidence) VALUES (:timestamp, :trade_signal, :confidence)")
        
        with engine.connect() as connection:
            connection.execute(query, {
                "timestamp": timestamp,
                "trade_signal": trade_signal,
                "confidence": confidence
            })
            connection.commit()
    logging.info("Dummy trading_signals data inserted.")

if __name__ == "__main__":
    try:
        engine = create_engine(DB_CONNECTION_STRING)
        with engine.connect() as connection:
            connection.execute(text("SELECT 1"))
        
        
        insert_dummy_learning_metrics(engine)
        insert_dummy_trading_signals(engine)
        
    except Exception as e:
        logging.error(f"Failed to insert dummy data: {e}")
