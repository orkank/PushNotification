<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use IDangerous\PushNotification\Model\ResourceModel\Token\CollectionFactory;

class UnregisterPushNotificationToken implements ResolverInterface
{
    private CollectionFactory $collectionFactory;

    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
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

        $token = $input['token'];

        try {
            // Find and deactivate the token
            $tokenModel = $this->collectionFactory->create()
                ->addFieldToFilter('token', $token)
                ->getFirstItem();

            if ($tokenModel->getId()) {
                $tokenModel->setIsActive(false);
                $tokenModel->save();

                return [
                    'success' => true,
                    'message' => __('Token unregistered successfully')
                ];
            } else {
                return [
                    'success' => false,
                    'message' => __('Token not found')
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

