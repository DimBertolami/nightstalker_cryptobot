from sqlalchemy import Column, Integer, String, Float, DateTime
from sqlalchemy.ext.declarative import declarative_base
from datetime import datetime

Base = declarative_base()

class Trade(Base):
    """Represents a single trade in the database"""
    __tablename__ = 'trades'
    
    id = Column(Integer, primary_key=True)
    symbol = Column(String(20), nullable=False)
    quantity = Column(Float, nullable=False)
    price = Column(Float, nullable=False)
    timestamp = Column(DateTime, default=datetime.utcnow)
    type = Column(String(10), nullable=False)  # 'buy' or 'sell'
    status = Column(String(20), default='completed')
    
    def to_dict(self):
        """Convert trade to dictionary for API responses"""
        return {
            'id': self.id,
            'symbol': self.symbol,
            'quantity': self.quantity,
            'price': self.price,
            'timestamp': self.timestamp.isoformat(),
            'type': self.type,
            'status': self.status
        }

class Position(Base):
    """Represents the current trading position"""
    __tablename__ = 'positions'
    
    id = Column(Integer, primary_key=True)
    symbol = Column(String(20), nullable=False)
    quantity = Column(Float, nullable=False)
    entry_price = Column(Float, nullable=False)
    entry_time = Column(DateTime, default=datetime.utcnow)
    
    def to_dict(self):
        """Convert position to dictionary for API responses"""
        return {
            'id': self.id,
            'symbol': self.symbol,
            'quantity': self.quantity,
            'entry_price': self.entry_price,
            'entry_time': self.entry_time.isoformat()
        }
