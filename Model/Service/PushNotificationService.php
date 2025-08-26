<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Model\Service;

use IDangerous\PushNotification\Model\ResourceModel\Token\CollectionFactory;
use IDangerous\PushNotification\Model\NotificationLogFactory;
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
    private Curl $curl;
    private Json $json;
    private StoreManagerInterface $storeManager;
    private DateTime $dateTime;
    private ScopeConfigInterface $scopeConfig;
    private LoggerInterface $logger;

    public function __construct(
        CollectionFactory $tokenCollectionFactory,
        NotificationLogFactory $notificationLogFactory,
        Curl $curl,
        Json $json,
        StoreManagerInterface $storeManager,
        DateTime $dateTime,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->tokenCollectionFactory = $tokenCollectionFactory;
        $this->notificationLogFactory = $notificationLogFactory;
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
        ?array $customData = null
    ): array {
        $tokens = $this->tokenCollectionFactory->create()
            ->addCustomerFilter($customerId)
            ->addActiveFilter()
            ->addStoreFilter((int)$this->storeManager->getStore()->getId());

        return $this->sendNotification($tokens, $title, $message, $imageUrl, $actionUrl, $notificationType, $customerId, null, $customData);
    }

    public function sendToMultipleUsers(
        string $title,
        string $message,
        array $filters = [],
        ?string $imageUrl = null,
        ?string $actionUrl = null,
        string $notificationType = 'general',
        ?array $customData = null
    ): array {
        $tokens = $this->tokenCollectionFactory->create()
            ->addActiveFilter()
            ->addStoreFilter((int)$this->storeManager->getStore()->getId());

        // Apply filters
        $tokens = $this->applyFilters($tokens, $filters);

        return $this->sendNotification($tokens, $title, $message, $imageUrl, $actionUrl, $notificationType, null, $filters, $customData);
    }

    public function sendToToken(
        string $token,
        string $title,
        string $message,
        ?string $imageUrl = null,
        ?string $actionUrl = null,
        string $notificationType = 'general',
        ?array $customData = null
    ): array {
        return $this->sendNotificationToTokens(
            [$token],
            $title,
            $message,
            $imageUrl,
            $actionUrl,
            $notificationType,
            $customData
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
        ?array $customData = null
    ): array {
        $tokenList = [];
        foreach ($tokens as $token) {
            $tokenList[] = $token->getToken();
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

        // Create notification log
        $notificationLog = $this->notificationLogFactory->create();
        $notificationLog->setTitle($title);
        $notificationLog->setMessage($message);
        $notificationLog->setImageUrl($imageUrl);
        $notificationLog->setActionUrl($actionUrl);
        $notificationLog->setCustomData($customData);
        $notificationLog->setNotificationType($notificationType);
        $notificationLog->setCustomerId($customerId);
        $notificationLog->setFilters($filters);
        $notificationLog->setStoreId((int)$this->storeManager->getStore()->getId());
        $notificationLog->setCreatedAt($this->dateTime->gmtDate());
        $notificationLog->setStatus('processing');
        $notificationLog->save();

        $result = $this->sendNotificationToTokens($tokenList, $title, $message, $imageUrl, $actionUrl, $notificationType, $customData);

        // Update notification log
        $notificationLog->setTotalSent($result['total_sent']);
        $notificationLog->setTotalFailed($result['total_failed']);
        $notificationLog->setStatus($result['success'] ? 'completed' : 'failed');
        $notificationLog->setErrorMessage($result['error_message'] ?? null);
        $notificationLog->setProcessedAt($this->dateTime->gmtDate());
        $notificationLog->save();

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
        ?array $customData = null
    ): array {
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
                                'notification' => [
                                    'sound' => 'default'
                                ]
                            ],
                            'apns' => [
                                'payload' => [
                                    'aps' => [
                                        'sound' => 'default',
                                        'badge' => 1
                                    ]
                                ]
                            ]
                        ]
                    ];

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
                    } else {
                        $errorMessage = $responseData['error']['message'] ?? 'Unknown error';
                        $this->logger->error("PushNotification: Failed to send to token", [
                            'error' => $errorMessage,
                            'http_status' => $httpStatus
                        ]);
                        $failureCount++;
                        $errors[] = "Token: {$errorMessage}";
                    }
                } catch (\Exception $e) {
                    $failureCount++;
                    $errors[] = "Token {$token}: {$e->getMessage()}";
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
}