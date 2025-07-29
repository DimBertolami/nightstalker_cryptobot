from sqlalchemy import create_engine, Column, Integer, String, Float, DateTime, ForeignKey
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker, relationship
from datetime import datetime

Base = declarative_base()

# Database configuration
DATABASE_URL = 'sqlite:///cryptobot.db'
engine = create_engine(DATABASE_URL, connect_args={'check_same_thread': False})
Session = sessionmaker(bind=engine)

class Trade(Base):
    __tablename__ = 'trades'
    
    id = Column(Integer, primary_key=True)
    symbol = Column(String, nullable=False)
    side = Column(String, nullable=False)  # 'buy' or 'sell'
    quantity = Column(Float, nullable=False)
    price = Column(Float, nullable=False)
    timestamp = Column(DateTime, default=datetime.utcnow)
    status = Column(String, default='active')  # 'active', 'closed', 'cancelled'
    
    # Relationships
    signals = relationship('TradingSignal', back_populates='trade')

class TradingSignal(Base):
    __tablename__ = 'trading_signals'
    
    id = Column(Integer, primary_key=True)
    symbol = Column(String, nullable=False)
    signal = Column(String, nullable=False)  # 'buy', 'sell', 'hold'
    confidence = Column(Float, nullable=False)
    timestamp = Column(DateTime, default=datetime.utcnow)
    trade_id = Column(Integer, ForeignKey('trades.id'))
    
    # Relationships
    trade = relationship('Trade', back_populates='signals')

class Position(Base):
    __tablename__ = 'positions'
    
    id = Column(Integer, primary_key=True)
    symbol = Column(String, nullable=False)
    entry_price = Column(Float, nullable=False)
    entry_time = Column(DateTime, default=datetime.utcnow)
    exit_price = Column(Float)
    exit_time = Column(DateTime)
    quantity = Column(Float, nullable=False)
    profit_loss = Column(Float)
    status = Column(String, default='open')  # 'open', 'closed'

def init_db():
    """Initialize the database and create tables if they don't exist."""
    Base.metadata.create_all(engine)
    print("Database initialized successfully!")

def get_session():
    """Get a new database session"""
    return Session()

def add_trade(symbol, side, quantity, price):
    """Add a new trade to the database"""
    session = get_session()
    try:
        trade = Trade(
            symbol=symbol,
            side=side,
            quantity=quantity,
            price=price
        )
        session.add(trade)
        session.commit()
        return trade
    except Exception as e:
        session.rollback()
        raise e
    finally:
        session.close()

def add_signal(symbol, signal, confidence):
    """Add a new trading signal to the database"""
    session = get_session()
    try:
        signal = TradingSignal(
            symbol=symbol,
            signal=signal,
            confidence=confidence
        )
        session.add(signal)
        session.commit()
        return signal
    except Exception as e:
        session.rollback()
        raise e
    finally:
        session.close()

def update_position(symbol, price=None, status=None):
    """Update a position's price or status"""
    session = get_session()
    try:
        position = session.query(Position).filter_by(symbol=symbol, status='open').first()
        if position:
            if price:
                position.exit_price = price
            if status:
                position.status = status
            session.commit()
            return position
        return None
    except Exception as e:
        session.rollback()
        raise e
    finally:
        session.close()

def get_open_positions():
    """Get all open positions"""
    session = get_session()
    try:
        positions = session.query(Position).filter_by(status='open').all()
        return positions
    finally:
        session.close()

if __name__ == '__main__':
    init_db()
