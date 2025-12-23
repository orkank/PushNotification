<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Cron;

use IDangerous\PushNotification\Model\ResourceModel\NotificationSent as NotificationSentResource;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ResourceConnection;

class CleanupOrphanedSentRecords
{
    private NotificationSentResource $notificationSentResource;
    private LoggerInterface $logger;
    private ResourceConnection $resourceConnection;

    public function __construct(
        NotificationSentResource $notificationSentResource,
        LoggerInterface $logger,
        ResourceConnection $resourceConnection
    ) {
        $this->notificationSentResource = $notificationSentResource;
        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Cleanup orphaned sent records for completed logs older than 7 days
     * This is a safety mechanism in case cleanup was skipped
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            
            // Get IDs of completed logs older than 7 days
            $completedLogIds = $connection->fetchCol(
                $connection->select()
                    ->from('idangerous_push_notification_logs', ['entity_id'])
                    ->where('status = ?', 'completed')
                    ->where('processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)')
            );
            
            if (empty($completedLogIds)) {
                $this->logger->info('CleanupOrphanedSentRecords: No old completed logs found');
                return;
            }
            
            // Delete sent records for these logs
            $deleted = $connection->delete(
                $this->notificationSentResource->getMainTable(),
                ['notification_log_id IN (?)' => $completedLogIds]
            );
            
            $this->logger->info('CleanupOrphanedSentRecords: Cleaned up ' . $deleted . ' orphaned sent records for ' . count($completedLogIds) . ' completed logs');
        } catch (\Exception $e) {
            $this->logger->error('CleanupOrphanedSentRecords: Error during cleanup: ' . $e->getMessage());
        }
    }
}
