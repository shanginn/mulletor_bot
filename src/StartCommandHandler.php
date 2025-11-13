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

class StartCommandHandler implements UpdateHandlerInterface
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = Discover::log() ?? new NullLogger();
    }

    public static function supports(UpdateInterface $update): bool
    {
        $message = $update->message;

        if ($message === null) {
            return false;
        }

        $text = $message->text ?? '';

        return str_starts_with($text, '/start');
    }

    public function handle(UpdateInterface $update, TelegramBot $bot)
    {
        $message = $update->message;
        $chatId = $message->chat->id;

        $welcomeMessage = "🎸 Привет! Я — Mulletor Bot!\n\n" .
            "Превращаю обычные фото в легенды 80-х! Спереди — бизнес, сзади — вечеринка 🎸\n\n" .
            "Как пользоваться:\n" .
            "1️⃣ Отправь мне фото человека\n" .
            "2️⃣ Оплати 10 звёзд ⭐️\n" .
            "3️⃣ Получи шикарный маллет!\n\n" .
            "Работаю в личке и в группах (упомяни меня или используй команду /mullet)\n\n" .
            "Поехали! 🚀";

        $bot->api->sendMessage(
            chatId: $chatId,
            text: $welcomeMessage,
            replyParameters: $message->messageId ? new ReplyParameters(
                messageId: $message->messageId,
                allowSendingWithoutReply: true
            ) : null,
        );

        $this->logger->info("Start command sent to chat: {$chatId}");
    }
}
