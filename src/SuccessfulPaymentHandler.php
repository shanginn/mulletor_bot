<?php

declare(strict_types=1);

namespace Bot;

use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Bindings\Types\ReplyParameters;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\TelegramBot;
use Phenogram\Framework\Type\LocalFile;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;
use Throwable;

class SuccessfulPaymentHandler implements UpdateHandlerInterface
{
    private const int DEV_CHAT_ID = -4576716287;

    private LoggerInterface $logger;

    public function __construct(
        private MulletService $mulletService,
        private ImageWatermarkService $watermarkService,
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
                text: '🎸 Ваш маллет готовится... Минутку!',
                replyParameters: $originalMessageId ? new ReplyParameters(
                    messageId: $originalMessageId,
                    allowSendingWithoutReply: true
                ) : null,
            );

            // Get the file URL from Telegram
            $file = $bot->api->getFile(fileId: $fileId);
            $fileUrl = "https://api.telegram.org/file/bot{$bot->getToken()}/{$file->filePath}";

            $this->logger->info("Processing mullet for paid photo: {$fileUrl}");

            // Transform the image
            $result = $this->mulletService->addMullet($fileUrl);
            $mulletImageUrl = $this->mulletService->getFirstImageUrl($result);

            $this->logger->info("Mullet created: {$mulletImageUrl}");

            // Add watermark to the image
            $watermarkedImagePath = $this->watermarkService->addWatermark($mulletImageUrl);

            $this->logger->info("Watermark added: {$watermarkedImagePath}");

            // Delete the status message
            $bot->api->deleteMessage(
                chatId: $chatId,
                messageId: $statusMessage->messageId,
            );

            // Send the result with watermarked image
            $bot->api->sendPhoto(
                chatId: $chatId,
                photo: new LocalFile($watermarkedImagePath),
                caption: "🎸 Готово! Спереди — бизнес, сзади — вечеринка 🎸\n\n Сделано с помощью @mulletor_bot",
                replyParameters: $originalMessageId ? new ReplyParameters(
                    messageId: $originalMessageId,
                    allowSendingWithoutReply: true
                ) : null,
            );

            // Clean up temporary file
            @unlink($watermarkedImagePath);

            $this->logger->info("Mullet sent to chat: {$chatId}");

        } catch (Throwable $e) {
            $this->logger->error("Failed to create mullet after payment: {$e->getMessage()}", [
                'exception' => $e,
                'chat_id' => $chatId,
                'telegram_payment_charge_id' => $successfulPayment->telegramPaymentChargeId,
            ]);

            // Send error to dev chat
            try {
                $username = $message->from->username ? "@{$message->from->username}" : $message->from->firstName ?? 'Unknown';
                $bot->api->sendMessage(
                    chatId: self::DEV_CHAT_ID,
                    text: "❌ Mullet generation failed after payment\n\n" .
                          "User: {$username} (ID: {$message->from->id})\n" .
                          "Chat: {$chatId}\n" .
                          "Payment ID: {$successfulPayment->telegramPaymentChargeId}\n" .
                          "Amount: {$successfulPayment->totalAmount} stars\n\n" .
                          "Error: {$e->getMessage()}\n\n" .
                          "File: {$e->getFile()}:{$e->getLine()}",
                );
            } catch (Throwable $notifyError) {
                $this->logger->error("Failed to send error to dev chat: {$notifyError->getMessage()}");
            }

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

                // Send refund failure to dev chat too
                try {
                    $bot->api->sendMessage(
                        chatId: self::DEV_CHAT_ID,
                        text: "🚨 CRITICAL: Failed to refund payment!\n\n" .
                              "User: {$message->from->id}\n" .
                              "Payment ID: {$successfulPayment->telegramPaymentChargeId}\n" .
                              "Refund error: {$refundError->getMessage()}",
                    );
                } catch (Throwable $criticalNotifyError) {
                    $this->logger->error("Failed to send critical error to dev chat: {$criticalNotifyError->getMessage()}");
                }

                $bot->api->sendMessage(
                    chatId: $chatId,
                    text: "❌ Не получилось создать маллет. Напиши в поддержку для возврата денег",
                );
            }
        }
    }
}
