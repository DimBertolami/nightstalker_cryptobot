from sqlalchemy import create_engine, Column, Integer, String, Float, DateTime, Boolean, JSON, ForeignKey
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker, relationship
from datetime import datetime

Base = declarative_base()

class TradingSignal(Base):
    __tablename__ = "trading_signals"
    
    id = Column(Integer, primary_key=True, autoincrement=True)
    symbol = Column(String(255), nullable=False)
    timestamp = Column(DateTime, nullable=False)
    signal = Column(String(255), nullable=False)
    confidence = Column(Float, nullable=False)
    price = Column(Float)
    price_change_24h = Column(Float)
    model_id = Column(String(255))
    trade_id = Column(Integer, ForeignKey('trades.id')) # Added ForeignKey
    trade = relationship('Trade', back_populates='signals') # Added relationship

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

class BotThought(Base):
    __tablename__ = "bot_thoughts"
    
    id = Column(Integer, primary_key=True, autoincrement=True)
    timestamp = Column(DateTime, nullable=False)
    thought_type = Column(String(255), nullable=False)
    thought_content = Column(String(255), nullable=False)
    symbol = Column(String(255))
    confidence = Column(Float)
    metrics = Column(JSON)

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

class Trade(Base):
    __tablename__ = 'trades'
    
    id = Column(Integer, primary_key=True)
    symbol = Column(String(255), nullable=False)
    side = Column(String(255), nullable=False)  # 'buy' or 'sell'
    quantity = Column(Float, nullable=False)
    price = Column(Float, nullable=False)
    timestamp = Column(DateTime, default=datetime.utcnow)
    status = Column(String(255), default='active')  # 'active', 'closed', 'cancelled'
    
    # Relationships
    signals = relationship('TradingSignal', back_populates='trade')
    metrics = relationship("TradeMetrics", back_populates="trade", uselist=False)


class Position(Base):
    __tablename__ = 'positions'
    
    id = Column(Integer, primary_key=True)
    symbol = Column(String(255), nullable=False)
    entry_price = Column(Float, nullable=False)
    entry_time = Column(DateTime, default=datetime.utcnow)
    exit_price = Column(Float)
    exit_time = Column(DateTime)
    quantity = Column(Float, nullable=False)
    profit_loss = Column(Float)
    status = Column(String(255), default='open')  # 'open', 'closed'

class ModelPerformance(Base):
    __tablename__ = 'model_performance'

    id = Column(Integer, primary_key=True)
    model_name = Column(String(255), nullable=False)
    version = Column(String(255), nullable=False)
    accuracy = Column(Float)
    rmse = Column(Float)
    mae = Column(Float)
    r_squared = Column(Float)
    last_updated = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    predictions = relationship("ModelPrediction", back_populates="performance")

class ModelPrediction(Base):
    __tablename__ = 'model_predictions'

    id = Column(Integer, primary_key=True)
    performance_id = Column(Integer, ForeignKey('model_performance.id'))
    actual_price = Column(Float)
    predicted_price = Column(Float)
    error = Column(Float)
    confidence_score = Column(Float)
    performance = relationship("ModelPerformance", back_populates="predictions")

class TradeMetrics(Base):
    __tablename__ = 'trade_metrics'

    id = Column(Integer, primary_key=True)
    trade_id = Column(Integer, ForeignKey('trades.id'))
    sharpe_ratio = Column(Float)
    max_drawdown = Column(Float)
    win_rate = Column(Float)
    total_return = Column(Float)
    volatility = Column(Float)
    trade = relationship("Trade", back_populates="metrics")

