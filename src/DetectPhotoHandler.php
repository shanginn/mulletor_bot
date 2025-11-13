<?php

declare(strict_types=1);

namespace Bot;

use Phenogram\Bindings\ApiInterface;
use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Bindings\Types\LabeledPrice;
use Phenogram\Bindings\Types\ReplyParameters;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\TelegramBot;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;
use Throwable;

class DetectPhotoHandler implements UpdateHandlerInterface
{
    private const string BOT_USERNAME = 'mulletor_bot';

    private LoggerInterface $logger;

    public function __construct(
        private MulletService $mulletService,
    ) {
        $this->logger = Discover::log() ?? new NullLogger();
    }

    public static function supports(UpdateInterface $update): bool
    {
//        return $update->message !== null && $update->message->photo !== null;
        // (message with photo or document of type image OR a reply to such message)
        // AND it's related to this bot:
        // 1. either it's a direct message
        // 2. or the bot is mentioned in the caption
        // 3. or it's a reply to the bot's message
        // 4. or the command /mullet is used in the caption
        $message = $update->message;

        if ($message === null) {
            return false;
        }

        $hasPhoto = $message->photo !== null ||
            ($message->document !== null && str_starts_with($message->document->mimeType ?? '', 'image/'));

        $repliedToPhoto = $message->replyToMessage !== null && (
            $message->replyToMessage->photo !== null ||
            ($message->replyToMessage->document !== null && str_starts_with($message->replyToMessage->document->mimeType ?? '', 'image/'))
        );

        if (!$hasPhoto && !$repliedToPhoto) {
            return false;
        }

        $isDirectMessage = $message->chat->type === 'private';
        $isMentioned = str_contains($message->text ?? $message->caption ?? '', static::BOT_USERNAME);
        $isReplyToBot = $message->replyToMessage?->from?->username === static::BOT_USERNAME;
        $hasMulletCommand = str_contains($message->text ?? $message->caption ?? '', '/mullet');

        return $isDirectMessage || $isMentioned || $isReplyToBot || $hasMulletCommand;
    }

    public function handle(UpdateInterface $update, TelegramBot $bot)
    {
        $message = $update->message;
        $chatId = $message->chat->id;

        try {
            // Get the photo to process
            $photoToProcess = null;

            // Check if this message has a photo
            if ($message->photo !== null) {
                $photoToProcess = end($message->photo); // Get the largest photo
            } elseif ($message->document !== null && str_starts_with($message->document->mimeType ?? '', 'image/')) {
                $photoToProcess = $message->document;
            }
            // Check if this is a reply to a message with a photo
            elseif ($message->replyToMessage !== null) {
                if ($message->replyToMessage->photo !== null) {
                    $photoToProcess = end($message->replyToMessage->photo);
                } elseif ($message->replyToMessage->document !== null && str_starts_with($message->replyToMessage->document->mimeType ?? '', 'image/')) {
                    $photoToProcess = $message->replyToMessage->document;
                }
            }

            if ($photoToProcess === null) {
                $bot->api->sendMessage(
                    chatId: $chatId,
                    text: '❌ Не вижу тут фотки',
                    replyParameters: $message->messageId ? new ReplyParameters(
                        messageId: $message->messageId,
                        allowSendingWithoutReply: true
                    ) : null,
                );
                return;
            }

            // Store payment context and get a short ID
            $paymentId = PaymentStorage::store(
                fileId: $photoToProcess->fileId,
                messageId: $message->messageId,
                chatId: $chatId
            );

            // Send invoice for payment
            $bot->api->sendInvoice(
                chatId: $chatId,
                title: '🎸 Маллет-трансформация',
                description: 'Превращение в легенду 80-х!',
                payload: $paymentId,
                currency: 'XTR',
                prices: [new LabeledPrice(label: 'Маллет', amount: 5)],
                replyParameters: $message->messageId ? new ReplyParameters(
                    messageId: $message->messageId,
                    allowSendingWithoutReply: true
                ) : null,
            );

            $this->logger->info("Invoice sent for photo: {$photoToProcess->fileId}");

        } catch (Throwable $e) {
            $this->logger->error("Failed to send invoice: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            $bot->api->sendMessage(
                chatId: $chatId,
                text: "❌ Что-то пошло не так, попробуй ещё раз \nОшибка: {$e->getMessage()}",
                replyParameters: $message->messageId ? new ReplyParameters(
                    messageId: $message->messageId,
                    allowSendingWithoutReply: true
                ) : null,
            );
        }
    }
}