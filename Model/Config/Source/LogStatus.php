<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LogStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'pending', 'label' => __('Pending')],
            ['value' => 'processing', 'label' => __('Processing')],
            ['value' => 'completed', 'label' => __('Completed')],
            ['value' => 'failed', 'label' => __('Failed')]
        ];
    }
}
