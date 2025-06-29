SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS cryptocurrencies, price_history, trades, system_logs;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE cryptocurrencies (
  id varchar(50) PRIMARY KEY,
  name varchar(100) NOT NULL,
  symbol varchar(10) NOT NULL,
  created_at datetime NOT NULL,
  added_to_system datetime DEFAULT CURRENT_TIMESTAMP,
  age_hours int DEFAULT 0,
  market_cap decimal(30,2) DEFAULT 0,
  volume decimal(30,2) DEFAULT 0,
  price decimal(20,8) DEFAULT 0,
  price_change_24h decimal(10,2) DEFAULT 0,
  last_updated datetime DEFAULT NULL,
  is_trending tinyint(1) DEFAULT 0,
  volume_spike tinyint(1) DEFAULT 0,
  KEY idx_trending (is_trending),
  KEY idx_volume_spike (volume_spike)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE price_history (
  id int AUTO_INCREMENT PRIMARY KEY,
  coin_id varchar(50) NOT NULL,
  price decimal(20,8) NOT NULL,
  volume decimal(30,2) NOT NULL,
  market_cap decimal(30,2) NOT NULL,
  recorded_at datetime DEFAULT CURRENT_TIMESTAMP,
  KEY idx_coin_id (coin_id),
  KEY idx_recorded_at (recorded_at),
  CONSTRAINT fk_coin FOREIGN KEY (coin_id) REFERENCES cryptocurrencies (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE trades (
  id int AUTO_INCREMENT PRIMARY KEY,
  coin_id varchar(50) NOT NULL,
  trade_type enum('buy','sell') NOT NULL,
  amount decimal(20,8) NOT NULL,
  price decimal(20,8) NOT NULL,
  total_value decimal(30,2) NOT NULL,
  trade_time datetime DEFAULT CURRENT_TIMESTAMP,
  profit_loss decimal(30,2) DEFAULT NULL,
  KEY idx_coin_id (coin_id),
  KEY idx_trade_time (trade_time),
  CONSTRAINT fk_trade_coin FOREIGN KEY (coin_id) REFERENCES cryptocurrencies (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

