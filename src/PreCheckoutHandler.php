<?php

declare(strict_types=1);

namespace Bot;

use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\TelegramBot;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;

class PreCheckoutHandler implements UpdateHandlerInterface
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = Discover::log() ?? new NullLogger();
    }

    public static function supports(UpdateInterface $update): bool
    {
        return $update->preCheckoutQuery !== null;
    }

    public function handle(UpdateInterface $update, TelegramBot $bot)
    {
        $preCheckoutQuery = $update->preCheckoutQuery;

        $this->logger->info("Pre-checkout query received", [
            'query_id' => $preCheckoutQuery->id,
            'from' => $preCheckoutQuery->from->id,
            'total_amount' => $preCheckoutQuery->totalAmount,
            'payload' => $preCheckoutQuery->invoicePayload,
        ]);

        // Answer the pre-checkout query - always approve
        $bot->api->answerPreCheckoutQuery(
            preCheckoutQueryId: $preCheckoutQuery->id,
            ok: true,
        );

        $this->logger->info("Pre-checkout query approved: {$preCheckoutQuery->id}");
    }
}
