from sqlalchemy import create_engine, Column, Integer, String, Float, DateTime, MetaData
from sqlalchemy.ext.declarative import declarative_base
from datetime import datetime

# Database configuration
DATABASE_URL = "mysql+pymysql://root:1304@localhost/NS"
engine = create_engine(DATABASE_URL)

# Define the base class for declarative models
Base = declarative_base()

class TradingPerformance(Base):
    __tablename__ = 'trading_performance'
    id = Column(Integer, primary_key=True)
    trade_id = Column(String(255), nullable=False, unique=True)
    profit_loss = Column(Float, nullable=False)
    entry_price = Column(Float, nullable=False)
    exit_price = Column(Float, nullable=True)
    holding_period = Column(Float, nullable=True)
    timestamp = Column(DateTime, default=datetime.utcnow)

# Drop the table if it exists
meta = MetaData()
meta.reflect(bind=engine)
if 'trading_performance' in meta.tables:
    TradingPerformance.__table__.drop(engine)
    print("Table 'trading_performance' dropped.")

# Create the table
Base.metadata.create_all(engine)

print("Table 'trading_performance' created successfully.")