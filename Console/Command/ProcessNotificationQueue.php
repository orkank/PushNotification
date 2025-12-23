<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;
use IDangerous\PushNotification\Model\ResourceModel\NotificationLog\CollectionFactory as NotificationLogCollectionFactory;
use IDangerous\PushNotification\Model\Service\PushNotificationService;
use IDangerous\PushNotification\Model\ResourceModel\NotificationSent as NotificationSentResource;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Lock\LockManagerInterface;

class ProcessNotificationQueue extends Command
{
    private const COMMAND_NAME = 'idangerous:pushnotification:process-queue';
    private const LOCK_NAME = 'idangerous_pushnotification_bulk_processing';
    private const LOCK_TIMEOUT = 3600; // 1 hour

    private NotificationLogCollectionFactory $notificationLogCollectionFactory;
    private PushNotificationService $pushNotificationService;
    private NotificationSentResource $notificationSentResource;
    private DateTime $dateTime;
    private LoggerInterface $logger;
    private Json $json;
    private LockManagerInterface $lockManager;

    public function __construct(
        NotificationLogCollectionFactory $notificationLogCollectionFactory,
        PushNotificationService $pushNotificationService,
        NotificationSentResource $notificationSentResource,
        DateTime $dateTime,
        LoggerInterface $logger,
        Json $json,
        LockManagerInterface $lockManager
    ) {
        $this->notificationLogCollectionFactory = $notificationLogCollectionFactory;
        $this->pushNotificationService = $pushNotificationService;
        $this->notificationSentResource = $notificationSentResource;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
        $this->json = $json;
        $this->lockManager = $lockManager;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Process pending push notification queue')
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Number of notifications to process (default: 10)',
                10
            )
            ->addOption(
                'status',
                's',
                InputOption::VALUE_OPTIONAL,
                'Process notifications with specific status (pending, processing, failed)',
                'pending'
            )
            ->addOption(
                'force-retry',
                'f',
                InputOption::VALUE_NONE,
                'Force retry failed notifications'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Try to acquire lock to prevent concurrent execution
        if (!$this->lockManager->lock(self::LOCK_NAME, self::LOCK_TIMEOUT)) {
            $output->writeln('<error>Push notification processing is already running. Skipping this execution.</error>');
            $output->writeln('<comment>If you believe this is an error, the lock will automatically expire after ' . (self::LOCK_TIMEOUT / 60) . ' minutes.</comment>');
            $this->logger->info('Console command: Push notification processing is already running. Skipping.');
            return Cli::RETURN_FAILURE;
        }

        try {
            $limit = (int)$input->getOption('limit');
            $status = $input->getOption('status');
            $forceRetry = $input->getOption('force-retry');

            $output->writeln('<info>Starting push notification queue processing...</info>');
            $output->writeln("Processing notifications with status: <comment>{$status}</comment>");
            $output->writeln("Limit: <comment>{$limit}</comment>");

            // If force retry is enabled, reset failed notifications to pending
            if ($forceRetry) {
                $this->resetFailedNotifications($output);
            }

            // Recover stuck processing logs first (processing for more than 1 hour)
            $this->recoverStuckProcessingLogs($output);

        // Determine which statuses to process
        // If status is 'pending', also include 'processing' to recover stuck logs
        $statusesToProcess = [$status];
        if ($status === 'pending') {
            $statusesToProcess[] = 'processing';
        }

        // Only process logs that are scheduled for now or earlier (or have no scheduled_at)
        $currentTime = $this->dateTime->gmtDate();
        $pendingNotifications = $this->notificationLogCollectionFactory->create()
            ->addFieldToFilter('status', ['in' => $statusesToProcess])
            ->addFieldToFilter('notification_type', ['neq' => 'single']) // Skip single notifications
            ->addFieldToFilter('scheduled_at', [
                ['null' => true],
                ['lteq' => $currentTime]
            ])
            ->setPageSize($limit)
            ->setOrder('created_at', 'ASC')
            ->load();

        if ($pendingNotifications->getSize() == 0) {
            $output->writeln('<comment>No pending notifications found.</comment>');
            return Cli::RETURN_SUCCESS;
        }

        $output->writeln("Found <info>{$pendingNotifications->getSize()}</info> notifications to process.");
        $processed = 0;
        $successful = 0;
        $failed = 0;

        foreach ($pendingNotifications as $notificationLog) {
            try {
                // Try to atomically claim this notification by updating status to 'processing'
                // This prevents concurrent processes from processing the same notification
                $connection = $this->notificationSentResource->getConnection();
                $tableName = $connection->getTableName('idangerous_push_notification_logs');

                $affectedRows = $connection->update(
                    $tableName,
                    ['status' => 'processing', 'processed_at' => $this->dateTime->gmtDate()],
                    [
                        'entity_id = ?' => $notificationLog->getId(),
                        'status = ?' => 'pending' // Only update if still pending
                    ]
                );

                // If no rows were affected, another process already claimed this notification
                if ($affectedRows === 0) {
                    $output->writeln("Notification ID <comment>{$notificationLog->getId()}</comment> already being processed by another instance. Skipping.");
                    continue;
                }

                $output->writeln("Processing notification ID: <comment>{$notificationLog->getId()}</comment>");

                // Reload to get updated status
                $notificationLog->load($notificationLog->getId());

                // Debug: Log emoji debugging info
                $title = $notificationLog->getTitle();
                $message = $notificationLog->getMessage();

                $this->logger->debug("PushNotification Console: Processing notification", [
                    'id' => $notificationLog->getId(),
                    'title' => $title,
                    'message' => $message,
                    'title_hex' => bin2hex($title),
                    'message_hex' => bin2hex($message),
                    'title_encoding' => mb_detect_encoding($title),
                    'message_encoding' => mb_detect_encoding($message)
                ]);

                $output->writeln("  Title: {$title}");
                $output->writeln("  Message: {$message}");

                // Show scheduled time if set
                if ($scheduledAt = $notificationLog->getScheduledAt()) {
                    $output->writeln("  Scheduled: {$scheduledAt}");
                }

                // Don't set status to processing here - sendNotification will handle it
                // This prevents issues if sendNotification fails
                $filtersArray = $notificationLog->getFilters() ?: [];

                $output->writeln("  Filters: " . json_encode($filtersArray));

                // Pass the existing log entry to prevent duplicate creation
                // The log will be updated inside sendToMultipleUsers -> sendNotification
                // Don't set status to processing here - sendNotification will handle it
                $result = $this->pushNotificationService->sendToMultipleUsers(
                    $notificationLog->getTitle(),
                    $notificationLog->getMessage(),
                    $filtersArray,
                    $notificationLog->getImageUrl(),
                    $notificationLog->getActionUrl(),
                    $notificationLog->getNotificationType(),
                    $notificationLog->getCustomData(),
                    null, // silent
                    null, // badge
                    $notificationLog // existingLog - Pass existing log entry to prevent creating a new log
                );

                // Verify log was updated - if still processing, mark as completed
                $notificationLog->load($notificationLog->getId()); // Reload to get latest status
                if ($notificationLog->getStatus() === 'processing') {
                    $output->writeln("  <comment>Warning: Log still processing, forcing to completed</comment>");
                    $notificationLog->setStatus('completed');
                    $notificationLog->setProcessedAt($this->dateTime->gmtDate());
                    $notificationLog->save();
                }

                // Log is already updated in sendNotification, just update if needed
                if (isset($result['notification_id']) && $result['notification_id'] != $notificationLog->getId()) {
                    // This shouldn't happen if log was passed correctly, but log it if it does
                    $this->logger->warning('Console Command: Notification ID mismatch. Expected: ' . $notificationLog->getId() . ', Got: ' . $result['notification_id']);
                }

                if ($result['success']) {
                    $output->writeln("  <info>✓ Success</info> - Sent: {$result['total_sent']}, Failed: {$result['total_failed']}");
                    $successful++;
                } else {
                    $output->writeln("  <error>✗ Failed</error> - Error: {$result['message']}");
                    $failed++;
                }

                $processed++;

            } catch (\Exception $e) {
                $this->logger->error(
                    'PushNotification Console: Error processing notification log ID ' . $notificationLog->getId() . ': ' . $e->getMessage()
                );

                $notificationLog->setStatus('failed');
                $notificationLog->setErrorMessage($e->getMessage());
                $notificationLog->setProcessedAt($this->dateTime->gmtDate());
                $notificationLog->save();

                $output->writeln("  <error>✗ Exception</error> - {$e->getMessage()}");
                $failed++;
                $processed++;
            }
        }

            $output->writeln('');
            $output->writeln('<info>Processing completed!</info>');
            $output->writeln("Total processed: <comment>{$processed}</comment>");
            $output->writeln("Successful: <info>{$successful}</info>");
            $output->writeln("Failed: <error>{$failed}</error>");

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Fatal error: ' . $e->getMessage() . '</error>');
            $this->logger->error('Console command fatal error: ' . $e->getMessage());
            return Cli::RETURN_FAILURE;
        } finally {
            // Always release the lock when done
            $this->lockManager->unlock(self::LOCK_NAME);
            $output->writeln('<comment>Lock released.</comment>');
        }
    }

