# Magento2 Firebase PushNotification Module

**Version:** 2.1.0 (December 2025)
**Status:** Production Ready ✅

A comprehensive Magento 2 module for managing and sending push notifications via Firebase Cloud Messaging (FCM).

## 🎯 Latest Updates (v2.1.0)

- ✅ **Critical Bug Fixes**: Resolved bulk notification processing issues
- ✅ **Scalability**: New tracking table for 10k-20k+ tokens
- ✅ **Concurrent Protection**: Multi-layer lock mechanism
- ✅ **Auto Cleanup**: Zero disk usage after completion
- ✅ **Scheduled Sends**: Future-dated notification support
- ✅ **Recovery System**: Automatic stuck process detection

[See detailed changelog below](#version-21---critical-updates-december-2025)

## Features

- **Token Management**: Register and manage APN/FCM tokens for iOS and Android devices
- **GraphQL API**: Register and unregister tokens via GraphQL mutations
- **Admin Interface**: Complete admin panel for managing tokens and sending notifications
- **Firebase Integration**: Send notifications via Firebase Cloud Messaging
- **Advanced Filtering**: Filter users by various criteria when sending bulk notifications
- **Custom Data Support**: Add custom JSON data to notifications for app-specific functionality
- **Multi-language Support**: Turkish and English translations
- **API Service**: Easy integration for other modules
- **Emoji Support**: Full UTF-8 emoji support in notifications
- **Statistics Dashboard**: Comprehensive analytics and reporting
- **Pagination & Filters**: Advanced token listing with search and pagination
- **Token Analytics**: Device distribution, user activity, and engagement metrics

## Emoji Support

This module includes comprehensive emoji support for push notifications, allowing you to send rich, engaging content with emojis that display correctly on all devices.

### Features
- ✅ **Full UTF-8 Support**: Complete emoji character support
- ✅ **Database Storage**: Emojis stored correctly in UTF-8 format
- ✅ **Firebase Transmission**: Proper UTF-8 encoding for Firebase API
- ✅ **Device Display**: Native emoji rendering on iOS and Android
- ✅ **Admin Interface**: Emoji input support in admin forms

### Database Configuration

**Important**: The module requires UTF-8 database configuration for proper emoji support.

#### Automatic Configuration
The module automatically configures the database connection for emoji support during installation.

#### Manual Configuration (if needed)
If you need to manually configure the database for emoji support:

1. **Update Database Tables**:
   ```sql
   ALTER TABLE idangerous_push_notification_tokens CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ALTER TABLE idangerous_push_notification_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Update Database Connection** (in `app/etc/env.php`):
   ```php
   'db' => [
       'table_prefix' => '',
       'connection' => [
           'default' => [
               'host' => '127.0.0.1',
               'dbname' => 'your_database',
               'username' => 'your_username',
               'password' => 'your_password',
               'model' => 'mysql4',
               'engine' => 'mysql4',
               'initStatements' => 'SET NAMES utf8mb4;', // Changed from utf8 to utf8mb4
               'active' => '1',
               'driver_options' => [
                   1014 => false
               ]
           ]
       ]
   ]
   ```

### Usage Examples

#### Admin Interface
When sending notifications through the admin panel, you can include emojis directly:

**Title**: `🚀 New Product Launch!`
**Message**: `🎉 Check out our latest products! ❤️`

#### API Usage
```php
// Send notification with emojis
$result = $this->pushNotificationService->sendToSingleUser(
    123,
    '✅ Order Confirmed!',
    '🎉 Your order #12345 has been successfully placed! 🚚',
    'https://example.com/image.jpg',
    'https://example.com/order/123',
    'order'
);

// Send notification with custom data
$customData = [
    'order_id' => '12345',
    'total_amount' => '99.99',
    'delivery_date' => '2024-01-15',
    'category' => 'electronics'
];

$result = $this->pushNotificationService->sendToSingleUser(
    123,
    'Order Update',
    'Your order has been shipped!',
    null,
    'https://example.com/order/123',
    'order',
    $customData
);
```

#### GraphQL with Emojis
```graphql
mutation {
  registerPushNotificationToken(input: {
    token: "your_fcm_token_here"
    device_type: "ios"
    device_id: "device_identifier"
    device_model: "📱 iPhone 12"
    os_version: "15.0"
    app_version: "1.0.0"
  }) {
    success
    message
  }
}
```

## Custom Data Support

This module supports adding custom JSON data to push notifications, allowing you to pass app-specific information that can be used by your mobile application.

### Features
- ✅ **Raw JSON Input**: Direct JSON input for advanced users
- ✅ **Key-Value Pairs**: User-friendly key-value interface
- ✅ **Data Validation**: JSON validation before sending
- ✅ **String Conversion**: All values automatically converted to strings for Firebase compatibility
- ✅ **Admin Interface**: Easy-to-use admin forms for both input methods

### Admin Interface

#### Raw JSON Method
Enter JSON directly in the textarea:
```json
{
  "order_id": "12345",
  "total_amount": "99.99",
  "delivery_date": "2024-01-15",
  "category": "electronics",
  "user_type": "vip"
}
```

#### Key-Value Pairs Method
Use the user-friendly interface to add key-value pairs:
- **Key**: `order_id` → **Value**: `12345`
- **Key**: `total_amount` → **Value**: `99.99`
- **Key**: `delivery_date` → **Value**: `2024-01-15`

### Firebase Data Payload

Custom data is merged with the default notification data and sent to Firebase:

```json
{
  "message": {
    "token": "device_token_here",
    "notification": {
      "title": "Order Update",
      "body": "Your order has been shipped!"
    },
    "data": {
      "notification_type": "order",
      "click_action": "https://example.com/order/123",
      "order_id": "12345",
      "total_amount": "99.99",
      "delivery_date": "2024-01-15",
      "category": "electronics"
    }
  }
}
```

### Use Cases

#### E-commerce
```json
{
  "order_id": "12345",
  "total_amount": "99.99",
  "delivery_date": "2024-01-15",
  "tracking_number": "1Z999AA1234567890"
}
```

#### Social Media
```json
{
  "post_id": "789",
  "author_id": "456",
  "post_type": "photo",
  "comment_count": "5"
}
```

#### Gaming
```json
{
  "game_id": "puzzle_quest",
  "level": "15",
  "score": "2500",
  "achievement": "speed_runner"
}
```

### API Usage Examples

#### Single User with Custom Data
```php
$customData = [
    'order_id' => '12345',
    'total_amount' => '99.99',
    'delivery_date' => '2024-01-15'
];

$result = $this->pushNotificationService->sendToSingleUser(
    123,
    'Order Update',
    'Your order has been shipped!',
    null,
    'https://example.com/order/123',
    'order',
    $customData
);
```

#### Multiple Users with Custom Data
```php
$customData = [
    'promotion_id' => 'SUMMER2024',
    'discount' => '20',
    'expires' => '2024-08-31'
];

$filters = ['user_type' => 'member'];

$result = $this->pushNotificationService->sendToMultipleUsers(
    'Summer Sale!',
    'Get 20% off on all items!',
    $filters,
    'https://example.com/sale-banner.jpg',
    'https://example.com/summer-sale',
    'promotion',
    $customData
);
```

#### Direct Token with Custom Data
```php
$customData = [
    'chat_room_id' => 'room_123',
    'sender_id' => 'user_456',
    'message_type' => 'text'
];

$result = $this->pushNotificationService->sendToToken(
    'device_token_here',
    'New Message',
    'John sent you a message',
    null,
    'https://example.com/chat/room_123',
    'chat',
    $customData
);
```

### Troubleshooting

#### If Emojis Show as Question Marks

1. **Check Database Configuration**:
   ```bash
   mysql -u username -p database_name -e "SHOW TABLE STATUS LIKE 'idangerous_push_notification%';"
   ```
   Ensure tables show `utf8mb4_unicode_ci` collation.

2. **Verify Database Connection**:
   Check `app/etc/env.php` has `'initStatements' => 'SET NAMES utf8mb4;'`

3. **Check PHP Configuration**:
   ```bash
   php -m | grep mbstring
   php -r "echo ini_get('default_charset');"
   ```

4. **Browser Encoding**:
   Ensure your browser is sending UTF-8 data. Check browser developer tools Network tab for proper `Content-Type` headers.

#### Console Display Issues
If emojis show as question marks in console output, this is normal for some terminal environments and doesn't affect actual functionality. Verify by checking the database directly.

### Testing Emoji Support

1. **Admin Form Test**:
   - Go to Admin Panel > Marketing > iDangerous > Push Notifications > Send Multiple Notifications
   - Enter emoji in title: `🚀 Test Title`
   - Enter emoji in message: `🎉 Test message with ❤️`
   - Submit and verify storage

2. **Database Verification**:
   ```bash
   mysql -u username -p database_name -e "
   SELECT title, message FROM idangerous_push_notification_logs
   WHERE title LIKE '%🚀%' ORDER BY entity_id DESC LIMIT 1;"
   ```

3. **Console Processing Test**:
   ```bash
   php bin/magento idangerous:pushnotification:process-queue --status=pending --limit=1
   ```

## Installation

1. Copy the module to `app/code/IDangerous/PushNotification/`
2. Run the following commands:
   ```bash
   php bin/magento module:enable IDangerous_PushNotification
   php bin/magento setup:upgrade
   php bin/magento setup:di:compile
   php bin/magento setup:static-content:deploy
   php bin/magento cache:flush
   ```

## Configuration

1. Go to **Stores > Configuration > iDangerous > Push Notifications**
2. Enable the module
3. Enter your Firebase Server Key and Project ID
4. Configure other settings as needed

## GraphQL API

### Register Token
```graphql
mutation {
  registerPushNotificationToken(input: {
    token: "your_fcm_token_here"
    device_type: "ios"
    device_id: "device_identifier"
    device_model: "iPhone 12"
    os_version: "15.0"
    app_version: "1.0.0"
  }) {
    success
    message
  }
}
```

### Unregister Token
```graphql
mutation {
  unregisterPushNotificationToken(input: {
    token: "your_fcm_token_here"
  }) {
    success
    message
  }
}
```

## Admin Interface

The module adds menu items under **Marketing > Push Notifications**:

1. **List Notification Tokens**: View and manage all registered tokens with advanced filtering and pagination
2. **Send Single Notification**: Send notification to a specific customer
3. **Send Multiple Notifications**: Send bulk notifications with filters
4. **Notification Logs**: View notification history and logs
5. **Statistics**: Comprehensive analytics dashboard with device and user insights

### Statistics Dashboard Features

- **Overview Metrics**: Total tokens, active tokens, registered vs guest users
- **Time-based Analytics**: New tokens today, this week, this month
- **Device Analytics**: Device type distribution, top 50 device models
- **App Version Tracking**: Most popular app versions
- **User Activity**: Last seen statistics (24h, 7d, 30d, 90d)

### Token Analytics
- **Device Distribution**: iOS vs Android ratio
- **Top Device Models**: Most popular device models (Top 50)
- **App Version Analysis**: Most used app versions
- **User Segmentation**: Registered vs guest user analysis
- **Activity Tracking**: User engagement over time

## API Usage for Other Modules

```php
use IDangerous\PushNotification\Api\PushNotificationServiceInterface;

class YourClass
{
    private PushNotificationServiceInterface $pushNotificationService;

    public function __construct(
        PushNotificationServiceInterface $pushNotificationService
    ) {
        $this->pushNotificationService = $pushNotificationService;
    }

    public function sendNotification()
    {
        // Send to single user
        $result = $this->pushNotificationService->sendToSingleUser(
            123, // customer ID
            'Order Update',
            'Your order has been shipped!',
            'https://example.com/image.jpg',
            'https://example.com/order/123',
            'order'
        );

        // Send to multiple users with filters
        $result = $this->pushNotificationService->sendToMultipleUsers(
            'Special Offer',
            'Get 20% off on all products!',
            [
                'user_type' => 'member',
                'device_type' => 'ios',
                'last_seen_from' => '2024-01-01'
            ],
            'https://example.com/offer.jpg',
            'https://example.com/offer',
            'promotion'
        );

        // Send to specific token
        $result = $this->pushNotificationService->sendToToken(
            'fcm_token_here',
            'Test Notification',
            'This is a test message',
            null,
            null,
            'general'
        );
    }
}
```

## Database Tables

### idangerous_push_notification_tokens
Stores device tokens and information:
- `entity_id` (Primary Key)
- `token` (FCM/APN Token)
- `device_type` (ios/android)
- `device_id`, `device_model`, `os_version`, `app_version`
- `customer_id`, `customer_email`
- `store_id`, `is_active`
- `created_at`, `updated_at`, `last_seen_at`

### idangerous_push_notification_logs
Stores notification history:
- `entity_id` (Primary Key)
- `title`, `message`, `image_url`, `action_url`
- `notification_type`, `customer_id`
- `filters` (JSON), `total_sent`, `total_failed`
- `status`, `error_message`
- `store_id`, `created_at`, `sent_at`

## Filtering Options

When sending multiple notifications, you can filter by:

- **User Type**: All users, Member users only, Guest users only
- **Device Type**: All devices, iOS only, Android only
- **Customer Group**: Specific customer groups
- **Last Seen Date**: Date range for last activity
- **Store**: Specific store (multi-store setup)

## Recent Updates

### Version 2.0 Features (Latest)
- ✅ **Statistics Dashboard**: Comprehensive analytics with visual charts
- ✅ **Token Analytics**: Device distribution and user activity insights
- ✅ **Menu Reorganization**: Moved to Marketing menu for better organization
- ✅ **Performance Optimization**: Improved database queries and caching

### Technical Improvements
- **SQL Query Optimization**: Direct database queries for better performance
- **Security Enhancements**: Input validation and SQL injection protection
- **Error Handling**: Improved error handling and user feedback

---

## Version 2.1 - Critical Updates (December 2025)

### 🔥 Critical Bug Fixes

#### 1. Bulk Notification Processing Issues
**Problem:** Multiple critical issues were discovered in bulk notification processing:
- Duplicate log records created on restart instead of updating existing ones
- Concurrent processes could process the same notification simultaneously
- Repeated notifications sent to the same users
- Logs stuck in "processing" status indefinitely
- No recovery mechanism for stuck/failed processes
- Parameter count mismatch in `sendToMultipleUsers()` calls (12 instead of 10)

**Solution:**
- ✅ Fixed parameter count in Console Command and Cron Job
- ✅ Implemented proper `$existingLog` parameter passing
- ✅ Added stuck log recovery mechanism (1-hour timeout)
- ✅ Improved status transition logic

#### 2. Missing Token Tracking System
**Problem:** Production system had NO tracking mechanism for sent tokens:
- No way to track which tokens received notification
- Restart would send to ALL tokens again (duplicates)
- No recovery mechanism for interrupted sends
- No way to resume from last sent token
- Impossible to prevent duplicate sends

**Solution - New Tracking Table:**
Created `idangerous_push_notification_sent` table with:
- `notification_log_id` + `token_id` relationship
- UNIQUE constraint for duplicate prevention
- CASCADE DELETE for automatic cleanup
- Efficient indexes for fast queries
- LEFT JOIN queries for unsent token filtering

#### 3. Automatic Cleanup System
**Problem:** Tracking table would grow indefinitely without cleanup.

**Solution:**
- ✅ Automatic cleanup when notification reaches `completed` status
- ✅ Optional cron job for orphaned records (7+ days old)
- ✅ Zero disk usage after completion

#### 4. Concurrent Execution Protection
**Problem:** Multiple processes could run simultaneously causing:
- Duplicate processing of same notification
- Database deadlocks
- Duplicate notifications to users

**Solution - Multi-Layer Protection:**

**Layer 1: Process-Level Lock**
```php
LockManagerInterface with 1-hour timeout
Prevents multiple process instances
```

**Layer 2: Atomic Status Update**
```php
UPDATE ... WHERE status = 'pending'
Each log claimed atomically by one process
```

**Layer 3: Recovery Mechanism**
```php
Detects logs stuck >1 hour in 'processing'
Automatically resets to 'pending'
Resumes from last sent token
```

**Test Results:**
- ✅ 5 concurrent processes tested
- ✅ Each processed different notifications
- ✅ Zero duplicates
- ✅ All completed successfully

#### 5. Bulk Notification Customer Association
**Problem:** Bulk notifications were incorrectly associated with a single customer_id.

**Solution:**
- ✅ Bulk sends now keep `customer_id = NULL`
- ✅ Only single-user notifications have customer_id
- ✅ Proper distinction between bulk and targeted sends

#### 6. Scheduled Notifications
**New Feature:** Added ability to schedule notifications for future delivery:
- ✅ `scheduled_at` column in logs table
- ✅ Admin UI date/time picker
- ✅ Cron respects scheduled time
- ✅ Grid column shows scheduled time

### 🏗️ Architecture Changes

#### New Database Table
```sql
CREATE TABLE idangerous_push_notification_sent (
    entity_id INT AUTO_INCREMENT PRIMARY KEY,
    notification_log_id INT NOT NULL,
    token_id INT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_log_token (notification_log_id, token_id),
    FOREIGN KEY (notification_log_id) REFERENCES idangerous_push_notification_logs (entity_id) ON DELETE CASCADE,
    FOREIGN KEY (token_id) REFERENCES idangerous_push_notification_tokens (entity_id) ON DELETE CASCADE,
    INDEX idx_notification_log_id (notification_log_id),
    INDEX idx_sent_at (sent_at)
);
```

#### New Model Files
- `Model/NotificationSent.php`
- `Model/ResourceModel/NotificationSent.php`
- `Model/ResourceModel/NotificationSent/Collection.php`

#### New Cron Job
- `Cron/CleanupOrphanedSentRecords.php` - Daily cleanup at 02:00 AM

#### New Service Methods
**Added:**
- `getUnsentTokensForLog()` - Efficient LEFT JOIN query to find unsent tokens
- `markTokensAsSent()` - Batch insert with duplicate handling (1000 tokens/batch)
- `cleanupSentRecords()` - Automatic cleanup after completion
- `getSentTokenCount()` - Fast count query for recovery

**Note:** Previous production version had no tracking methods at all.

### 🔒 Security & Reliability

#### Concurrent Execution Safety
```
✅ LockManager prevents multiple process instances
✅ Atomic database updates prevent race conditions
✅ Status-based locking at log level
✅ Recovery mechanism for stuck processes
✅ Sent token tracking prevents duplicates
```

#### Data Integrity
```
✅ UNIQUE constraints prevent duplicate sends
✅ Foreign keys with CASCADE DELETE
✅ Atomic status transitions
✅ Transaction-safe updates
```

#### Error Recovery
```
✅ 1-hour stuck detection
✅ Automatic retry mechanism
✅ Resume from last sent token
✅ Failed status with error messages
✅ Comprehensive logging
```

### 📊 Monitoring & Debugging

#### Log Messages
```
PushNotification: Bulk send - all tokens belong to customer ID: X but keeping customer_id NULL
PushNotification: Log ID X - Sending to Y unsent tokens (skipped Z)
PushNotification: Cleaned up N sent records for log ID: X
ProcessNotificationQueue: Log ID X already being processed by another instance. Skipping.
CleanupOrphanedSentRecords: Cleaned up N orphaned sent records for X completed logs
```

#### Console Command Updates
```bash
# Check for concurrent execution
php bin/magento idangerous:pushnotification:process-queue

# Output includes:
# - Lock status
# - Stuck log recovery
# - Atomic claim results
# - Detailed processing info
```

### 🚀 Migration Guide

#### For Existing Installations

1. **Run Database Schema Update:**
```bash
php bin/magento setup:upgrade
```

2. **Verify New Table:**
```sql
SHOW CREATE TABLE idangerous_push_notification_sent;
```

3. **Check Scheduled Column:**
```sql
DESCRIBE idangerous_push_notification_logs;
-- Should show 'scheduled_at' column
```

4. **Test Concurrent Execution:**
```bash
# Run multiple instances simultaneously
php bin/magento idangerous:pushnotification:process-queue &
php bin/magento idangerous:pushnotification:process-queue &
# Should see "already running" message
```

### ⚠️ Breaking Changes

**None** - All changes are backward compatible:
- Old logs without `scheduled_at` work normally
- Existing cron jobs continue working
- No API changes
- New tracking table works alongside existing logs

### 📝 Upgrade Notes

**Recommended Actions:**
1. Monitor logs for first 24 hours after upgrade
2. Check cleanup cron runs successfully (02:00 AM daily)
3. Verify sent table remains empty after completions
4. Test scheduled notifications feature
5. Confirm no duplicate sends occur

**Performance Expectations:**
- 10k tokens: <30 seconds processing
- 20k tokens: <60 seconds processing
- Memory usage: <10MB per process
- Disk usage: ~0MB after completion

---

## 🚨 Troubleshooting

### Issue: Notifications Stuck in "Processing"

**Symptoms:**
- Notification log shows "processing" status for >1 hour
- No progress in sending

**Solution:**
```bash
# Manual recovery
php bin/magento idangerous:pushnotification:process-queue

# Check logs
tail -f var/log/system.log | grep -i "pushnotification"
```

**Automatic Recovery:**
- System automatically detects stuck logs after 1 hour
- Resets to "pending" status
- Resumes from last sent token

### Issue: Duplicate Notifications

**Symptoms:**
- Users receiving same notification multiple times
- Multiple log entries with same content

**Cause:** Concurrent execution without proper locking (fixed in v2.1.0)

**Verification:**
```sql
-- Check for duplicate sends
SELECT notification_log_id, token_id, COUNT(*)
FROM idangerous_push_notification_sent
GROUP BY notification_log_id, token_id
HAVING COUNT(*) > 1;
```

**Expected Result:** Empty (no duplicates)

### Issue: Duplicate Sends on Restart

**Symptoms:**
- Users receiving same notification multiple times
- Happens when process restarts during sending

**Cause:** Old version had no tracking mechanism

**Solution (v2.1.0):**
- Upgrade to v2.1.0 (implements tracking table)
- System now tracks each sent token
- Restart automatically resumes from last sent token
- Duplicates prevented by UNIQUE constraint

### Issue: Sent Table Growing Indefinitely

**Symptoms:**
- `idangerous_push_notification_sent` table has millions of rows
- Slow queries

**Solution:**
```bash
# Check table size
mysql> SELECT COUNT(*) FROM idangerous_push_notification_sent;

# Should be 0 or very small (only active sends)
# If large, run cleanup manually:
php -r "require 'app/bootstrap.php';
\$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, \$_SERVER);
\$obj = \$bootstrap->getObjectManager();
\$cron = \$obj->get('IDangerous\PushNotification\Cron\CleanupOrphanedSentRecords');
\$cron->execute();"
```

**Prevention:**
- Automatic cleanup runs daily at 02:00 AM
- Cleanup runs after each notification completion
- Check cron is working: `php bin/magento cron:run`

### Issue: "Already Running" Message

**Symptoms:**
```
Push notification processing is already running. Skipping this execution.
```

**Cause:** Another process is currently running (this is normal)

**If Stuck:**
```bash
# Check if process is actually running
ps aux | grep "pushnotification:process-queue"

