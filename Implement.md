# SMTP Server Test Tool - Implementation Plan

## Project Overview
A web-based SMTP and IMAP server testing tool that allows users to test email server configurations and send test emails. The application will validate server connectivity, authentication, and email delivery functionality.

## Technology Stack
- **Backend**: PHP 8.x
- **Web Server**: Nginx
- **Database**: MySQL 8.x
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Email Libraries**: PHPMailer for SMTP, PHP IMAP extension for IMAP testing

## Project Structure
```
smtp-test-tool/
├── public/
│   ├── index.php
│   ├── install.php              # Installation wizard
│   ├── assets/
│   │   ├── css/
│   │   │   ├── style.css
│   │   │   └── install.css      # Installation form styles
│   │   ├── js/
│   │   │   ├── main.js
│   │   │   ├── validation.js
│   │   │   └── install.js       # Installation form logic
│   │   └── images/
│   └── api/
│       ├── test-smtp.php
│       ├── test-imap.php
│       ├── send-test-email.php
│       ├── port-scanner.php
│       └── install-check.php    # Installation validation
├── src/
│   ├── classes/
│   │   ├── SMTPTester.php
│   │   ├── IMAPTester.php
│   │   ├── PortScanner.php
│   │   ├── EmailValidator.php
│   │   └── Installer.php        # Installation helper class
│   ├── config/
│   │   ├── database.php
│   │   ├── constants.php
│   │   └── config.example.php   # Configuration template
│   └── utils/
│       ├── Logger.php
│       └── SecurityUtils.php
├── database/
│   ├── migrations/
│   │   └── create_test_logs.sql
│   └── schema.sql
├── logs/
├── vendor/
├── .env.example                 # Environment variables template
├── .env                        # Environment variables (created during install)
└── composer.json
```

## Core Features Implementation

### 1. SMTP Server Testing
**Components:**
- SMTP connection validator
- Authentication testing
- TLS/SSL support verification
- Port connectivity checker

**Implementation:**
```php
// Using PHPMailer for robust SMTP testing
class SMTPTester {
    public function testConnection($host, $port, $security, $username, $password)
    public function validateAuthentication()
    public function checkCapabilities()
}
```

### 2. IMAP Server Testing
**Components:**
- IMAP connection validator
- Authentication verification
- Mailbox access testing
- Security protocol validation

**Implementation:**
```php
// Using PHP IMAP extension
class IMAPTester {
    public function testConnection($host, $port, $security, $username, $password)
    public function listMailboxes()
    public function checkQuota()
}
```

### 3. Test Email Functionality
**Features:**
- Send test emails with custom content
- HTML and plain text support
- Attachment testing capability
- Delivery confirmation

### 4. Port Scanner & Protocol Verification
**Additional Tools:**
- Port availability checker
- Common email ports scanner (25, 465, 587, 993, 995)
- Protocol detection
- Response time measurement

## Database Schema

### test_logs Table
```sql
CREATE TABLE test_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    test_type ENUM('smtp', 'imap', 'email', 'port') NOT NULL,
    server_host VARCHAR(255) NOT NULL,
    server_port INT NOT NULL,
    security_type ENUM('none', 'ssl', 'tls', 'starttls') NOT NULL,
    username VARCHAR(255),
    test_result ENUM('success', 'failure', 'partial') NOT NULL,
    error_message TEXT,
    response_time INT, -- in milliseconds
    test_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45), -- Support IPv6
    user_agent TEXT
);
```

### email_templates Table
```sql
CREATE TABLE email_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html TEXT,
    body_plain TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Frontend Implementation

### Main Interface
- **Form Design**: Responsive Bootstrap-like design
- **Real-time Validation**: JavaScript form validation
- **Progress Indicators**: Loading states during testing
- **Results Display**: Color-coded success/failure indicators

### Key JavaScript Features
```javascript
// Real-time form validation
class EmailFormValidator {
    validateEmail(email)
    validatePort(port)
    validateHost(hostname)
}