    private function resetFailedNotifications(OutputInterface $output): void
    {
        $failedNotifications = $this->notificationLogCollectionFactory->create()
            ->addStatusFilter('failed')
            ->load();

        if ($failedNotifications->getSize() > 0) {
            $output->writeln("<info>Resetting {$failedNotifications->getSize()} failed notifications to pending...</info>");

            foreach ($failedNotifications as $notification) {
                $notification->setStatus('pending');
                $notification->setErrorMessage(null);
                $notification->setProcessedAt(null);
                $notification->save();
            }
        }
    }

    /**
     * Recover stuck processing logs (processing for more than 1 hour)
     * These are logs that were interrupted by fatal errors or crashes
     *
     * @param OutputInterface $output
     * @return void
     */
    private function recoverStuckProcessingLogs(OutputInterface $output): void
    {
        $oneHourAgoTimestamp = time() - 3600; // 1 hour ago
        $currentTimestamp = time();

        $stuckLogs = $this->notificationLogCollectionFactory->create()
            ->addFieldToFilter('status', 'processing')
            ->addFieldToFilter('notification_type', ['neq' => 'single'])
            ->setPageSize(50);

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
                    $output->writeln("<info>Recovering stuck processing log ID: {$log->getId()} (Stuck for: {$hoursAgo} hours)</info>");

                    // Reset stuck log to pending so it can be processed again
                    // sendNotification will check sent tokens and determine if completed
                    $totalSent = (int)$log->getTotalSent();
                    $sentTokensCount = $this->getSentTokenCountFromDb((int)$log->getId());

                    $log->setStatus('pending');
                    $log->setProcessedAt(null);
                    $log->save();
                    $recoveredCount++;
                    $output->writeln("<info>Log ID {$log->getId()} reset to pending (total_sent: {$totalSent}, sent_in_db: {$sentTokensCount})</info>");
                }
            } catch (\Exception $e) {
                $output->writeln("<error>Failed to recover stuck log ID: {$log->getId()} - {$e->getMessage()}</error>");
                $this->logger->error('Console Command: Failed to recover stuck log ID: ' . $log->getId() . ' - ' . $e->getMessage());
            }
        }

        if ($recoveredCount > 0) {
            $output->writeln("<info>Recovered {$recoveredCount} stuck processing logs</info>");
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
            $this->logger->error('Console Command: Error getting sent token count: ' . $e->getMessage());
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

            $this->logger->info('Console Command: Cleaned up ' . $deleted . ' sent records for log ID: ' . $notificationLogId);
        } catch (\Exception $e) {
            $this->logger->error('Console Command: Error cleaning up sent records: ' . $e->getMessage());
        }
    }
}
