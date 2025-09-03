-- SMTP Sendmail Test Tool Database Schema

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    body TEXT,
    status VARCHAR(50),
    error_message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS smtp_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    smtp_server VARCHAR(255) NOT NULL,
    smtp_port INT NOT NULL,
    result VARCHAR(50),
    details TEXT,
    tested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS imap_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    imap_server VARCHAR(255) NOT NULL,
    imap_port INT NOT NULL,
    result VARCHAR(50),
    details TEXT,
    tested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS port_scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    target_host VARCHAR(255) NOT NULL,
    port INT NOT NULL,
    status VARCHAR(50),
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
