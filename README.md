# Magento2 Firebase PushNotification Module

A comprehensive Magento 2 module for managing and sending push notifications via Firebase Cloud Messaging (FCM).

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

The module adds three menu items under **Marketing > iDangerous > Push Notifications**:

1. **List Notification Tokens**: View and manage all registered tokens
2. **Send Single Notification**: Send notification to a specific customer
3. **Send Multiple Notifications**: Send bulk notifications with filters

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