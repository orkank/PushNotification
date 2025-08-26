<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Token extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('idangerous_push_notification_tokens', 'entity_id');
    }
}

