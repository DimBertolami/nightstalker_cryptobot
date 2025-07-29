import React from 'react';
import PriceChart from '../components/PriceChart';
import { useSelector, useDispatch } from 'react-redux';
import { setSymbol } from '../store/actions';
import { selectSymbol } from '../store/selectors';

const Dashboard = () => {
  const dispatch = useDispatch();
  const symbol = useSelector(selectSymbol);
  const [selectedSymbol, setSelectedSymbol] = React.useState('BTC-USD');

  React.useEffect(() => {
    dispatch(setSymbol(selectedSymbol));
  }, [dispatch, selectedSymbol]);

  return (
    <div className="dashboard-container">
      <div className="header">
        <h1>Crypto Trading Dashboard</h1>
        <div className="symbol-selector">
          <select 
            value={selectedSymbol} 
            onChange={(e) => setSelectedSymbol(e.target.value)}
          >
            <option value="BTC-USD">Bitcoin (BTC-USD)</option>
            <option value="ETH-USD">Ethereum (ETH-USD)</option>
            <option value="BNB-USD">Binance Coin (BNB-USD)</option>
            <option value="ADA-USD">Cardano (ADA-USD)</option>
            <option value="XRP-USD">Ripple (XRP-USD)</option>
            <option value="SOL-USD">Solana (SOL-USD)</option>
            <option value="DOT-USD">Polkadot (DOT-USD)</option>
            <option value="DOGE-USD">Dogecoin (DOGE-USD)</option>
            <option value="SHIB-USD">Shiba Inu (SHIB-USD)</option>
            <option value="AVAX-USD">Avalanche (AVAX-USD)</option>
          </select>
        </div>
      </div>

      <div className="dashboard">
        <div className="chart-section">
          <PriceChart />
        </div>
        
        <div className="metrics-section">
          <div className="metric-card">
            <h3>Current Price</h3>
            <div className="metric-value">$0.00</div>
          </div>
          
          <div className="metric-card">
            <h3>24h Change</h3>
            <div className="metric-value">0.00%</div>
          </div>
          
          <div className="metric-card">
            <h3>Market Cap</h3>
            <div className="metric-value">$0.00B</div>
          </div>
          
          <div className="metric-card">
            <h3>Volume (24h)</h3>
            <div className="metric-value">$0.00B</div>
          </div>
        </div>

        <div className="trading-section">
          <h2>Trading Position</h2>
          <div className="position-card">
            <div className="position-info">
              <div className="position-label">Current Position:</div>
              <div className="position-value">None</div>
            </div>
            <div className="position-info">
              <div className="position-label">Entry Price:</div>
              <div className="position-value">$0.00</div>
            </div>
            <div className="position-info">
              <div className="position-label">Profit/Loss:</div>
              <div className="position-value">0.00%</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
