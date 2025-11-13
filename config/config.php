<?php

declare(strict_types=1);

$botToken = getenv('TELEGRAM_BOT_TOKEN');
assert(is_string($botToken), 'Bot token must be a string');

$falApiKey = getenv('FAL_API_KEY');
assert(is_string($falApiKey), 'FAL API key must be a string');

return [
    'botToken' => $botToken,
    'falApiKey' => $falApiKey,
];