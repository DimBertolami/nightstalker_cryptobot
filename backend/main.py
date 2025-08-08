from flask import Flask, jsonify, request
from flask_cors import CORS
import trading_db
import json

app = Flask(__name__)
CORS(app)

@app.route('/api/market-data')
def get_market_data():
    try:
        data = trading_db.get_market_data()
        return jsonify(data)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/trading-signals')
def get_trading_signals():
    try:
        data = trading_db.get_trading_signals()
        return jsonify(data)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/paper-trading', methods=['POST'])
def paper_trading():
    try:
        data = request.get_json()
        result = trading_db.process_paper_trade(data)
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/backtest', methods=['POST'])
def backtest():
    try:
        data = request.get_json()
        result = trading_db.run_backtest(data)
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
