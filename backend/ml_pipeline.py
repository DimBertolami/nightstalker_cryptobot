import sys
sys.path.append('/opt/lampp/htdocs/NS/backend')
import pandas as pd
from pandas import DataFrame as df
import numpy as np
from sqlalchemy.orm import Session
from sklearn.ensemble import RandomForestRegressor, GradientBoostingRegressor, ExtraTreesRegressor
from sklearn.preprocessing import StandardScaler
from sklearn.model_selection import train_test_split
from sklearn.metrics import mean_squared_error, mean_absolute_error, r2_score
import tensorflow as tf
import keras as keras
from keras.models import Sequential
from keras.layers import LSTM, Dense, Dropout, GRU, Attention
import logging
from datetime import datetime, timedelta
from math import *
import torch.nn as nn

import optuna
import yfinance as yf
from typing import Dict, Tuple
from torch.utils.data import Dataset, DataLoader
from sklearn.model_selection import GridSearchCV
from transformers import AutoTokenizer, AutoModelForSequenceClassification
from backend.models.unified_models import Trade
from backend.models.unified_models import TradeMetrics, ModelPerformance, ModelPrediction
from backend.database import get_db
from backend.app import LearningMetric, TradingPerformance



class RiskManager:
    def __init__(self, config):
        self.config = config
        self.max_position_size = config.get('max_position_size', 0.1)
        self.stop_loss_pct = config.get('stop_loss_pct', 0.02)
        self.take_profit_pct = config.get('take_profit_pct', 0.03)
        self.max_drawdown_pct = config.get('max_drawdown_pct', 0.05)
        self.position_adjustment_factor = config.get('position_adjustment_factor', 1.0)
        self.risk_tolerance = config.get('risk_tolerance', 0.01)
        self.logger = logging.getLogger(__name__)

    def calculate_position_size(self, current_balance, confidence_score):
        """Calculate position size based on risk parameters and confidence"""
        base_size = current_balance * self.max_position_size
        adjusted_size = base_size * confidence_score * self.position_adjustment_factor
        return min(adjusted_size, current_balance * self.risk_tolerance)

    def calculate_stop_loss(self, current_price, position_type):
        """Calculate stop loss price"""
        if position_type == 'BUY':
            return current_price * (1 - self.stop_loss_pct)
        else:  # SELL
            return current_price * (1 + self.stop_loss_pct)

    def calculate_take_profit(self, current_price, position_type):
        """Calculate take profit price"""
        if position_type == 'BUY':
            return current_price * (1 + self.take_profit_pct)
        else:  # SELL
            return current_price * (1 - self.take_profit_pct)

    def evaluate_risk(self, current_balance, position_size, confidence_score):
        """Evaluate overall risk score"""
        risk_score = (
            (position_size / current_balance) *  # Position size risk
            (1 / confidence_score) *  # Confidence risk
            (1 / self.position_adjustment_factor)  # Adjustment factor
        )
        return min(risk_score, 1.0)

    def update_risk_profile(self, db: Session, strategy_version: str):
        """Update risk profile in database"""
        risk_profile = RiskProfile(
            strategy_version=strategy_version,
            max_position_size=self.max_position_size,
            stop_loss_pct=self.stop_loss_pct,
            take_profit_pct=self.take_profit_pct,
            max_drawdown_pct=self.max_drawdown_pct,
            position_adjustment_factor=self.position_adjustment_factor,
            risk_tolerance=self.risk_tolerance
        )
        db.add(risk_profile)
        db.commit()
        return risk_profile

    def get_current_risk_profile(self, db: Session):
        """Get the latest risk profile from database"""
        return db.query(RiskProfile).order_by(RiskProfile.last_updated.desc()).first()

