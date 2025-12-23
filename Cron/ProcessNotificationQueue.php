<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Cron;

use IDangerous\PushNotification\Model\ResourceModel\NotificationLog\CollectionFactory;
use IDangerous\PushNotification\Model\NotificationLogFactory;
use IDangerous\PushNotification\Model\Service\PushNotificationService;
use IDangerous\PushNotification\Model\ResourceModel\NotificationSent as NotificationSentResource;
use Psr\Log\LoggerInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class ProcessNotificationQueue
{
    private const LOCK_NAME = 'idangerous_pushnotification_bulk_processing';
    private const LOCK_TIMEOUT = 3600; // 1 hour

    private CollectionFactory $logCollectionFactory;
    private NotificationLogFactory $notificationLogFactory;
    private PushNotificationService $pushNotificationService;
    private NotificationSentResource $notificationSentResource;
    private LoggerInterface $logger;
    private LockManagerInterface $lockManager;
    private DateTime $dateTime;

    public function __construct(
        CollectionFactory $logCollectionFactory,
        NotificationLogFactory $notificationLogFactory,
        PushNotificationService $pushNotificationService,
        NotificationSentResource $notificationSentResource,
        LoggerInterface $logger,
        LockManagerInterface $lockManager,
        DateTime $dateTime
    ) {
        $this->logCollectionFactory = $logCollectionFactory;
        $this->notificationLogFactory = $notificationLogFactory;
        $this->pushNotificationService = $pushNotificationService;
        $this->notificationSentResource = $notificationSentResource;
        $this->logger = $logger;
        $this->lockManager = $lockManager;
        $this->dateTime = $dateTime;
    }

    public function execute(): void
    {
        // Try to acquire lock to prevent concurrent execution
        if (!$this->lockManager->lock(self::LOCK_NAME, self::LOCK_TIMEOUT)) {
            $this->logger->info('Push notification bulk processing is already running. Skipping this execution.');
            return;
        }

        try {
            // First, recover stuck processing logs (processing for more than 1 hour)
            $this->recoverStuckProcessingLogs();

            // Get pending and processing logs (processing ones might have been interrupted)
            // Order by created_at ASC to process oldest first
            // Only process logs that are scheduled for now or earlier (or have no scheduled_at)
            $currentTime = $this->dateTime->gmtDate();
            $pendingLogs = $this->logCollectionFactory->create()
                ->addFieldToFilter('status', ['in' => ['pending', 'processing']])
                ->addFieldToFilter('notification_type', ['neq' => 'single']) // Skip single notifications
                ->addFieldToFilter('scheduled_at', [
                    ['null' => true],
                    ['lteq' => $currentTime]
                ])
                ->setOrder('created_at', 'ASC')
                ->setPageSize(10) // Process 10 at a time
                ->setCurPage(1);

            $this->logger->info('ProcessNotificationQueue: Found ' . $pendingLogs->getSize() . ' logs to process');

            foreach ($pendingLogs as $log) {
                try {
                    $this->processNotificationLog($log);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to process notification log ID: ' . $log->getId() . ' - ' . $e->getMessage());

                    // Try to mark as failed, but don't fail if save fails
                    try {
                        $logModel = $this->notificationLogFactory->create()->load($log->getId());
                        if ($logModel->getId()) {
                            $logModel->setStatus('failed');
                            $logModel->setErrorMessage($e->getMessage());
                            $logModel->setProcessedAt($this->dateTime->gmtDate());
                            $logModel->save();
                        }
                    } catch (\Exception $saveException) {
                        $this->logger->error('Failed to save failed status for log ID: ' . $log->getId() . ' - ' . $saveException->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Cron job failed: ' . $e->getMessage());
        } finally {
            // Always release the lock when done
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    private function processNotificationLog($log): void
    {
        $logId = $log->getId() ?: $log->getData('entity_id');
        if (!$logId) {
            $this->logger->error('ProcessNotificationQueue: Log has no ID, skipping');
            return;
        }

        // Try to atomically claim this notification by updating status to 'processing'
        // This prevents concurrent processes from processing the same notification
        $connection = $this->notificationSentResource->getConnection();
        $tableName = $connection->getTableName('idangerous_push_notification_logs');

        $affectedRows = $connection->update(
            $tableName,
            ['status' => 'processing', 'processed_at' => $this->dateTime->gmtDate()],
            [
                'entity_id = ?' => $logId,
                'status = ?' => 'pending' // Only update if still pending
            ]
        );

        // If no rows were affected, another process already claimed this notification
        if ($affectedRows === 0) {
            $this->logger->info('ProcessNotificationQueue: Log ID ' . $logId . ' already being processed by another instance. Skipping.');
            return;
        }

        $this->logger->info('ProcessNotificationQueue: Processing log ID: ' . $logId . ', Title: ' . $log->getTitle());

        // Use NotificationLogFactory to load the log by ID to ensure we have a proper model instance
        $logModel = $this->notificationLogFactory->create()->load($logId);

        if (!$logModel->getId()) {
            $this->logger->error('ProcessNotificationQueue: Log ID ' . $logId . ' not found, skipping');
            return;
        }

        $this->logger->info('ProcessNotificationQueue: Loaded log ID: ' . $logModel->getId() . ', Status: ' . $logModel->getStatus() . ', Content Hash: ' . ($logModel->getContentHash() ?: 'not set'));

        // Ensure content_hash is set for older logs (backward compatibility)
        if (!$logModel->getContentHash()) {
            $filters = $logModel->getFilters() ? json_decode($logModel->getFilters(), true) : [];
            // Sort filters array by keys for consistent hash generation
            if (is_array($filters)) {
                ksort($filters);
            }
            $filtersJson = json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $contentHash = hash('sha256', $logModel->getTitle() . '|' . $logModel->getMessage() . '|' . $filtersJson . '|' . $logModel->getStoreId() . '|' . $logModel->getNotificationType());
            $logModel->setContentHash($contentHash);
            $logModel->save();
            $this->logger->info('ProcessNotificationQueue: Set content_hash for log ID ' . $logModel->getId() . ': ' . $contentHash);
        }

        $filters = $logModel->getFilters() ? json_decode($logModel->getFilters(), true) : [];

        // Pass the existing log entry to prevent duplicate creation
        // The log will be updated inside sendToMultipleUsers -> sendNotification
        // Sent tokens will be tracked in idangerous_push_notification_sent table
        $result = $this->pushNotificationService->sendToMultipleUsers(
            $logModel->getTitle(),
            $logModel->getMessage(),
            $filters,
            $logModel->getImageUrl(),
            $logModel->getActionUrl(),
            $logModel->getNotificationType(),
            null, // customData
            null, // silent
            null, // badge
            $logModel // existingLog - Pass existing log entry to prevent creating a new log
        );

        // Log is already updated in sendNotification, just log the result
        $this->logger->info('Processed notification log ID: ' . $logModel->getId() . ' - Sent: ' . $result['total_sent'] . ' Failed: ' . $result['total_failed']);

        // Verify log was updated - if still processing, mark as completed
        // This is a safety check in case sendNotification didn't update the status
        $logModel->load($logModel->getId()); // Reload to get latest status
        if ($logModel->getStatus() === 'processing') {
            $this->logger->warning('ProcessNotificationQueue: Log ID ' . $logModel->getId() . ' still processing after sendNotification, forcing to completed');
            $logModel->setStatus('completed');
            $logModel->setProcessedAt($this->dateTime->gmtDate());
            $logModel->save();
        }
    }

    /**
     * Recover stuck processing logs (processing for more than 1 hour)
     * These are logs that were interrupted by fatal errors or crashes
     *
     * @return void
     */
    private function recoverStuckProcessingLogs(): void
    {
        // Check for logs that have been processing for more than 1 hour
        // Use created_at as the base time since processed_at might be updated during processing
        $oneHourAgoTimestamp = time() - 3600; // 1 hour ago
        $currentTimestamp = time();

        $stuckLogs = $this->logCollectionFactory->create()
            ->addFieldToFilter('status', 'processing')
            ->addFieldToFilter('notification_type', ['neq' => 'single'])
            ->setPageSize(50);

        $this->logger->info('ProcessNotificationQueue: Checking ' . $stuckLogs->getSize() . ' processing logs for recovery');

        $recoveredCount = 0;
        foreach ($stuckLogs as $log) {
            try {
                $createdAt = $log->getCreatedAt();
                $processedAt = $log->getProcessedAt();

                // Check if log is stuck: created more than 1 hour ago
                // OR if processed_at exists and is more than 1 hour ago (stuck during processing)
                $isStuck = false;
                $checkTimestamp = null;

                if ($createdAt) {
                    $createdTimestamp = strtotime($createdAt);
                    if ($createdTimestamp && $createdTimestamp < $oneHourAgoTimestamp) {
                        // Created more than 1 hour ago
                        $isStuck = true;
                        $checkTimestamp = $createdTimestamp;
                    }
                }

                if (!$isStuck && $processedAt) {
                    $processedTimestamp = strtotime($processedAt);
                    if ($processedTimestamp && $processedTimestamp < $oneHourAgoTimestamp) {
                        // Processed more than 1 hour ago but still processing (stuck)
                        $isStuck = true;
                        $checkTimestamp = $processedTimestamp;
                    }
                }

                if ($isStuck) {
                    $hoursAgo = round(($currentTimestamp - $checkTimestamp) / 3600, 1);
                    $this->logger->info('ProcessNotificationQueue: Recovering stuck processing log ID: ' . $log->getId() . ' (Created: ' . $createdAt . ', Last processed: ' . ($processedAt ?: 'never') . ', Stuck for: ' . $hoursAgo . ' hours)');

                    // Load the log model to check its status
                    $logModel = $this->notificationLogFactory->create()->load($log->getId());
                    if ($logModel->getId()) {
                        // Check if log already has sent notifications using the tracking table
                        $totalSent = (int)$logModel->getTotalSent();
                        $sentTokensCount = $this->getSentTokenCountFromDb((int)$logModel->getId());

                        // If log has sent notifications and all tokens were sent, mark as completed and cleanup
                        if ($totalSent > 0 && $sentTokensCount >= $totalSent) {
                            $this->logger->info('ProcessNotificationQueue: Log ID ' . $logModel->getId() . ' already completed (' . $totalSent . ' sent), marking as completed');
                            $logModel->setStatus('completed');
                            $logModel->setProcessedAt($this->dateTime->gmtDate());
                            $logModel->save();

                            // Cleanup sent records
                            $this->cleanupSentRecordsForLog((int)$logModel->getId());

                            $recoveredCount++;
                            $this->logger->info('ProcessNotificationQueue: Successfully recovered log ID: ' . $logModel->getId() . ' as completed and cleaned up');
                        } else {
                            // Reset to pending so it can be processed again
                            // This allows recovery from fatal errors
                            $logModel->setStatus('pending');
                            $logModel->setProcessedAt(null);
                            $logModel->save();
                            $recoveredCount++;
                            $this->logger->info('ProcessNotificationQueue: Successfully recovered log ID: ' . $logModel->getId() . ' as pending (total_sent: ' . $totalSent . ', sent_in_db: ' . $sentTokensCount . ')');
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('ProcessNotificationQueue: Failed to recover stuck log ID: ' . $log->getId() . ' - ' . $e->getMessage());
            }
        }

        if ($recoveredCount > 0) {
            $this->logger->info('ProcessNotificationQueue: Recovered ' . $recoveredCount . ' stuck processing logs');
        } else {
            $this->logger->info('ProcessNotificationQueue: No stuck processing logs found');
        }
    }

    /**
     * Get sent token count from tracking table
     *
     * @param int $notificationLogId
     * @return int
     */
    private function getSentTokenCountFromDb(int $notificationLogId): int
    {
        try {
            $connection = $this->notificationSentResource->getConnection();
            $select = $connection->select()
                ->from($this->notificationSentResource->getMainTable(), ['COUNT(*)'])
                ->where('notification_log_id = ?', $notificationLogId);

            return (int)$connection->fetchOne($select);
        } catch (\Exception $e) {
            $this->logger->error('ProcessNotificationQueue: Error getting sent token count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Cleanup sent records for a notification log
     *
     * @param int $notificationLogId
     * @return void
     */
    private function cleanupSentRecordsForLog(int $notificationLogId): void
    {
        try {
            $connection = $this->notificationSentResource->getConnection();
            $deleted = $connection->delete(
                $this->notificationSentResource->getMainTable(),
                ['notification_log_id = ?' => $notificationLogId]
            );

            $this->logger->info('ProcessNotificationQueue: Cleaned up ' . $deleted . ' sent records for log ID: ' . $notificationLogId);
        } catch (\Exception $e) {
            $this->logger->error('ProcessNotificationQueue: Error cleaning up sent records: ' . $e->getMessage());
        }
    }
}
