<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use IDangerous\PushNotification\Model\TokenFactory;
use IDangerous\PushNotification\Model\ResourceModel\Token\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Customer\Api\CustomerRepositoryInterface;

class RegisterPushNotificationToken implements ResolverInterface
{
    private TokenFactory $tokenFactory;
    private CollectionFactory $collectionFactory;
    private StoreManagerInterface $storeManager;
    private DateTime $dateTime;
    private CustomerRepositoryInterface $customerRepository;

    public function __construct(
        TokenFactory $tokenFactory,
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager,
        DateTime $dateTime,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->tokenFactory = $tokenFactory;
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
        $this->dateTime = $dateTime;
        $this->customerRepository = $customerRepository;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($args['input'])) {
            throw new GraphQlInputException(__('Input is required'));
        }

        $input = $args['input'];

        if (empty($input['token'])) {
            throw new GraphQlInputException(__('Token is required'));
        }

        if (empty($input['device_type'])) {
            throw new GraphQlInputException(__('Device type is required'));
        }

        $token = $input['token'];
        $deviceType = $input['device_type'];
        $deviceId = $input['device_id'] ?? null;
        $deviceModel = $input['device_model'] ?? null;
        $osVersion = $input['os_version'] ?? null;
        $appVersion = $input['app_version'] ?? null;

        try {
            $currentTime = $this->dateTime->gmtDate();
            $storeId = (int)$this->storeManager->getStore()->getId();

            // Get customer information from GraphQL context
            $customerId = null;
            $customerEmail = null;

            if ($context->getUserId()) {
                $customerId = $context->getUserId();
                try {
                    $customer = $this->customerRepository->getById($customerId);
                    $customerEmail = $customer->getEmail();
                } catch (\Exception $e) {
                    // Customer not found, continue as guest
                    $customerId = null;
                    $customerEmail = null;
                }
            }

            // First, check if token already exists
            $existingToken = $this->collectionFactory->create()
                ->addFieldToFilter('token', $token)
                ->getFirstItem();

            if ($existingToken->getId()) {
                // Update existing token with same token
                $existingToken->setDeviceType($deviceType);
                $existingToken->setDeviceId($deviceId);
                $existingToken->setDeviceModel($deviceModel);
                $existingToken->setOsVersion($osVersion);
                $existingToken->setAppVersion($appVersion);
                $existingToken->setCustomerId($customerId);
                $existingToken->setCustomerEmail($customerEmail);
                $existingToken->setStoreId((int)$storeId);
                $existingToken->setIsActive(true);
                $existingToken->setLastSeenAt($currentTime);
                $existingToken->save();

                return [
                    'success' => true,
                    'message' => __('Token updated successfully'),
                    'customer_id' => $customerId,
                    'is_guest' => $customerId === null
                ];
            }

            // If device_id is provided, check if device already exists with different token
            if ($deviceId) {
                $existingDeviceToken = $this->collectionFactory->create()
                    ->addFieldToFilter('device_id', $deviceId)
                    ->addFieldToFilter('device_id', ['notnull' => true])
                    ->addFieldToFilter('store_id', $storeId)
                    ->getFirstItem();

                if ($existingDeviceToken->getId()) {
                    // Update existing device with new token
                    $existingDeviceToken->setToken($token);
                    $existingDeviceToken->setDeviceType($deviceType);
                    $existingDeviceToken->setDeviceModel($deviceModel);
                    $existingDeviceToken->setOsVersion($osVersion);
                    $existingDeviceToken->setAppVersion($appVersion);
                    $existingDeviceToken->setCustomerId($customerId);
                    $existingDeviceToken->setCustomerEmail($customerEmail);
                    $existingDeviceToken->setIsActive(true);
                    $existingDeviceToken->setLastSeenAt($currentTime);
                    $existingDeviceToken->save();

                    return [
                        'success' => true,
                        'message' => __('Device token updated successfully'),
                        'customer_id' => $customerId,
                        'is_guest' => $customerId === null
                    ];
                }
            }

            // Create new token
            $newToken = $this->tokenFactory->create();
            $newToken->setToken($token);
            $newToken->setDeviceType($deviceType);
            $newToken->setDeviceId($deviceId);
            $newToken->setDeviceModel($deviceModel);
            $newToken->setOsVersion($osVersion);
            $newToken->setAppVersion($appVersion);
            $newToken->setCustomerId($customerId);
            $newToken->setCustomerEmail($customerEmail);
            $newToken->setStoreId((int)$storeId);
            $newToken->setIsActive(true);
            $newToken->setCreatedAt($currentTime);
            $newToken->setUpdatedAt($currentTime);
            $newToken->setLastSeenAt($currentTime);
            $newToken->save();

            return [
                'success' => true,
                'message' => __('Token registered successfully'),
                'customer_id' => $customerId,
                'is_guest' => $customerId === null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'customer_id' => null,
                'is_guest' => true
            ];
        }
    }
}

