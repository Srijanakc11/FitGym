ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_success_mail_sent_at DATETIME NULL AFTER payment_verified_at;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS expiry_reminder_mail_sent_at DATETIME NULL AFTER payment_success_mail_sent_at;

INSERT INTO settings (setting_key, setting_value) VALUES
('mail_smtp_host', 'smtp.gmail.com'),
('mail_smtp_port', '587'),
('mail_smtp_secure', 'tls'),
('mail_smtp_username', 'srijanaseru@gmail.com'),
('mail_from_email', 'srijanaseru@gmail.com'),
('mail_from_name', 'FitGym'),
('mail_reply_to_email', 'srijanaseru@gmail.com'),
('mail_admin_notification_email', 'srijanaseru@gmail.com'),
('mail_test_recipient', '')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
