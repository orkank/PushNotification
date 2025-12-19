<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Model\ResourceModel\Token;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \IDangerous\PushNotification\Model\Token::class,
            \IDangerous\PushNotification\Model\ResourceModel\Token::class
        );
    }

    public function addCustomerFilter(int $customerId): self
    {
        return $this->addFieldToFilter('customer_id', $customerId);
    }

    public function addDeviceTypeFilter(string $deviceType): self
    {
        return $this->addFieldToFilter('device_type', $deviceType);
    }

    public function addActiveFilter(): self
    {
        // Use explicit table prefix to avoid ambiguity when joins are present
        $this->getSelect()->where('main_table.is_active = ?', 1);
        return $this;
    }

    public function addStoreFilter(int $storeId): self
    {
        // Use explicit table prefix to avoid ambiguity when joins are present
        $this->getSelect()->where('main_table.store_id = ?', $storeId);
        return $this;
    }
}