class AdvancedRiskManager(RiskManager):
    def __init__(self, config):
        super().__init__(config)
        self.volatility_window = config.get('volatility_window', 20)
        self.market_regime_threshold = config.get('market_regime_threshold', 0.1)
        self.correlation_threshold = config.get('correlation_threshold', 0.8)
        self.diversification_factor = config.get('diversification_factor', 1.5)
        self.market_regime = None
        self.logger = logging.getLogger(__name__)

    def calculate_market_regime(self, df: pd.DataFrame) -> str:
        """Determine current market regime (Bull, Bear, or Sideways)"""
        try:
            # Calculate volatility
            volatility = df['Close'].rolling(window=self.volatility_window).std()
            
            # Calculate momentum
            momentum = df['Close'].pct_change(self.volatility_window)
            
            # Calculate market strength
            market_strength = momentum / volatility
            
            # Determine regime
            if market_strength.iloc[-1] > self.market_regime_threshold:
                return 'Bull'
            elif market_strength.iloc[-1] < -self.market_regime_threshold:
                return 'Bear'
            else:
                return 'Sideways'
                
        except Exception as e:
            self.logger.error(f"Error calculating market regime: {e}")
            return 'Sideways'

    def calculate_correlation_matrix(self, df: pd.DataFrame) -> pd.DataFrame:
        """Calculate correlation matrix for risk management"""
        try:
            returns = df['Close'].pct_change()
            return returns.rolling(window=self.volatility_window).corr()
        except Exception as e:
            self.logger.error(f"Error calculating correlation matrix: {e}")
            return pd.DataFrame()

    def calculate_diversification_score(self, correlation_matrix: pd.DataFrame) -> float:
        """Calculate diversification score based on correlation matrix"""
        try:
            if correlation_matrix.empty:
                return 1.0
                
            avg_corr = correlation_matrix.mean().mean()
            return 1 - (avg_corr / self.correlation_threshold)
            
        except Exception as e:
            self.logger.error(f"Error calculating diversification score: {e}")
            return 1.0

    def adjust_position_size(self, base_size: float, market_regime: str, diversification_score: float) -> float:
        """Adjust position size based on market conditions"""
        try:
            # Base adjustments
            regime_factor = {
                'Bull': 1.2,
                'Bear': 0.8,
                'Sideways': 1.0
            }[market_regime]
            
            # Apply all factors
            adjusted_size = base_size * regime_factor * self.diversification_factor * diversification_score
            
            return min(adjusted_size, self.current_balance * self.risk_tolerance)
            
        except Exception as e:
            self.logger.error(f"Error adjusting position size: {e}")
            return base_size

    def evaluate_risk(self, current_balance: float, position_size: float, confidence_score: float, 
                     correlation_matrix: pd.DataFrame) -> float:
        """Enhanced risk evaluation with multiple factors"""
        try:
            # Base risk components
            position_risk = position_size / current_balance
            confidence_risk = 1 / confidence_score if confidence_score > 0 else 1
            
            # Advanced risk factors
            diversification_score = self.calculate_diversification_score(correlation_matrix)
            market_regime = self.calculate_market_regime(df)
            regime_factor = {
                'Bull': 0.8,
                'Bear': 1.2,
                'Sideways': 1.0
            }[market_regime]
            
            # Calculate overall risk score
            risk_score = (
                position_risk * 
                confidence_risk * 
                regime_factor * 
                (1 / diversification_score)
            )
            
            return min(risk_score, 1.0)
            
        except Exception as e:
            self.logger.error(f"Error evaluating risk: {e}")
            return 1.0

