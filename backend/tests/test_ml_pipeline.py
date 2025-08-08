import unittest
from unittest.mock import patch, MagicMock
import pandas as pd
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
import sys
import os

# Add the backend directory to the Python path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

from ml_pipeline import MLTradingPipeline
from models.unified_models import Base

class TestMLPipeline(unittest.TestCase):

    def setUp(self):
        """Set up a temporary in-memory database and a pipeline instance for each test."""
        self.engine = create_engine('sqlite:///:memory:')
        Base.metadata.create_all(self.engine)
        self.Session = sessionmaker(bind=self.engine)
        self.session = self.Session()

        # Mock config for the pipeline
        self.config = {
            'symbol': 'BTC-USD',
            'interval': '1d',
            'lookback_days': 30,
            'threshold': 0.01
        }
        self.pipeline = MLTradingPipeline(self.config)

    def tearDown(self):
        """Clean up the database session after each test."""
        self.session.close()
        Base.metadata.drop_all(self.engine)

    @patch('yfinance.download')
    def test_fetch_data_success(self, mock_yf_download):
        """Test that fetch_data successfully returns a DataFrame on valid API response."""
        # Arrange: Configure the mock to return a sample DataFrame
        sample_data = {
            'Open': [100, 102, 101],
            'High': [103, 104, 102],
            'Low': [99, 101, 100],
            'Close': [102, 103, 101],
            'Volume': [1000, 1100, 900]
        }
        mock_df = pd.DataFrame(sample_data)
        mock_yf_download.return_value = mock_df

        # Act: Call the function we are testing
        result_df = self.pipeline.fetch_data()

        # Assert: Check that the function behaved as expected
        self.assertIsNotNone(result_df)
        self.assertIsInstance(result_df, pd.DataFrame)
        self.assertFalse(result_df.empty)
        pd.testing.assert_frame_equal(result_df, mock_df)
        mock_yf_download.assert_called_once_with(
            self.config['symbol'], 
            start=unittest.mock.ANY, 
            end=unittest.mock.ANY, 
            interval=self.config['interval']
        )

    @patch('yfinance.download')
    def test_fetch_data_failure(self, mock_yf_download):
        """Test that fetch_data returns None when the API returns an empty DataFrame."""
        # Arrange: Configure the mock to return an empty DataFrame
        mock_yf_download.return_value = pd.DataFrame()

        # Act: Call the function
        result_df = self.pipeline.fetch_data()

        # Assert: Check that the result is None
        self.assertIsNone(result_df)

if __name__ == '__main__':
    unittest.main()
