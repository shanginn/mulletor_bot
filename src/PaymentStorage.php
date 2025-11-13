<?php

declare(strict_types=1);

namespace Bot;

/**
 * Simple in-memory storage for payment context
 */
class PaymentStorage
{
    private static array $storage = [];

    /**
     * Store payment context and return a unique ID
     *
     * @param string $fileId
     * @param int    $messageId
     * @param int    $chatId
     *
     * @return string Unique payment ID
     */
    public static function store(string $fileId, int $messageId, int $chatId): string
    {
        $paymentId = bin2hex(random_bytes(8));

        self::$storage[$paymentId] = [
            'file_id' => $fileId,
            'message_id' => $messageId,
            'chat_id' => $chatId,
            'timestamp' => time(),
        ];

        return $paymentId;
    }

    /**
     * Retrieve payment context by ID
     *
     * @param string $paymentId
     *
     * @return array|null ['file_id' => string, 'message_id' => int, 'chat_id' => int] or null if not found
     */
    public static function retrieve(string $paymentId): ?array
    {
        return self::$storage[$paymentId] ?? null;
    }

    /**
     * Remove payment context by ID
     *
     * @param string $paymentId
     */
    public static function remove(string $paymentId): void
    {
        unset(self::$storage[$paymentId]);
    }

    /**
     * Clean up old entries (older than 1 hour)
     */
    public static function cleanup(): void
    {
        $now = time();
        $maxAge = 3600; // 1 hour

        foreach (self::$storage as $paymentId => $data) {
            if (($now - $data['timestamp']) > $maxAge) {
                unset(self::$storage[$paymentId]);
            }
        }
    }
}