# If no process found, lock might be stuck
# Wait 1 hour for automatic timeout, or:
mysql> DELETE FROM magento_lock WHERE name = 'idangerous_pushnotification_bulk_processing';
```

### Issue: Scheduled Notifications Not Sending

**Symptoms:**
- Notification scheduled for past time but still "pending"

**Checks:**
```bash
# 1. Verify cron is running
php bin/magento cron:run

# 2. Check scheduled_at value
mysql> SELECT entity_id, title, scheduled_at, status
       FROM idangerous_push_notification_logs
       WHERE scheduled_at IS NOT NULL;

# 3. Ensure time is in GMT
# scheduled_at should be in GMT timezone
```

### Issue: Customer ID Set for Bulk Sends

**Symptoms:**
- Bulk notification shows customer_id instead of NULL

**Solution:** Fixed in v2.1.0
- Upgrade to v2.1.0
- Bulk sends now correctly keep customer_id = NULL

### Debug Commands

```bash
# Check module version
php bin/magento module:status IDangerous_PushNotification

# View recent logs
tail -100 var/log/system.log | grep -i "pushnotification"

# Check pending notifications
mysql> SELECT entity_id, title, status, created_at, scheduled_at
       FROM idangerous_push_notification_logs
       WHERE status = 'pending'
       ORDER BY created_at DESC;

