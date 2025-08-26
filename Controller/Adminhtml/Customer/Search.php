<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\App\RequestInterface;

class Search extends Action
{
    private JsonFactory $jsonFactory;
    private CollectionFactory $customerCollectionFactory;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        CollectionFactory $customerCollectionFactory
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->customerCollectionFactory = $customerCollectionFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $query = $this->getRequest()->getParam('q', '');
        $limit = (int)$this->getRequest()->getParam('limit', 10);

        if (empty($query)) {
            return $result->setData([]);
        }

        try {
            $collection = $this->customerCollectionFactory->create();
            $collection->addAttributeToSelect(['email', 'firstname', 'lastname', 'entity_id']);

            // Search by email, firstname, lastname, or entity_id
            $collection->addAttributeToFilter([
                ['attribute' => 'email', 'like' => '%' . $query . '%'],
                ['attribute' => 'firstname', 'like' => '%' . $query . '%'],
                ['attribute' => 'lastname', 'like' => '%' . $query . '%'],
                ['attribute' => 'entity_id', 'eq' => $query]
            ], null, 'left');

            $collection->setPageSize($limit);
            $collection->setOrder('email', 'ASC');

            $customers = [];
            foreach ($collection as $customer) {
                $customers[] = [
                    'id' => $customer->getId(),
                    'email' => $customer->getEmail(),
                    'name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
                    'text' => $customer->getEmail() . ' (' . $customer->getFirstname() . ' ' . $customer->getLastname() . ')'
                ];
            }

            return $result->setData($customers);
        } catch (\Exception $e) {
            return $result->setData([]);
        }
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('IDangerous_PushNotification::notification');
    }
}
