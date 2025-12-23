<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Model\Service;

use IDangerous\PushNotification\Model\ResourceModel\Token\CollectionFactory;
use IDangerous\PushNotification\Model\NotificationLogFactory;
use IDangerous\PushNotification\Model\ResourceModel\NotificationSent as NotificationSentResource;
use IDangerous\PushNotification\Model\ResourceModel\Token as TokenResource;
use IDangerous\PushNotification\Api\PushNotificationServiceInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class PushNotificationService implements PushNotificationServiceInterface
{
    private const FIREBASE_API_URL_V1 = 'https://fcm.googleapis.com/v1/projects/{project_id}/messages:send';
    private const SCOPE_STORE = ScopeInterface::SCOPE_STORE;
    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private CollectionFactory $tokenCollectionFactory;
    private NotificationLogFactory $notificationLogFactory;
    private NotificationSentResource $notificationSentResource;
    private TokenResource $tokenResource;
    private Curl $curl;
    private Json $json;
    private StoreManagerInterface $storeManager;
    private DateTime $dateTime;
    private ScopeConfigInterface $scopeConfig;
    private LoggerInterface $logger;

    public function __construct(
        CollectionFactory $tokenCollectionFactory,
        NotificationLogFactory $notificationLogFactory,
        NotificationSentResource $notificationSentResource,
        TokenResource $tokenResource,
        Curl $curl,
        Json $json,
        StoreManagerInterface $storeManager,
        DateTime $dateTime,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->tokenCollectionFactory = $tokenCollectionFactory;
        $this->notificationLogFactory = $notificationLogFactory;
        $this->notificationSentResource = $notificationSentResource;
        $this->tokenResource = $tokenResource;
        $this->curl = $curl;
        $this->json = $json;
        $this->storeManager = $storeManager;
        $this->dateTime = $dateTime;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function sendToSingleUser(
        int $customerId,
        string $title,
        string $message,
        ?string $imageUrl = null,
        ?string $actionUrl = null,
        string $notificationType = 'general',
        ?array $customData = null,
        ?bool $silent = null,
        ?int $badge = null
    ): array {
        $tokens = $this->tokenCollectionFactory->create()
            ->addCustomerFilter($customerId)
            ->addActiveFilter()
            ->addStoreFilter((int)$this->storeManager->getStore()->getId());

        return $this->sendNotification($tokens, $title, $message, $imageUrl, $actionUrl, $notificationType, $customerId, null, $customData, $silent, $badge);
    }

    public function sendToMultipleUsers(
        string $title,
        string $message,
        array $filters = [],
        ?string $imageUrl = null,
        ?string $actionUrl = null,
        string $notificationType = 'general',
        ?array $customData = null,
        ?bool $silent = null,
        ?int $badge = null,
        $existingLog = null
    ): array {
        $tokens = $this->tokenCollectionFactory->create()
            ->addActiveFilter()
            ->addStoreFilter((int)$this->storeManager->getStore()->getId());

        // Apply filters
        $tokens = $this->applyFilters($tokens, $filters);

        return $this->sendNotification($tokens, $title, $message, $imageUrl, $actionUrl, $notificationType, null, $filters, $customData, $silent, $badge, $existingLog);
    }

    public function sendToToken(
        string $token,
        string $title,
        string $message,
        ?string $imageUrl = null,
        ?string $actionUrl = null,
        string $notificationType = 'general',
        ?array $customData = null,
        ?bool $silent = null,
        ?int $badge = null
    ): array {
        return $this->sendNotificationToTokens(
            [$token],
            $title,
            $message,
            $imageUrl,
            $actionUrl,
            $notificationType,
            $customData,
            $silent,
            $badge
        );
    }

    private function sendNotification(
        $tokens,
        string $title,
        string $message,
        ?string $imageUrl,
        ?string $actionUrl,
        string $notificationType,
        ?int $customerId = null,
        ?array $filters = null,
        ?array $customData = null,
        ?bool $silent = null,
        ?int $badge = null,
        $existingLog = null
    ): array {
        $tokenList = [];
        $tokenIds = []; // Track token IDs for sent tracking
        $processedTokens = []; // Track processed tokens to prevent duplicates
        $customerIds = []; // Track customer IDs from tokens

        foreach ($tokens as $token) {
            $tokenString = $token->getToken();
            $tokenId = $token->getId();

            // Skip if token was already processed in this batch
            if (in_array($tokenString, $processedTokens)) {
                $this->logger->info(sprintf(
                    'Skipping duplicate token: %s',
                    substr($tokenString, 0, 20) . '...'
                ));
                continue;
            }
            $processedTokens[] = $tokenString;
            $tokenList[] = $tokenString;
            $tokenIds[] = $tokenId;

            // Collect customer IDs (only non-null ones)
            $tokenCustomerId = $token->getCustomerId();
            if ($tokenCustomerId) {
                $customerIds[] = $tokenCustomerId;
            }
        }

        // Determine customer_id for log: Only set if $customerId parameter is provided (single user send)
        // For bulk sends ($customerId is null), always keep customer_id as null regardless of token ownership
        $logCustomerId = null;
        if ($customerId !== null) {
            // Single user send - use the provided customer_id
            $logCustomerId = $customerId;
        } elseif (!empty($customerIds)) {
            // Bulk send - check if all tokens belong to same customer (for logging purposes only)
            $uniqueCustomerIds = array_unique($customerIds);
            if (count($uniqueCustomerIds) === 1) {
                $this->logger->info('PushNotification: Bulk send - all tokens belong to customer ID: ' . reset($uniqueCustomerIds) . ' but keeping customer_id NULL');
            } else {
                $this->logger->info('PushNotification: Bulk send - tokens belong to multiple customers, customer_id will be null');
            }
        }

        if (empty($tokenList)) {
            $this->logger->info('PushNotification: No active tokens found for sending');
            return [
                'success' => false,
                'message' => __('No active tokens found'),
                'total_sent' => 0,
                'total_failed' => 0
            ];
        }

        // Use existing log if provided (from cron), otherwise create new one
        $isExistingLog = false;
        if ($existingLog !== null) {
            $this->logger->info('PushNotification: $existingLog provided, type: ' . get_class($existingLog));

            // Check if it's a valid model instance with an ID
            $existingLogId = null;
            if (method_exists($existingLog, 'getId')) {
                $existingLogId = $existingLog->getId();
                $this->logger->info('PushNotification: $existingLog->getId() = ' . ($existingLogId ?: 'null/empty'));
            } else {
                $this->logger->warning('PushNotification: $existingLog does not have getId() method');
            }

            // Also try getData('entity_id') as fallback
            if (!$existingLogId && method_exists($existingLog, 'getData')) {
                $existingLogId = $existingLog->getData('entity_id');
                $this->logger->info('PushNotification: $existingLog->getData("entity_id") = ' . ($existingLogId ?: 'null/empty'));
            }

            if ($existingLogId) {
                $notificationLog = $existingLog;
                $isExistingLog = true;
                // Ensure status is processing
                $notificationLog->setStatus('processing');
                $notificationLog->save();
                $this->logger->info('PushNotification: Using existing log ID: ' . $notificationLog->getId() . ' (Title: ' . $notificationLog->getTitle() . ')');
            } else {
                $this->logger->warning('PushNotification: $existingLog provided but has no ID (getId=' . ($existingLogId ?? 'null') . '), creating new log');
            }
        } else {
            $this->logger->info('PushNotification: $existingLog is null, will create new log');
        }

        if (!$isExistingLog) {
            // Calculate content hash for duplicate detection
            $storeId = (int)$this->storeManager->getStore()->getId();
            // Sort filters array by keys for consistent hash generation
            $sortedFilters = $filters;
            if (is_array($sortedFilters)) {
                ksort($sortedFilters);
            }
            $filtersJson = json_encode($sortedFilters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $contentHash = hash('sha256', $title . '|' . $message . '|' . $filtersJson . '|' . $storeId . '|' . $notificationType);

            // Check for existing log with same content_hash before creating new one
            // IMPORTANT: Only check for duplicates from admin panel (when existingLog is not provided)
            // If existingLog is provided by cron/console, use that log regardless of content_hash
            $existingLogCollection = $this->notificationLogFactory->create()->getCollection();
            $existingLogCollection->addFieldToFilter('content_hash', $contentHash);
            $existingLogCollection->addFieldToFilter('status', ['in' => ['pending', 'processing', 'completed']]);
            $existingLogByHash = $existingLogCollection->getFirstItem();

            if ($existingLogByHash->getId()) {
                // Found existing log with same content - use it instead of creating new
                $notificationLog = $existingLogByHash;
                $isExistingLog = true;
                $this->logger->info('PushNotification: Found existing log with same content_hash (ID: ' . $notificationLog->getId() . '), reusing it instead of creating new');

                // Ensure status is processing
                if ($notificationLog->getStatus() === 'completed') {
                    // If completed, check sent tokens in tracking table - will be handled below
                    $this->logger->info('PushNotification: Existing log is completed, will check sent tokens in tracking table');
                } else {
                    $notificationLog->setStatus('processing');
                    $notificationLog->save();
                }
            } else {
                // Create notification log only if not found by hash
                $notificationLog = $this->notificationLogFactory->create();
                $notificationLog->setTitle($title);
                $notificationLog->setMessage($message);
                $notificationLog->setImageUrl($imageUrl);
                $notificationLog->setActionUrl($actionUrl);
                $notificationLog->setCustomData($customData);
                $notificationLog->setNotificationType($notificationType);
                // Use customer_id from parameter if provided, otherwise use the one determined from tokens
                $finalCustomerId = $customerId ?? $logCustomerId;
                $notificationLog->setCustomerId($finalCustomerId);
                $notificationLog->setFilters($filters);
                $notificationLog->setStoreId($storeId);
                $notificationLog->setContentHash($contentHash);
                $notificationLog->setCreatedAt($this->dateTime->gmtDate());
                $notificationLog->setStatus('processing');
                $notificationLog->save();
                $this->logger->info('PushNotification: Created new log ID: ' . $notificationLog->getId() . ' (Title: ' . $title . ', Customer ID: ' . ($finalCustomerId ?? 'null') . ', Content Hash: ' . $contentHash . ')');
            }
        } else {
            // For existing log, do not update customer_id (it was set during creation)
            // Ensure content_hash is set if missing
            if (!$notificationLog->getContentHash()) {
                $storeId = (int)$this->storeManager->getStore()->getId();
                // Sort filters array by keys for consistent hash generation
                $sortedFilters = $filters;
                if (is_array($sortedFilters)) {
                    ksort($sortedFilters);
                }
                $filtersJson = json_encode($sortedFilters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $contentHash = hash('sha256', $title . '|' . $message . '|' . $filtersJson . '|' . $storeId . '|' . $notificationType);
                $notificationLog->setContentHash($contentHash);
                $notificationLog->save();
                $this->logger->info('PushNotification: Set content_hash for existing log ID ' . $notificationLog->getId() . ': ' . $contentHash);
            }
        }


        // Check for already sent tokens using the tracking table
        // This prevents duplicate sends when a completed log is reset to pending
        // Uses efficient LEFT JOIN to filter out already sent tokens
        $tokensToSend = $tokenList;
        $tokenIdsToSend = $tokenIds;

        if ($notificationLog->getId()) {
            $logId = (int)$notificationLog->getId();
            $this->logger->info('PushNotification: Checking sent tokens for log ID: ' . $logId);

            // Get unsent tokens using LEFT JOIN (efficient database query)
            $unsentTokensData = $this->getUnsentTokensForLog($logId, $tokenIds);

            if (count($unsentTokensData) < count($tokenIds)) {
                // Some tokens were already sent
                $unsentTokenStrings = array_column($unsentTokensData, 'token');
                $unsentTokenIds = array_column($unsentTokensData, 'entity_id');

                $skippedCount = count($tokenIds) - count($unsentTokenIds);
                $this->logger->info('PushNotification: Skipping ' . $skippedCount . ' already sent tokens for log ID: ' . $logId);

                if (empty($unsentTokenIds)) {
                    // All tokens were already sent - mark as completed and cleanup
                    $this->logger->info('PushNotification: All tokens already sent for log ID: ' . $logId);
                    $notificationLog->setStatus('completed');
                    $notificationLog->setProcessedAt($this->dateTime->gmtDate());
                    $notificationLog->save();

                    // Cleanup sent records
                    $this->cleanupSentRecords($logId);

                    return [
                        'success' => true,
                        'message' => __('All recipients have already received this notification'),
                        'total_sent' => 0,
                        'total_failed' => 0,
                        'notification_id' => $logId
                    ];
                }

                $tokensToSend = $unsentTokenStrings;
                $tokenIdsToSend = $unsentTokenIds;

                $this->logger->info(sprintf(
                    'PushNotification: Log ID %d - Sending to %d unsent tokens (skipped %d)',
                    $logId,
                    count($tokensToSend),
                    $skippedCount
                ));
            } else {
                $this->logger->info('PushNotification: Log ID ' . $logId . ' - All ' . count($tokenIds) . ' tokens are unsent');
            }
        }

        // If no tokens to send, mark as completed immediately
        if (empty($tokensToSend)) {
            $this->logger->info('PushNotification: No tokens to send for log ID ' . $notificationLog->getId() . ', marking as completed');
            $notificationLog->setStatus('completed');
            $notificationLog->setProcessedAt($this->dateTime->gmtDate());
            $notificationLog->save();
            return [
                'success' => true,
                'message' => __('No tokens to send'),
                'total_sent' => 0,
                'total_failed' => 0,
                'notification_id' => $notificationLog->getId()
            ];
        }

        $result = null;
        try {
            $result = $this->sendNotificationToTokens($tokensToSend, $title, $message, $imageUrl, $actionUrl, $notificationType, $customData, $silent, $badge, $notificationLog->getId() ? (int)$notificationLog->getId() : null, $tokenIdsToSend);
        } catch (\Exception $e) {
            // If exception occurs during sending, create error result
            $this->logger->error('PushNotification: Exception in sendNotificationToTokens for log ID ' . $notificationLog->getId() . ': ' . $e->getMessage());
            $result = [
                'success' => false,
                'message' => $e->getMessage(),
                'total_sent' => 0,
                'total_failed' => count($tokensToSend),
                'error_message' => $e->getMessage()
            ];
        }

        // Update notification log - ALWAYS update status to completed or failed
        // This ensures log is never left in processing state
        try {
            $notificationLog->setTotalSent($result['total_sent'] + ($notificationLog->getTotalSent() ?: 0));
            $notificationLog->setTotalFailed($result['total_failed'] + ($notificationLog->getTotalFailed() ?: 0));

            // Determine final status: if we sent at least one notification successfully, mark as completed
            // If total_sent > 0, mark as completed even if some failed
            // Only mark as failed if total_sent is 0 AND we attempted to send
            if ($result['total_sent'] > 0) {
                $notificationLog->setStatus('completed');
            } elseif ($result['total_failed'] > 0 && $result['total_sent'] == 0) {
                $notificationLog->setStatus('failed');
                $notificationLog->setErrorMessage($result['error_message'] ?? __('Failed to send notification'));
            } else {
                // If no sends and no failures, mark as completed (all tokens were already sent)
                $notificationLog->setStatus('completed');
            }

            $notificationLog->setProcessedAt($this->dateTime->gmtDate());
            $notificationLog->save();

            $this->logger->info('PushNotification: Updated log ID ' . $notificationLog->getId() . ' - Status: ' . $notificationLog->getStatus() . ', Sent: ' . $notificationLog->getTotalSent() . ', Failed: ' . $notificationLog->getTotalFailed());

            // Cleanup sent records if status is completed
            if ($notificationLog->getStatus() === 'completed') {
                $this->cleanupSentRecords((int)$notificationLog->getId());
            }
        } catch (\Exception $saveException) {
            // Even if save fails, log it but don't throw - we've done our best
            $this->logger->error('PushNotification: Failed to update log ID ' . $notificationLog->getId() . ' after sending: ' . $saveException->getMessage());
        }

        $result['notification_id'] = $notificationLog->getId();
        return $result;
    }

    private function sendNotificationToTokens(
        array $tokens,
        string $title,
        string $message,
        ?string $imageUrl,
        ?string $actionUrl,
        string $notificationType,
        ?array $customData = null,
        ?bool $silent = null,
        ?int $badge = null,
        ?int $notificationLogId = null,
        array $tokenIds = []
    ): array {
        // If no tokens provided, return success with 0 sent
        if (empty($tokens)) {
            $this->logger->info('PushNotification: No tokens provided to sendNotificationToTokens');
            return [
                'success' => true,
                'message' => __('No tokens to send'),
                'total_sent' => 0,
                'total_failed' => 0
            ];
        }

        // Ensure UTF-8 encoding for emoji support
        $title = mb_convert_encoding($title, 'UTF-8', 'auto');
        $message = mb_convert_encoding($message, 'UTF-8', 'auto');

        // Get Firebase configuration
        $projectId = $this->scopeConfig->getValue(
            'idangerous_pushnotification/firebase/project_id',
            self::SCOPE_STORE
        );

        $serviceAccountJson = $this->scopeConfig->getValue(
            'idangerous_pushnotification/firebase/service_account_json',
            self::SCOPE_STORE
        );

        if (!$projectId || !$serviceAccountJson) {
            $this->logger->error('PushNotification: Firebase configuration missing - Project ID and Service Account JSON are required');
            return [
                'success' => false,
                'message' => __('Firebase project ID and service account JSON are required for HTTP v1 API'),
                'total_sent' => 0,
                'total_failed' => count($tokens),
                'error_message' => 'Firebase configuration missing'
            ];
        }

        try {
            // Get OAuth2 access token for Firebase HTTP v1 API
            $accessToken = $this->getFirebaseAccessToken($serviceAccountJson);

            $successCount = 0;
            $failureCount = 0;
            $errors = [];
            $successfullySentTokens = []; // Track successfully sent tokens

            // Firebase HTTP v1 API requires individual requests for each token
            foreach ($tokens as $token) {
                try {
                    // Build data payload with custom data
                    $dataPayload = [
                        'notification_type' => $notificationType,
                        'click_action' => $actionUrl ?: 'FLUTTER_NOTIFICATION_CLICK'
                    ];

                    // Add custom data if provided
                    if ($customData && is_array($customData)) {
                        foreach ($customData as $key => $value) {
                            // Ensure all values are strings for Firebase data payload
                            $dataPayload[$key] = (string)$value;
                        }
                    }

                    $payload = [
                        'message' => [
                            'token' => $token,
                            'notification' => [
                                'title' => $title,
                                'body' => $message
                            ],
                            'data' => $dataPayload,
                            'android' => [
                                'priority' => 'high',
                                'notification' => []
                            ],
                            'apns' => [
                                'payload' => [
                                    'aps' => []
                                ]
                            ]
                        ]
                    ];

                    // Handle sound/silent
                    if (!$silent) {
                        $payload['message']['android']['notification']['sound'] = 'default';
                        $payload['message']['apns']['payload']['aps']['sound'] = 'default';
                    }

                    // Handle badge
                    if ($badge !== null) {
                        $payload['message']['apns']['payload']['aps']['badge'] = (int)$badge;
                        // Android approximate badge via notification_count
                        $payload['message']['android']['notification']['notification_count'] = (int)$badge;
                    } else {
                        // Preserve previous default if none provided
                        $payload['message']['apns']['payload']['aps']['badge'] = 1;
                    }

                    if ($imageUrl) {
                        $payload['message']['notification']['image'] = $imageUrl;
                    }

                                        $apiUrl = str_replace('{project_id}', $projectId, self::FIREBASE_API_URL_V1);

                    // Reset curl headers for each request
                    $this->curl = new Curl();
                    $this->curl->addHeader('Authorization', 'Bearer ' . $accessToken);
                    $this->curl->addHeader('Content-Type', 'application/json; charset=utf-8');

                    // Ensure UTF-8 encoding for emoji support
                    $jsonPayload = $this->serializeJsonWithEmojiSupport($payload);

                    // Debug: Log payload for emoji debugging
                    $this->logger->debug("PushNotification: Sending payload", [
                        'title' => $title,
                        'message' => $message,
                        'title_hex' => bin2hex($title),
                        'message_hex' => bin2hex($message),
                        'json_payload' => $jsonPayload
                    ]);

                    $this->curl->post($apiUrl, $jsonPayload);

                    $response = $this->curl->getBody();
                    $httpStatus = $this->curl->getStatus();
                    $responseData = $this->json->unserialize($response);

                    if ($httpStatus === 200 && isset($responseData['name'])) {
                        $successCount++;
                        $successfullySentTokens[] = $token;
                    } else {
                        $errorMessage = 'Unknown error';
                        if (is_array($responseData) && isset($responseData['error']['message'])) {
                            $errorMessage = $responseData['error']['message'];
                        }

                        $this->logger->error("PushNotification: Failed to send to token", [
                            'error' => $errorMessage,
                            'http_status' => $httpStatus,
                            'response' => $response
                        ]);

                        // Delete token if it's invalid (404 = not found, 400 = invalid argument)
                        if ($httpStatus === 404 || ($httpStatus === 400 && strpos(strtolower($errorMessage), 'invalid') !== false)) {
                            $this->deleteInvalidToken($token, $errorMessage);
                        }

                        $failureCount++;
                        $errors[] = "Token: {$errorMessage}";
                    }
                } catch (\Exception $e) {
                    $failureCount++;
                    $errors[] = "Token {$token}: {$e->getMessage()}";
                }
            }

            // Mark successfully sent tokens in tracking table
            if ($notificationLogId && !empty($tokenIds)) {
                // Map successful token strings to their IDs
                $successfulTokenIds = [];
                foreach ($successfullySentTokens as $sentTokenString) {
                    $tokenIndex = array_search($sentTokenString, $tokens);
                    if ($tokenIndex !== false && isset($tokenIds[$tokenIndex])) {
                        $successfulTokenIds[] = $tokenIds[$tokenIndex];
                    }
                }

                if (!empty($successfulTokenIds)) {
                    $this->markTokensAsSent($notificationLogId, $successfulTokenIds);
                }
            }

            return [
                'success' => $successCount > 0,
                'message' => $successCount > 0 ?
                    __('Notification sent successfully to %1 devices', $successCount) :
                    __('Failed to send notification'),
                'total_sent' => $successCount,
                'total_failed' => $failureCount,
                'error_message' => !empty($errors) ? implode('; ', $errors) : null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Firebase authentication failed: %1', $e->getMessage()),
                'total_sent' => 0,
                'total_failed' => count($tokens),
                'error_message' => $e->getMessage()
            ];
        }
    }

    private function applyFilters($tokens, array $filters)
    {
        // Filter by user type
        if (isset($filters['user_type'])) {
            if ($filters['user_type'] === 'member') {
                $tokens->addFieldToFilter('customer_id', ['notnull' => true]);
            } elseif ($filters['user_type'] === 'guest') {
                $tokens->addFieldToFilter('customer_id', ['null' => true]);
            }
        }

        // Filter by device type
        if (isset($filters['device_type']) && $filters['device_type']) {
            $tokens->addDeviceTypeFilter($filters['device_type']);
        }

        // Filter by customer group
        if (isset($filters['customer_group']) && $filters['customer_group']) {
            $tokens->join(
                ['customer' => $tokens->getTable('customer_entity')],
                'main_table.customer_id = customer.entity_id',
                []
            )->addFieldToFilter('customer.group_id', $filters['customer_group']);
        }

        // Filter by last seen date range
        if (isset($filters['last_seen_from']) && $filters['last_seen_from']) {
            $tokens->addFieldToFilter('last_seen_at', ['gteq' => $filters['last_seen_from']]);
        }

        if (isset($filters['last_seen_to']) && $filters['last_seen_to']) {
            $tokens->addFieldToFilter('last_seen_at', ['lteq' => $filters['last_seen_to']]);
        }

        // Filter by order quantity
        if (isset($filters['order_quantity']) && $filters['order_quantity']) {
            $this->applyOrderQuantityFilter($tokens, $filters['order_quantity']);
        }

        return $tokens;
    }

    /**
     * Apply order quantity filter
     */
    private function applyOrderQuantityFilter($tokens, string $orderQuantity)
    {
        // Only apply to customers (not guests)
        $tokens->addFieldToFilter('customer_id', ['notnull' => true]);

        // Join with sales_order table to count orders
        $tokens->join(
            ['order_count' => $tokens->getTable('sales_order')],
            'main_table.customer_id = order_count.customer_id',
            []
        );

        switch ($orderQuantity) {
            case '0':
                // Customers with no orders (left join to find customers not in sales_order)
                $tokens->getSelect()->reset(\Zend_Db_Select::WHERE);
                $tokens->getSelect()->where('order_count.entity_id IS NULL');
                break;
            case '1':
                $tokens->getSelect()->group('main_table.customer_id');
                $tokens->getSelect()->having('COUNT(order_count.entity_id) = 1');
                break;
            case '2':
                $tokens->getSelect()->group('main_table.customer_id');
                $tokens->getSelect()->having('COUNT(order_count.entity_id) = 2');
                break;
            case '3':
                $tokens->getSelect()->group('main_table.customer_id');
                $tokens->getSelect()->having('COUNT(order_count.entity_id) = 3');
                break;
            case '4-10':
                $tokens->getSelect()->group('main_table.customer_id');
                $tokens->getSelect()->having('COUNT(order_count.entity_id) BETWEEN 4 AND 10');
                break;
            case '11-50':
                $tokens->getSelect()->group('main_table.customer_id');
                $tokens->getSelect()->having('COUNT(order_count.entity_id) BETWEEN 11 AND 50');
                break;
            case '51+':
                $tokens->getSelect()->group('main_table.customer_id');
                $tokens->getSelect()->having('COUNT(order_count.entity_id) >= 51');
                break;
        }
    }

    /**
     * Get Firebase OAuth2 access token using service account
     */
        private function getFirebaseAccessToken(string $serviceAccountJson): string
    {
        try {
            $serviceAccount = $this->json->unserialize($serviceAccountJson);

            if (!isset($serviceAccount['private_key']) || !isset($serviceAccount['client_email'])) {
                throw new \Exception('Invalid service account JSON format');
            }

            // Create JWT token
            $header = [
                'alg' => 'RS256',
                'typ' => 'JWT'
            ];

            $now = time();
            $payload = [
                'iss' => $serviceAccount['client_email'],
                'scope' => self::FCM_SCOPE,
                'aud' => self::OAUTH_TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600
            ];

            $headerEncoded = $this->base64UrlEncode($this->json->serialize($header));
            $payloadEncoded = $this->base64UrlEncode($this->json->serialize($payload));

            $signature = '';
            $success = openssl_sign(
                $headerEncoded . '.' . $payloadEncoded,
                $signature,
                $serviceAccount['private_key'],
                OPENSSL_ALGO_SHA256
            );

            if (!$success) {
                throw new \Exception('Failed to sign JWT token');
            }

            $signatureEncoded = $this->base64UrlEncode($signature);
            $jwt = $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;

            // Exchange JWT for access token
            $tokenPayload = [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ];

                        $curl = new Curl();
            $curl->addHeader('Content-Type', 'application/x-www-form-urlencoded');
            $curl->post(self::OAUTH_TOKEN_URL, http_build_query($tokenPayload));

            $response = $curl->getBody();
            $responseData = $this->json->unserialize($response);

            if (!isset($responseData['access_token'])) {
                $this->logger->error('PushNotification: Failed to get Firebase access token', [
                    'error' => $responseData['error_description'] ?? 'Unknown OAuth2 error'
                ]);
                throw new \Exception('Failed to get access token: ' . ($responseData['error_description'] ?? 'Unknown error'));
            }

            return $responseData['access_token'];

        } catch (\Exception $e) {
            throw new \Exception('Firebase OAuth2 authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Serialize JSON with proper UTF-8 emoji support
     */
    private function serializeJsonWithEmojiSupport(array $data): string
    {
        // Use PHP's native json_encode with UTF-8 flags for emoji support
        $jsonString = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("PushNotification: JSON encoding error", [
                'error' => json_last_error_msg(),
                'data' => $data
            ]);
            throw new \Exception('JSON encoding failed: ' . json_last_error_msg());
        }

        return $jsonString;
    }

    /**
     * Delete invalid token from database when Firebase rejects it
     */
    private function deleteInvalidToken(string $tokenString, string $errorMessage): void
    {
        try {
            $tokenModel = $this->tokenCollectionFactory->create()
                ->addFieldToFilter('token', $tokenString)
                ->getFirstItem();

            if ($tokenModel->getId()) {
                $tokenModel->delete();
                $this->logger->info("PushNotification: Deleted invalid token", [
                    'token_id' => $tokenModel->getId(),
                    'error' => $errorMessage
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error("PushNotification: Failed to delete invalid token", [
                'token' => substr($tokenString, 0, 20) . '...',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get unsent tokens for a specific notification log
     * Uses LEFT JOIN to efficiently filter out already sent tokens
     *
     * @param int $notificationLogId
     * @param array $allTokenIds
     * @return array
     */
    private function getUnsentTokensForLog(int $notificationLogId, array $allTokenIds): array
    {
        if (empty($allTokenIds)) {
            return [];
        }

        try {
            $connection = $this->notificationSentResource->getConnection();
            $select = $connection->select()
                ->from(['t' => $this->tokenResource->getMainTable()], ['entity_id', 'token'])
                ->joinLeft(
                    ['ns' => $this->notificationSentResource->getMainTable()],
                    'ns.token_id = t.entity_id AND ns.notification_log_id = ' . (int)$notificationLogId,
                    []
                )
                ->where('t.entity_id IN (?)', $allTokenIds)
                ->where('ns.entity_id IS NULL'); // Only unsent tokens

            return $connection->fetchAll($select);
        } catch (\Exception $e) {
            $this->logger->error('PushNotification: Error getting unsent tokens: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark tokens as sent in the tracking table
     * Uses batch INSERT for performance
     *
     * @param int $notificationLogId
     * @param array $tokenIds
     * @return void
     */
    private function markTokensAsSent(int $notificationLogId, array $tokenIds): void
    {
        if (empty($tokenIds)) {
            return;
        }

        try {
            $connection = $this->notificationSentResource->getConnection();
            $table = $this->notificationSentResource->getMainTable();

            $batchSize = 1000;
            $batches = array_chunk($tokenIds, $batchSize);

            foreach ($batches as $batch) {
                $data = [];
                foreach ($batch as $tokenId) {
                    $data[] = [
                        'notification_log_id' => $notificationLogId,
                        'token_id' => $tokenId,
                        'sent_at' => $this->dateTime->gmtDate()
                    ];
                }

                // INSERT IGNORE - Skip duplicates
                $connection->insertOnDuplicate($table, $data, []);
            }

            $this->logger->info('PushNotification: Marked ' . count($tokenIds) . ' tokens as sent for log ID: ' . $notificationLogId);
        } catch (\Exception $e) {
            $this->logger->error('PushNotification: Error marking tokens as sent: ' . $e->getMessage());
        }
    }

    /**
     * Cleanup sent records for a completed notification log
     * Called when log status becomes 'completed'
     *
     * @param int $notificationLogId
     * @return void
     */
    private function cleanupSentRecords(int $notificationLogId): void
    {
        try {
            $connection = $this->notificationSentResource->getConnection();
            $deleted = $connection->delete(
                $this->notificationSentResource->getMainTable(),
                ['notification_log_id = ?' => $notificationLogId]
            );

            $this->logger->info('PushNotification: Cleaned up ' . $deleted . ' sent records for log ID: ' . $notificationLogId);
        } catch (\Exception $e) {
            $this->logger->error('PushNotification: Error cleaning up sent records: ' . $e->getMessage());
        }
    }

    /**
     * Get count of sent tokens for a notification log
     *
     * @param int $notificationLogId
     * @return int
     */
    private function getSentTokenCount(int $notificationLogId): int
    {
        try {
            $connection = $this->notificationSentResource->getConnection();
            $select = $connection->select()
                ->from($this->notificationSentResource->getMainTable(), ['COUNT(*)'])
                ->where('notification_log_id = ?', $notificationLogId);

            return (int)$connection->fetchOne($select);
        } catch (\Exception $e) {
            $this->logger->error('PushNotification: Error getting sent token count: ' . $e->getMessage());
            return 0;
        }
    }
}