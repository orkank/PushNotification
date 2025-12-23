<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;

class NotificationLog extends AbstractModel implements IdentityInterface
{
    const CACHE_TAG = 'idangerous_push_notification_log';
    const ENTITY_ID = 'entity_id';
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';

    protected function _construct()
    {
        $this->_init(\IDangerous\PushNotification\Model\ResourceModel\NotificationLog::class);
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getTitle(): ?string
    {
        return $this->getData('title');
    }

    public function setTitle(string $title): self
    {
        return $this->setData('title', $title);
    }

    public function getMessage(): ?string
    {
        return $this->getData('message');
    }

    public function setMessage(string $message): self
    {
        return $this->setData('message', $message);
    }

    public function getImageUrl(): ?string
    {
        return $this->getData('image_url');
    }

    public function setImageUrl(?string $imageUrl): self
    {
        return $this->setData('image_url', $imageUrl);
    }

    public function getActionUrl(): ?string
    {
        return $this->getData('action_url');
    }

    public function setActionUrl(?string $actionUrl): self
    {
        return $this->setData('action_url', $actionUrl);
    }

    public function getNotificationType(): string
    {
        return $this->getData('notification_type') ?: 'general';
    }

    public function setNotificationType(string $notificationType): self
    {
        return $this->setData('notification_type', $notificationType);
    }

    public function getCustomerId(): ?int
    {
        return $this->getData('customer_id') ? (int)$this->getData('customer_id') : null;
    }

    public function setCustomerId(?int $customerId): self
    {
        return $this->setData('customer_id', $customerId);
    }

    public function getFilters(): ?array
    {
        $filters = $this->getData('filters');
        if (!$filters) {
            return null;
        }

        // If it's already an array, return it
        if (is_array($filters)) {
            return $filters;
        }

        // If it's a string, decode it
        return is_string($filters) ? json_decode($filters, true) : null;
    }

    public function setFilters(?array $filters): self
    {
        return $this->setData('filters', $filters ? json_encode($filters) : null);
    }

    public function getTotalSent(): int
    {
        return (int)$this->getData('total_sent');
    }

    public function setTotalSent(int $totalSent): self
    {
        return $this->setData('total_sent', $totalSent);
    }

    public function getTotalFailed(): int
    {
        return (int)$this->getData('total_failed');
    }

    public function setTotalFailed(int $totalFailed): self
    {
        return $this->setData('total_failed', $totalFailed);
    }

    public function getStatus(): string
    {
        return $this->getData('status') ?: self::STATUS_PENDING;
    }

    public function setStatus(string $status): self
    {
        return $this->setData('status', $status);
    }

    public function getErrorMessage(): ?string
    {
        return $this->getData('error_message');
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        return $this->setData('error_message', $errorMessage);
    }

    public function getStoreId(): int
    {
        return (int)$this->getData('store_id');
    }

    public function setStoreId(int $storeId): self
    {
        return $this->setData('store_id', $storeId);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData('created_at');
    }

    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData('created_at', $createdAt);
    }

    public function getProcessedAt(): ?string
    {
        return $this->getData('processed_at');
    }

    public function setProcessedAt(?string $processedAt): self
    {
        return $this->setData('processed_at', $processedAt);
    }

    public function getContentHash(): ?string
    {
        return $this->getData('content_hash');
    }

    public function setContentHash(?string $contentHash): self
    {
        return $this->setData('content_hash', $contentHash);
    }

    public function getScheduledAt(): ?string
    {
        return $this->getData('scheduled_at');
    }

    public function setScheduledAt(?string $scheduledAt): self
    {
        return $this->setData('scheduled_at', $scheduledAt);
    }

    public function getCustomData(): ?array
    {
        $customData = $this->getData('custom_data');
        if (!$customData) {
            return null;
        }

        // If it's already an array, return it
        if (is_array($customData)) {
            return $customData;
        }

        // If it's a string, decode it
        return is_string($customData) ? json_decode($customData, true) : null;
    }

    public function setCustomData(?array $customData): self
    {
        return $this->setData('custom_data', $customData ? json_encode($customData) : null);
    }
}

