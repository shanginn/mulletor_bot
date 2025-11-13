<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Bot\DetectPhotoHandler;
use Bot\Fal\Fal;
use Bot\Fal\Fal\FalClient;
use Bot\ImageWatermarkService;
use Bot\MulletService;
use Bot\PreCheckoutHandler;
use Bot\StartCommandHandler;
use Bot\SuccessfulPaymentHandler;
use Phenogram\Framework\TelegramBot;

Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..')->safeLoad();

[
    'botToken'  => $botToken,
    'falApiKey' => $falApiKey,
] = require __DIR__ . '/../config/config.php';

$bot = new TelegramBot(
    $botToken,
);

// Initialize Fal client and MulletService
$falClient = new FalClient($falApiKey);
$fal = new Fal($falClient);
$mulletService = new MulletService($fal);
$watermarkService = new ImageWatermarkService();

// Register handlers
$startCommandHandler = new StartCommandHandler();
$bot->addHandler($startCommandHandler)
    ->supports($startCommandHandler::supports(...));

$detectPhotoHandler = new DetectPhotoHandler($mulletService);
$bot->addHandler($detectPhotoHandler)
    ->supports($detectPhotoHandler::supports(...));

$preCheckoutHandler = new PreCheckoutHandler();
$bot->addHandler($preCheckoutHandler)
    ->supports($preCheckoutHandler::supports(...));

$successfulPaymentHandler = new SuccessfulPaymentHandler($mulletService, $watermarkService);
$bot->addHandler($successfulPaymentHandler)
    ->supports($successfulPaymentHandler::supports(...));

$pressedCtrlC     = false;
$gracefulShutdown = function (int $signal) use ($bot, &$pressedCtrlC): void {
    if ($pressedCtrlC) {
        echo "Shutting down now...\n";
        exit(0);
    }

    $keysCombination = $signal === SIGINT ? 'Ctrl+C' : 'Ctrl+Break';

    echo "\n" . $keysCombination . " pressed. Gracefully shutting down...\nPress it again to force shutdown.\n\n";

    $pressedCtrlC = true;

    try {
        $bot->stop();
    } catch (Throwable) {
    }

    exit(0);
};

pcntl_signal(SIGTERM, $gracefulShutdown);
pcntl_signal(SIGINT, $gracefulShutdown);

$bot->run();
