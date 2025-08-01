import os
import sys
import logging
from logging.handlers import RotatingFileHandler
from dotenv import load_dotenv

# Load environment variables from .env file
load_dotenv()

# Add the current directory to Python path
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from flask import Flask, jsonify, request
from flask_cors import CORS
from flask_socketio import SocketIO
from sqlalchemy.orm import sessionmaker
from backend.models.unified_models import Base, LearningMetric, TradingPerformance, engine
import os
from datetime import datetime, timedelta
from cachetools import TTLCache
import logging
from logging.handlers import RotatingFileHandler
from dotenv import load_dotenv

# Load environment variables from .env file
load_dotenv()

# Add the current directory to Python path
import sys
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}}, supports_credentials=True)
socketio = SocketIO(app, cors_allowed_origins="*")

# Configure logging
log_dir = os.getenv('LOG_DIR', '/tmp')
if not os.path.exists(log_dir):
    os.makedirs(log_dir)

log_file = os.path.join(log_dir, 'app.log')
file_handler = RotatingFileHandler(log_file, maxBytes=1024 * 1024 * 10, backupCount=5)
file_handler.setLevel(logging.INFO)
formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
file_handler.setFormatter(formatter)
app.logger.addHandler(file_handler)
app.logger.info(f"Logging to: {log_file}")

# Also log to stderr for systemd
stream_handler = logging.StreamHandler()
stream_handler.setLevel(logging.INFO)
stream_handler.setFormatter(formatter)
app.logger.addHandler(stream_handler)

# Set the logger level
app.logger.setLevel(logging.INFO)

# Cache configuration (100 items, 1 hour TTL)
CACHE = TTLCache(maxsize=100, ttl=3600)

# Create a configured "Session" class
SessionLocal = sessionmaker(bind=engine)

# Initialize database
app.logger.info(f"Current working directory: {os.getcwd()}")
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
        # Define a time window, e.g., last 30 days
        time_window = datetime.now() - timedelta(days=30)

        # Fetch data from LearningMetric for accuracy and bot confidence, filtered by time window
        learning_metrics = session.query(LearningMetric).filter(LearningMetric.timestamp >= time_window).order_by(LearningMetric.timestamp).all()
        
        # Fetch data from TradingPerformance for cumulative profit, filtered by time window
        trading_performance = session.query(TradingPerformance).filter(TradingPerformance.timestamp >= time_window).order_by(TradingPerformance.timestamp).all()

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
        app.logger.error(f"Error fetching chart data: {str(e)}", exc_info=True)
        return jsonify({'error': 'Failed to fetch chart data', 'details': str(e)}), 500
    finally:
        session.close()

if __name__ == '__main__':
    socketio.run(app, host='0.0.0.0', allow_unsafe_werkzeug=True, port=5000, debug=False)
