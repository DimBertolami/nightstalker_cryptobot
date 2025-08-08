import React, { useState, useEffect } from 'react';
import {
  LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
  ComposedChart, ReferenceLine, Label, Brush, Scatter
} from 'recharts';
import { ChevronDown, ChevronUp } from 'lucide-react';

interface PriceData {
  date: string;
  price: number;
  volume: number;
  sma20: number;
  sma50: number;
  ema12: number;
  ema26: number;
  upperBand: number;
  middleBand: number;
  lowerBand: number;
  macd: number;
  signal: number;
  histogram: number;
  rsi: number;
  buySignal?: boolean;
  sellSignal?: boolean;
}

interface TechnicalAnalysisChartProps {
  symbol?: string;
  timeframe?: string;
  cryptoOptions?: { name: string; symbol: string; imageUrl: string }[];
  onSymbolChange?: (symbol: string) => void;
}

// Rate limiting cache to prevent excessive API calls
const requestCache: Record<string, { data: PriceData[], timestamp: number }> = {};

// Function to get cache duration based on timeframe
const getCacheDuration = (timeframe: string): number => {
  // Convert timeframe to milliseconds
  switch(timeframe.toLowerCase()) {
    case '1m': return 60 * 1000; // 1 minute
    case '5m': return 5 * 60 * 1000; // 5 minutes
    case '10m': return 10 * 60 * 1000; // 10 minutes
    case '30m': return 30 * 60 * 1000; // 30 minutes
    case '1h': return 60 * 60 * 1000; // 1 hour
    case '1d': return 24 * 60 * 60 * 1000; // 1 day
    default: return 5 * 60 * 1000; // Default to 5 minutes
  }
};

