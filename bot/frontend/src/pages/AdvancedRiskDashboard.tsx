import React, { useState, useEffect } from 'react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { Card, CardContent, Typography, Grid, Box, CircularProgress, Paper, Tabs, Tab, 
         Table, TableBody, TableCell, TableContainer, TableHead, TableRow, 
         LinearProgress, Chip } from '@mui/material';

interface RiskMetrics {
  riskLevel: number;
  volatility: number;
  drawdown: number;
  confidence: number;
  strategyPerformance: {
    [key: string]: {
      exposure: number;
      returns: number;
      risk: number;
    };
  };
  portfolioMetrics: {
    sharpeRatio: number;
    sortinoRatio: number;
    maxDrawdown: number;
    volatility: number;
  };
  marketConditions: {
    regime: string;
    volatility: number;
    trend: string;
    volume: number;
  };
  backtestResults: {
    performance: any;
    riskMetrics: any;
    strategyPerformance: any;
    trades: any[];
    metrics: any;
  };
}

const AdvancedRiskDashboard: React.FC = () => {
  const [metrics, setMetrics] = useState<RiskMetrics | null>(null);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState(0);

  const handleChangeTab = (_: React.SyntheticEvent, newValue: number) => {
    setTab(newValue);
  };

  useEffect(() => {
    fetchMetrics();
  }, []);

  const fetchMetrics = async () => {
    try {
      const response = await fetch('/api/risk/metrics');
      const data = await response.json();
      setMetrics(data);
    } catch (error) {
      console.error('Error fetching metrics:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="50vh">
        <CircularProgress />
      </Box>
    );
  }

  if (!metrics) {
    return <div>Error loading metrics</div>;
  }

  const formatPercentage = (value: number) => `${(value * 100).toFixed(2)}%`;

  return (
    <Grid container spacing={3} p={3}>
      {/* Main Dashboard Header */}
      <Grid item xs={12}>
        <Paper elevation={3}>
          <Tabs value={tab} onChange={handleChangeTab} centered>
            <Tab label="Risk Overview" />
            <Tab label="Portfolio Analysis" />
            <Tab label="Market Conditions" />
            <Tab label="Backtest Results" />
          </Tabs>
        </Paper>
      </Grid>

      {/* Risk Overview Tab */}
      <Grid item xs={12}>
        {tab === 0 && (
          <Grid container spacing={3}>
            {/* Risk Level Card */}
            <Grid item xs={12} md={4}>
              <Card>
                <CardContent>
                  <Typography variant="h6" gutterBottom>
                    Current Risk Level
                  </Typography>
                  <Box display="flex" justifyContent="center" alignItems="center" height={150}>
                    <Typography variant="h2" color={metrics.riskLevel > 0.7 ? 'error' : metrics.riskLevel > 0.5 ? 'warning' : 'success'}>
                      {formatPercentage(metrics.riskLevel)}
                    </Typography>
                  </Box>
                  <Typography variant="body2" color="text.secondary">
                    Confidence: {formatPercentage(metrics.confidence)}
                  </Typography>
                </CardContent>
              </Card>
            </Grid>

            {/* Portfolio Metrics Card */}
            <Grid item xs={12} md={8}>
              <Card>
                <CardContent>
                  <Typography variant="h6" gutterBottom>
                    Portfolio Metrics
                  </Typography>
                  <Grid container spacing={2}>
                    <Grid item xs={6}>
                      <Typography variant="body1">
                        Sharpe Ratio: {metrics.portfolioMetrics.sharpeRatio.toFixed(2)}
                      </Typography>
                    </Grid>
                    <Grid item xs={6}>
                      <Typography variant="body1">
                        Sortino Ratio: {metrics.portfolioMetrics.sortinoRatio.toFixed(2)}
                      </Typography>
                    </Grid>
                    <Grid item xs={6}>
                      <Typography variant="body1">
                        Max Drawdown: {formatPercentage(metrics.portfolioMetrics.maxDrawdown)}
                      </Typography>
                    </Grid>
                    <Grid item xs={6}>
                      <Typography variant="body1">
                        Volatility: {formatPercentage(metrics.portfolioMetrics.volatility)}
                      </Typography>
                    </Grid>
                  </Grid>
                </CardContent>
              </Card>
            </Grid>

            {/* Strategy Performance Chart */}
            <Grid item xs={12}>
              <Card>
                <CardContent>
                  <Typography variant="h6" gutterBottom>
                    Strategy Performance
                  </Typography>
                  <ResponsiveContainer width="100%" height={300}>
                    <LineChart data={Object.entries(metrics.strategyPerformance).map(
                      ([strategy, data]) => ({
                        strategy,
                        exposure: data.exposure,
                        returns: data.returns,
                        risk: data.risk,
                      })
                    )}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="strategy" />
                      <YAxis />
                      <Tooltip />
                      <Legend />
                      <Line type="monotone" dataKey="exposure" name="Exposure" stroke="#8884d8" />
                      <Line type="monotone" dataKey="returns" name="Returns" stroke="#82ca9d" />
                      <Line type="monotone" dataKey="risk" name="Risk" stroke="#ffc658" />
                    </LineChart>
                  </ResponsiveContainer>
                </CardContent>
              </Card>
            </Grid>
          </Grid>
        )}

        {/* Portfolio Analysis Tab */}
        {tab === 1 && (
          <Grid container spacing={2}>
            {/* Portfolio Composition */}
            <Grid item xs={12} md={6}>
              <Card>
                <CardContent>
                  <Typography variant="h6" gutterBottom>
                    Portfolio Composition
                  </Typography>
                  <TableContainer>
                    <Table>
                      <TableHead>
                        <TableRow>
                          <TableCell>Strategy</TableCell>
                          <TableCell>Weight</TableCell>
                          <TableCell>Performance</TableCell>
                          <TableCell>Risk Level</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {Object.entries(metrics.strategyPerformance).map(([strategy, data]) => (
                          <TableRow key={strategy}>
                            <TableCell>{strategy}</TableCell>
                            <TableCell>{formatPercentage(data.exposure)}</TableCell>
                            <TableCell>{formatPercentage(data.returns)}</TableCell>
                            <TableCell>
                              <Chip
                                label={formatPercentage(data.risk)}
                                color={data.risk > 0.7 ? 'error' : data.risk > 0.5 ? 'warning' : 'success'}
                              />
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </TableContainer>
                </CardContent>
              </Card>
            </Grid>

            {/* Risk Heatmap */}
            <Grid item xs={12} md={6}>
              <Card>
                <CardContent>
                  <Typography variant="h6" gutterBottom>
                    Risk Heatmap
                  </Typography>
                  <Grid container spacing={2}>
                    {Object.entries(metrics.strategyPerformance).map(([strategy, data]) => (
                      <Grid item xs={6} md={3} key={strategy}>
                        <Box
                          sx={{
                            p: 2,
                            bgcolor: `rgba(255, 0, 0, ${data.risk})`,
                            borderRadius: 2,
                            height: 100,
                            display: 'flex',
                            flexDirection: 'column',
                            justifyContent: 'space-between',
                          }}
                        >
                          <Typography variant="h6" color="white">
                            {strategy}
                          </Typography>
                          <Typography variant="body2" color="white">
                            Risk: {formatPercentage(data.risk)}
                          </Typography>
                        </Box>
                      </Grid>
                    ))}
                  </Grid>
                </CardContent>
              </Card>
            </Grid>
          </Grid>
        )}

        {/* Market Conditions Tab */}
        {tab === 2 && (
          <Grid container spacing={2}>
            <Grid item xs={12}>
              <Card>
                <CardContent>
                  <Typography variant="h6" gutterBottom>
                    Current Market Conditions
                  </Typography>
                  <Grid container spacing={2}>
                    <Grid item xs={6}>
                      <Typography variant="h6" gutterBottom>
                        Market Regime
                      </Typography>
                      <Chip
                        label={metrics.marketConditions.regime}
                        color={metrics.marketConditions.regime === 'volatile' ? 'error' : 
                              metrics.marketConditions.regime === 'trending' ? 'success' : 
                              'warning'}
                      />
                    </Grid>
                    <Grid item xs={6}>
                      <Typography variant="h6" gutterBottom>
                        Volatility
                      </Typography>
                      <LinearProgress
                        variant="determinate"
                        value={metrics.marketConditions.volatility * 100}
                        color={metrics.marketConditions.volatility > 0.7 ? 'error' : 
                              metrics.marketConditions.volatility > 0.5 ? 'warning' : 'primary'}
                      />
                    </Grid>
                  </Grid>
                </CardContent>
              </Card>
            </Grid>
          </Grid>
        )}

        {/* Backtest Results Tab */}
        {tab === 3 && (
          <Grid container spacing={3}>
            <Grid item xs={12}>
              <Card>
                <CardContent>
                  <Typography variant="h6" gutterBottom>
                    Backtest Performance
                  </Typography>
                  <TableContainer>
                    <Table>
                      <TableHead>
                        <TableRow>
                          <TableCell>Metric</TableCell>
                          <TableCell>Value</TableCell>
                          <TableCell>Rank</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        <TableRow>
                          <TableCell>Annual Return</TableCell>
                          <TableCell>{formatPercentage(metrics.backtestResults.metrics.annualized_return)}</TableCell>
                          <TableCell>{metrics.backtestResults.metrics.annualized_return > 0.2 ? 'Excellent' : 
                                    metrics.backtestResults.metrics.annualized_return > 0.1 ? 'Good' : 'Fair'}</TableCell>
                        </TableRow>
                        <TableRow>
                          <TableCell>Sharpe Ratio</TableCell>
                          <TableCell>{metrics.backtestResults.metrics.sharpe_ratio.toFixed(2)}</TableCell>
                          <TableCell>{metrics.backtestResults.metrics.sharpe_ratio > 2 ? 'Excellent' : 
                                    metrics.backtestResults.metrics.sharpe_ratio > 1 ? 'Good' : 'Fair'}</TableCell>
                        </TableRow>
                        <TableRow>
                          <TableCell>Max Drawdown</TableCell>
                          <TableCell>{formatPercentage(metrics.backtestResults.metrics.max_drawdown)}</TableCell>
                          <TableCell>{metrics.backtestResults.metrics.max_drawdown < 0.2 ? 'Excellent' : 
                                    metrics.backtestResults.metrics.max_drawdown < 0.3 ? 'Good' : 'Fair'}</TableCell>
                        </TableRow>
                      </TableBody>
                    </Table>
                  </TableContainer>
                </CardContent>
              </Card>
            </Grid>
          </Grid>
        )}
      </Grid>
    </Grid>
  );
};

export default AdvancedRiskDashboard;
