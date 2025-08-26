# Migration Guide: Legacy to Firebase HTTP v1 API

This guide helps you migrate from the deprecated Firebase Legacy Cloud Messaging API to the new Firebase HTTP v1 API.

## 🚨 Important Notice

**Firebase has deprecated the Legacy Cloud Messaging API and it's no longer accepting new registrations.** All projects must migrate to Firebase HTTP v1 API.

## What Changed

### Before (Legacy API)
- Used Firebase Server Key for authentication
- API endpoint: `https://fcm.googleapis.com/fcm/send`
- Simple key-based authentication
- Batch sending supported (up to 1000 tokens)

### After (HTTP v1 API)
- Uses OAuth2 service account for authentication
- API endpoint: `https://fcm.googleapis.com/v1/projects/{project_id}/messages:send`
- JWT-based authentication with Google OAuth2
- Individual token sending (more reliable)

## Migration Steps

### 1. Update Magento Configuration

#### Before:
- Only needed Firebase Server Key
- Project ID was optional

#### After:
1. Go to **Stores → Configuration → Push Notifications → Firebase Configuration**
2. You'll see the new interface with:
   - **Firebase Project ID** (required)
   - **Service Account JSON** (required - replaces server key)
   - **Firebase Server Key** (marked as deprecated)

### 2. Get Firebase Service Account

1. **Go to Firebase Console**:
   - Visit [Firebase Console](https://console.firebase.google.com/)
   - Select your project

2. **Navigate to Service Accounts**:
   - Go to Project Settings (gear icon)
   - Click "Service accounts" tab

3. **Generate Private Key**:
   - Click "Generate new private key"
   - Download the JSON file
   - **Keep this file secure - it contains sensitive credentials**

4. **Copy JSON Content**:
   - Open the downloaded JSON file
   - Copy the entire content
   - Paste it into the "Service Account JSON" field in Magento admin

### 3. Enable Firebase Cloud Messaging API v1

1. **Go to Google Cloud Console**:
   - Visit [Google Cloud Console](https://console.cloud.google.com/)
   - Select your Firebase project

2. **Enable the API**:
   - Navigate to "APIs & Services" → "Library"
   - Search for "Firebase Cloud Messaging API"
   - Click on it and press "Enable"

### 4. Verify Configuration

After configuration, test the setup:

1. **Send a test notification** from Magento admin
2. **Check logs** at `var/log/system.log` for any authentication errors
3. **Verify delivery** in Firebase Console → Messaging

## Configuration Example

### Service Account JSON Format
```json
{
  "type": "service_account",
  "project_id": "your-firebase-project-id",
  "private_key_id": "key-id-here",
  "private_key": "-----BEGIN PRIVATE KEY-----\nYour-Private-Key-Here\n-----END PRIVATE KEY-----\n",
  "client_email": "firebase-adminsdk-xxxxx@your-project.iam.gserviceaccount.com",
  "client_id": "123456789",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token",
  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
  "client_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs/firebase-adminsdk-xxxxx%40your-project.iam.gserviceaccount.com"
}
```

### Magento Admin Configuration
```
Stores → Configuration → Push Notifications → Firebase Configuration (HTTP v1 API)

✅ Enable Push Notifications: Yes
📋 Firebase Project ID: your-firebase-project-id
🔑 Service Account JSON: [Paste entire JSON content here]
❌ Firebase Server Key (Legacy): [Keep for backup, but not used]
```

## Troubleshooting Migration Issues

### 1. Authentication Errors

**Error**: `Firebase OAuth2 authentication failed`

**Solutions**:
- Verify the Service Account JSON is complete and valid
- Check that the private key includes proper line breaks (`\n`)
- Ensure the client_email matches your Firebase project

### 2. API Not Enabled

**Error**: `API not enabled`

**Solutions**:
- Enable Firebase Cloud Messaging API in Google Cloud Console
- Wait a few minutes for the API to activate
- Verify you're using the correct Google Cloud project

### 3. Project ID Mismatch

**Error**: `Project not found`

**Solutions**:
- Double-check the Project ID in Magento matches Firebase Console
- Ensure there are no extra spaces or characters
- Project ID is case-sensitive

### 4. Invalid JSON Format

**Error**: `Invalid service account JSON format`

**Solutions**:
- Verify JSON is properly formatted (use a JSON validator)
- Ensure all required fields are present
- Don't modify the downloaded JSON file

## Benefits of HTTP v1 API

### ✅ Improved Security
- OAuth2 authentication instead of static keys
- JWT tokens with expiration
- Better access control

### ✅ Better Reliability
- Individual token sending reduces failure rates
- More detailed error responses
- Improved delivery tracking

### ✅ Future-Proof
- Officially supported by Google
- Regular updates and improvements
- Long-term compatibility

### ✅ Enhanced Features
- Better platform-specific targeting
- Rich notification support
- Advanced delivery options

## Testing Your Migration

### 1. Basic Functionality Test
```bash
# Send a single notification from Magento admin
# Check system logs for any errors
tail -f var/log/system.log | grep -i firebase
```

### 2. Token Registration Test
```bash
# Test GraphQL token registration
# Verify tokens appear in admin panel
```

### 3. Batch Notification Test
```bash
# Send multiple notifications
# Check delivery reports in Firebase Console
```

## Rollback Plan

If you need to rollback (temporarily):

1. **Keep Legacy Server Key**: Don't delete it immediately
2. **Module Version**: You can temporarily use an older version of the module
3. **Gradual Migration**: Test with a subset of users first

**Note**: This is only a temporary solution as Firebase will completely disable the Legacy API soon.

## Support

If you encounter issues during migration:

1. **Check Logs**: Always start with `var/log/system.log`
2. **Verify Configuration**: Double-check all Firebase settings
3. **Test Incrementally**: Start with single notifications
4. **Firebase Console**: Check message delivery reports

## Migration Checklist

- [ ] Firebase Cloud Messaging API v1 enabled in Google Cloud Console
- [ ] Service account created and JSON downloaded
- [ ] Project ID configured in Magento admin
- [ ] Service Account JSON pasted in Magento admin
- [ ] Test notification sent successfully
- [ ] Logs show no authentication errors
- [ ] Token registration working via GraphQL
- [ ] Multiple notifications working
- [ ] Legacy server key kept as backup
- [ ] Documentation updated for developers

---

**Migration completed successfully?** 🎉

Your push notification system is now using the modern Firebase HTTP v1 API and is future-proof!
