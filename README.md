# SMTP Test Tool

A comprehensive web-based tool for testing SMTP and IMAP server configurations, sending test emails, and diagnosing email server connectivity issues.

![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue)
![License](https://img.shields.io/badge/License-MIT-green)
![Version](https://img.shields.io/badge/Version-1.0.0-orange)
![Status](https://img.shields.io/badge/Status-Complete-brightgreen)

## 🚀 Features

- **SMTP Server Testing**: Test connection, authentication, and capabilities
- **IMAP Server Testing**: Validate IMAP connections and mailbox access
- **Email Sending**: Send test emails with HTML/plain text content
- **Port Scanner**: Scan common email ports and detect server types  
- **Real-time Testing**: Modern web interface with popup forms and instant results
- **Test History**: View and manage all previous test results
- **Security**: CSRF protection, rate limiting, and input validation
- **Logging**: Comprehensive logging of all test activities
- **Installation Wizard**: Easy setup with web-based installer

## ✅ Implementation Status

**Current Status: FULLY IMPLEMENTED AND WORKING** 🎉

All core features have been successfully implemented and tested:

- ✅ **SMTP Server Testing** - Complete with authentication support
- ✅ **IMAP Server Testing** - Full mailbox access and capability testing
- ✅ **Email Sending Interface** - Test email sending with popup forms  
- ✅ **Port Scanner** - Common email ports + custom range scanning
- ✅ **Modern Web Interface** - Responsive design with tabbed navigation
- ✅ **API Endpoints** - Full REST API backend (6 endpoints)
- ✅ **Security Features** - CSRF protection, rate limiting, validation
- ✅ **Installation System** - 4-step web-based installation wizard
- ✅ **Logging System** - Comprehensive test and security logging

**Ready for immediate use!** 🚀

## 📋 Requirements

### Server Requirements
- **PHP 8.0+** with the following extensions:
  - `mysqli` (Required)
  - `pdo` (Required)
  - `pdo_mysql` (Required)
  - `curl` (Required)
  - `openssl` (Required)
  - `mbstring` (Required)
  - `json` (Required)
  - `imap` (Optional - for IMAP testing)
- **MySQL 8.0+** or **MariaDB 10.3+**
- **Nginx** or **Apache** web server
- **Composer** for dependency management

### PHP Extensions Installation (Ubuntu/Debian)
```bash
sudo apt-get update
sudo apt-get install php8.1-cli php8.1-fpm php8.1-mysql php8.1-imap php8.1-curl php8.1-mbstring php8.1-json php8.1-zip php8.1-xml
```

## 🔧 Installation

### Step 1: Download and Setup

1. **Clone or download** the project to your web server:
```bash
cd /var/www/
git clone https://github.com/luozongbao/smtp-sendmail-test.git
cd smtp-sendmail-test/smtp-test-tool
```

2. **Install PHP dependencies** using Composer:
```bash
composer install --no-dev --optimize-autoloader
```

3. **Set proper file permissions**:
```bash
sudo chown -R www-data:www-data .
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 775 logs/
chmod 600 .env.example
```

### Step 2: Web Server Configuration

#### Nginx Configuration
Create a new Nginx site configuration (`/etc/nginx/sites-available/smtp-test-tool`):

```nginx
server {
    listen 80;
    server_name smtp-tester.local;  # Change to your domain
    root /var/www/smtp-sendmail-test/smtp-test-tool/public;
    index index.php install.php;
    
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
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/smtp-test-tool /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Step 3: Database Setup

1. **Create a MySQL database and user**:
```sql
CREATE DATABASE smtp_test_tool CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smtp_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON smtp_test_tool.* TO 'smtp_user'@'localhost';
FLUSH PRIVILEGES;
```

### Step 4: Web Installation

1. **Access the installation wizard** in your browser:
```
http://your-domain/install.php
```

2. **Follow the 4-step installation process**:
   - **Step 1**: System requirements check
   - **Step 2**: Database configuration
   - **Step 3**: Application settings
   - **Step 4**: Installation completion

3. **After installation**, delete the install file for security:
```bash
sudo rm public/install.php
```

## 🎯 Usage

### Quick Start (Development)

For development and testing purposes, you can quickly start the application:

```bash
cd smtp-test-tool
php -S localhost:8080 -t public
```

Then access: http://localhost:8080

### Main Interface

Once installed, access the main application at:
```
http://your-domain/
```

### Testing SMTP Servers

1. **Enter SMTP Configuration**:
   - Host: `smtp.gmail.com`
   - Port: `587`
   - Security: `TLS`
   - Username: `your-email@gmail.com`
   - Password: `your-password`

2. **Click "Test SMTP Connection"** to validate the configuration

3. **Send Test Email** by clicking "Send Test Email" and entering recipient details

### Testing IMAP Servers

1. **Enter IMAP Configuration**:
   - Host: `imap.gmail.com`
   - Port: `993`
   - Security: `SSL`
   - Username: `your-email@gmail.com`
   - Password: `your-password`

2. **Click "Test IMAP Connection"** to validate access

### Port Scanning

1. **Enter hostname** to scan
2. **Select ports** to test (or use default email ports)
3. **Click "Scan Ports"** to check connectivity

## 🔧 Configuration

### Environment Variables (.env)

After installation, you can modify settings in the `.env` file:

```env
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=smtp_test_tool
DB_USERNAME=smtp_user
DB_PASSWORD=your_password

# Application Configuration
APP_NAME="SMTP Test Tool"
APP_URL=http://your-domain
ADMIN_EMAIL=admin@example.com

# Security
SESSION_LIFETIME=120

# Logging
LOG_LEVEL=info
LOG_FILE=logs/application.log
```

### Common Email Provider Settings

#### Gmail
- **SMTP**: `smtp.gmail.com:587` (TLS)
- **IMAP**: `imap.gmail.com:993` (SSL)
- **Note**: Use App Passwords for 2FA enabled accounts

#### Outlook/Hotmail
- **SMTP**: `smtp-mail.outlook.com:587` (STARTTLS)
- **IMAP**: `outlook.office365.com:993` (SSL)

#### Yahoo Mail
- **SMTP**: `smtp.mail.yahoo.com:587` (TLS)
- **IMAP**: `imap.mail.yahoo.com:993` (SSL)

## 🛠️ API Endpoints

The tool provides REST API endpoints for integration:

**Available Endpoints:**
- `POST /api/smtp-test.php` - Test SMTP server connection
- `POST /api/imap-test.php` - Test IMAP server connection  
- `POST /api/send-email.php` - Send test emails
- `POST /api/port-scan.php` - Scan server ports
- `GET /api/get-logs.php` - Retrieve test logs
- `POST /api/clear-logs.php` - Clear all test logs

**Note:** All POST endpoints require CSRF tokens for security.

### Test SMTP Connection
```bash
curl -X POST http://your-domain/api/smtp-test.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d 'host=smtp.gmail.com&port=587&security=tls&username=user@gmail.com&password=password&csrf_token=your_csrf_token'
```

### Send Test Email
```bash
curl -X POST http://your-domain/api/send-email.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d 'smtp_host=smtp.gmail.com&smtp_port=587&smtp_username=user@gmail.com&smtp_password=password&from_email=user@gmail.com&to_email=recipient@example.com&subject=Test Email&body=This is a test message&csrf_token=your_csrf_token'
```

### Test IMAP Connection
```bash
curl -X POST http://your-domain/api/imap-test.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d 'host=imap.gmail.com&port=993&security=ssl&username=user@gmail.com&password=password&csrf_token=your_csrf_token'
```

### Port Scanner
```bash
curl -X POST http://your-domain/api/port-scan.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d 'host=mail.example.com&port_type=common&csrf_token=your_csrf_token'
```

## 🔐 Security Features

- **CSRF Protection**: All forms include CSRF tokens
- **Rate Limiting**: Prevents abuse with configurable limits
- **Input Validation**: Comprehensive validation of all user inputs
- **SQL Injection Prevention**: Uses prepared statements
- **XSS Protection**: Proper output escaping
- **Secure Headers**: Security headers automatically set
- **Session Security**: Secure session configuration

## 📊 Logging

All activities are logged to `logs/application.log`:

```php
[2025-08-05 10:30:15] INFO: Test successful: smtp {"host":"smtp.gmail.com","success":true}
[2025-08-05 10:31:22] WARNING: Test failed: smtp - Authentication failed {"host":"smtp.gmail.com"}
[2025-08-05 10:32:05] INFO: Test email sent successfully {"to":"user@example.com"}
```

## 🐛 Troubleshooting

### Common Issues

#### 1. Database Connection Failed
```bash
# Check MySQL service
sudo systemctl status mysql

# Test connection manually
mysql -h localhost -u smtp_user -p smtp_test_tool
```

#### 2. PHP Extensions Missing
```bash
# Check loaded extensions
php -m | grep -E "(mysqli|imap|curl)"

# Install missing extensions
sudo apt-get install php8.1-mysqli php8.1-imap
sudo systemctl restart php8.1-fpm
```

#### 3. Permission Denied
```bash
# Fix file permissions
sudo chown -R www-data:www-data /var/www/smtp-sendmail-test/smtp-test-tool/
sudo chmod 775 /var/www/smtp-sendmail-test/smtp-test-tool/logs/
```

#### 4. Gmail Authentication Issues
- Use **App Passwords** for 2FA-enabled accounts
- Enable **"Less secure app access"** for regular passwords (not recommended)
- Check **"Allow access for less secure apps"** in Gmail settings

#### 5. HTTP 500 Error
```bash
# Check Nginx error logs
sudo tail -20 /var/log/nginx/error.log

# Check PHP-FPM error logs  
sudo tail -20 /var/log/php8.1-fpm.log

# Common cause: Missing config file paths
# Ensure paths in PHP files match actual directory structure
```

#### 6. CSRF Token Error During Installation
```bash
# If you get "Invalid security token" during installation:

# Check if sessions are working
# Visit: http://your-domain/session-test.php

# Clear browser cookies and cache
# Then restart the installation process

# Ensure you're accessing via the correct protocol (HTTP vs HTTPS)
```

### Debug Mode

Enable debug logging by setting in `.env`:
```env
LOG_LEVEL=debug
```

## 📁 Project Structure

```
smtp-sendmail-test/
└── smtp-test-tool/        # Main application directory
    ├── public/            # Web accessible files
    │   ├── index.php      # Main application
    │   ├── install.php    # Installation wizard
    │   ├── css/           # Stylesheets
    │   ├── js/            # JavaScript files
    │   └── api/           # API endpoints
    ├── src/               # PHP classes
    │   ├── Classes/       # Core application classes
    │   ├── Config/        # Configuration classes
    │   └── Utils/         # Utility classes
    ├── database/          # Database files
    │   ├── schema.sql     # Database structure
    │   └── migrations/    # Migration files
    ├── logs/              # Application logs
    ├── vendor/            # Composer dependencies
    └── .env               # Environment configuration
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: Check this README and inline comments
- **Issues**: Report bugs via GitHub Issues
- **Logs**: Check `logs/application.log` for detailed error information

## 🔄 Updates

To update the application:

1. **Backup your data**:
```bash
mysqldump smtp_test_tool > backup.sql
cp .env .env.backup
```

2. **Pull latest changes**:
```bash
git pull origin main
composer install --no-dev --optimize-autoloader
```

3. **Run any new migrations** if needed

---

**🎉 You're ready to test your email servers!** 

Visit your installation and start testing SMTP configurations, sending test emails, and diagnosing email server issues.

## 🔧 Development Notes

- **Namespace**: All PHP classes use the `EmailTester\` namespace
- **PSR-4 Autoloading**: Composer autoloader handles all class loading
- **Directory Structure**: Follows PSR-4 standards (Classes/, Utils/, Config/)
- **Error Handling**: Comprehensive exception handling throughout
- **Code Quality**: Modern PHP 8+ features with type declarations

---
