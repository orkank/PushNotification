<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Block\Adminhtml\Notification;

use Magento\Backend\Block\Template;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;

class Single extends Template
{
    private CustomerCollectionFactory $customerCollectionFactory;

    public function __construct(
        Template\Context $context,
        CustomerCollectionFactory $customerCollectionFactory,
        array $data = []
    ) {
        $this->customerCollectionFactory = $customerCollectionFactory;
        parent::__construct($context, $data);
    }

    public function getCustomers()
    {
        return $this->customerCollectionFactory->create()
            ->addAttributeToSelect(['email', 'firstname', 'lastname'])
            ->setOrder('email', 'ASC');
    }

    public function getSendUrl()
    {
        return $this->getUrl('idangerous_pushnotification/notification/sendSingle');
    }

    public function getCustomerSearchUrl()
    {
        return $this->getUrl('idangerous_pushnotification/customer/search');
    }
}