class MLTradingPipeline:
    def __init__(self, config):
        self.config = config
        self.symbol = config['symbol']
        self.interval = config['interval']
        self.lookback_days = config['lookback_days']
        self.scaler = StandardScaler()
        self.models = {}
        self.risk_manager = RiskManager(config)
        self.logger = logging.getLogger(__name__)

    def fetch_data(self):
        """Fetch historical data from Yahoo Finance"""
        try:
            end_date = datetime.now()
            start_date = end_date - timedelta(days=self.lookback_days)
            
            df = yf.download(self.symbol, 
                           start=start_date, 
                           end=end_date, 
                           interval=self.interval)
            
            if df.empty:
                raise ValueError(f"No data fetched for {self.symbol}")
                
            return df
        except Exception as e:
            self.logger.error(f"Error fetching data: {e}")
            return None

    def create_features(self, df):
        """Create technical indicators and features"""
        try:
            # Basic features
            df['MA_20'] = df['Close'].rolling(window=20).mean()
            df['MA_50'] = df['Close'].rolling(window=50).mean()
            df['MA_200'] = df['Close'].rolling(window=200).mean()
            
            # RSI
            delta = df['Close'].diff()
            gain = (delta.where(delta > 0, 0)).rolling(window=14).mean()
            loss = (-delta.where(delta < 0, 0)).rolling(window=14).mean()
            rs = gain / loss
            df['RSI'] = 100 - (100 / (1 + rs))
            
            # MACD
            exp1 = df['Close'].ewm(span=12, adjust=False).mean()
            exp2 = df['Close'].ewm(span=26, adjust=False).mean()
            df['MACD'] = exp1 - exp2
            df['MACD_Signal'] = df['MACD'].ewm(span=9, adjust=False).mean()
            
            # Volume indicators
            df['Volume_MA'] = df['Volume'].rolling(window=20).mean()
            
            # Technical features
            df['Price_Change'] = df['Close'].pct_change()
            df['Volatility'] = df['Close'].rolling(window=20).std()
            
            # Advanced features
            df['RSI_MA'] = df['RSI'].rolling(window=20).mean()
            df['MACD_Hist'] = df['MACD'] - df['MACD_Signal']
            df['Volume_Ratio'] = df['Volume'] / df['Volume_MA']
            df['Price_Momentum'] = df['Close'] / df['MA_20'] - 1
            
            # Drop NaN values
            df = df.dropna()
            
            # Create feature matrix
            features = [
                'Open', 'High', 'Low', 'Close', 'Volume',
                'MA_20', 'MA_50', 'MA_200',
                'RSI', 'RSI_MA', 'MACD', 'MACD_Signal', 'MACD_Hist',
                'Volume_MA', 'Volume_Ratio',
                'Price_Change', 'Volatility', 'Price_Momentum'
            ]
            
            return df[features], df['Close'].shift(-1)
            
        except Exception as e:
            self.logger.error(f"Error creating features: {e}")
            return None, None

    def train_models(self, X, y, db: Session):
        """Train multiple models and evaluate their performance"""
        try:
            X_train, X_test, y_train, y_test = train_test_split(
                X, y, test_size=0.2, random_state=42
            )
            
            # Scale features
            X_train_scaled = self.scaler.fit_transform(X_train)
            X_test_scaled = self.scaler.transform(X_test)
            
            # Train Random Forest
            rf_model = RandomForestRegressor(
                n_estimators=150,
                max_depth=12,
                random_state=42,
                n_jobs=-1
            )
            rf_model.fit(X_train_scaled, y_train)
            
            # Train Gradient Boosting
            gb_model = GradientBoostingRegressor(
                n_estimators=100,
                learning_rate=0.1,
                max_depth=5,
                random_state=42
            )
            gb_model.fit(X_train_scaled, y_train)
            
            # Train LSTM
            X_lstm = X_train_scaled.reshape((X_train_scaled.shape[0], 1, X_train_scaled.shape[1]))
            X_lstm_test = X_test_scaled.reshape((X_test_scaled.shape[0], 1, X_test_scaled.shape[1]))
            
            lstm_model = Sequential([
                LSTM(100, return_sequences=True, input_shape=(1, X.shape[1])),
                Dropout(0.3),
                LSTM(100, return_sequences=False),
                Dropout(0.3),
                Dense(1)
            ])
            
            lstm_model.compile(optimizer='adam', loss='mse')
            lstm_model.fit(
                X_lstm, y_train,
                epochs=50,
                batch_size=32,
                validation_data=(X_lstm_test, y_test),
                verbose=0
            )
            
            # Store models
            self.models = {
                'rf': {'model': rf_model, 'weight': 0.4},
                'gb': {'model': gb_model, 'weight': 0.3},
                'lstm': {'model': lstm_model, 'weight': 0.3}
            }
            
            # Evaluate models
            predictions = {}
            for name, model in self.models.items():
                if name == 'lstm':
                    pred = model['model'].predict(X_lstm_test.reshape(X_lstm_test.shape[0], 1, -1))[:, 0]
                else:
                    pred = model['model'].predict(X_test_scaled)
                
                rmse = np.sqrt(mean_squared_error(y_test, pred))
                mae = mean_absolute_error(y_test, pred)
                r2 = r2_score(y_test, pred)
                
                # Store model performance
                model_perf = ModelPerformance(
                    model_name=name,
                    version='1.0',
                    accuracy=r2,
                    rmse=rmse,
                    mae=mae,
                    r_squared=r2
                )
                db.add(model_perf)

                # Store learning metric for chart
                learning_metric = LearningMetric(
                    timestamp=datetime.now(),
                    model_id=name,
                    accuracy=r2,
                    precision=r2, # Placeholder
                    recall=r2,    # Placeholder
                    f1_score=r2,  # Placeholder
                    profit_factor=0.0, # Placeholder
                    sharpe_ratio=0.0,  # Placeholder
                    win_rate=0.0,      # Placeholder
                    dataset_size=len(X_train) + len(X_test),
                    training_duration=0.0 # Placeholder
                )
                db.add(learning_metric)
                predictions[name] = pred
            
            db.commit()
            
            return True
            
        except Exception as e:
            self.logger.error(f"Error training models: {e}")
            return False

    def make_ensemble_prediction(self, latest_data, db: Session):
        """Make prediction using ensemble of models"""
        try:
            # Scale the latest data
            latest_scaled = self.scaler.transform(latest_data)
            
            # Get predictions from each model
            predictions = {}
            confidences = {}
            
            for name, model in self.models.items():
                if name == 'lstm':
                    pred = model['model'].predict(
                        latest_scaled.reshape(1, 1, -1)
                    )[0][0]
                else:
                    pred = model['model'].predict(latest_scaled)[0]
                
                # Calculate confidence based on model's historical performance
                model_perf = db.query(ModelPerformance).filter_by(model_name=name).order_by(
                    ModelPerformance.last_updated.desc()
                ).first()
                
                if model_perf:
                    confidence = 1 - (model_perf.rmse / model_perf.r_squared)
                    confidences[name] = confidence
                else:
                    confidences[name] = 0.5
                
                predictions[name] = pred
            
            # Calculate weighted prediction
            total_weight = sum(model['weight'] for model in self.models.values())
            weighted_pred = sum(
                (pred * model['weight'] * confidences[name])
                for name, pred in predictions.items()
                for model in self.models.values() if model['model'] == self.models[name]['model']
            ) / total_weight
            
            # Calculate overall confidence score
            confidence_score = sum(confidences.values()) / len(confidences)
            
            # Store prediction in database
            latest_price = latest_data['Close'].iloc[0]
            error = abs(weighted_pred - latest_price)
            
            model_pred = ModelPrediction(
                performance_id=db.query(ModelPerformance).order_by(
                    ModelPerformance.last_updated.desc()
                ).first().id,
                actual_price=latest_price,
                predicted_price=weighted_pred,
                error=error,
                confidence_score=confidence_score
            )
            db.add(model_pred)
            db.commit()
            
            return weighted_pred, confidence_score
            
        except Exception as e:
            self.logger.error(f"Error making ensemble prediction: {e}")
            return None, None

    def make_trading_decision(self, current_price, predicted_price, confidence_score, db: Session):
        """Make trading decision with risk management"""
        try:
            # Get current risk profile
            risk_profile = self.risk_manager.get_current_risk_profile(db)
            
            # Calculate price change
            price_change = (predicted_price - current_price) / current_price
            
            # Calculate risk score
            risk_score = self.risk_manager.evaluate_risk(
                current_balance=db.query(Trade).order_by(Trade.id.desc()).first().balance,
                position_size=self.risk_manager.calculate_position_size(
                    db.query(Trade).order_by(Trade.id.desc()).first().balance,
                    confidence_score
                ),
                confidence_score=confidence_score
            )
            
            # Make decision based on multiple factors
            if price_change > self.config['threshold'] and confidence_score > 0.6:
                decision = 'BUY'
            elif price_change < -self.config['threshold'] and confidence_score > 0.6:
                decision = 'SELL'
            else:
                decision = 'HOLD'
            
            # Store trade in database
            trade = Trade(
                symbol=self.symbol,
                decision=decision,
                price=current_price,
                amount=0,  # Will be calculated later
                balance=db.query(Trade).order_by(Trade.id.desc()).first().balance,
                position=0,  # Will be calculated later
                risk_score=risk_score,
                model_confidence=confidence_score,
                strategy_version='1.0',
                notes=f"Decision based on ensemble prediction with confidence {confidence_score:.2f}"
            )
            
            db.add(trade)
            db.commit()
            
            return decision, risk_score
            
        except Exception as e:
            self.logger.error(f"Error making trading decision: {e}")
            return 'HOLD', 1.0

    def evaluate_strategy(self, db: Session):
        """Evaluate trading strategy performance"""
        try:
            # Get all trades
            trades = db.query(Trade).all()
            
            if not trades:
                return None
            
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
            
            # Update trade metrics
            latest_trade = trades[-1]
            metrics = TradeMetrics(
                trade_id=latest_trade.id,
                sharpe_ratio=sharpe_ratio,
                max_drawdown=max_drawdown,
                win_rate=win_rate,
                total_return=total_return,
                volatility=volatility
            )
            
            db.add(metrics)
            db.commit()
            
            return {
                'total_return': total_return,
                'volatility': volatility,
                'sharpe_ratio': sharpe_ratio,
                'max_drawdown': max_drawdown,
                'win_rate': win_rate,
                'total_trades': total_trades
            }
            
        except Exception as e:
            self.logger.error(f"Error evaluating strategy: {e}")
            return None

