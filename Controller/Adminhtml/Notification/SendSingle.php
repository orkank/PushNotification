<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Controller\Adminhtml\Notification;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use IDangerous\PushNotification\Api\PushNotificationServiceInterface;

class SendSingle extends Action
{
    private JsonFactory $resultJsonFactory;
    private PushNotificationServiceInterface $pushNotificationService;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        PushNotificationServiceInterface $pushNotificationService
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->pushNotificationService = $pushNotificationService;
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $customerId = (int)$this->getRequest()->getParam('customer_id');
            $title = $this->getRequest()->getParam('title');
            $message = $this->getRequest()->getParam('message');
            $imageUrl = $this->getRequest()->getParam('image_url');
            $actionUrl = $this->getRequest()->getParam('action_url');
            $notificationType = $this->getRequest()->getParam('notification_type', 'general');
            $customData = $this->getRequest()->getParam('custom_data');

            // Ensure UTF-8 encoding for emoji support
            $title = mb_convert_encoding($title, 'UTF-8', 'auto');
            $message = mb_convert_encoding($message, 'UTF-8', 'auto');

            if (!$customerId || !$title || !$message) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('Required fields are missing.')
                ]);
            }

            // Parse custom data if provided
            $parsedCustomData = null;
            if ($customData) {
                if (is_string($customData)) {
                    $parsedCustomData = json_decode($customData, true);
                } elseif (is_array($customData)) {
                    $parsedCustomData = $customData;
                }
            }

            $result = $this->pushNotificationService->sendToSingleUser(
                $customerId,
                $title,
                $message,
                $imageUrl ? (string)$imageUrl : null,
                $actionUrl ? (string)$actionUrl : null,
                $notificationType,
                $parsedCustomData
            );

            return $resultJson->setData($result);
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



