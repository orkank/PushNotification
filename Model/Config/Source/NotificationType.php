<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class NotificationType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'general', 'label' => __('General')],
            ['value' => 'promotion', 'label' => __('Promotion')],
            ['value' => 'order', 'label' => __('Order')],
            ['value' => 'news', 'label' => __('News')]
        ];
    }
}
