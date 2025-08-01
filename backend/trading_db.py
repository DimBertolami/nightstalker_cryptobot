from datetime import datetime
from backend.models.unified_models import Base, engine, Session, Trade, TradingSignal, Position

def init_db():
    """Initializes the database and creates tables if they don't exist."""
    Base.metadata.create_all(engine)

def add_trade(symbol, side, price, quantity, timestamp=None):
    """Adds a new trade to the database."""
    session = Session()
    if timestamp is None:
        timestamp = datetime.utcnow()
    trade = Trade(symbol=symbol, side=side, price=price, quantity=quantity, timestamp=timestamp)
    session.add(trade)
    session.commit()
    session.close()

def get_trades(symbol=None, limit=100):
    """Retrieves trades from the database."""
    session = Session()
    if symbol:
        trades = session.query(Trade).filter_by(symbol=symbol).order_by(Trade.timestamp.desc()).limit(limit).all()
    else:
        trades = session.query(Trade).order_by(Trade.timestamp.desc()).limit(limit).all()
    session.close()
    return trades

def add_trading_signal(symbol, signal, price, confidence, timestamp=None):
    """Adds a new trading signal to the database."""
    session = Session()
    if timestamp is None:
        timestamp = datetime.utcnow()
    trading_signal = TradingSignal(symbol=symbol, signal=signal, price=price, confidence=confidence, timestamp=timestamp)
    session.add(trading_signal)
    session.commit()
    session.close()

def get_trading_signals(symbol=None, limit=100):
    """Retrieve trading signals from the database."""
    session = Session()
    if symbol:
        signals = session.query(TradingSignal).filter_by(symbol=symbol).order_by(TradingSignal.timestamp.desc()).limit(limit).all()
    else:
        signals = session.query(TradingSignal).order_by(TradingSignal.timestamp.desc()).limit(limit).all()
    session.close()
    return signals

def update_position(symbol, quantity, entry_price):
    """Updates a position in the database."""
    session = Session()
    position = session.query(Position).filter_by(symbol=symbol).first()
    if position:
        position.quantity = quantity
        position.entry_price = entry_price
        position.last_updated = datetime.utcnow()
    else:
        position = Position(symbol=symbol, quantity=quantity, entry_price=entry_price)
        session.add(position)
    session.commit()
    session.close()

def get_position(symbol):
    """Retriever a position from the database."""
    session = Session()
    position = session.query(Position).filter_by(symbol=symbol).first()
    session.close()
    return position

if __name__ == '__main__':
    # Example usage
    init_db()
    add_trade('BTCUSDT', 'buy', 50000.0, 0.1)
    add_trading_signal('BTCUSDT', 'buy', 50000.0, 0.85)
    update_position('BTCUSDT', 0.1, 50000.0)

    print("Trades:", get_trades('BTCUSDT'))
    print("Signals:", get_trading_signals('BTCUSDT'))
    print("Position:", get_position('BTCUSDT'))