const TechnicalAnalysisChart: React.FC<TechnicalAnalysisChartProps> = ({ 
  symbol = 'BTCUSDT', 
  timeframe = '1D',
  cryptoOptions = [],
  onSymbolChange = () => {}
}) => {
  const [data, setData] = useState<PriceData[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedIndicators, setSelectedIndicators] = useState<string[]>(
    symbol === 'BTCUSDT' && timeframe === '1m' 
      ? ['price', 'sma', 'ema', 'bollinger', 'macd', 'signal', 'rsi', 'tradingSignals']
      : ['price', 'sma', 'ema', 'bollinger', 'macd', 'signal', 'rsi', 'tradingSignals']
  );
  const [indicatorMenuOpen, setIndicatorMenuOpen] = useState<boolean>(false);
  const [isVisible, setIsVisible] = useState<boolean>(true);
  
  // Define all available indicators
  const availableIndicators = [
    { id: 'price', name: 'Price', color: '#8884d8' },
    { id: 'sma20', name: 'SMA (20)', color: '#82ca9d' },
    { id: 'sma50', name: 'SMA (50)', color: '#ffc658' },
    { id: 'ema12', name: 'EMA (12)', color: '#ff8042' },
    { id: 'ema26', name: 'EMA (26)', color: '#0088fe' },
    { id: 'upperBand', name: 'Bollinger Upper', color: '#ff0000', dashed: true },
    { id: 'middleBand', name: 'Bollinger Middle', color: '#00ff00', dashed: true },
    { id: 'lowerBand', name: 'Bollinger Lower', color: '#0000ff', dashed: true },
    { id: 'macd', name: 'MACD Line', color: '#ff00ff' },
    { id: 'signal', name: 'Signal Line', color: '#00ffff' },
    { id: 'rsi', name: 'RSI', color: '#ffff00' },
    { id: 'tradingSignals', name: 'Trading Signals', color: '#22c55e' }
  ];

  // Fetch price data and calculate technical indicators with rate limiting
  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        setError(null);
        
        // Create a cache key based on symbol and timeframe
        const cacheKey = `${symbol}-${timeframe}`;
        const now = Date.now();
        
        // Get cache duration based on selected timeframe
        const cacheDuration = getCacheDuration(timeframe);
        
        // Check if we have cached data that's still valid
        if (requestCache[cacheKey] && (now - requestCache[cacheKey].timestamp) < cacheDuration) {
          console.log(`Using cached data for ${cacheKey}`);
          setData(requestCache[cacheKey].data);
          setLoading(false);
          return;
        }

        console.log('Fetching fresh data for', symbol, timeframe);
        
        // Generate mock data for demonstration
        const mockData = generateMockData(symbol, timeframe);
        
        // Cache the data
        requestCache[cacheKey] = {
          data: mockData,
          timestamp: now
        };
        
        setData(mockData);
        setLoading(false);
      } catch (error) {
        console.error('Error fetching data:', error);
        setError('Failed to fetch data. Please try again.');
        setLoading(false);
      }
    };

    fetchData();
  }, [symbol, timeframe]);

  // Generate mock data for demonstration
  const generateMockData = (symbol: string, timeframe: string): PriceData[] => {
    const data: PriceData[] = [];
    const now = new Date();
    let basePrice = symbol.includes('BTC') ? 65000 : symbol.includes('ETH') ? 3500 : 500;
    let trend = 0.5;
    
    // Adjust data points based on timeframe
    const dataPoints = timeframe === '1D' ? 24 : 
                       timeframe === '1W' ? 7 * 12 : 
                       timeframe === '1M' ? 30 : 50;
    
    // Pre-calculate some values for technical indicators
    let macdLine = 0;
    let signalLine = 0;
    let rsiValue = 50;
    
    for (let i = 0; i < dataPoints; i++) {
      // Create realistic price movement
      trend += (Math.random() - 0.5) * 0.2;
      if (trend > 0.8) trend = 0.8;
      if (trend < 0.2) trend = 0.2;
      
      const change = (Math.random() - 0.5) * basePrice * 0.02 * trend;
      basePrice += change;
      
      // Calculate technical indicators
      const date = new Date(now.getTime() - (dataPoints - i) * 3600000);
      const volume = Math.random() * basePrice * 10;
      
      // Calculate moving averages
      const sma20 = basePrice * (1 + (Math.random() - 0.5) * 0.01);
      const sma50 = basePrice * (1 + (Math.random() - 0.5) * 0.02);
      const ema12 = basePrice * (1 + (Math.random() - 0.5) * 0.015);
      const ema26 = basePrice * (1 + (Math.random() - 0.5) * 0.025);
      
      // Calculate MACD
      macdLine = macdLine * 0.8 + (ema12 - ema26) * 0.2;
      signalLine = signalLine * 0.9 + macdLine * 0.1;
      const histogram = macdLine - signalLine;
      
      // Calculate RSI
      const rsiChange = (Math.random() - 0.5) * 5;
      rsiValue += rsiChange;
      if (rsiValue > 95) rsiValue = 95;
      if (rsiValue < 5) rsiValue = 5;
      
      // Generate trading signals
      let buySignal = false;
      let sellSignal = false;
      
      // Add random signals for demonstration
      if (Math.random() < 0.05) {
        if (Math.random() < 0.5) {
          buySignal = true;
        } else {
          sellSignal = true;
        }
      }
      
      data.push({
        date: date.toISOString(),
        price: basePrice,
        volume: volume,
        sma20: sma20,
        sma50: sma50,
        ema12: ema12,
        ema26: ema26,
        upperBand: basePrice * 1.02,
        middleBand: basePrice,
        lowerBand: basePrice * 0.98,
        macd: macdLine,
        signal: signalLine,
        histogram: histogram,
        rsi: rsiValue,
        buySignal: buySignal,
        sellSignal: sellSignal
      });
    }
    
    return data;
  };

  const toggleIndicator = (indicatorId: string) => {
    if (selectedIndicators.includes(indicatorId)) {
      // If price is being removed, make sure at least one indicator remains
      if (indicatorId === 'price' && selectedIndicators.length === 1) {
        return;
      }
      setSelectedIndicators(selectedIndicators.filter(id => id !== indicatorId));
    } else {
      setSelectedIndicators([...selectedIndicators, indicatorId]);
    }
  };

  const toggleIndicatorMenu = () => {
    setIndicatorMenuOpen(!indicatorMenuOpen);
  };

  if (error) {
    return (
      <div className="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg text-center">
        <h3 className="text-red-600 dark:text-red-400 font-medium mb-2">Error</h3>
        <p className="text-gray-700 dark:text-gray-300 text-sm">
          {error}
          <button 
            onClick={() => window.location.reload()}
            className="ml-2 underline text-blue-600 dark:text-blue-400"
          >
            Reload
          </button>
        </p>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[300px]">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900 dark:border-gray-100"></div>
      </div>
    );
  }

  if (!data.length) {
    return (
      <div className="p-4 text-center text-gray-600 dark:text-gray-400">
        No data available
      </div>
    );
  }

  if (!isVisible) {
    return (
      <div className="technical-analysis-chart bg-gray-50 dark:bg-gray-800 rounded-xl p-6 shadow-md">
        <div className="flex justify-between items-center">
          <h3 className="text-xl font-semibold text-gray-900 dark:text-white">
            {symbol} Technical Analysis
          </h3>
          <button 
            onClick={() => setIsVisible(true)}
            className="theme-button px-4 py-2 rounded-lg flex items-center gap-2"
          >
            Show Technical Analysis
          </button>
        </div>
      </div>
    );
  }

  // Determine if we should show the RSI in a separate chart
  const showRSI = selectedIndicators.includes('rsi');
  
  // Determine if we should show the MACD in a separate chart
  const showMACD = selectedIndicators.includes('macd') || 
                   selectedIndicators.includes('signal') || 
                   selectedIndicators.includes('histogram');

  // Display a loading overlay when refreshing data but we already have data shown
  const LoadingOverlay = () => (
    loading && data.length > 0 ? (
      <div className="absolute inset-0 bg-black/10 dark:bg-black/20 flex items-center justify-center backdrop-blur-[1px] rounded-xl z-10">
        <div className="bg-white dark:bg-gray-800 rounded-lg p-3 shadow-lg">
          <div className="flex items-center space-x-3">
            <div className="animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-indigo-500"></div>
            <p className="text-gray-700 dark:text-gray-300 text-sm font-medium">
              Refreshing {symbol} data...
            </p>
          </div>
        </div>
      </div>
    ) : null
  );

  return (
    <div className="technical-analysis-chart bg-gray-50 dark:bg-gray-800 rounded-xl p-6 shadow-md relative">
      {/* Show loading overlay when refreshing data */}
      <LoadingOverlay />
      
      <div className="chart-header flex justify-between items-center mb-6">
        <h3 className="text-xl font-semibold text-gray-900 dark:text-white">
          {symbol} Technical Analysis <span className="text-purple-600 dark:text-purple-400 font-bold">{timeframe}</span>
        </h3>
        
        <div className="indicator-selector flex items-center gap-2">
          <button 
            onClick={() => setIsVisible(false)}
            className="theme-button-secondary px-4 py-2 rounded-lg"
          >
            Hide Chart
          </button>
          
          {/* Crypto dropdown selector */}
          {cryptoOptions.length > 0 && (
            <div className="relative">
              <select
                value={symbol}
                onChange={(e) => {
                  const newSymbol = e.target.value;
                  onSymbolChange(newSymbol);
                }}
                className="appearance-none bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg pl-10 pr-10 py-2 cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500 text-gray-900 dark:text-white"
              >
                {cryptoOptions.map((crypto) => (
                  <option key={crypto.symbol} value={crypto.symbol}>
                    {crypto.name} ({crypto.symbol})
                  </option>
                ))}
              </select>
              <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                {cryptoOptions.find(c => c.symbol === symbol)?.imageUrl && (
                  <img 
                    src={cryptoOptions.find(c => c.symbol === symbol)?.imageUrl} 
                    alt={symbol}
                    className="w-5 h-5 rounded-full"
                  />
                )}
              </div>
              <div className="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path>
                </svg>
              </div>
            </div>
          )}
          
          <button 
            className="indicator-menu-button theme-button-secondary px-4 py-2 rounded-lg flex items-center gap-2"
            onClick={toggleIndicatorMenu}
          >
            Indicators
            {indicatorMenuOpen ? <ChevronUp size={16} /> : <ChevronDown size={16} />}
          </button>
          
          {indicatorMenuOpen && (
            <div className="indicator-dropdown theme-card rounded-lg p-3 shadow-lg absolute z-10 mt-1 right-0 w-64">
              <div className="grid grid-cols-1 gap-2">
                {availableIndicators.map(indicator => (
                  <label key={indicator.id} className="flex items-center gap-2 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={selectedIndicators.includes(indicator.id)}
                      onChange={() => toggleIndicator(indicator.id)}
                      className="form-checkbox h-4 w-4 text-indigo-600"
                    />
                    <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                      {indicator.name}
                    </span>
                    <span 
                      className="w-4 h-4 rounded-full ml-auto"
                      style={{ backgroundColor: indicator.color }}
                    ></span>
                  </label>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
      
      <div className="chart-container bg-white dark:bg-gray-900 p-4 rounded-lg shadow-inner">
        <div className="chart-description mb-4 text-sm text-gray-600 dark:text-gray-400">
          Select indicators from the dropdown to customize your view. Price data is always shown in <span className="text-purple-600 dark:text-purple-400 font-bold">dark purple</span>.  
        </div>
        {/* Main price chart with selected indicators */}
        <ResponsiveContainer width="100%" height={400}>
          <ComposedChart
            data={data}
            margin={{ top: 10, right: 30, left: 20, bottom: 5 }}
          >
            <CartesianGrid strokeDasharray="3 3" opacity={0.2} />
            <XAxis 
              dataKey="date" 
              tick={{ fill: '#888' }}
              tickFormatter={(value) => value.split('T')[0]}
            />
            <YAxis 
              yAxisId="price"
              domain={['auto', 'auto']}
              tick={{ fill: '#888' }}
              tickFormatter={(value) => value.toLocaleString()}
            />
            <Tooltip
              contentStyle={{ 
                backgroundColor: 'rgba(23, 23, 23, 0.9)', 
                border: '1px solid #666',
                borderRadius: '4px',
                color: '#fff',
                padding: '8px 12px'
              }}
              labelStyle={{
                color: '#fff'
              }}
              cursor={{
                stroke: '#666',
                strokeWidth: 1
              }}
              content={(props) => {
                const { active, payload, label } = props;
                if (!active || !payload) return null;
                return (
                  <div>
                    <p>{label}</p>
                    {payload.map((item: { name: string; value: number }) => (
                      <p key={item.name}>{item.name}: {item.value}</p>
                    ))}
                  </div>
                );
              }}
            />
            <Legend 
              verticalAlign="top"
              height={36}
              formatter={(value: string) => {
                const indicator = availableIndicators.find(ind => ind.id === value);
                return indicator?.name || value;
              }}
            />
            <Brush dataKey="date" height={30} stroke="#8884d8" />
            
            {/* Price line - always show if selected */}
            {selectedIndicators.includes('price') && (
              <Line
                type="monotone"
                dataKey="price"
                stroke="#6b46c1"
                strokeWidth={3}
                dot={false}
                yAxisId="price"
                name="price"
              />
            )}
            
            {/* Enhanced Trading Signals */}
            {selectedIndicators.includes('tradingSignals') && (
              <>
                {/* Buy Signals */}
                <Scatter
                  name="Buy Signals"
                  dataKey="price"
                  data={data.filter(d => d.buySignal)}
                  fill="#22c55e"
                  shape="square"
                  stroke="#22c55e"
                  strokeWidth={2}
                  yAxisId="price"
                >
                  {data.filter(d => d.buySignal).map((_, index) => (
                    <circle key={index} r={4} />
                  ))}
                </Scatter>

                {/* Sell Signals */}
                <Scatter
                  name="Sell Signals"
                  dataKey="price"
                  data={data.filter(d => d.sellSignal)}
                  fill="#ef4444"
                  shape="square"
                  stroke="#ef4444"
                  strokeWidth={2}
                  yAxisId="price"
                >
                  {data.filter(d => d.sellSignal).map((_, index) => (
                    <circle key={index} r={4} />
                  ))}
                </Scatter>

                {/* Enhanced Signal Labels */}
                {data.map((entry, index) => (
                  entry.buySignal && (
                    <Label
                      key={`buy-label-${index}`}
                      value="BUY"
                      position="insideBottom"
                      x={index}
                      y={entry.price}
                      fill="#22c55e"
                      fontSize={12}
                      fontWeight="bold"
                      offset={10}
                    />
                  )
                ))}
                {data.map((entry, index) => (
                  entry.sellSignal && (
                    <Label
                      key={`sell-label-${index}`}
                      value="SELL"
                      position="insideBottom"
                      x={index}
                      y={entry.price}
                      fill="#ef4444"
                      fontSize={12}
                      fontWeight="bold"
                      offset={10}
                    />
                  )
                ))}
              </>
            )}
            
            {/* SMA lines */}
            {selectedIndicators.includes('sma20') && (
              <Line
                type="monotone"
                dataKey="sma20"
                stroke="#82ca9d"
                strokeWidth={1.5}
                dot={false}
                yAxisId="price"
                name="sma20"
              />
            )}
            
            {selectedIndicators.includes('sma50') && (
              <Line
                type="monotone"
                dataKey="sma50"
                stroke="#ffc658"
                strokeWidth={1.5}
                dot={false}
                yAxisId="price"
                name="sma50"
              />
            )}
            
            {/* EMA lines */}
            {selectedIndicators.includes('ema12') && (
              <Line
                type="monotone"
                dataKey="ema12"
                stroke="#ff8042"
                strokeWidth={1.5}
                dot={false}
                yAxisId="price"
                name="ema12"
              />
            )}
            
            {selectedIndicators.includes('ema26') && (
              <Line
                type="monotone"
                dataKey="ema26"
                stroke="#0088fe"
                strokeWidth={1.5}
                dot={false}
                yAxisId="price"
                name="ema26"
              />
            )}
            
            {/* Bollinger Bands */}
            {selectedIndicators.includes('upperBand') && (
              <Line
                type="monotone"
                dataKey="upperBand"
                stroke="#ff0000"
                strokeDasharray="3 3"
                strokeWidth={1.5}
                dot={false}
                yAxisId="price"
                name="upperBand"
              />
            )}
            
            {selectedIndicators.includes('middleBand') && (
              <Line
                type="monotone"
                dataKey="middleBand"
                stroke="#00ff00"
                strokeDasharray="3 3"
                strokeWidth={1.5}
                dot={false}
                yAxisId="price"
                name="middleBand"
              />
            )}
            
            {selectedIndicators.includes('lowerBand') && (
              <Line
                type="monotone"
                dataKey="lowerBand"
                stroke="#0000ff"
                strokeDasharray="3 3"
                strokeWidth={1.5}
                dot={false}
                yAxisId="price"
                name="lowerBand"
              />
            )}
          </ComposedChart>
        </ResponsiveContainer>
        
        {/* MACD Chart */}
        {showMACD && (
          <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">MACD Indicator</h4>
          <ResponsiveContainer width="100%" height={150}>
            <ComposedChart
              data={data}
              margin={{ top: 10, right: 30, left: 20, bottom: 5 }}
            >
              <CartesianGrid strokeDasharray="3 3" opacity={0.1} />
              <XAxis 
                dataKey="date" 
                tick={{ fill: '#888' }}
                tickFormatter={(value) => value.split('T')[0]}
              />
              <YAxis 
                yAxisId="macd"
                domain={['auto', 'auto']}
                tick={{ fill: '#888' }}
              />
              <Tooltip
                contentStyle={{ 
                  backgroundColor: 'rgba(23, 23, 23, 0.9)', 
                  border: 'none',
                  borderRadius: '8px',
                  color: '#fff',
                  padding: '10px'
                }}
                labelStyle={{ color: '#aaa', marginBottom: '5px' }}
              />
              <Legend verticalAlign="top" height={36} />
              <ReferenceLine y={0} stroke="#666" strokeDasharray="3 3" yAxisId="macd" />
              
              {selectedIndicators.includes('macd') && (
                <Line
                  type="monotone"
                  dataKey="macd"
                  stroke="#ff00ff"
                  dot={false}
                  yAxisId="macd"
                  name="MACD Line"
                />
              )}
              
              {selectedIndicators.includes('signal') && (
                <Line
                  type="monotone"
                  dataKey="signal"
                  stroke="#00ffff"
                  dot={false}
                  yAxisId="macd"
                  name="Signal Line"
                />
              )}
              
              {/* Simplified histogram rendering to prevent browser crashes */}
              {selectedIndicators.includes('histogram') && (
                <Line
                  type="monotone"
                  dataKey="histogram"
                  stroke="transparent"
                  yAxisId="macd"
                  name="Histogram"
                  dot={false}
                  activeDot={false}
                  isAnimationActive={false}
                />
              )}
            </ComposedChart>
          </ResponsiveContainer>
          </div>
        )}
        
        {/* RSI Chart */}
        {showRSI && (
          <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Relative Strength Index (RSI)</h4>
          <ResponsiveContainer width="100%" height={150}>
            <LineChart
              data={data}
              margin={{ top: 10, right: 30, left: 20, bottom: 5 }}
            >
              <CartesianGrid strokeDasharray="3 3" opacity={0.1} />
              <XAxis 
                dataKey="date" 
                tick={{ fill: '#888' }}
                tickFormatter={(value) => value.split('T')[0]}
              />
              <YAxis 
                domain={[0, 100]}
                tick={{ fill: '#888' }}
              />
              <Tooltip
                contentStyle={{ 
                  backgroundColor: 'rgba(23, 23, 23, 0.9)', 
                  border: 'none',
                  borderRadius: '8px',
                  color: '#fff',
                  padding: '10px'
                }}
                labelStyle={{ color: '#aaa', marginBottom: '5px' }}
              />
              <Legend verticalAlign="top" height={36} />
              <ReferenceLine y={70} stroke="#ff0000" strokeDasharray="3 3">
                <Label value="Overbought" position="right" fill="#ff0000" />
              </ReferenceLine>
              <ReferenceLine y={30} stroke="#00ff00" strokeDasharray="3 3">
                <Label value="Oversold" position="right" fill="#00ff00" />
              </ReferenceLine>
              
              <Line
                type="monotone"
                dataKey="rsi"
                stroke="#ffff00"
                dot={false}
                name="RSI"
              />
            </LineChart>
          </ResponsiveContainer>
          </div>
        )}
      </div>
    </div>
  );
};

// Error boundary to prevent chart errors from crashing the entire app
class ChartErrorBoundary extends React.Component<{ children: React.ReactNode }, { hasError: boolean }> {
  constructor(props: { children: React.ReactNode }) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError(_: Error) { // eslint-disable-line @typescript-eslint/no-unused-vars
    // Parameter is required by React's error boundary API but not used
    return { hasError: true };
  }

  componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
    console.error('Chart error:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg text-center">
          <h3 className="text-red-600 dark:text-red-400 font-medium mb-2">Chart Error</h3>
          <p className="text-gray-700 dark:text-gray-300 text-sm">
            There was an error rendering the technical analysis chart.
            <button 
              onClick={() => this.setState({ hasError: false })}
              className="ml-2 underline text-blue-600 dark:text-blue-400"
            >
              Try again
            </button>
          </p>
        </div>
      );
    }

    return this.props.children;
  }
}

const TechnicalAnalysisChartWithErrorBoundary = (props: TechnicalAnalysisChartProps) => (
  <ChartErrorBoundary>
    <TechnicalAnalysisChart {...props} />
  </ChartErrorBoundary>
);

export default TechnicalAnalysisChartWithErrorBoundary;
