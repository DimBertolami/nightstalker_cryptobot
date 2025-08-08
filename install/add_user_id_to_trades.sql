-- Add user_id column to trades table
ALTER TABLE `trades` ADD COLUMN `user_id` INT NOT NULL DEFAULT 1 AFTER `id`;

-- Update existing trades to use default user ID 1
UPDATE `trades` SET `user_id` = 1 WHERE `user_id` = 0 OR `user_id` IS NULL;

-- Add index for better performance
ALTER TABLE `trades` ADD INDEX `idx_user_id` (`user_id`);
