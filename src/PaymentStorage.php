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
     * Generate a unique payment ID
     *
     * @return string Unique payment ID
     */
    public static function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Store payment context with a pre-generated ID
     *
     * @param string $paymentId
     * @param string $fileId
     * @param int    $messageId
     * @param int    $chatId
     * @param int    $invoiceMessageId
     */
    public static function storeWithId(string $paymentId, string $fileId, int $messageId, int $chatId, int $invoiceMessageId): void
    {
        self::$storage[$paymentId] = [
            'file_id' => $fileId,
            'message_id' => $messageId,
            'chat_id' => $chatId,
            'invoice_message_id' => $invoiceMessageId,
            'timestamp' => time(),
        ];
    }

    /**
     * Store payment context and return a unique ID
     *
     * @param string $fileId
     * @param int    $messageId
     * @param int    $chatId
     * @param int    $invoiceMessageId
     *
     * @return string Unique payment ID
     */
    public static function store(string $fileId, int $messageId, int $chatId, int $invoiceMessageId): string
    {
        $paymentId = self::generateId();
        self::storeWithId($paymentId, $fileId, $messageId, $chatId, $invoiceMessageId);
        return $paymentId;
    }

    /**
     * Retrieve payment context by ID
     *
     * @param string $paymentId
     *
     * @return array|null ['file_id' => string, 'message_id' => int, 'chat_id' => int, 'invoice_message_id' => int] or null if not found
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
