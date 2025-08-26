<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Cron;

use IDangerous\PushNotification\Model\ResourceModel\NotificationLog\CollectionFactory;
use IDangerous\PushNotification\Model\Service\PushNotificationService;
use Psr\Log\LoggerInterface;

class ProcessNotificationQueue
{
    private CollectionFactory $logCollectionFactory;
    private PushNotificationService $pushNotificationService;
    private LoggerInterface $logger;

    public function __construct(
        CollectionFactory $logCollectionFactory,
        PushNotificationService $pushNotificationService,
        LoggerInterface $logger
    ) {
        $this->logCollectionFactory = $logCollectionFactory;
        $this->pushNotificationService = $pushNotificationService;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        try {
            $pendingLogs = $this->logCollectionFactory->create()
                ->addFieldToFilter('status', 'pending')
                ->addFieldToFilter('notification_type', ['neq' => 'single']) // Skip single notifications
                ->setPageSize(10) // Process 10 at a time
                ->setCurPage(1);

            foreach ($pendingLogs as $log) {
                try {
                    $this->processNotificationLog($log);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to process notification log ID: ' . $log->getId() . ' - ' . $e->getMessage());

                    // Mark as failed
                    $log->setStatus('failed');
                    $log->setErrorMessage($e->getMessage());
                    $log->save();
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Cron job failed: ' . $e->getMessage());
        }
    }

    private function processNotificationLog($log): void
    {
        // Mark as processing
        $log->setStatus('processing');
        $log->save();

        $filters = $log->getFilters() ? json_decode($log->getFilters(), true) : [];

        $result = $this->pushNotificationService->sendToMultipleUsers(
            $log->getTitle(),
            $log->getMessage(),
            $filters,
            $log->getImageUrl(),
            $log->getActionUrl(),
            $log->getNotificationType()
        );

        // Update log with results
        $log->setStatus($result['success'] ? 'completed' : 'failed');
        $log->setTotalSent($result['total_sent']);
        $log->setTotalFailed($result['total_failed']);
        $log->setProcessedAt(date('Y-m-d H:i:s'));

        if (!$result['success']) {
            $log->setErrorMessage($result['message']);
        }

        $log->save();

        $this->logger->info('Processed notification log ID: ' . $log->getId() . ' - Sent: ' . $result['total_sent'] . ' Failed: ' . $result['total_failed']);
    }
}
