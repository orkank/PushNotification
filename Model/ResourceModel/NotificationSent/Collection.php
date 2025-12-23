<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Model\ResourceModel\NotificationSent;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(
            \IDangerous\PushNotification\Model\NotificationSent::class,
            \IDangerous\PushNotification\Model\ResourceModel\NotificationSent::class
        );
    }
}
