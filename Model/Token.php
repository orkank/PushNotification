<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;

class Token extends AbstractModel implements IdentityInterface
{
    const CACHE_TAG = 'idangerous_push_notification_token';
    const ENTITY_ID = 'entity_id';

    protected function _construct()
    {
        $this->_init(\IDangerous\PushNotification\Model\ResourceModel\Token::class);
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getToken(): ?string
    {
        return $this->getData('token');
    }

    public function setToken(string $token): self
    {
        return $this->setData('token', $token);
    }

    public function getDeviceType(): ?string
    {
        return $this->getData('device_type');
    }

    public function setDeviceType(string $deviceType): self
    {
        return $this->setData('device_type', $deviceType);
    }

    public function getDeviceId(): ?string
    {
        return $this->getData('device_id');
    }

    public function setDeviceId(?string $deviceId): self
    {
        return $this->setData('device_id', $deviceId);
    }

    public function getDeviceModel(): ?string
    {
        return $this->getData('device_model');
    }

    public function setDeviceModel(?string $deviceModel): self
    {
        return $this->setData('device_model', $deviceModel);
    }

    public function getOsVersion(): ?string
    {
        return $this->getData('os_version');
    }

    public function setOsVersion(?string $osVersion): self
    {
        return $this->setData('os_version', $osVersion);
    }

    public function getAppVersion(): ?string
    {
        return $this->getData('app_version');
    }

    public function setAppVersion(?string $appVersion): self
    {
        return $this->setData('app_version', $appVersion);
    }

    public function getCustomerId(): ?int
    {
        return $this->getData('customer_id') ? (int)$this->getData('customer_id') : null;
    }

    public function setCustomerId(?int $customerId): self
    {
        return $this->setData('customer_id', $customerId);
    }

    public function getCustomerEmail(): ?string
    {
        return $this->getData('customer_email');
    }

    public function setCustomerEmail(?string $customerEmail): self
    {
        return $this->setData('customer_email', $customerEmail);
    }

    public function getStoreId(): int
    {
        return (int)$this->getData('store_id');
    }

    public function setStoreId(int $storeId): self
    {
        return $this->setData('store_id', $storeId);
    }

    public function getIsActive(): bool
    {
        return (bool)$this->getData('is_active');
    }

    public function setIsActive(bool $isActive): self
    {
        return $this->setData('is_active', $isActive);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData('created_at');
    }

    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData('created_at', $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData('updated_at');
    }

    public function setUpdatedAt(string $updatedAt): self
    {
        return $this->setData('updated_at', $updatedAt);
    }

    public function getLastSeenAt(): ?string
    {
        return $this->getData('last_seen_at');
    }

    public function setLastSeenAt(string $lastSeenAt): self
    {
        return $this->setData('last_seen_at', $lastSeenAt);
    }
}

