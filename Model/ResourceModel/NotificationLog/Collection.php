<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Model\ResourceModel\NotificationLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \IDangerous\PushNotification\Model\NotificationLog::class,
            \IDangerous\PushNotification\Model\ResourceModel\NotificationLog::class
        );
    }

    public function addStatusFilter(string $status): self
    {
        return $this->addFieldToFilter('status', $status);
    }

    public function addCustomerFilter(int $customerId): self
    {
        return $this->addFieldToFilter('customer_id', $customerId);
    }

    public function addStoreFilter(int $storeId): self
    {
        return $this->addFieldToFilter('store_id', $storeId);
    }

    public function addDateRangeFilter(string $fromDate, string $toDate): self
    {
        return $this->addFieldToFilter('created_at', [
            'from' => $fromDate,
            'to' => $toDate
        ]);
    }
}