// AJAX communication with backend
class APIClient {
    async testSMTP(config)
    async testIMAP(config)
    async sendTestEmail(emailData)
    async scanPorts(host, ports)
}
```

## Security Considerations

### Input Validation
- Sanitize all user inputs
- Validate email addresses, hostnames, and ports
- Prevent SMTP injection attacks
- Rate limiting for testing requests

### Credential Handling
- Never store user credentials in database
- Use secure session management
- Implement CSRF protection
- Encrypt sensitive data in transit

### Access Control
- IP-based rate limiting
- User session management
- Audit logging for all tests

## API Endpoints

### POST /api/test-smtp.php
```json
{
    "host": "smtp.gmail.com",
    "port": 587,
    "security": "tls",
    "username": "user@example.com",
    "password": "password"
}
```

### POST /api/test-imap.php
```json
{
    "host": "imap.gmail.com", 
    "port": 993,
    "security": "ssl",
    "username": "user@example.com",
    "password": "password"
}
```

### POST /api/send-test-email.php
```json
{
    "smtp_config": {...},
    "to": "recipient@example.com",
    "subject": "Test Email",
    "body": "Test message content",
    "format": "html"
}
```

### GET /api/port-scanner.php
```json
{
    "host": "mail.example.com",
    "ports": [25, 465, 587, 993, 995]
}
```

## Additional Features

### 1. Email Server Diagnostics
- DNS MX record lookup
- SPF record validation
- DKIM verification capability
- Server response analysis

### 2. Configuration Templates
- Pre-configured settings for popular email providers
- Gmail, Outlook, Yahoo, etc.
- Custom configuration saving

### 3. Batch Testing
- Test multiple server configurations
- Export results to CSV/JSON
- Scheduled testing capability

### 4. Monitoring Dashboard
- Real-time server status monitoring
- Historical test results
- Performance metrics

## Development Phases

### Phase 0: Installation Setup (Week 1)
- Create installation wizard
- Database configuration form
- Environment setup validation
- Initial database schema creation

### Phase 1: Core Functionality (Week 1-2)
- Basic SMTP/IMAP testing
- Simple web interface
- Database setup

### Phase 2: Enhanced Features (Week 3)
- Test email functionality
- Port scanner
- Improved UI/UX

### Phase 3: Advanced Tools (Week 4)
- DNS diagnostics
- Configuration templates
- Monitoring dashboard

### Phase 4: Polish & Security (Week 5)
- Security hardening
- Performance optimization
- Documentation

## Installation Process

### 1. Installation Wizard (`install.php`)

The installation form will collect the following information:

#### Database Configuration Form
```html
<form id="installation-form" method="POST" action="install.php">
    <div class="form-group">
        <label for="db_host">Database Host:</label>
        <input type="text" id="db_host" name="db_host" value="localhost" required>
        <small>Usually 'localhost' or IP address of your MySQL server</small>
    </div>
    
    <div class="form-group">
        <label for="db_port">Database Port:</label>
        <input type="number" id="db_port" name="db_port" value="3306" required>
        <small>Default MySQL port is 3306</small>
    </div>
    
    <div class="form-group">
        <label for="db_name">Database Name:</label>
        <input type="text" id="db_name" name="db_name" required>
        <small>Name of the database for this application</small>
    </div>
    
    <div class="form-group">
        <label for="db_user">Database Username:</label>
        <input type="text" id="db_user" name="db_user" required>
        <small>MySQL username with access to the database</small>
    </div>
    
    <div class="form-group">
        <label for="db_password">Database Password:</label>
        <input type="password" id="db_password" name="db_password">
        <small>MySQL user password</small>
    </div>
    
    <div class="form-group">
        <label for="admin_email">Admin Email:</label>
        <input type="email" id="admin_email" name="admin_email" required>
        <small>Email address for system notifications</small>
    </div>
    
    <div class="form-group">
        <label for="app_url">Application URL:</label>
        <input type="url" id="app_url" name="app_url" required>
        <small>Full URL where this application will be accessed</small>
    </div>
    
    <button type="submit" class="btn btn-primary">Test Connection & Install</button>