# Check active tracking records
mysql> SELECT COUNT(*) as active_sends
       FROM idangerous_push_notification_sent;

# Check lock status
mysql> SELECT * FROM magento_lock
       WHERE name LIKE '%pushnotification%';

# Force process queue
php bin/magento idangerous:pushnotification:process-queue --force-retry
```

### Performance Monitoring

```bash
# Monitor real-time processing
tail -f var/log/system.log | grep -E "Processing notification|Success|Failed"

# Check processing times
mysql> SELECT
    entity_id,
    title,
    total_sent,
    TIMESTAMPDIFF(SECOND, created_at, processed_at) as seconds_to_complete
FROM idangerous_push_notification_logs
WHERE status = 'completed'
ORDER BY entity_id DESC
LIMIT 10;
```

---

## Requirements

- Magento 2.4.x
- PHP 7.4+ (compatible with PHP 8.x)
- Firebase Cloud Messaging account
- Valid Firebase Server Key

### Non-Commercial Use
- This software is free for non-commercial use
- You may copy, distribute and modify the software as long as you track changes/dates in source files
- Any modifications must be made available under the same license terms

### Commercial Use
- Commercial use of this software requires explicit permission from the author
- Please contact [Orkan Köylü](orkan.koylu@gmail.com) for commercial licensing inquiries
- Usage without proper licensing agreement is strictly prohibited

Copyright (c) 2025 Orkan Köylü. All Rights Reserved.

[Developer: Orkan Köylü](orkan.koylu@gmail.com)