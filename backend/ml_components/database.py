from sqlalchemy import create_engine, Column, Integer, Float, String, DateTime, Boolean, ForeignKey
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker, relationship
from datetime import datetime
from .ml_component_base import MLComponentBase

# Database configuration
DATABASE_URL = 'sqlite:///cryptobot.db'
engine = create_engine(DATABASE_URL, connect_args={'check_same_thread': False})
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
Base = declarative_base()

class Trade(Base, MLComponentBase):
    __tablename__ = "trades"
    
    id = Column(Integer, primary_key=True, index=True)
    timestamp = Column(DateTime, default=datetime.utcnow)
    symbol = Column(String(20))
    decision = Column(String(10))  # BUY, SELL, HOLD
    price = Column(Float)
    amount = Column(Float)
    balance = Column(Float)
    position = Column(Float)
    profit = Column(Float)
    risk_score = Column(Float)
    model_confidence = Column(Float)
    strategy_version = Column(String(50))
    notes = Column(String(500))
    
    metrics = relationship("TradeMetrics", back_populates="trade", uselist=False)

class TradeMetrics(Base, MLComponentBase):
    __tablename__ = "trade_metrics"
    
    id = Column(Integer, primary_key=True, index=True)
    trade_id = Column(Integer, ForeignKey("trades.id"))
    
    sharpe_ratio = Column(Float)
    max_drawdown = Column(Float)
    win_rate = Column(Float)
    total_return = Column(Float)
    volatility = Column(Float)
    
    trade = relationship("Trade", back_populates="metrics")

class RiskProfile(Base, MLComponentBase):
    __tablename__ = "risk_profiles"
    
    id = Column(Integer, primary_key=True, index=True)
    strategy_version = Column(String(50))
    max_position_size = Column(Float)
    stop_loss_pct = Column(Float)
    take_profit_pct = Column(Float)
    max_drawdown_pct = Column(Float)
    position_adjustment_factor = Column(Float)
    risk_tolerance = Column(Float)
    last_updated = Column(DateTime, default=datetime.utcnow)

class ModelPerformance(Base, MLComponentBase):
    __tablename__ = "model_performance"
    
    id = Column(Integer, primary_key=True, index=True)
    model_name = Column(String(50))
    version = Column(String(20))
    accuracy = Column(Float)
    rmse = Column(Float)
    mae = Column(Float)
    r_squared = Column(Float)
    last_updated = Column(DateTime, default=datetime.utcnow)
    
    predictions = relationship("ModelPrediction", back_populates="performance")

class ModelPrediction(Base, MLComponentBase):
    __tablename__ = "model_predictions"
    
    id = Column(Integer, primary_key=True, index=True)
    performance_id = Column(Integer, ForeignKey("model_performance.id"))
    
    timestamp = Column(DateTime, default=datetime.utcnow)
    actual_price = Column(Float)
    predicted_price = Column(Float)
    error = Column(Float)
    confidence_score = Column(Float)
    
    performance = relationship("ModelPerformance", back_populates="predictions")

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

def init_db():
    """Initialize database tables"""
    Base.metadata.create_all(bind=engine)
    print("Database tables created successfully")
