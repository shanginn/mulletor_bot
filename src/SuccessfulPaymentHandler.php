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
            // Retrieve payment context from storage
            $paymentId = $successfulPayment->invoicePayload;
            $context = PaymentStorage::retrieve($paymentId);

            if (!$context) {
                throw new \RuntimeException('Payment context not found or expired');
            }

            $fileId = $context['file_id'];
            $originalMessageId = $context['message_id'];
            $originalChatId = $context['chat_id'];

            // Send a "processing" message to the original chat
            $statusMessage = $bot->api->sendMessage(
                chatId: $originalChatId,
                text: '🎸 Делаю маллет... Минутку!',
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
                chatId: $originalChatId,
                messageId: $statusMessage->messageId,
            );

            // Send the result with watermarked image to the original chat
            $bot->api->sendPhoto(
                chatId: $originalChatId,
                photo: new LocalFile($watermarkedImagePath),
                caption: "🎸 Готово! Спереди — бизнес, сзади — вечеринка 🎸",
                replyParameters: $originalMessageId ? new ReplyParameters(
                    messageId: $originalMessageId,
                    allowSendingWithoutReply: true
                ) : null,
            );

            // Clean up temporary file
            @unlink($watermarkedImagePath);

            // Clean up payment storage
            PaymentStorage::remove($paymentId);

            $this->logger->info("Mullet sent to chat: {$originalChatId}");

        } catch (Throwable $e) {
            // Get original chat ID from storage if available
            $paymentId = $successfulPayment->invoicePayload;
            $context = PaymentStorage::retrieve($paymentId);
            $originalChatId = $context['chat_id'] ?? $chatId;

            $this->logger->error("Failed to create mullet after payment: {$e->getMessage()}", [
                'exception' => $e,
                'chat_id' => $chatId,
                'original_chat_id' => $originalChatId,
                'telegram_payment_charge_id' => $successfulPayment->telegramPaymentChargeId,
            ]);

            // Send error to dev chat
            try {
                $username = $message->from->username ? "@{$message->from->username}" : $message->from->firstName ?? 'Unknown';
                $bot->api->sendMessage(
                    chatId: self::DEV_CHAT_ID,
                    text: "❌ Mullet generation failed after payment\n\n" .
                          "User: {$username} (ID: {$message->from->id})\n" .
                          "Payment Chat: {$chatId}\n" .
                          "Original Chat: {$originalChatId}\n" .
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
                    chatId: $originalChatId,
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
                    chatId: $originalChatId,
                    text: "❌ Не получилось создать маллет. Напиши в поддержку для возврата денег",
                );
            }
        }
    }
}
