<?php

declare(strict_types=1);

namespace Bot;

use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Bindings\Types\ReplyParameters;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\TelegramBot;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;
use Throwable;

class SuccessfulPaymentHandler implements UpdateHandlerInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private MulletService $mulletService,
    ) {
        $this->logger = Discover::log() ?? new NullLogger();
    }

    public static function supports(UpdateInterface $update): bool
    {
        return $update->message?->successfulPayment !== null;
    }

    public function handle(UpdateInterface $update, TelegramBot $bot)
    {
        $message = $update->message;
        $successfulPayment = $message->successfulPayment;
        $chatId = $message->chat->id;

        $this->logger->info("Successful payment received", [
            'chat_id' => $chatId,
            'total_amount' => $successfulPayment->totalAmount,
            'telegram_payment_charge_id' => $successfulPayment->telegramPaymentChargeId,
            'payload' => $successfulPayment->invoicePayload,
        ]);

        try {
            // Parse the payload to get the file_id
            $payload = json_decode($successfulPayment->invoicePayload, true);
            $fileId = $payload['file_id'] ?? null;
            $originalMessageId = $payload['message_id'] ?? null;

            if (!$fileId) {
                throw new \RuntimeException('No file_id in payment payload');
            }

            // Send a "processing" message
            $statusMessage = $bot->api->sendMessage(
                chatId: $chatId,
                text: '🎸 Делаю маллет... Минутку!',
                replyParameters: $originalMessageId ? new ReplyParameters(messageId: $originalMessageId) : null,
            );

            // Get the file URL from Telegram
            $file = $bot->api->getFile(fileId: $fileId);
            $fileUrl = "https://api.telegram.org/file/bot{$bot->getToken()}/{$file->filePath}";

            $this->logger->info("Processing mullet for paid photo: {$fileUrl}");

            // Transform the image
            $result = $this->mulletService->addMullet($fileUrl);
            $mulletImageUrl = $this->mulletService->getFirstImageUrl($result);

            $this->logger->info("Mullet created: {$mulletImageUrl}");

            // Delete the status message
            $bot->api->deleteMessage(
                chatId: $chatId,
                messageId: $statusMessage->messageId,
            );

            // Send the result
            $bot->api->sendPhoto(
                chatId: $chatId,
                photo: $mulletImageUrl,
                caption: "🎸 Готово! Спереди — бизнес, сзади — вечеринка 🎸",
                replyParameters: $originalMessageId ? new ReplyParameters(messageId: $originalMessageId) : null,
            );

            $this->logger->info("Mullet sent to chat: {$chatId}");

        } catch (Throwable $e) {
            $this->logger->error("Failed to create mullet after payment: {$e->getMessage()}", [
                'exception' => $e,
                'chat_id' => $chatId,
                'telegram_payment_charge_id' => $successfulPayment->telegramPaymentChargeId,
            ]);

            // Refund the user
            try {
                $bot->api->refundStarPayment(
                    userId: $message->from->id,
                    telegramPaymentChargeId: $successfulPayment->telegramPaymentChargeId,
                );

                $this->logger->info("Payment refunded", [
                    'user_id' => $message->from->id,
                    'telegram_payment_charge_id' => $successfulPayment->telegramPaymentChargeId,
                ]);

                $bot->api->sendMessage(
                    chatId: $chatId,
                    text: "❌ Не получилось создать маллет, деньги вернули\n\nОшибка: {$e->getMessage()}",
                );
            } catch (Throwable $refundError) {
                $this->logger->error("Failed to refund payment: {$refundError->getMessage()}", [
                    'exception' => $refundError,
                    'original_error' => $e->getMessage(),
                ]);

                $bot->api->sendMessage(
                    chatId: $chatId,
                    text: "❌ Не получилось создать маллет. Напиши в поддержку для возврата денег",
                );
            }
        }
    }
}
