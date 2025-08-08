"""
Utility functions for the crypto selection system.
"""

import logging
from typing import Dict, Any

class ValidationError(Exception):
    """Custom exception for validation errors."""
    pass

def setup_logger(name: str, level: str = "INFO") -> logging.Logger:
    """Set up a logger with consistent formatting."""
    logger = logging.getLogger(name)
    logger.setLevel(getattr(logging, level.upper()))
    
    if not logger.handlers:
        handler = logging.StreamHandler()
        formatter = logging.Formatter(
            '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
        )
        handler.setFormatter(formatter)
        logger.addHandler(handler)
    
    return logger

def validate_config(config: Dict[str, Any]) -> bool:
    """Validate configuration dictionary."""
    required_keys = ['models', 'features', 'risk', 'decision']
    
    for key in required_keys:
        if key not in config:
            raise ValidationError(f"Missing required config key: {key}")
    
    return True

def safe_divide(numerator: float, denominator: float, default: float = 0.0) -> float:
    """Safe division with default value for zero denominator."""
    return numerator / denominator if denominator != 0 else default

def calculate_sharpe_ratio(returns: list, risk_free_rate: float = 0.0) -> float:
    """Calculate Sharpe ratio from returns."""
    if not returns:
        return 0.0
    
    excess_returns = [r - risk_free_rate for r in returns]
    mean_return = sum(excess_returns) / len(excess_returns)
    
    if len(excess_returns) < 2:
        return 0.0
    
    variance = sum((r - mean_return) ** 2 for r in excess_returns) / (len(excess_returns) - 1)
    std_dev = variance ** 0.5
    
    return safe_divide(mean_return, std_dev)

def calculate_max_drawdown(prices: list) -> float:
    """Calculate maximum drawdown from price series."""
    if not prices:
        return 0.0
    
    max_drawdown = 0.0
    peak = prices[0]
    
    for price in prices[1:]:
        if price > peak:
            peak = price
        else:
            drawdown = (peak - price) / peak
            max_drawdown = max(max_drawdown, drawdown)
    
    return max_drawdown

def format_currency(value: float, currency: str = "USD") -> str:
    """Format currency value."""
    if abs(value) >= 1_000_000:
        return f"{value/1_000_000:.2f}M {currency}"
    elif abs(value) >= 1_000:
        return f"{value/1_000:.2f}K {currency}"
    else:
        return f"{value:.2f} {currency}"

def format_percentage(value: float, decimals: int = 2) -> str:
    """Format percentage value."""
    return f"{value*100:.{decimals}f}%"

def validate_dataframe(df, required_columns: list) -> bool:
    """Validate DataFrame has required columns."""
    if df is None or df.empty:
        return False
    
    missing_cols = [col for col in required_columns if col not in df.columns]
    if missing_cols:
        raise ValidationError(f"Missing required columns: {missing_cols}")
    
    return True

def create_directory_if_not_exists(directory: str) -> None:
    """Create directory if it doesn't exist."""
    import os
    os.makedirs(directory, exist_ok=True)

def get_current_timestamp() -> str:
    """Get current timestamp as string."""
    from datetime import datetime
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")

def safe_get(dictionary: Dict, key: str, default: Any = None) -> Any:
    """Safely get value from dictionary."""
    return dictionary.get(key, default)

def clamp_value(value: float, min_val: float, max_val: float) -> float:
    """Clamp value between min and max."""
    return max(min_val, min(max_val, value))

def calculate_volatility(returns: list) -> float:
    """Calculate volatility (standard deviation) of returns."""
    if len(returns) < 2:
        return 0.0
    
    mean = sum(returns) / len(returns)
    variance = sum((r - mean) ** 2 for r in returns) / (len(returns) - 1)
    return variance ** 0.5

def calculate_correlation(x: list, y: list) -> float:
    """Calculate Pearson correlation coefficient."""
    if len(x) != len(y) or len(x) < 2:
        return 0.0
    
    mean_x = sum(x) / len(x)
    mean_y = sum(y) / len(y)
    
    numerator = sum((xi - mean_x) * (yi - mean_y) for xi, yi in zip(x, y))
    denominator = (sum((xi - mean_x) ** 2 for xi in x) * sum((yi - mean_y) ** 2 for yi in y)) ** 0.5
    
    return safe_divide(numerator, denominator)

def moving_average(data: list, window: int) -> list:
    """Calculate moving average."""
    if window <= 0 or window > len(data):
        return data
    
    return [sum(data[i:i+window]) / window for i in range(len(data) - window + 1)]

def exponential_moving_average(data: list, span: int) -> list:
    """Calculate exponential moving average."""
    if span <= 0 or not data:
        return data
    
    alpha = 2 / (span + 1)
    ema = [data[0]]
    
    for value in data[1:]:
        ema.append(alpha * value + (1 - alpha) * ema[-1])
    
    return ema
