<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DeviceType implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'ios', 'label' => __('iOS')],
            ['value' => 'android', 'label' => __('Android')]
        ];
    }
}

