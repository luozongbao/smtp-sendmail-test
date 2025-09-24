
-- SMTP Sendmail Test Tool Database Schema
-- This schema includes all tables required for the application to function properly

-- Application settings table for configuration storage
CREATE TABLE IF NOT EXISTS app_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- Test logs table for storing all test results
CREATE TABLE IF NOT EXISTS test_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_type VARCHAR(50) NOT NULL,
    server_host VARCHAR(255) NOT NULL,
    server_port INT NOT NULL,
    security_type VARCHAR(50),
    username VARCHAR(255),
    test_result VARCHAR(20) NOT NULL,
    error_message TEXT,
    response_time INT,
    test_timestamp TIMESTAMP NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_test_type (test_type),
    INDEX idx_test_result (test_result),
    INDEX idx_test_timestamp (test_timestamp),
    INDEX idx_server_host (server_host)
);

-- Insert default application settings
INSERT INTO app_settings (setting_key, setting_value) VALUES 
('installation_completed', 'false'),
('app_version', '1.0.0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Insert installation_date only if it does not already exist
INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('installation_date', NOW());
