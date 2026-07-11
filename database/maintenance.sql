DELETE FROM api_nonces WHERE expires_at < NOW();
DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY);
DELETE FROM email_verifications WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY);
