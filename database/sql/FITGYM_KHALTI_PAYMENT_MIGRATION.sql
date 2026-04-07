ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_provider VARCHAR(30) NOT NULL DEFAULT 'cash' AFTER payment_method;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid' AFTER payment_provider;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_order_id VARCHAR(120) NULL AFTER payment_status;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_pidx VARCHAR(120) NULL AFTER payment_order_id;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_transaction_id VARCHAR(120) NULL AFTER payment_pidx;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_amount_paisa INT NULL AFTER payment_transaction_id;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_requested_at DATETIME NULL AFTER payment_amount_paisa;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_completed_at DATETIME NULL AFTER payment_requested_at;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_verified_at DATETIME NULL AFTER payment_completed_at;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_response_json LONGTEXT NULL AFTER payment_verified_at;

UPDATE bookings
SET payment_method = 'khalti'
WHERE payment_method = 'online';

UPDATE bookings
SET payment_provider = 'khalti'
WHERE COALESCE(payment_method, 'cash') = 'khalti'
  AND COALESCE(payment_provider, 'cash') <> 'khalti';

UPDATE bookings
SET payment_status = 'cancelled'
WHERE COALESCE(status, 'Pending') = 'Cancelled'
  AND COALESCE(payment_status, '') NOT IN ('cancelled', 'paid', 'refunded');

UPDATE bookings
SET payment_status = 'paid'
WHERE COALESCE(payment_method, 'cash') = 'khalti'
  AND COALESCE(payment_status, 'unpaid') = 'unpaid'
  AND payment_pidx IS NULL
  AND payment_transaction_id IS NULL
  AND COALESCE(status, 'Pending') <> 'Cancelled';

UPDATE bookings
SET payment_status = 'unpaid'
WHERE COALESCE(payment_method, 'cash') = 'cash'
  AND (payment_status IS NULL OR payment_status = '');

UPDATE bookings
SET payment_status = 'expired'
WHERE COALESCE(payment_provider, 'cash') = 'khalti'
  AND COALESCE(payment_status, 'unpaid') IN ('initiated', 'pending')
  AND preferred_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY);

UPDATE bookings
SET status = 'Confirmed'
WHERE COALESCE(payment_provider, 'cash') = 'khalti'
  AND COALESCE(payment_status, 'unpaid') = 'paid'
  AND preferred_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  AND COALESCE(status, 'Pending') NOT IN ('Confirmed', 'Expired');

UPDATE bookings
SET status = 'Pending'
WHERE COALESCE(payment_provider, 'cash') = 'khalti'
  AND COALESCE(payment_status, 'unpaid') IN ('initiated', 'pending')
  AND preferred_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  AND COALESCE(status, 'Pending') = 'Confirmed';

UPDATE bookings
SET status = 'Cancelled'
WHERE COALESCE(payment_provider, 'cash') = 'khalti'
  AND COALESCE(payment_status, 'unpaid') IN ('cancelled', 'expired', 'failed', 'refunded')
  AND COALESCE(status, 'Pending') <> 'Cancelled';

UPDATE bookings
SET status = 'Expired'
WHERE preferred_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  AND COALESCE(status, 'Pending') NOT IN ('Cancelled', 'Expired');

UPDATE bookings
SET payment_amount_paisa = 200000
WHERE payment_amount_paisa IS NULL;

INSERT INTO settings (setting_key, setting_value)
VALUES ('khalti_environment', 'sandbox')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
