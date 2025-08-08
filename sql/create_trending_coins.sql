-- Create trending_coins table if it doesn't exist
CREATE TABLE IF NOT EXISTS `trending_coins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coin_id` int(11) NOT NULL,
  `symbol` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coin_id` (`coin_id`),
  KEY `symbol` (`symbol`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add some sample trending coins (optional)
INSERT IGNORE INTO `trending_coins` (`coin_id`, `symbol`) VALUES 
(1, 'BTC'),
(1027, 'ETH'),
(52, 'XRP'),
(2010, 'ADA'),
(1839, 'BNB');
