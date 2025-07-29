import logging
import time
from datetime import datetime, timedelta
from advanced_ml_pipeline import AdvancedMLPipeline
from database import get_db, init_db
import pandas as pd
import numpy as np
from pathlib import Path
import plotly.graph_objects as go
from fastapi import FastAPI, Request
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from fastapi.responses import HTMLResponse
import uvicorn

class CryptoTradingBot:
    def __init__(self, config):
        self.config = config
        self.pipeline = AdvancedMLPipeline(config)
        self.position = None
        self.initial_balance = config['initial_balance']
        self.current_balance = self.initial_balance
        self.trades = []
        self.db = next(get_db())
        self.logger = logging.getLogger(__name__)
        self.setup_logging()
        self.setup_database()
        self.setup_web_interface()

    def setup_logging(self):
        """Setup logging configuration"""
        log_dir = Path('logs')
        log_dir.mkdir(exist_ok=True)
        
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
            handlers=[
                logging.FileHandler(log_dir / 'trading_bot.log'),
                logging.StreamHandler()
            ]
        )

    def setup_database(self):
        """Initialize database"""
        try:
            init_db()
            self.logger.info("Database initialized successfully")
        except Exception as e:
            self.logger.error(f"Error initializing database: {e}")
            raise

    def setup_web_interface(self):
        """Setup FastAPI web interface"""
        self.app = FastAPI()
        self.app.mount("/static", StaticFiles(directory="static"), name="static")
        self.templates = Jinja2Templates(directory="templates")

        @self.app.get("/")
        async def root(request: Request):
            return self.templates.TemplateResponse("index.html", {
                "request": request,
                "balance": self.current_balance,
                "position": self.position,
                "trades": self.trades[-10:],  # Last 10 trades
                "performance": self.get_performance_metrics()
            })

        @self.app.get("/performance")
        async def get_performance():
            return self.get_performance_metrics()

        @self.app.get("/trades")
        async def get_trades():
            return self.get_trades()

        @self.app.get("/metrics")
        async def get_metrics():
            return self.get_metrics()

        @self.app.get("/plot")
        async def get_plot():
            return self.generate_performance_plot()

    def get_performance_metrics(self):
        """Get performance metrics"""
        try:
            # Get all trades
            trades = self.db.query(Trade).all()
            
            if not trades:
                return {
                    'total_return': 0,
                    'volatility': 0,
                    'sharpe_ratio': 0,
                    'max_drawdown': 0,
                    'win_rate': 0,
                    'total_trades': 0
                }
            
            # Calculate metrics
            total_trades = len(trades)
            win_trades = len([t for t in trades if t.decision == 'SELL'])
            win_rate = win_trades / total_trades if total_trades > 0 else 0
            
            # Calculate returns
            returns = []
            for i in range(1, len(trades)):
                current_balance = trades[i].balance
                prev_balance = trades[i-1].balance
                returns.append((current_balance - prev_balance) / prev_balance)
            
            if returns:
                total_return = sum(returns)
                volatility = np.std(returns)
                sharpe_ratio = total_return / volatility if volatility != 0 else 0
                max_drawdown = max(0, min(returns))
            else:
                total_return = 0
                volatility = 0
                sharpe_ratio = 0
                max_drawdown = 0

            return {
                'total_return': total_return,
                'volatility': volatility,
                'sharpe_ratio': sharpe_ratio,
                'max_drawdown': max_drawdown,
                'win_rate': win_rate,
                'total_trades': total_trades
            }
            
        except Exception as e:
            self.logger.error(f"Error getting performance metrics: {e}")
            return None

    def get_trades(self):
        """Get recent trades"""
        try:
            trades = self.db.query(Trade).order_by(Trade.timestamp.desc()).limit(50).all()
            return [
                {
                    'timestamp': trade.timestamp,
                    'decision': trade.decision,
                    'price': trade.price,
                    'amount': trade.amount,
                    'balance': trade.balance,
                    'position': trade.position,
                    'risk_score': trade.risk_score,
                    'model_confidence': trade.model_confidence,
                    'notes': trade.notes
                }
                for trade in trades
            ]
            
        except Exception as e:
            self.logger.error(f"Error getting trades: {e}")
            return []

    def get_metrics(self):
        """Get model metrics"""
        try:
            metrics = self.db.query(ModelPerformance).order_by(
                ModelPerformance.last_updated.desc()
            ).limit(10).all()
            return [
                {
                    'model_name': m.model_name,
                    'version': m.version,
                    'accuracy': m.accuracy,
                    'rmse': m.rmse,
                    'mae': m.mae,
                    'r_squared': m.r_squared,
                    'last_updated': m.last_updated
                }
                for m in metrics
            ]
            
        except Exception as e:
            self.logger.error(f"Error getting metrics: {e}")
            return []

    def generate_performance_plot(self):
        """Generate performance plot"""
        try:
            trades = self.db.query(Trade).all()
            if not trades:
                return None

            # Create plot data
            timestamps = [trade.timestamp for trade in trades]
            balances = [trade.balance for trade in trades]
            prices = [trade.price for trade in trades]
            decisions = [trade.decision for trade in trades]

            # Create figure
            fig = go.Figure()

            # Add balance line
            fig.add_trace(
                go.Scatter(
                    x=timestamps,
                    y=balances,
                    name='Balance',
                    line=dict(color='blue')
                )
            )

            # Add price line
            fig.add_trace(
                go.Scatter(
                    x=timestamps,
                    y=prices,
                    name='Price',
                    line=dict(color='green')
                )
            )

            # Add buy/sell markers
            buy_indices = [i for i, d in enumerate(decisions) if d == 'BUY']
            sell_indices = [i for i, d in enumerate(decisions) if d == 'SELL']

            if buy_indices:
                fig.add_trace(
                    go.Scatter(
                        x=[timestamps[i] for i in buy_indices],
                        y=[prices[i] for i in buy_indices],
                        mode='markers',
                        name='Buy',
                        marker=dict(color='green', size=10)
                    )
                )

            if sell_indices:
                fig.add_trace(
                    go.Scatter(
                        x=[timestamps[i] for i in sell_indices],
                        y=[prices[i] for i in sell_indices],
                        mode='markers',
                        name='Sell',
                        marker=dict(color='red', size=10)
                    )
                )

            # Update layout
            fig.update_layout(
                title='Trading Performance',
                xaxis_title='Time',
                yaxis_title='Value',
                legend_title='Metrics'
            )

            return fig.to_html(full_html=False)
            
        except Exception as e:
            self.logger.error(f"Error generating plot: {e}")
            return None

    def run(self):
        """Run the trading bot with monitoring"""
        try:
            if not self.train_models():
                self.logger.error("Failed to train models")
                return

            # Start web server in a separate thread
            import threading
            web_thread = threading.Thread(target=self.start_web_server)
            web_thread.daemon = True
            web_thread.start()

            while True:
                try:
                    # Fetch latest data
                    df = self.pipeline.fetch_data()
                    if df is None or df.empty:
                        self.logger.error("No data available")
                        continue

                    # Get latest data point
                    latest_data = df.iloc[-1]
                    latest_price = latest_data['Close']

                    # Make prediction with confidence
                    prediction, confidence = self.make_prediction(latest_data)
                    if prediction is None or confidence is None:
                        self.logger.error("Prediction failed")
                        continue

                    # Make trading decision with risk management
                    decision, risk_score = self.pipeline.make_trading_decision(
                        current_price=latest_price,
                        predicted_price=prediction,
                        confidence_score=confidence,
                        db=self.db
                    )

                    # Calculate trade amount with risk management
                    trade_amount = self.calculate_trade_amount(confidence)

                    # Execute trade
                    self.execute_trade(decision, latest_price, trade_amount)

                    # Analyze performance periodically
                    if len(self.trades) % self.config['evaluation_interval'] == 0:
                        self.analyze_performance()

                    # Sleep before next iteration
                    time.sleep(self.config['sleep_interval'])

                except Exception as e:
                    self.logger.error(f"Error in trading loop: {e}")
                    time.sleep(60)  # Wait before retrying

        except KeyboardInterrupt:
            self.logger.info("Trading bot stopped by user")
        except Exception as e:
            self.logger.error(f"Critical error: {e}")

    def start_web_server(self):
        """Start the web server"""
        uvicorn.run(
            self.app,
            host="0.0.0.0",
            port=8000,
            log_level="info"
        )

if __name__ == "__main__":
    # Configuration
    config = {
        'symbol': 'BTC-USD',
        'interval': '1h',
        'lookback_days': 60,
        'initial_balance': 1000.0,
        'initial_trade_amount': 50.0,
        'min_trade_amount': 10.0,
        'threshold': 0.001,
        'evaluation_interval': 10,
        'sleep_interval': 3600,
        'max_position_size': 0.1,
        'stop_loss_pct': 0.02,
        'take_profit_pct': 0.03,
        'max_drawdown_pct': 0.05,
        'position_adjustment_factor': 1.5,
        'risk_tolerance': 0.01,
        'volatility_window': 20,
        'market_regime_threshold': 0.1,
        'correlation_threshold': 0.8,
        'diversification_factor': 1.5
    }

    # Create and run trading bot
    bot = CryptoTradingBot(config)
    bot.run()
