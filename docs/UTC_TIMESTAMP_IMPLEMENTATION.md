# UTC Timestamp Implementation

## Overview

The SMTP Test Tool has been updated to properly handle timestamps using UTC format for database storage while displaying times in the user's browser local timezone.

## Implementation Details

### Database Storage (UTC)

All timestamps are now stored in UTC format in the database:

1. **Database Schema**: All timestamp columns use `UTC_TIMESTAMP()` as default value
2. **Application Code**: Uses `gmdate('Y-m-d H:i:s')` for UTC timestamps
3. **Utility Function**: `SecurityUtils::getUTCTimestamp()` provides consistent UTC timestamp formatting

### Frontend Display (Browser Local Time)

The frontend JavaScript automatically converts UTC timestamps to browser local time:

1. **API Response**: Includes both `created_at` (with UTC marker) and `created_at_utc` (raw UTC)
2. **JavaScript Parsing**: Uses `new Date(utcTimestamp + ' UTC')` for proper timezone conversion
3. **Enhanced Tooltips**: Shows UTC time, local time, and timezone information

## Files Modified

### Backend (PHP)

1. **`database/schema.sql`**
   - Updated all timestamp columns to use `UTC_TIMESTAMP()`
   - `test_logs.test_timestamp`
   - `email_templates.created_at`
   - `app_settings.created_at` and `updated_at`

2. **`src/Utils/Logger.php`**
   - Modified `logTestToDatabase()` to use UTC timestamps
   - Updated `logSecurityEvent()` and `logInstallation()` methods
   - Uses `SecurityUtils::getUTCTimestamp()` utility function

3. **`src/Utils/SecurityUtils.php`**
   - Added utility functions:
     - `getUTCTimestamp()`: Returns current UTC timestamp in Y-m-d H:i:s format
     - `getUTCTimestampISO()`: Returns ISO 8601 UTC format for JavaScript
     - `convertToUTC()`: Converts local timestamp to UTC
   - Updated `logSecurityEvent()` to use UTC timestamps

4. **`src/Classes/Installer.php`**
   - Updated installation logging to use UTC timestamps

5. **`public/api/get-logs.php`**
   - Enhanced API response to include both UTC and local timestamp information
   - Added `created_at_utc` field for JavaScript processing

6. **`public/api/send-email.php`**
   - Added template processing for `{{timestamp}}` placeholder
   - Replaces placeholder with UTC timestamp and local time information

### Frontend (JavaScript)

1. **`public/js/main.js`**
   - Enhanced timestamp parsing and display
   - Added timezone detection and offset calculation
   - Improved tooltips with comprehensive time information
   - Better fallback handling for legacy timestamps

### Database Migration

1. **`database/migrations/update_timestamps_to_utc.sql`**
   - Migration script to update existing databases
   - Converts existing timestamp columns to use UTC_TIMESTAMP()

2. **`migrate_to_utc.php`**
   - Command-line migration runner
   - Includes verification and rollback capabilities
   - Colored output for better user experience

## Usage

### For New Installations

New installations automatically use UTC timestamps. No additional configuration needed.

### For Existing Installations

Run the migration script to update existing databases:

```bash
php migrate_to_utc.php
```

The migration script will:
1. Check if migration is needed
2. Backup existing timestamp data
3. Update column definitions to use UTC_TIMESTAMP()
4. Verify the migration was successful

### Email Templates

Email templates now support the `{{timestamp}}` placeholder which will be replaced with:
- UTC timestamp
- Local server time with timezone

Example:
```
Sent at: {{timestamp}}
```

Becomes:
```
Sent at: 2025-08-06 12:30:45 UTC (Local: 2025-08-06 15:30:45 EST)
```

## Frontend Features

### Log Display

- **Primary Display**: Shows time in browser's local timezone
- **Tooltip Information**: 
  - UTC time
  - Local time
  - Timezone name and offset
- **Automatic Conversion**: No user intervention required

### Timezone Information

The frontend automatically detects and displays:
- Browser's timezone (e.g., "America/New_York")
- UTC offset (e.g., "UTC-05:00")
- Daylight saving time adjustments

## Technical Benefits

1. **Consistency**: All stored timestamps are in UTC, eliminating timezone confusion
2. **User-Friendly**: Display times are automatically shown in user's local timezone
3. **International Support**: Works correctly across all timezones
4. **Database Integrity**: UTC storage prevents daylight saving time issues
5. **API Compatibility**: Provides both UTC and local time information

## Utility Functions

### SecurityUtils Methods

```php
// Get current UTC timestamp
$utcTime = SecurityUtils::getUTCTimestamp();
// Returns: "2025-08-06 12:30:45"

// Get ISO format for JavaScript
$isoTime = SecurityUtils::getUTCTimestampISO();
// Returns: "2025-08-06T12:30:45Z"

// Convert local to UTC
$utcTime = SecurityUtils::convertToUTC("2025-08-06 15:30:45", "America/New_York");
// Returns: "2025-08-06 20:30:45"
```

### JavaScript Handling

```javascript
// Parse UTC timestamp
const utcDate = new Date(utcTimestamp + ' UTC');
const localTime = utcDate.toLocaleString();

// Get timezone info
const timezoneName = Intl.DateTimeFormat().resolvedOptions().timeZone;
const offset = utcDate.getTimezoneOffset();
```

## Testing

To verify the implementation:

1. **Create Test Logs**: Perform SMTP/IMAP tests and check log timestamps
2. **Change Browser Timezone**: Verify logs display in new local time
3. **Check Database**: Confirm timestamps are stored in UTC format
4. **Send Test Emails**: Verify `{{timestamp}}` placeholder replacement

## Troubleshooting

### Common Issues

1. **Migration Fails**: Check database permissions and backup first
2. **Timezone Detection**: Ensure browser allows timezone detection
3. **Display Issues**: Clear browser cache and reload
4. **API Errors**: Check that all PHP files are updated

### Verification

```sql
-- Check database column definitions
SHOW COLUMNS FROM test_logs LIKE 'test_timestamp';

-- Verify UTC storage
SELECT test_timestamp FROM test_logs ORDER BY id DESC LIMIT 5;

-- Compare with system time
SELECT NOW(), UTC_TIMESTAMP();
```

## Future Enhancements

Potential improvements for future versions:
- User-selectable timezone preferences
- Timezone conversion utilities
- Date range filters with timezone awareness
- Export functionality with timezone options
