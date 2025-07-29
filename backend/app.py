import os
import sys
import logging
from logging.handlers import RotatingFileHandler

# Add the current directory to Python path
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from flask import Flask, jsonify, request
from flask_cors import CORS, cross_origin
from flask_socketio import SocketIO # type: ignore
from sqlalchemy import create_engine, Column, Integer, String, Float, DateTime, Boolean, JSON
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker
import os
from datetime import datetime, timedelta
import pandas as pd
import numpy as np
import requests
from cachetools import TTLCache

app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}}, supports_credentials=True)
socketio = SocketIO(app, cors_allowed_origins="*")

# Configure logging
log_dir = os.getenv('LOG_DIR', '/home/dim/git/Cryptobot/logs')
if not os.path.exists(log_dir):
    os.makedirs(log_dir)

log_file = os.path.join(log_dir, 'app.log')
file_handler = RotatingFileHandler(log_file, maxBytes=1024 * 1024 * 10, backupCount=5)
file_handler.setLevel(logging.INFO)
formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
file_handler.setFormatter(formatter)
app.logger.addHandler(file_handler)

# Also log to stderr for systemd
stream_handler = logging.StreamHandler()
stream_handler.setLevel(logging.INFO)
stream_handler.setFormatter(formatter)
app.logger.addHandler(stream_handler)

# Set the logger level
app.logger.setLevel(logging.INFO)

# Cache configuration (100 items, 1 hour TTL)
CACHE = TTLCache(maxsize=100, ttl=3600)

# Binance API configuration
BINANCE_API_BASE = "https://api.binance.com/api/v3"

# Interval mapping
INTERVAL_MAP = {
    '1m': '1m',
    '5m': '5m',
    '10m': '10m',
    '15m': '15m',
    '30m': '30m',
    '1h': '1h',
    '4h': '4h',
    '1d': '1d',
    '1w': '1w'
}

# Database configuration
DATABASE_URL = "sqlite:///trading.db"
engine = create_engine(DATABASE_URL)
Base = declarative_base()

# Create a configured "Session" class
SessionLocal = sessionmaker(bind=engine)

# Define SQLAlchemy models
class TradingSignal(Base):
    __tablename__ = "trading_signals"
    
    id = Column(Integer, primary_key=True, autoincrement=True)
    symbol = Column(String, nullable=False)
    timestamp = Column(DateTime, nullable=False)
    signal = Column(String, nullable=False)
    confidence = Column(Float, nullable=False)
    price = Column(Float, nullable=False)
    price_change_24h = Column(Float)
    model_id = Column(String)

class TradingPerformance(Base):
    __tablename__ = "trading_performance"
    
    id = Column(Integer, primary_key=True, autoincrement=True)
    timestamp = Column(DateTime, nullable=False)
    symbol = Column(String, nullable=False)
    initial_price = Column(Float, nullable=False)
    final_price = Column(Float, nullable=False)
    profit_loss = Column(Float, nullable=False)
    signal = Column(String, nullable=False)
    confidence = Column(Float, nullable=False)
    duration_minutes = Column(Integer)
    success = Column(Boolean)

class BotThought(Base):
    __tablename__ = "bot_thoughts"
    
    id = Column(Integer, primary_key=True, autoincrement=True)
    timestamp = Column(DateTime, nullable=False)
    thought_type = Column(String, nullable=False)
    thought_content = Column(String, nullable=False)
    symbol = Column(String)
    confidence = Column(Float)
    metrics = Column(JSON)

class LearningMetric(Base):
    __tablename__ = "learning_metrics"
    
    id = Column(Integer, primary_key=True, autoincrement=True)
    timestamp = Column(DateTime, nullable=False)
    model_id = Column(String, nullable=False)
    accuracy = Column(Float)
    precision = Column(Float)
    recall = Column(Float)
    f1_score = Column(Float)
    profit_factor = Column(Float)
    sharpe_ratio = Column(Float)
    win_rate = Column(Float)
    dataset_size = Column(Integer)
    training_duration = Column(Float)

# Initialize database
try:
    # Create all tables defined in the models
    Base.metadata.create_all(engine)
    app.logger.info("Database initialized successfully")
except Exception as e:
    app.logger.error(f"Failed to initialize database: {str(e)}")

# Initialize Socket.IO
@socketio.on('connect')
def handle_connect():
    print('Client connected')
    socketio.emit('server_status', {'status': 'ready'})

@socketio.on('disconnect')
def handle_disconnect():
    print('Client disconnected')

@app.route('/api/status')
def get_status():
    import psutil # type: ignore
    import time
    
    # Get system stats
    cpu_percent = psutil.cpu_percent()
    memory = psutil.virtual_memory()
    uptime = int(time.time() - psutil.boot_time())
    
    return jsonify({
        'success': True,
        'data': {
            'status': 'running',
            'uptime': uptime,
            'memory': memory.total,
            'cpu': cpu_percent
        }
    })

@app.route('/api/bot/performance/chart_data')
def get_chart_data():
    session = SessionLocal()
    try:
        # Fetch data from LearningMetric for accuracy and bot confidence
        learning_metrics = session.query(LearningMetric).order_by(LearningMetric.timestamp).all()
        
        # Fetch data from TradingPerformance for cumulative profit
        trading_performance = session.query(TradingPerformance).order_by(TradingPerformance.timestamp).all()

        labels = []
        decision_accuracy = []
        bot_confidence = []
        cumulative_profit = []

        # Process learning metrics
        for metric in learning_metrics:
            label = metric.timestamp.strftime('%m/%d')
            if label not in labels:
                labels.append(label)
            
            # Assuming accuracy and confidence are directly available
            decision_accuracy.append(metric.accuracy * 100 if metric.accuracy is not None else 0)
            # Assuming bot_confidence can be derived or is a separate metric in LearningMetric
            # For now, using a placeholder or a related metric if available
            bot_confidence.append(metric.precision * 100 if metric.precision is not None else 0) # Using precision as a placeholder for bot_confidence

        # Process trading performance for cumulative profit
        current_profit = 0
        profit_data_points = {} # To store profit for each date

        for trade in trading_performance:
            current_profit += trade.profit_loss
            label = trade.timestamp.strftime('%m/%d')
            profit_data_points[label] = current_profit # Update profit for the latest trade on that day

        # Align cumulative profit with labels
        for label in labels:
            cumulative_profit.append(profit_data_points.get(label, 0)) # Use 0 if no profit data for that day

        return jsonify({
            'labels': labels,
            'decision_accuracy': decision_accuracy,
            'cumulative_profit': cumulative_profit,
            'bot_confidence': bot_confidence,
            'total_trades': [], # Placeholder, needs to be calculated from TradingPerformance
            'significant_trades': [] # Placeholder, needs to be identified from TradingPerformance
        })
    except Exception as e:
        app.logger.error(f"Error fetching chart data: {str(e)}")
        return jsonify({'error': 'Failed to fetch chart data'}), 500
    finally:
        session.close()

if __name__ == '__main__':
    socketio.run(app, host='0.0.0.0', allow_unsafe_werkzeug=True, port=5000, debug=False)
