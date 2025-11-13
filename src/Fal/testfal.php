<?php

require_once 'vendor/autoload.php';

use Amp\Http\Client\HttpException;
use Bot\Fal\Fal;
use Bot\Fal\Fal\FalClient;

$apiKey = getenv('FAL_API_KEY');

if (!$apiKey) {
    throw new RuntimeException('FAL_API_KEY not set');
}

$client = new FalClient($apiKey);

$fal = new Fal($client);

// Example usage of fluxPro
try {
    $result = $fal->fluxPro('A majestic lion', 'square_hd');
    dump($result);
} catch (HttpException|RuntimeException $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}

// Example usage of fluxDev
try {
    $result = $fal->fluxDev('A beautiful sunset', 512, 512);
    dump($result);
} catch (HttpException|RuntimeException $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}