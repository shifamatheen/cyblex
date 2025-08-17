-- Add missing columns for payment processing

-- Add payhere_payment_id column to payments table (if not exists)
ALTER TABLE `payments` 
ADD COLUMN IF NOT EXISTS `payhere_payment_id` varchar(100) DEFAULT NULL AFTER `transaction_id`;

-- Add updated_at column to payments table (if not exists)
ALTER TABLE `payments` 
ADD COLUMN IF NOT EXISTS `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() AFTER `created_at`;

-- Add indexes for better performance (if not exist)
CREATE INDEX IF NOT EXISTS `idx_payments_transaction_id` ON `payments` (`transaction_id`);
CREATE INDEX IF NOT EXISTS `idx_payments_payhere_payment_id` ON `payments` (`payhere_payment_id`);
