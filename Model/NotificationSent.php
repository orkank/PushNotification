<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Model;

use Magento\Framework\Model\AbstractModel;

class NotificationSent extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\IDangerous\PushNotification\Model\ResourceModel\NotificationSent::class);
    }

    public function getNotificationLogId(): ?int
    {
        $value = $this->getData('notification_log_id');
        return $value !== null ? (int)$value : null;
    }

    public function setNotificationLogId(int $notificationLogId): self
    {
        return $this->setData('notification_log_id', $notificationLogId);
    }

    public function getTokenId(): ?int
    {
        $value = $this->getData('token_id');
        return $value !== null ? (int)$value : null;
    }

    public function setTokenId(int $tokenId): self
    {
        return $this->setData('token_id', $tokenId);
    }

    public function getSentAt(): ?string
    {
        return $this->getData('sent_at');
    }

    public function setSentAt(string $sentAt): self
    {
        return $this->setData('sent_at', $sentAt);
    }
}
