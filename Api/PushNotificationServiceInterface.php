<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Api;

interface PushNotificationServiceInterface
{
    /**
     * Send notification to a single user
     *
     * @param int $customerId
     * @param string $title
     * @param string $message
     * @param string|null $imageUrl
     * @param string|null $actionUrl
     * @param string $notificationType
     * @param array|null $customData
     * @return array
     */
    public function sendToSingleUser(
        int $customerId,
        string $title,
        string $message,
        ?string $imageUrl = null,
        ?string $actionUrl = null,
        string $notificationType = 'general',
        ?array $customData = null
    ): array;

    /**
     * Send notification to multiple users with filters
     *
     * @param string $title
     * @param string $message
     * @param array $filters
     * @param string|null $imageUrl
     * @param string|null $actionUrl
     * @param string $notificationType
     * @param array|null $customData
     * @return array
     */
    public function sendToMultipleUsers(
        string $title,
        string $message,
        array $filters = [],
        ?string $imageUrl = null,
        ?string $actionUrl = null,
        string $notificationType = 'general',
        ?array $customData = null
    ): array;

    /**
     * Send notification to a specific token
     *
     * @param string $token
     * @param string $message
     * @param string|null $imageUrl
     * @param string|null $actionUrl
     * @param string $notificationType
     * @param array|null $customData
     * @return array
     */
    public function sendToToken(
        string $token,
        string $title,
        string $message,
        ?string $imageUrl = null,
        ?string $actionUrl = null,
        string $notificationType = 'general',
        ?array $customData = null
    ): array;
}

