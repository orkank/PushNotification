<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Block\Adminhtml\Notification;

use Magento\Backend\Block\Template;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;

class Multiple extends Template
{
    private GroupCollectionFactory $groupCollectionFactory;

    public function __construct(
        Template\Context $context,
        GroupCollectionFactory $groupCollectionFactory,
        array $data = []
    ) {
        $this->groupCollectionFactory = $groupCollectionFactory;
        parent::__construct($context, $data);
    }

    public function getCustomerGroups()
    {
        return $this->groupCollectionFactory->create();
    }

    public function getSendUrl()
    {
        return $this->getUrl('idangerous_pushnotification/notification/sendMultiple');
    }
}

