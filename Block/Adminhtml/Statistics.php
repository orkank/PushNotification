<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Block\Adminhtml;

use IDangerous\PushNotification\Model\ResourceModel\Token\CollectionFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;

class Statistics extends Template
{
    private CollectionFactory $collectionFactory;
    private ResourceConnection $resourceConnection;

    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        ResourceConnection $resourceConnection,
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context, $data);
    }

    public function getTotalTokens(): int
    {
        $collection = $this->collectionFactory->create();
        return $collection->getSize();
    }

    public function getActiveTokens(): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        return $collection->getSize();
    }

    public function getRegisteredUsers(): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_id', ['notnull' => true]);
        return $collection->getSize();
    }

    public function getGuestUsers(): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_id', ['null' => true]);
        return $collection->getSize();
    }

    public function getDeviceTypeDistribution(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('idangerous_push_notification_tokens');

        $select = $connection->select()
            ->from($tableName, ['device_type', 'COUNT(*) as count'])
            ->where('is_active = ?', 1)
            ->group('device_type');

        return $connection->fetchAll($select);
    }

    public function getDeviceModelDistribution(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('idangerous_push_notification_tokens');

        $select = $connection->select()
            ->from($tableName, ['device_model', 'COUNT(*) as count'])
            ->where('is_active = ?', 1)
            ->where('device_model IS NOT NULL')
            ->where('device_model != ?', '')
            ->group('device_model')
            ->order('count DESC')
            ->limit(50);

        return $connection->fetchAll($select);
    }

    public function getAppVersionDistribution(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('idangerous_push_notification_tokens');

        $select = $connection->select()
            ->from($tableName, ['app_version', 'COUNT(*) as count'])
            ->where('is_active = ?', 1)
            ->where('app_version IS NOT NULL')
            ->where('app_version != ?', '')
            ->group('app_version')
            ->order('count DESC')
            ->limit(10);

        return $connection->fetchAll($select);
    }

    public function getLastSeenStats(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('idangerous_push_notification_tokens');

        $stats = [];

        // Last 24 hours
        $select24h = $connection->select()
            ->from($tableName, ['COUNT(*) as count'])
            ->where('is_active = ?', 1)
            ->where('last_seen_at >= ?', date('Y-m-d H:i:s', strtotime('-24 hours')));
        $stats['last_24h'] = $connection->fetchOne($select24h);

        // Last 7 days
        $select7d = $connection->select()
            ->from($tableName, ['COUNT(*) as count'])
            ->where('is_active = ?', 1)
            ->where('last_seen_at >= ?', date('Y-m-d H:i:s', strtotime('-7 days')));
        $stats['last_7d'] = $connection->fetchOne($select7d);

        // Last 30 days
        $select30d = $connection->select()
            ->from($tableName, ['COUNT(*) as count'])
            ->where('is_active = ?', 1)
            ->where('last_seen_at >= ?', date('Y-m-d H:i:s', strtotime('-30 days')));
        $stats['last_30d'] = $connection->fetchOne($select30d);

        // Last 90 days
        $select90d = $connection->select()
            ->from($tableName, ['COUNT(*) as count'])
            ->where('is_active = ?', 1)
            ->where('last_seen_at >= ?', date('Y-m-d H:i:s', strtotime('-90 days')));
        $stats['last_90d'] = $connection->fetchOne($select90d);

        return $stats;
    }

    public function getNewTokensToday(): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('created_at', ['gteq' => date('Y-m-d 00:00:00')]);
        return $collection->getSize();
    }

    public function getNewTokensThisWeek(): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('created_at', ['gteq' => date('Y-m-d 00:00:00', strtotime('-7 days'))]);
        return $collection->getSize();
    }

    public function getNewTokensThisMonth(): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('created_at', ['gteq' => date('Y-m-01 00:00:00')]);
        return $collection->getSize();
    }
}