</form>
```

#### Installation Process Flow
1. **Form Validation**: Client-side validation for required fields
2. **Database Connection Test**: Verify database credentials
3. **Database Creation**: Create database if it doesn't exist
4. **Schema Installation**: Run SQL migrations
5. **Configuration File Creation**: Generate `.env` and config files
6. **Security Setup**: Generate application keys and secrets
7. **Final Verification**: Test all components

### 2. Installation Class (`src/classes/Installer.php`)
```php
<?php
class Installer {
    public function testDatabaseConnection($host, $port, $user, $password, $database)
    public function createDatabase($database)
    public function runMigrations()
    public function createConfigFiles($config)
    public function generateSecurityKeys()
    public function finalizeInstallation()
}
```

### 3. Environment Configuration (`.env`)
```env
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=smtp_test_tool
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Application Configuration
APP_NAME="SMTP Test Tool"
APP_URL=http://localhost/smtp-test-tool
APP_KEY=generated-during-install
ADMIN_EMAIL=admin@example.com

# Security
CSRF_SECRET=generated-during-install
SESSION_LIFETIME=120

# Logging
LOG_LEVEL=info
LOG_FILE=logs/application.log
```

### 4. Installation Validation Steps
- **PHP Version Check**: Ensure PHP 8.0+
- **Extension Check**: Verify required PHP extensions
- **File Permissions**: Check write permissions for logs and config
- **Database Connection**: Test MySQL connectivity
- **Database Privileges**: Verify CREATE, INSERT, UPDATE, DELETE permissions
- **Composer Dependencies**: Ensure vendor directory exists

### 5. Post-Installation Security
- Remove or rename `install.php` after successful installation
- Create `.htaccess` rules to protect sensitive directories
- Set proper file permissions (644 for files, 755 for directories)
- Generate secure session configuration

## Installation Requirements

### Server Requirements
- PHP 8.0+ with extensions: mysqli, imap, curl, openssl, json, mbstring
- Nginx with PHP-FPM
- MySQL 8.0+
- Composer for dependency management

### Required PHP Extensions
```bash
# Install required PHP extensions on Ubuntu/Debian
sudo apt-get install php8.1-cli php8.1-fpm php8.1-mysql php8.1-imap php8.1-curl php8.1-mbstring php8.1-json php8.1-zip
```

### Composer Dependencies
Create `composer.json` with the following dependencies:

```json
{
    "name": "smtp-test-tool/email-tester",
    "description": "SMTP and IMAP server testing tool",
    "type": "project",
    "require": {
        "php": ">=8.0",
        "phpmailer/phpmailer": "^6.8",
        "league/dns": "^1.0",
        "monolog/monolog": "^3.0",
        "vlucas/phpdotenv": "^5.5"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0"
    },
    "autoload": {
        "psr-4": {
            "EmailTester\\": "src/"
        }
    }
}
```

### Installation Command
```bash
# Install dependencies
composer install --no-dev --optimize-autoloader
```

### Key Composer Components Explained

#### PHPMailer (`phpmailer/phpmailer`)
- **Purpose**: Robust SMTP email sending with authentication support
- **Features**: TLS/SSL encryption, OAuth2 support, attachment handling
- **Usage**: Primary library for sending test emails

#### DNS League (`league/dns`)
- **Purpose**: DNS record lookup and validation
- **Features**: MX record queries, SPF record validation
- **Usage**: Email server diagnostics and domain verification

#### Monolog (`monolog/monolog`)
- **Purpose**: Comprehensive logging system
- **Features**: Multiple log handlers, log levels, formatters
- **Usage**: Application logging, error tracking, audit trails

#### PHP DotEnv (`vlucas/phpdotenv`)
- **Purpose**: Environment variable management
- **Features**: Load configuration from .env files
- **Usage**: Secure configuration management, environment-specific settings

### Additional Recommended Extensions
```bash
# Optional but recommended PHP extensions
sudo apt-get install php8.1-xml php8.1-dom php8.1-simplexml php8.1-xmlwriter
```

## Deployment Configuration

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name smtp-tester.local;
    root /var/www/smtp-test-tool/public;
    index index.php;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Protect sensitive directories
    location ~ ^/(src|database|logs|vendor)/ {
        deny all;
        return 404;
    }
    
    # Protect configuration files
    location ~ /\.(env|git|htaccess) {
        deny all;
        return 404;
    }
    
    # Remove install.php after installation
    location = /install.php {
        # Comment out this line after installation
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### File Permissions Setup
```bash
# Set proper ownership
sudo chown -R www-data:www-data /var/www/smtp-test-tool/