class AdvancedMLPipeline(MLTradingPipeline):
    def __init__(self, config):
        super().__init__(config)
        self.risk_manager = AdvancedRiskManager(config)
        self.models = {}
        self.hyperparameters = {}
        self.logger = logging.getLogger(__name__)

    def optimize_hyperparameters(self, X_train, y_train, model_name: str) -> Dict:
        """Optimize hyperparameters using Optuna"""
        def objective(trial):
            if model_name == 'rf':
                params = {
                    'n_estimators': trial.suggest_int('n_estimators', 50, 200),
                    'max_depth': trial.suggest_int('max_depth', 5, 20),
                    'min_samples_split': trial.suggest_int('min_samples_split', 2, 10)
                }
            elif model_name == 'gb':
                params = {
                    'n_estimators': trial.suggest_int('n_estimators', 50, 200),
                    'learning_rate': trial.suggest_float('learning_rate', 0.01, 0.1),
                    'max_depth': trial.suggest_int('max_depth', 3, 10)
                }
            elif model_name == 'lstm':
                params = {
                    'units': trial.suggest_int('units', 50, 150),
                    'dropout': trial.suggest_float('dropout', 0.1, 0.5),
                    'learning_rate': trial.suggest_float('learning_rate', 0.001, 0.01)
                }
            
            # Train model with current parameters
            model = self.train_model(X_train, y_train, model_name, params)
            
            # Evaluate
            predictions = model.predict(X_train)
            return mean_squared_error(y_train, predictions)
        
        study = optuna.create_study(direction='minimize')
        study.optimize(objective, n_trials=20)
        
        return study.best_params

    def create_deep_model(self, input_shape):
        """Create advanced deep learning model"""
        model = Sequential([
            LSTM(128, return_sequences=True, input_shape=input_shape),
            Dropout(0.3),
            GRU(128, return_sequences=True),
            Dropout(0.3),
            Attention(),
            Dense(64, activation='relu'),
            Dropout(0.2),
            Dense(1)
        ])
        
        model.compile(
            optimizer=tf.keras.optimizers.Adam(learning_rate=0.001),
            loss='mse'
        )
        
        return model

    def create_transformer_model(self, input_shape):
        """Create transformer-based model"""
        class TransformerModel(nn.Module):
            def __init__(self, input_dim, hidden_dim=128, num_heads=4, num_layers=2):
                super().__init__()
                self.embedding = nn.Linear(input_dim, hidden_dim)
                self.transformer = nn.TransformerEncoder(
                    nn.TransformerEncoderLayer(
                        d_model=hidden_dim,
                        nhead=num_heads,
                        dim_feedforward=hidden_dim * 4,
                        dropout=0.1
                    ),
                    num_layers=num_layers
                )
                self.fc = nn.Linear(hidden_dim, 1)

            def forward(self, x):
                x = self.embedding(x)
                x = self.transformer(x)
                x = self.fc(x.mean(dim=1))
                return x

        return TransformerModel(input_shape[1])

    def train_models(self, X, y, db: Session):
        """Train multiple advanced models with optimization"""
        try:
            X_train, X_test, y_train, y_test = train_test_split(
                X, y, test_size=0.2, random_state=42
            )
            
            # Scale features
            X_train_scaled = self.scaler.fit_transform(X_train)
            X_test_scaled = self.scaler.transform(X_test)
            
            # Optimize and train models
            models = {
                'rf': RandomForestRegressor,
                'gb': GradientBoostingRegressor,
                'et': ExtraTreesRegressor
            }
            
            for name, model_class in models.items():
                # Optimize hyperparameters
                best_params = self.optimize_hyperparameters(X_train_scaled, y_train, name)
                
                # Train model
                model = model_class(**best_params)
                model.fit(X_train_scaled, y_train)
                
                # Evaluate
                pred = model.predict(X_test_scaled)
                rmse = np.sqrt(mean_squared_error(y_test, pred))
                mae = mean_absolute_error(y_test, pred)
                r2 = r2_score(y_test, pred)
                
                # Store model performance
                model_perf = ModelPerformance(
                    model_name=name,
                    version='2.0',
                    accuracy=r2,
                    rmse=rmse,
                    mae=mae,
                    r_squared=r2
                )
                db.add(model_perf)
                
                # Store in models dict
                self.models[name] = {
                    'model': model,
                    'weight': 0.25,  # Equal weight initially
                    'performance': {
                        'rmse': rmse,
                        'r2': r2
                    }
                }
            
            # Train deep learning models
            X_lstm = X_train_scaled.reshape((X_train_scaled.shape[0], 1, X_train_scaled.shape[1]))
            X_lstm_test = X_test_scaled.reshape((X_test_scaled.shape[0], 1, X_test_scaled.shape[1]))
            
            # LSTM with Attention
            lstm_model = self.create_deep_model((1, X.shape[1]))
            lstm_model.fit(
                X_lstm, y_train,
                epochs=50,
                batch_size=32,
                validation_data=(X_lstm_test, y_test),
                verbose=0
            )
            
            # Transformer model
            transformer_model = self.create_transformer_model((1, X.shape[1]))
            transformer_optimizer = torch.optim.Adam(transformer_model.parameters(), lr=0.001)
            transformer_criterion = nn.MSELoss()
            
            # Convert data to PyTorch tensors
            X_torch = torch.FloatTensor(X_lstm)
            y_torch = torch.FloatTensor(y_train.values)
            
            # Train transformer
            for epoch in range(50):
                transformer_optimizer.zero_grad()
                outputs = transformer_model(X_torch)
                loss = transformer_criterion(outputs, y_torch)
                loss.backward()
                transformer_optimizer.step()
            
            # Store deep learning models
            self.models['lstm_attention'] = {
                'model': lstm_model,
                'weight': 0.25,
                'performance': {'rmse': loss.item()}
            }
            
            self.models['transformer'] = {
                'model': transformer_model,
                'weight': 0.25,
                'performance': {'rmse': loss.item()}
            }
            
            db.commit()
            return True
            
        except Exception as e:
            self.logger.error(f"Error training models: {e}")
            return False

    def make_ensemble_prediction(self, latest_data, db: Session):
        """Make prediction using ensemble of advanced models"""
        try:
            # Scale the latest data
            latest_scaled = self.scaler.transform(latest_data)
            
            # Get predictions from each model
            predictions = {}
            confidences = {}
            
            for name, model in self.models.items():
                if name in ['lstm_attention', 'transformer']:
                    # Deep learning models
                    if name == 'lstm_attention':
                        pred = model['model'].predict(
                            latest_scaled.reshape(1, 1, -1)
                        )[0][0]
                    else:  # transformer
                        with torch.no_grad():
                            input_tensor = torch.FloatTensor(
                                latest_scaled.reshape(1, 1, -1)
                            )
                            pred = model['model'](input_tensor).item()
                else:
                    # Traditional models
                    pred = model['model'].predict(latest_scaled)[0]
                
                # Calculate confidence based on model's historical performance
                perf = model['performance']
                confidence = 1 - (perf['rmse'] / (perf['r2'] + 1e-6))
                confidences[name] = confidence
                predictions[name] = pred
            
            # Calculate weighted prediction
            total_weight = sum(model['weight'] * confidences[name] 
                             for name, model in self.models.items())
            
            weighted_pred = sum(
                (pred * model['weight'] * confidences[name])
                for name, pred in predictions.items()
                for model in self.models.values() if model['model'] == self.models[name]['model']
            ) / total_weight
            
            # Calculate overall confidence score
            confidence_score = sum(confidences.values()) / len(confidences)
            
            # Store prediction in database
            latest_price = latest_data['Close'].iloc[0]
            error = abs(weighted_pred - latest_price)
            
            model_pred = ModelPrediction(
                performance_id=db.query(ModelPerformance).order_by(
                    ModelPerformance.last_updated.desc()
                ).first().id,
                actual_price=latest_price,
                predicted_price=weighted_pred,
                error=error,
                confidence_score=confidence_score
            )
            db.add(model_pred)
            db.commit()
            
            return weighted_pred, confidence_score
            
        except Exception as e:
            self.logger.error(f"Error making ensemble prediction: {e}")
            return None, None

    def make_trading_decision(self, current_price, predicted_price, confidence_score, db: Session):
        """Make trading decision with advanced risk management"""
        try:
            # Calculate market regime
            market_regime = self.risk_manager.calculate_market_regime(self.pipeline.fetch_data())
            
            # Calculate correlation matrix
            correlation_matrix = self.risk_manager.calculate_correlation_matrix(
                self.pipeline.fetch_data()
            )
            
            # Calculate price change
            price_change = (predicted_price - current_price) / current_price
            
            # Calculate risk score
            risk_score = self.risk_manager.evaluate_risk(
                current_balance=db.query(Trade).order_by(Trade.id.desc()).first().balance,
                position_size=self.risk_manager.calculate_position_size(
                    db.query(Trade).order_by(Trade.id.desc()).first().balance,
                    confidence_score
                ),
                confidence_score=confidence_score,
                correlation_matrix=correlation_matrix
            )
            
            # Make decision based on multiple factors
            if (price_change > self.config['threshold'] and 
                confidence_score > 0.7 and 
                risk_score < 0.8 and 
                market_regime != 'Bear'):
                decision = 'BUY'
            elif (price_change < -self.config['threshold'] and 
                  confidence_score > 0.7 and 
                  risk_score < 0.8 and 
                  market_regime != 'Bull'):
                decision = 'SELL'
            else:
                decision = 'HOLD'
            
            # Store trade in database with additional risk metrics
            trade = Trade(
                symbol=self.symbol,
                decision=decision,
                price=current_price,
                amount=0,  # Will be calculated later
                balance=db.query(Trade).order_by(Trade.id.desc()).first().balance,
                position=0,  # Will be calculated later
                risk_score=risk_score,
                model_confidence=confidence_score,
                strategy_version='2.0',
                notes=f"Decision based on ensemble prediction with confidence {confidence_score:.2f}, "
                      f"market regime: {market_regime}, risk score: {risk_score:.2f}"
            )
            
            db.add(trade)
            db.commit()
            
            return decision, risk_score
            
        except Exception as e:
            self.logger.error(f"Error making trading decision: {e}")
            return 'HOLD', 1.0
