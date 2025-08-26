<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Ui\DataProvider;

use IDangerous\PushNotification\Model\ResourceModel\NotificationLog\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;

class LogDataProvider extends AbstractDataProvider
{
    protected $collection;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (!$this->getCollection()->isLoaded()) {
            $this->getCollection()->load();
        }

        $items = [];
        foreach ($this->getCollection()->getItems() as $log) {
            $data = $log->getData();

            // Add customer email if customer_id exists
            if (!empty($data['customer_id'])) {
                try {
                    $customer = $this->getCustomerById($data['customer_id']);
                    $data['customer_email'] = $customer ? $customer->getEmail() : null;
                } catch (\Exception $e) {
                    $data['customer_email'] = null;
                }
            } else {
                $data['customer_email'] = null;
            }

            $items[] = $data;
        }

        return [
            'totalRecords' => $this->getCollection()->getSize(),
            'items' => $items
        ];
    }

    private function getCustomerById($customerId)
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $customerRepository = $objectManager->create(\Magento\Customer\Api\CustomerRepositoryInterface::class);
            return $customerRepository->getById($customerId);
        } catch (\Exception $e) {
            return null;
        }
    }
}