# Set directory permissions
find /var/www/smtp-test-tool/ -type d -exec chmod 755 {} \;

# Set file permissions
find /var/www/smtp-test-tool/ -type f -exec chmod 644 {} \;

# Make logs directory writable
chmod 775 /var/www/smtp-test-tool/logs/

# Protect sensitive files
chmod 600 /var/www/smtp-test-tool/.env
```

### Installation Commands Summary
```bash
# 1. Clone or download the project
cd /var/www/
git clone https://github.com/yourusername/smtp-test-tool.git

# 2. Install PHP dependencies
cd smtp-test-tool
composer install --no-dev --optimize-autoloader

# 3. Set permissions
sudo chown -R www-data:www-data .
chmod 775 logs/
chmod 600 .env.example

# 4. Copy environment template
cp .env.example .env

# 5. Access installation wizard
# Open browser: http://your-domain/install.php

# 6. After installation, secure the install file
sudo rm public/install.php
# OR rename it: mv public/install.php public/install.php.bak
```

## Troubleshooting Guide

### Common Installation Issues

#### 1. Database Connection Failures
```bash
# Check MySQL service status
sudo systemctl status mysql

# Test database connection manually
mysql -h localhost -u username -p database_name
```

#### 2. PHP Extension Missing
```bash
# Check installed PHP extensions
php -m | grep -E "(mysqli|imap|curl|openssl)"

# Install missing extensions
sudo apt-get install php8.1-mysqli php8.1-imap
sudo systemctl restart php8.1-fpm
```

#### 3. Permission Issues
```bash
# Fix ownership and permissions
sudo chown -R www-data:www-data /var/www/smtp-test-tool/
sudo chmod -R 755 /var/www/smtp-test-tool/
sudo chmod 775 /var/www/smtp-test-tool/logs/
```

#### 4. Composer Dependencies
```bash
# Clear composer cache
composer clear-cache

# Update dependencies
composer update --no-dev

# Regenerate autoloader
composer dump-autoload --optimize
```

## Security Best Practices

### 1. Database Security
- Use dedicated database user with minimal privileges
- Enable SSL connections to MySQL when possible
- Regular database backups
- Monitor for SQL injection attempts

### 2. Application Security
- Validate all user inputs
- Use prepared statements for database queries
- Implement rate limiting for API endpoints
- Regular security updates for dependencies

### 3. Server Security
- Keep PHP and server software updated
- Use HTTPS in production environments
- Configure proper firewall rules
- Monitor application logs for suspicious activity

## Production Deployment Checklist

### Pre-Deployment
- [ ] All dependencies installed via Composer
- [ ] Database schema created and migrated
- [ ] Environment variables configured
- [ ] File permissions set correctly
- [ ] Nginx/web server configured
- [ ] SSL certificate installed (for production)

### Post-Deployment
- [ ] Installation wizard completed successfully
- [ ] Install.php file removed or secured
- [ ] Test all major functionality
- [ ] Monitor application logs
- [ ] Set up backup procedures
- [ ] Configure monitoring/alerting

### Maintenance
- [ ] Regular dependency updates
- [ ] Log rotation configured
- [ ] Database optimization
- [ ] Performance monitoring
- [ ] Security audits

This implementation plan provides a comprehensive foundation for building a robust SMTP/IMAP testing tool using your preferred technology stack. The modular design allows for incremental development and easy maintenance.
