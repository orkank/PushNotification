<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Ui\DataProvider;

use IDangerous\PushNotification\Model\ResourceModel\Token\CollectionFactory;
use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\SearchResultInterface;

class TokenDataProvider extends AbstractDataProvider
{
    private RequestInterface $request;
    private FilterBuilder $filterBuilder;
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->request = $request;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData()
    {
        $this->applyFilters();
        $this->applyPagination();
        $this->applySorting();

        if (!$this->getCollection()->isLoaded()) {
            $this->getCollection()->load();
        }
        $items = $this->getCollection()->toArray();

        return [
            'totalRecords' => $this->getCollection()->getSize(),
            'items' => array_values($items['items'] ?? [])
        ];
    }

    private function applyFilters()
    {
        $filters = $this->request->getParam('filters', []);

        if (!empty($filters) && is_array($filters)) {
            foreach ($filters as $field => $value) {
                if ($value !== null && $value !== '' && $value !== '0') {
                    switch ($field) {
                        case 'entity_id':
                            if (is_array($value) && isset($value['from']) && isset($value['to'])) {
                                if ($value['from']) {
                                    $this->getCollection()->addFieldToFilter('entity_id', ['gteq' => $value['from']]);
                                }
                                if ($value['to']) {
                                    $this->getCollection()->addFieldToFilter('entity_id', ['lteq' => $value['to']]);
                                }
                            } else {
                                $this->getCollection()->addFieldToFilter('entity_id', $value);
                            }
                            break;
                        case 'token':
                            $this->getCollection()->addFieldToFilter('token', ['like' => '%' . $value . '%']);
                            break;
                        case 'customer_email':
                            $this->getCollection()->addFieldToFilter('customer_email', ['like' => '%' . $value . '%']);
                            break;
                        case 'device_type':
                            $this->getCollection()->addFieldToFilter('device_type', $value);
                            break;
                        case 'device_model':
                            $this->getCollection()->addFieldToFilter('device_model', ['like' => '%' . $value . '%']);
                            break;
                        case 'app_version':
                            $this->getCollection()->addFieldToFilter('app_version', ['like' => '%' . $value . '%']);
                            break;
                        case 'is_active':
                            $this->getCollection()->addFieldToFilter('is_active', $value);
                            break;
                        case 'created_at':
                            if (is_array($value) && isset($value['from']) && isset($value['to'])) {
                                if ($value['from']) {
                                    $this->getCollection()->addFieldToFilter('created_at', ['gteq' => $value['from']]);
                                }
                                if ($value['to']) {
                                    $this->getCollection()->addFieldToFilter('created_at', ['lteq' => $value['to']]);
                                }
                            }
                            break;
                        case 'last_seen_at':
                            if (is_array($value) && isset($value['from']) && isset($value['to'])) {
                                if ($value['from']) {
                                    $this->getCollection()->addFieldToFilter('last_seen_at', ['gteq' => $value['from']]);
                                }
                                if ($value['to']) {
                                    $this->getCollection()->addFieldToFilter('last_seen_at', ['lteq' => $value['to']]);
                                }
                            }
                            break;
                        case 'store_id':
                            if ($value != '0') {
                                $this->getCollection()->addFieldToFilter('store_id', $value);
                            }
                            break;
                    }
                }
            }
        }
    }

    private function applyPagination()
    {
        $paging = $this->request->getParam('paging', []);
        $pageSize = isset($paging['pageSize']) ? (int)$paging['pageSize'] : 20;
        $currentPage = isset($paging['current']) ? (int)$paging['current'] : 1;

        // Ensure valid values
        $pageSize = max(1, min($pageSize, 200)); // Limit between 1 and 200
        $currentPage = max(1, $currentPage);

        $this->getCollection()->setPageSize($pageSize);
        $this->getCollection()->setCurPage($currentPage);
    }

    private function applySorting()
    {
        $sorting = $this->request->getParam('sorting', []);

        if (!empty($sorting) && is_array($sorting)) {
            foreach ($sorting as $field => $direction) {
                // Validate field name to prevent SQL injection
                $allowedFields = [
                    'entity_id', 'token', 'customer_id', 'customer_email',
                    'device_type', 'device_model', 'os_version', 'app_version',
                    'is_active', 'created_at', 'updated_at', 'last_seen_at', 'store_id'
                ];

                if (in_array($field, $allowedFields)) {
                    $this->getCollection()->setOrder($field, $direction);
                }
            }
        } else {
            // Default sorting
            $this->getCollection()->setOrder('entity_id', 'DESC');
        }
    }
}

