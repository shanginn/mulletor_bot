<?php

declare(strict_types=1);

namespace Bot;

use Amp\Http\Client\HttpException;
use Bot\Fal\Fal;
use RuntimeException;

class MulletService
{
    public function __construct(
        private Fal $fal,
    ) {}

    /**
     * Transform an image to add a mullet hairstyle
     *
     * @param string      $imageUrl The URL of the image to transform
     * @param string|null $prompt   Optional custom prompt (defaults to mullet transformation)
     * @param int         $timeout  Maximum wait time in seconds (default: 300 = 5 minutes)
     *
     * @throws HttpException
     * @throws RuntimeException
     *
     * @return array{images: array, description: string} The result with transformed images
     */
    public function addMullet(string $imageUrl, ?string $prompt = null, int $timeout = 300): array
    {
        $prompt = $prompt ?? 'give this person a spectacular 1980s mullet hairstyle and gorgeous mustache';

        // Create the nano-banana edit run
        $run = $this->fal->createRun(
            '/fal-ai/nano-banana/edit',
            [
                'prompt' => $prompt,
                'image_urls' => [$imageUrl],
                'num_images' => 1,
                'output_format' => 'png',
                'sync_mode' => false, // We'll manually wait for completion
            ]
        );

        // Wait for the run to complete
        $result = $this->fal->waitForRun(
            requestId: $run['request_id'],
            baseUrl: $run['response_url'],
            waitFor: $timeout
        );

        return $result;
    }

    /**
     * Get the first mullet image URL from the result
     *
     * @param array $result The result from addMullet()
     *
     * @return string The URL of the first transformed image
     */
    public function getFirstImageUrl(array $result): string
    {
        if (empty($result['images'][0]['url'])) {
            throw new RuntimeException('No image URL found in result');
        }

        return $result['images'][0]['url'];
    }
}
