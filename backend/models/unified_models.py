
from sqlalchemy import create_engine, Column, Integer, String, Float, DateTime, ForeignKey
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker, relationship
import os
from datetime import datetime

# Database configuration using environment variables
DATABASE_URL = f"mysql+pymysql://{os.getenv('DB_USER')}:{os.getenv('DB_PASS')}@{os.getenv('DB_HOST')}/{os.getenv('DB_NAME')}"
engine = create_engine(DATABASE_URL)

# Create a configured "Session" class
Session = sessionmaker(bind=engine)

# Define the base class for declarative models
Base = declarative_base()

class LearningMetric(Base):
    __tablename__ = 'learning_metrics'
    id = Column(Integer, primary_key=True)
    model_name = Column(String(255), nullable=False)
    accuracy = Column(Float, nullable=True)
    precision = Column(Float, nullable=True)
    recall = Column(Float, nullable=True)
    f1_score = Column(Float, nullable=True)
    timestamp = Column(DateTime, default=datetime.utcnow)

class TradingPerformance(Base):
    __tablename__ = 'trading_performance'
    id = Column(Integer, primary_key=True)
    trade_id = Column(String(255), nullable=False, unique=True)
    profit_loss = Column(Float, nullable=False)
    entry_price = Column(Float, nullable=False)
    exit_price = Column(Float, nullable=True)
    timestamp = Column(DateTime, default=datetime.utcnow)

class TradeMetrics(Base):
    __tablename__ = "trade_metrics"
    
    id = Column(Integer, primary_key=True, index=True)
    trade_id = Column(Integer)
    
    sharpe_ratio = Column(Float)
    max_drawdown = Column(Float)
    win_rate = Column(Float)
    total_return = Column(Float)
    volatility = Column(Float)

class ModelPerformance(Base):
    __tablename__ = "model_performance"
    
    id = Column(Integer, primary_key=True, index=True)
    model_name = Column(String(50))
    version = Column(String(20))
    accuracy = Column(Float)
    rmse = Column(Float)
    mae = Column(Float)
    r_squared = Column(Float)
    last_updated = Column(DateTime, default=datetime.utcnow)

class Trade(Base):
    __tablename__ = 'trades'
    id = Column(Integer, primary_key=True)
    symbol = Column(String(50), nullable=False)
    side = Column(String(10), nullable=False)
    price = Column(Float, nullable=False)
    quantity = Column(Float, nullable=False)
    timestamp = Column(DateTime, default=datetime.utcnow)

class TradingSignal(Base):
    __tablename__ = 'trading_signals'
    id = Column(Integer, primary_key=True)
    symbol = Column(String(50), nullable=False)
    signal = Column(String(10), nullable=False)
    price = Column(Float, nullable=False)
    confidence = Column(Float, nullable=True)
    timestamp = Column(DateTime, default=datetime.utcnow)

class Position(Base):
    __tablename__ = 'positions'
    id = Column(Integer, primary_key=True)
    symbol = Column(String(50), nullable=False, unique=True)
    quantity = Column(Float, nullable=False)
    entry_price = Column(Float, nullable=False)
    last_updated = Column(DateTime, default=datetime.utcnow)

class BotThought(Base):
    __tablename__ = 'bot_thoughts'
    id = Column(Integer, primary_key=True)
    timestamp = Column(DateTime, default=datetime.utcnow)
    thought = Column(String(1024), nullable=False)
    confidence = Column(Float, nullable=True)

class ModelPrediction(Base):
    __tablename__ = 'model_predictions'
    id = Column(Integer, primary_key=True)
    model_name = Column(String(255), nullable=False)
    prediction = Column(String(1024), nullable=False)
    confidence = Column(Float, nullable=True)
    timestamp = Column(DateTime, default=datetime.utcnow)
