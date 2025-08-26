<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Controller\Adminhtml\Notification;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use IDangerous\PushNotification\Api\PushNotificationServiceInterface;
use IDangerous\PushNotification\Model\NotificationLogFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;

class SendMultiple extends Action
{
    private JsonFactory $resultJsonFactory;
    private PushNotificationServiceInterface $pushNotificationService;
    private NotificationLogFactory $notificationLogFactory;
    private DateTime $dateTime;
    private StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        PushNotificationServiceInterface $pushNotificationService,
        NotificationLogFactory $notificationLogFactory,
        DateTime $dateTime,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->pushNotificationService = $pushNotificationService;
        $this->notificationLogFactory = $notificationLogFactory;
        $this->dateTime = $dateTime;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $title = $this->getRequest()->getParam('title');
            $message = $this->getRequest()->getParam('message');
            $imageUrl = $this->getRequest()->getParam('image_url');
            $actionUrl = $this->getRequest()->getParam('action_url');
            $notificationType = $this->getRequest()->getParam('notification_type', 'general');
            $customData = $this->getRequest()->getParam('custom_data');

            // Debug: Log input for emoji debugging
            $logger = \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class);
            $logger->debug("PushNotification Admin: Received input", [
                'title' => $title,
                'message' => $message,
                'title_hex' => bin2hex($title),
                'message_hex' => bin2hex($message),
                'title_encoding' => mb_detect_encoding($title),
                'message_encoding' => mb_detect_encoding($message),
                'raw_post' => file_get_contents('php://input'),
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
            ]);

            if (!$title || !$message) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('Required fields are missing.')
                ]);
            }

            // Build filters array
            $filters = [];

            if ($userType = $this->getRequest()->getParam('user_type')) {
                $filters['user_type'] = $userType;
            }

            if ($deviceType = $this->getRequest()->getParam('device_type')) {
                $filters['device_type'] = $deviceType;
            }

            if ($customerGroup = $this->getRequest()->getParam('customer_group')) {
                $filters['customer_group'] = $customerGroup;
            }

            if ($lastSeenFrom = $this->getRequest()->getParam('last_seen_from')) {
                $filters['last_seen_from'] = $lastSeenFrom;
            }

            if ($lastSeenTo = $this->getRequest()->getParam('last_seen_to')) {
                $filters['last_seen_to'] = $lastSeenTo;
            }

            if ($orderQuantity = $this->getRequest()->getParam('order_quantity')) {
                $filters['order_quantity'] = $orderQuantity;
            }

            // Ensure UTF-8 encoding for emoji support before saving to database
            $title = mb_convert_encoding($title, 'UTF-8', 'auto');
            $message = mb_convert_encoding($message, 'UTF-8', 'auto');

            // Parse custom data if provided
            $parsedCustomData = null;
            if ($customData) {
                if (is_string($customData)) {
                    $parsedCustomData = json_decode($customData, true);
                } elseif (is_array($customData)) {
                    $parsedCustomData = $customData;
                }
            }

            // Create notification log entry for async processing
            $notificationLog = $this->notificationLogFactory->create();
            $notificationLog->setTitle($title);
            $notificationLog->setMessage($message);
            $notificationLog->setImageUrl($imageUrl ? (string)$imageUrl : null);
            $notificationLog->setActionUrl($actionUrl ? (string)$actionUrl : null);
            $notificationLog->setCustomData($parsedCustomData);
            $notificationLog->setNotificationType($notificationType);
            $notificationLog->setFilters($filters);
            $notificationLog->setStoreId((int)$this->storeManager->getStore()->getId());
            $notificationLog->setCreatedAt($this->dateTime->gmtDate());
            $notificationLog->setStatus('pending');
            $notificationLog->save();

            return $resultJson->setData([
                'success' => true,
                'message' => __('Notification has been queued for processing. You can check the status in Notification Logs.'),
                'log_id' => $notificationLog->getId()
            ]);
        } catch (\Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('IDangerous_PushNotification::send_notification');
    }
}



