# Console Command Guide for Push Notifications

Since cron is not available in your development environment, I've created a console command that you can run manually to process the notification queue.

## 📋 **Console Command Available**

**Command**: `idangerous:pushnotification:process-queue`

## 🚀 **Basic Usage**

### **Process Pending Notifications**
```bash
php bin/magento idangerous:pushnotification:process-queue
```

### **Process with Options**
```bash
# Process only 5 notifications
php bin/magento idangerous:pushnotification:process-queue --limit=5

# Process failed notifications
php bin/magento idangerous:pushnotification:process-queue --status=failed

# Force retry failed notifications (resets failed to pending first)
php bin/magento idangerous:pushnotification:process-queue --force-retry

# Combine options
php bin/magento idangerous:pushnotification:process-queue --status=failed --limit=3 --force-retry
```

## ⚙️ **Command Options**

| Option | Short | Description | Default |
|--------|-------|-------------|---------|
| `--limit` | `-l` | Number of notifications to process | 10 |
| `--status` | `-s` | Process notifications with specific status (`pending`, `processing`, `failed`) | `pending` |
| `--force-retry` | `-f` | Reset failed notifications to pending before processing | - |

## 📊 **Status Types**

- **`pending`**: Newly created notifications waiting to be processed
- **`processing`**: Currently being processed (usually temporary)
- **`failed`**: Previously failed notifications
- **`completed`**: Successfully processed notifications

## 🔧 **Command Workflow**

1. **Queue Notifications**: Send multiple notifications from admin → Creates `pending` status
2. **Run Command**: Execute console command to process queue
3. **Processing**: Status changes to `processing` → Sends via Firebase → Updates to `completed`/`failed`
4. **Results**: View detailed output with success/failure counts

## 📋 **Example Workflow**

### **Step 1: Create Test Notification**
1. Go to admin: **Push Notifications → Send Multiple Notifications**
2. Fill form:
   ```
   Title: "Test Notification"
   Message: "Testing console command"
   User Type: "guest"
   ```
3. Submit → Creates notification with `pending` status

### **Step 2: Process Queue**
```bash
php bin/magento idangerous:pushnotification:process-queue
```

**Output:**
```
Starting push notification queue processing...
Processing notifications with status: pending
Limit: 10
Found 1 notifications to process.
Processing notification ID: 5
  Title: Test Notification
  Message: Testing console command
  Filters: {"user_type":"guest"}
  ✓ Success - Sent: 5, Failed: 0

Processing completed!
Total processed: 1
Successful: 1
Failed: 0
```

### **Step 3: Check Logs**
- Go to admin: **Push Notifications → Notification Logs**
- Verify status is `completed`
- Check sent/failed counts

## 🔄 **Development Workflow**

Since you don't have cron available, use this workflow:

1. **Send Notifications**: Use admin interface to queue notifications
2. **Process Manually**: Run console command when ready
3. **Debug Issues**: Use `--status=failed` to retry failed notifications
4. **Monitor Results**: Check admin logs for delivery reports

## 🛠️ **Troubleshooting**

### **No Notifications Found**
```bash
php bin/magento idangerous:pushnotification:process-queue --status=pending
# Output: No pending notifications found.
```
**Solution**: Create notifications via admin interface first.

### **Processing Failed Notifications**
```bash
# Reset failed notifications and retry
php bin/magento idangerous:pushnotification:process-queue --force-retry
```

### **Debug Specific Issues**
```bash
# Process only 1 notification for debugging
php bin/magento idangerous:pushnotification:process-queue --limit=1 --status=failed
```

### **Check All Statuses**
```bash
# Check pending
php bin/magento idangerous:pushnotification:process-queue --status=pending

# Check failed
php bin/magento idangerous:pushnotification:process-queue --status=failed

# Check processing (usually empty)
php bin/magento idangerous:pushnotification:process-queue --status=processing
```

## 🎯 **Firebase Configuration Required**

Before notifications can be sent successfully, ensure Firebase is configured:

1. **Admin Configuration**:
   - Go to **Stores → Configuration → Push Notifications → Firebase Configuration**
   - Enter **Firebase Project ID**
   - Paste **Service Account JSON**

2. **Firebase Setup**:
   - Enable Firebase Cloud Messaging API (v1) in Google Cloud Console
   - Generate service account key from Firebase Console

3. **Test Configuration**:
   ```bash
   php bin/magento idangerous:pushnotification:process-queue --limit=1
   ```

## 🔍 **Command Details**

### **Verbose Output**
The command provides detailed information:
- Notification ID being processed
- Title and message content
- Applied filters (JSON format)
- Success/failure status with counts
- Total processing summary

### **Error Handling**
- Catches and reports exceptions
- Updates notification status appropriately
- Continues processing remaining notifications
- Provides clear error messages

### **Logging**
- Errors are logged to `var/log/system.log`
- Admin panel shows detailed logs
- Console output shows real-time progress

## 📚 **Integration with Development**

### **Testing Workflow**
```bash
# 1. Create test notification via admin
# 2. Process queue
php bin/magento idangerous:pushnotification:process-queue

# 3. Check results
php bin/magento idangerous:pushnotification:process-queue --status=completed
```

### **Debugging Workflow**
```bash
# 1. Check what failed
php bin/magento idangerous:pushnotification:process-queue --status=failed

# 2. Retry with limited scope
php bin/magento idangerous:pushnotification:process-queue --status=failed --limit=1

# 3. Reset and retry all
php bin/magento idangerous:pushnotification:process-queue --force-retry
```

## ✅ **Ready to Use!**

Your console command is now ready to replace cron functionality in development:

1. ✅ **Manual Processing**: Run when needed instead of automatic cron
2. ✅ **Flexible Options**: Control what and how much to process
3. ✅ **Debug Friendly**: Clear output and error handling
4. ✅ **Status Management**: Handle pending, failed, and completed notifications
5. ✅ **Production Ready**: Same logic as cron, just manually triggered

---

**Next Steps:**
1. Configure Firebase credentials in admin
2. Create test notifications via admin interface
3. Run console command to process queue
4. Monitor results in admin logs
5. Use in your development workflow! 🚀
