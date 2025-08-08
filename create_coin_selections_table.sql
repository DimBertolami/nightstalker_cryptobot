
CREATE TABLE `CoinSelections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coin_id` int(11) NOT NULL,
  `symbol` varchar(255) NOT NULL,
  `predicted_score` float NOT NULL,
  `actual_outcome` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
