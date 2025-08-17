-- Payment Schema Updates
-- Add missing columns for proper payment status tracking

-- Add payhere_payment_id column to payments table
ALTER TABLE `payments` 
ADD COLUMN `payhere_payment_id` varchar(100) DEFAULT NULL AFTER `transaction_id`;

-- Add updated_at column to payments table
ALTER TABLE `payments` 
ADD COLUMN `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() AFTER `created_at`;

-- Add payment_status column to legal_queries table if it doesn't exist
-- Note: This column already exists, so this line is commented out
-- ALTER TABLE `legal_queries` 
-- ADD COLUMN `payment_status` enum('pending','completed','failed') DEFAULT 'pending' AFTER `status`;

-- Update existing payments to have proper status
UPDATE `payments` SET `payment_status` = 'pending' WHERE `payment_status` IS NULL;

-- Add index for better performance
CREATE INDEX `idx_payments_transaction_id` ON `payments` (`transaction_id`);
CREATE INDEX `idx_payments_payhere_payment_id` ON `payments` (`payhere_payment_id`);
