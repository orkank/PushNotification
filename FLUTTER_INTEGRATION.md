# Flutter Integration Guide for Push Notifications

This guide explains how to integrate the Magento 2 Push Notification module with Flutter applications using GraphQL and Firebase Cloud Messaging (FCM) HTTP v1 API.

## ⚠️ Important: Firebase HTTP v1 API Only

This module now uses **Firebase HTTP v1 API** instead of the deprecated legacy API. Make sure you configure your Firebase project accordingly.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Flutter Setup](#flutter-setup)
3. [GraphQL Integration](#graphql-integration)
4. [Firebase Cloud Messaging Setup](#firebase-cloud-messaging-setup)
5. [Token Registration](#token-registration)
6. [Handling Push Notifications](#handling-push-notifications)
7. [Complete Implementation Example](#complete-implementation-example)
8. [Testing](#testing)
9. [Troubleshooting](#troubleshooting)

## Prerequisites

- Flutter SDK installed
- Firebase project configured with **HTTP v1 API enabled**
- Firebase service account key file downloaded
- Magento 2 store with IDangerous_PushNotification module installed
- GraphQL endpoint available

### Firebase Setup Requirements

1. **Enable Firebase Cloud Messaging API (v1)**:
   - Go to [Google Cloud Console](https://console.cloud.google.com/)
   - Select your Firebase project
   - Navigate to "APIs & Services" → "Library"
   - Search for "Firebase Cloud Messaging API" and enable it

2. **Create Service Account**:
   - Go to Firebase Console → Project Settings → Service Accounts
   - Click "Generate new private key"
   - Download the JSON file and keep it secure
   - This JSON will be used in Magento admin configuration

3. **Configure Magento Admin**:
   - Go to Stores → Configuration → Push Notifications → Firebase Configuration (HTTP v1 API)
   - Enable Push Notifications
   - Enter your Firebase Project ID (e.g., `my-project-12345`)
   - Paste the entire Service Account JSON content in the "Service Account JSON" field
   - The JSON should look like this:
   ```json
   {
     "type": "service_account",
     "project_id": "your-project-id",
     "private_key_id": "...",
     "private_key": "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",
     "client_email": "firebase-adminsdk-...@your-project.iam.gserviceaccount.com",
     "client_id": "...",
     "auth_uri": "https://accounts.google.com/o/oauth2/auth",
     "token_uri": "https://oauth2.googleapis.com/token",
     "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
     "client_x509_cert_url": "..."
   }
   ```

## Flutter Setup

### 1. Add Dependencies

Add these dependencies to your `pubspec.yaml`:

```yaml
dependencies:
  flutter:
    sdk: flutter
  firebase_core: ^2.24.2
  firebase_messaging: ^14.7.10
  graphql_flutter: ^5.1.2
  shared_preferences: ^2.2.2
  permission_handler: ^11.2.0

dev_dependencies:
  flutter_test:
    sdk: flutter
```

### 2. Firebase Configuration

Add your Firebase configuration files:
- Android: `android/app/google-services.json`
- iOS: `ios/Runner/GoogleService-Info.plist`

### 3. Android Configuration

Update `android/app/build.gradle`:

```gradle
dependencies {
    implementation 'com.google.firebase:firebase-messaging:23.4.0'
    implementation 'com.google.firebase:firebase-analytics'
}
```

Add to `android/app/src/main/AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.RECEIVE_BOOT_COMPLETED" />
<uses-permission android:name="android.permission.VIBRATE" />
<uses-permission android:name="android.permission.WAKE_LOCK" />

<service
    android:name=".MyFirebaseMessagingService"
    android:exported="false">
    <intent-filter>
        <action android:name="com.google.firebase.MESSAGING_EVENT" />
    </intent-filter>
</service>
```

### 4. iOS Configuration

Add to `ios/Runner/Info.plist`:

```xml
<key>FirebaseAppDelegateProxyEnabled</key>
<false/>
```

## GraphQL Integration

### 1. GraphQL Client Setup

Create a GraphQL client service:

```dart
// lib/services/graphql_service.dart
import 'package:graphql_flutter/graphql_flutter.dart';

class GraphQLService {
  static late GraphQLClient _client;

  static void initialize(String endpoint) {
    final HttpLink httpLink = HttpLink(endpoint);

    _client = GraphQLClient(
      link: httpLink,
      cache: GraphQLCache(store: InMemoryStore()),
    );
  }

  static GraphQLClient get client => _client;
}
```

### 2. Initialize in main.dart

```dart
// lib/main.dart
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'services/graphql_service.dart';
import 'services/push_notification_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize Firebase
  await Firebase.initializeApp();

  // Initialize GraphQL
  GraphQLService.initialize('https://your-magento-store.com/graphql');

  // Initialize Push Notifications
  await PushNotificationService.initialize();

  runApp(MyApp());
}
```

## Firebase Cloud Messaging Setup

### 1. Push Notification Service

```dart
// lib/services/push_notification_service.dart
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:permission_handler/permission_handler.dart';
import 'dart:io';

class PushNotificationService {
  static FirebaseMessaging messaging = FirebaseMessaging.instance;
  static String? _token;

  static Future<void> initialize() async {
    // Request permissions
    await _requestPermissions();

    // Get FCM token
    await _getToken();

    // Setup message handlers
    _setupMessageHandlers();

    // Register token with Magento
    await _registerTokenWithMagento();
  }

  static Future<void> _requestPermissions() async {
    if (Platform.isIOS) {
      await Permission.notification.request();
    }

    NotificationSettings settings = await messaging.requestPermission(
      alert: true,
      announcement: false,
      badge: true,
      carPlay: false,
      criticalAlert: false,
      provisional: false,
      sound: true,
    );

    print('User granted permission: ${settings.authorizationStatus}');
  }

  static Future<void> _getToken() async {
    try {
      _token = await messaging.getToken();
      print('FCM Token: $_token');

      // Save token locally
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('fcm_token', _token ?? '');

    } catch (e) {
      print('Error getting FCM token: $e');
    }
  }

  static void _setupMessageHandlers() {
    // Handle foreground messages
    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      print('Received foreground message: ${message.messageId}');
      _handleMessage(message);
    });

    // Handle background messages when app is opened
    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      print('Message opened app: ${message.messageId}');
      _handleMessage(message);
    });

    // Handle token refresh
    messaging.onTokenRefresh.listen((String token) {
      print('Token refreshed: $token');
      _token = token;
      _registerTokenWithMagento();
    });
  }

  static void _handleMessage(RemoteMessage message) {
    print('Title: ${message.notification?.title}');
    print('Body: ${message.notification?.body}');
    print('Data: ${message.data}');

    // Handle action URL if present
    if (message.data.containsKey('action_url')) {
      // Navigate to specific screen or open URL
      _handleActionUrl(message.data['action_url']);
    }
  }

  static void _handleActionUrl(String actionUrl) {
    // Implement navigation logic based on action URL
    print('Action URL: $actionUrl');
  }

  static Future<void> _registerTokenWithMagento() async {
    if (_token == null) return;

    try {
      final result = await TokenRegistrationService.registerToken(
        token: _token!,
        deviceType: Platform.isIOS ? 'ios' : 'android',
      );

      if (result['success'] == true) {
        print('Token registered successfully');
      } else {
        print('Token registration failed: ${result['message']}');
      }
    } catch (e) {
      print('Error registering token: $e');
    }
  }

  static String? get token => _token;
}

// Background message handler (must be top-level function)
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  print('Background message: ${message.messageId}');
}
```

## Token Registration

### 1. GraphQL Mutations

```dart
// lib/services/token_registration_service.dart
import 'package:graphql_flutter/graphql_flutter.dart';
import 'graphql_service.dart';
import 'dart:io';

class TokenRegistrationService {
  static const String _registerTokenMutation = '''
    mutation RegisterPushNotificationToken(
      \$token: String!
      \$deviceType: String!
      \$deviceId: String
      \$deviceModel: String
      \$deviceVersion: String
      \$appVersion: String
    ) {
      registerPushNotificationToken(
        input: {
          token: \$token
          device_type: \$deviceType
          device_id: \$deviceId
          device_model: \$deviceModel
          os_version: \$deviceVersion
          app_version: \$appVersion
        }
      ) {
        success
        message
        customer_id
        is_guest
      }
    }
  ''';

  static const String _unregisterTokenMutation = '''
    mutation UnregisterPushNotificationToken(\$token: String!) {
      unregisterPushNotificationToken(input: { token: \$token }) {
        success
        message
      }
    }
  ''';

  static Future<Map<String, dynamic>> registerToken({
    required String token,
    required String deviceType,
    String? deviceId,
    String? deviceModel,
    String? deviceVersion,
    String? appVersion,
  }) async {
    try {
      final MutationOptions options = MutationOptions(
        document: gql(_registerTokenMutation),
        variables: {
          'token': token,
          'deviceType': deviceType,
          'deviceId': deviceId ?? _getDeviceId(),
          'deviceModel': deviceModel ?? _getDeviceModel(),
          'deviceVersion': deviceVersion ?? _getDeviceVersion(),
          'appVersion': appVersion ?? _getAppVersion(),
        },
      );

      final QueryResult result = await GraphQLService.client.mutate(options);

      if (result.hasException) {
        throw result.exception!;
      }

      return result.data?['registerPushNotificationToken'] ?? {};
    } catch (e) {
      throw Exception('Failed to register token: $e');
    }
  }

  static Future<Map<String, dynamic>> unregisterToken(String token) async {
    try {
      final MutationOptions options = MutationOptions(
        document: gql(_unregisterTokenMutation),
        variables: {'token': token},
      );

      final QueryResult result = await GraphQLService.client.mutate(options);

      if (result.hasException) {
        throw result.exception!;
      }

      return result.data?['unregisterPushNotificationToken'] ?? {};
    } catch (e) {
      throw Exception('Failed to unregister token: $e');
    }
  }

  static String _getDeviceId() {
    // Implement device ID generation
    // You can use device_info_plus package for unique device identifier
    return Platform.isIOS ? 'ios_device_id' : 'android_device_id';
  }

  static String _getDeviceModel() {
    // Implement device model detection
    return Platform.isIOS ? 'iPhone' : 'Android Device';
  }

  static String _getDeviceVersion() {
    // Implement OS version detection
    return Platform.operatingSystemVersion;
  }

  static String _getAppVersion() {
    // Get from package_info_plus plugin
    return '1.0.0';
  }
}
```

### 2. User Authentication Integration

**Important**: The token registration behavior differs based on authentication status:

- **Authenticated Users**: Token is associated with customer account (`customer_id` will be set)
- **Guest Users**: Token is registered as guest (`is_guest` will be `true`)

#### Authentication Setup

```dart
// lib/services/auth_graphql_service.dart
import 'package:graphql_flutter/graphql_flutter.dart';

class AuthGraphQLService {
  static GraphQLClient createAuthenticatedClient(
    String endpoint,
    String? authToken,
  ) {
    final Map<String, String> headers = {};

    if (authToken != null) {
      headers['Authorization'] = 'Bearer $authToken';
    }

    final HttpLink httpLink = HttpLink(
      endpoint,
      defaultHeaders: headers,
    );

    return GraphQLClient(
      link: httpLink,
      cache: GraphQLCache(store: InMemoryStore()),
    );
  }
}
```

#### Token Registration with Authentication

```dart
// lib/services/authenticated_token_service.dart
import 'package:graphql_flutter/graphql_flutter.dart';

class AuthenticatedTokenService {
  static Future<Map<String, dynamic>> registerTokenWithAuth({
    required String token,
    required String deviceType,
    required String? authToken,
    String? deviceId,
    String? deviceModel,
    String? deviceVersion,
    String? appVersion,
  }) async {
    // Create authenticated GraphQL client
    final client = AuthGraphQLService.createAuthenticatedClient(
      'https://your-magento-store.com/graphql',
      authToken,
    );

    const String mutation = '''
      mutation RegisterPushNotificationToken(
        \$token: String!
        \$deviceType: String!
        \$deviceId: String
        \$deviceModel: String
        \$deviceVersion: String
        \$appVersion: String
      ) {
        registerPushNotificationToken(
          input: {
            token: \$token
            device_type: \$deviceType
            device_id: \$deviceId
            device_model: \$deviceModel
            os_version: \$deviceVersion
            app_version: \$appVersion
          }
        ) {
          success
          message
          customer_id
          is_guest
        }
      }
    ''';

    try {
      final MutationOptions options = MutationOptions(
        document: gql(mutation),
        variables: {
          'token': token,
          'deviceType': deviceType,
          'deviceId': deviceId,
          'deviceModel': deviceModel,
          'deviceVersion': deviceVersion,
          'appVersion': appVersion,
        },
      );

      final QueryResult result = await client.mutate(options);

      if (result.hasException) {
        throw result.exception!;
      }

      final data = result.data?['registerPushNotificationToken'] ?? {};

      // Log registration status
      print('Token registration result:');
      print('- Success: ${data['success']}');
      print('- Message: ${data['message']}');
      print('- Customer ID: ${data['customer_id']}');
      print('- Is Guest: ${data['is_guest']}');

      return data;
    } catch (e) {
      throw Exception('Failed to register token: $e');
    }
  }
}
```

#### Usage Examples

**For Guest Users** (no authentication):
```dart
// Register token without authentication
final result = await TokenRegistrationService.registerToken(
  token: fcmToken,
  deviceType: Platform.isIOS ? 'ios' : 'android',
  deviceId: deviceId,
  deviceModel: deviceModel,
  deviceVersion: osVersion,
  appVersion: appVersion,
);

print('Registration result: ${result['is_guest']}'); // true
```

**For Authenticated Users** (with Bearer token):
```dart
// Register token with authentication
final result = await AuthenticatedTokenService.registerTokenWithAuth(
  token: fcmToken,
  deviceType: Platform.isIOS ? 'ios' : 'android',
  authToken: userAuthToken, // Bearer token from login
  deviceId: deviceId,
  deviceModel: deviceModel,
  deviceVersion: osVersion,
  appVersion: appVersion,
);

print('Registration result: ${result['is_guest']}'); // false
print('Customer ID: ${result['customer_id']}'); // actual customer ID
```

#### Authentication Flow Integration

```dart
// lib/services/push_notification_manager.dart
class PushNotificationManager {
  static Future<void> registerToken({
    required String token,
    String? authToken,
  }) async {
    try {
      Map<String, dynamic> result;

      if (authToken != null) {
        // User is logged in - register with authentication
        result = await AuthenticatedTokenService.registerTokenWithAuth(
          token: token,
          deviceType: Platform.isIOS ? 'ios' : 'android',
          authToken: authToken,
          deviceId: await _getDeviceId(),
          deviceModel: await _getDeviceModel(),
          deviceVersion: await _getDeviceVersion(),
          appVersion: await _getAppVersion(),
        );
      } else {
        // User is guest - register without authentication
        result = await TokenRegistrationService.registerToken(
          token: token,
          deviceType: Platform.isIOS ? 'ios' : 'android',
          deviceId: await _getDeviceId(),
          deviceModel: await _getDeviceModel(),
          deviceVersion: await _getDeviceVersion(),
          appVersion: await _getAppVersion(),
        );
      }

      // Handle result
      if (result['success'] == true) {
        print('Token registered successfully');
        print('Is guest: ${result['is_guest']}');
        if (result['customer_id'] != null) {
          print('Customer ID: ${result['customer_id']}');
        }
      } else {
        print('Token registration failed: ${result['message']}');
      }
    } catch (e) {
      print('Error registering token: $e');
    }
  }

  static Future<String> _getDeviceId() async {
    // Implement device ID generation
    // Use device_info_plus package for unique identifier
    return 'device_${DateTime.now().millisecondsSinceEpoch}';
  }

  static Future<String> _getDeviceModel() async {
    // Implement device model detection
    return Platform.isIOS ? 'iPhone' : 'Android Device';
  }

  static Future<String> _getDeviceVersion() async {
    // Implement OS version detection
    return Platform.operatingSystemVersion;
  }

  static Future<String> _getAppVersion() async {
    // Get from package_info_plus plugin
    return '1.0.0';
  }
}
```

## Handling Push Notifications

### 1. Local Notification Display

Add `flutter_local_notifications` to display notifications when app is in foreground:

```dart
// lib/services/local_notification_service.dart
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

class LocalNotificationService {
  static final FlutterLocalNotificationsPlugin _notifications =
      FlutterLocalNotificationsPlugin();

  static Future<void> initialize() async {
    const AndroidInitializationSettings androidSettings =
        AndroidInitializationSettings('@mipmap/ic_launcher');

    const DarwinInitializationSettings iosSettings =
        DarwinInitializationSettings(
      requestAlertPermission: true,
      requestBadgePermission: true,
      requestSoundPermission: true,
    );

    const InitializationSettings settings = InitializationSettings(
      android: androidSettings,
      iOS: iosSettings,
    );

    await _notifications.initialize(
      settings,
      onDidReceiveNotificationResponse: _onNotificationTapped,
    );
  }

  static Future<void> showNotification(RemoteMessage message) async {
    const AndroidNotificationDetails androidDetails =
        AndroidNotificationDetails(
      'push_notifications',
      'Push Notifications',
      channelDescription: 'Push notifications from the store',
      importance: Importance.high,
      priority: Priority.high,
    );

    const DarwinNotificationDetails iosDetails = DarwinNotificationDetails();

    const NotificationDetails details = NotificationDetails(
      android: androidDetails,
      iOS: iosDetails,
    );

    await _notifications.show(
      message.hashCode,
      message.notification?.title ?? 'New Notification',
      message.notification?.body ?? '',
      details,
      payload: message.data['action_url'],
    );
  }

  static void _onNotificationTapped(NotificationResponse response) {
    if (response.payload != null) {
      // Handle action URL
      print('Notification tapped with payload: ${response.payload}');
    }
  }
}
```

### 2. Update Push Service

Update the push notification service to use local notifications:

```dart
// Add to PushNotificationService._setupMessageHandlers()
static void _setupMessageHandlers() {
  // Handle foreground messages
  FirebaseMessaging.onMessage.listen((RemoteMessage message) {
    print('Received foreground message: ${message.messageId}');

    // Show local notification when app is in foreground
    LocalNotificationService.showNotification(message);

    _handleMessage(message);
  });

  // ... rest of the handlers
}
```

## Complete Implementation Example

### 1. Main App Structure

```dart
// lib/main.dart
import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'services/graphql_service.dart';
import 'services/push_notification_service.dart';
import 'services/local_notification_service.dart';

// Background message handler
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  print('Background message: ${message.messageId}');
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize Firebase
  await Firebase.initializeApp();

  // Set background message handler
  FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

  // Initialize services
  GraphQLService.initialize('https://your-magento-store.com/graphql');
  await LocalNotificationService.initialize();
  await PushNotificationService.initialize();

  runApp(MyApp());
}

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Push Notification Demo',
      theme: ThemeData(primarySwatch: Colors.blue),
      home: HomeScreen(),
    );
  }
}
```

### 2. Home Screen Example

```dart
// lib/screens/home_screen.dart
import 'package:flutter/material.dart';
import '../services/push_notification_service.dart';
import '../services/token_registration_service.dart';

class HomeScreen extends StatefulWidget {
  @override
  _HomeScreenState createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  String? _token;
  bool _isRegistered = false;

  @override
  void initState() {
    super.initState();
    _token = PushNotificationService.token;
  }

  Future<void> _registerToken() async {
    if (_token == null) return;

    try {
      final result = await TokenRegistrationService.registerToken(
        token: _token!,
        deviceType: 'android', // or 'ios'
      );

      setState(() {
        _isRegistered = result['success'] == true;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(result['message'] ?? 'Registration completed'),
          backgroundColor: _isRegistered ? Colors.green : Colors.red,
        ),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Future<void> _unregisterToken() async {
    if (_token == null) return;

    try {
      final result = await TokenRegistrationService.unregisterToken(_token!);

      setState(() {
        _isRegistered = !(result['success'] == true);
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(result['message'] ?? 'Unregistration completed'),
          backgroundColor: Colors.orange,
        ),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Push Notifications'),
      ),
      body: Padding(
        padding: EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Card(
              child: Padding(
                padding: EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'FCM Token:',
                      style: TextStyle(fontWeight: FontWeight.bold),
                    ),
                    SizedBox(height: 8),
                    Text(
                      _token ?? 'Not available',
                      style: TextStyle(fontSize: 12),
                    ),
                  ],
                ),
              ),
            ),
            SizedBox(height: 16),
            Text(
              'Status: ${_isRegistered ? "Registered" : "Not Registered"}',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
                color: _isRegistered ? Colors.green : Colors.red,
              ),
            ),
            SizedBox(height: 16),
            ElevatedButton(
              onPressed: _token != null ? _registerToken : null,
              child: Text('Register for Notifications'),
            ),
            SizedBox(height: 8),
            ElevatedButton(
              onPressed: _token != null ? _unregisterToken : null,
              style: ElevatedButton.styleFrom(backgroundColor: Colors.orange),
              child: Text('Unregister from Notifications'),
            ),
          ],
        ),
      ),
    );
  }
}
```

## Testing

### 1. Test Token Registration

```dart
// lib/utils/test_helpers.dart
class TestHelpers {
  static Future<void> testTokenRegistration() async {
    try {
      final result = await TokenRegistrationService.registerToken(
        token: 'test_token_123',
        deviceType: 'android',
        deviceModel: 'Test Device',
        deviceVersion: 'Android 12',
        appVersion: '1.0.0',
      );

      print('Registration result: $result');
    } catch (e) {
      print('Registration error: $e');
    }
  }

  static Future<void> testTokenUnregistration() async {
    try {
      final result = await TokenRegistrationService.unregisterToken('test_token_123');
      print('Unregistration result: $result');
    } catch (e) {
      print('Unregistration error: $e');
    }
  }
}
```

### 3. Firebase Console Testing

You can now test notifications directly from Firebase Console:

1. Go to Firebase Console → Messaging
2. Click "Send your first message"
3. Enter title and message
4. Select "Send test message to app"
5. Enter your FCM token
6. The message will be delivered using HTTP v1 API

### 2. GraphQL Query Examples

You can test these GraphQL mutations in your Magento GraphQL playground:

```graphql
# Register Token
mutation RegisterToken {
  registerPushNotificationToken(
    input: {
      token: "your_fcm_token_here"
      device_type: "android"
      device_model: "Samsung Galaxy S21"
      device_version: "Android 12"
      app_version: "1.0.0"
    }
  ) {
    success
    message
    token_id
  }
}

# Unregister Token
mutation UnregisterToken {
  unregisterPushNotificationToken(
    input: {
      token: "your_fcm_token_here"
    }
  ) {
    success
    message
  }
}
```

## Troubleshooting

### Common Issues

1. **Token not generated**
   - Check Firebase configuration files
   - Verify internet connectivity
   - Ensure proper permissions

2. **GraphQL mutations failing**
   - Verify GraphQL endpoint URL
   - Check network connectivity
   - Validate mutation syntax

3. **Notifications not received**
   - Check Firebase console for delivery reports
   - Verify Firebase service account JSON in Magento admin
   - Test with Firebase console test messages
   - Ensure Firebase Cloud Messaging API (v1) is enabled

4. **Background notifications not working**
   - Ensure background handler is registered
   - Check app background restrictions
   - Verify notification channel settings (Android)

5. **Firebase HTTP v1 API Issues**
   - Verify service account JSON format is correct
   - Check Firebase project ID matches exactly
   - Ensure OAuth2 token generation is working
   - Verify API permissions in Google Cloud Console

### Debug Tips

```dart
// Enable Firebase Messaging debug logging
await FirebaseMessaging.instance.setAutoInitEnabled(true);

// Log token changes
FirebaseMessaging.instance.onTokenRefresh.listen((token) {
  print('New token: $token');
});

// Test notification permissions
NotificationSettings settings = await FirebaseMessaging.instance.getNotificationSettings();
print('Authorization status: ${settings.authorizationStatus}');
```

### Android Specific Issues

1. **Add to `android/app/proguard-rules.pro`:**
```
-keep class com.google.firebase.** { *; }
-keep class com.google.android.gms.** { *; }
```

2. **Ensure minimum SDK version in `android/app/build.gradle`:**
```gradle
android {
    compileSdkVersion 33
    defaultConfig {
        minSdkVersion 21
        targetSdkVersion 33
    }
}
```

### iOS Specific Issues

1. **Enable Push Notifications capability in Xcode**
2. **Add Background Modes capability with "Background fetch" and "Remote notifications"**
3. **Ensure proper APNs certificates are configured in Firebase console**

## Support

For additional support:
- Check Magento admin logs at `var/log/system.log`
- Review Firebase console for delivery reports
- Monitor GraphQL responses for error details
- Use Flutter inspector for debugging UI issues

---

This integration provides a complete solution for handling push notifications between your Magento 2 store and Flutter application, with proper token management, GraphQL communication, and notification handling.
