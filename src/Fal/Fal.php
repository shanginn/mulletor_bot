<?php

declare(strict_types=1);

namespace Bot\Fal;

use function Amp\delay;

use Amp\Http\Client\HttpException;
use Bot\Fal\Fal\FalClientInterface;
use Bot\Fal\Fal\FalSerializer;
use Bot\Fal\Fal\FalSerializerInterface;
use RuntimeException;
use Spiral\Prototype\Annotation\Prototyped;
use Throwable;

class Fal
{
    private FalSerializerInterface $serializer;

    public function __construct(
        private FalClientInterface $client,
    ) {
        $this->serializer = new FalSerializer();
    }

    /**
     * @param string $base64File
     *
     * @return string
     */
    public static function toMediaStringPath(string $base64File): string
    {
        return "data:application/octet-stream;base64,{$base64File}";
    }

    /**
     * @param string            $model
     * @param array<mixed>|null $data
     *
     * @throws HttpException
     * @throws RuntimeException
     *
     * @return array<mixed>
     */
    public function createRun(string $model, ?array $data = null): array
    {
        $url      = $this->client->queueUrlFormat . $model;
        $json     = $this->serializer->serialize($data ?? []);
        $response = $this->client->postRequest($url, $json);

        return $this->serializer->deserialize($response);
    }

    /**
     * @param string $baseUrl
     *
     * @throws HttpException
     * @throws RuntimeException
     *
     * @return array<mixed>
     */
    public function getRun(string $baseUrl): array
    {
        $response = $this->client->getRequest($baseUrl);

        return $this->serializer->deserialize($response);
    }

    /**
     * @param string $baseUrl
     *
     * @throws HttpException
     * @throws RuntimeException
     *
     * @return array<mixed>
     */
    public function getRunStatus(string $baseUrl): array
    {
        $response = $this->client->getRequest($baseUrl . '/status');

        return $this->serializer->deserialize($response);
    }

    /**
     * @param string $requestId
     * @param string $baseUrl
     * @param int    $waitFor
     *
     * @throws RuntimeException
     *
     * @return array<mixed>
     */
    public function waitForRun(string $requestId, string $baseUrl, int $waitFor = 600): array
    {
        for ($i = 0; $i < $waitFor; ++$i) {
            try {
                $status = $this->getRunStatus($baseUrl);
            } catch (Throwable $e) {
                throw new RuntimeException("Error while getting run status {$e->getMessage()}");
            }

            if ($status['status'] === 'FAILED') {
                throw new RuntimeException("Prediction {$requestId} failed: " . ($status['error'] ?? 'Unknown error'));
            }

            if ($status['status'] === 'COMPLETED') {
                return $this->getRun($baseUrl);
            }

            delay(1);
        }

        throw new RuntimeException("Prediction {$requestId} failed to complete in {$waitFor} seconds");
    }

    /**
     * @param string   $prompt
     * @param string   $imageSize
     * @param int      $numInferenceSteps
     * @param float    $guidanceScale
     * @param int      $numImages
     * @param string   $safetyTolerance
     * @param int|null $seed
     *
     * @throws HttpException
     * @throws RuntimeException
     *
     * @return array<mixed>
     */
    public function fluxPro(
        string $prompt,
        string $imageSize = 'square_hd',
        int $numInferenceSteps = 28,
        float $guidanceScale = 3.5,
        int $numImages = 1,
        string $safetyTolerance = '5',
        ?int $seed = null
    ): array {
        $seed = $seed ?? random_int(0, 2 ** 32 - 1);

        $run = $this->createRun(
            '/fal-ai/flux-pro',
            [
                'prompt'              => $prompt,
                'image_size'          => $imageSize,
                'num_inference_steps' => $numInferenceSteps,
                'guidance_scale'      => $guidanceScale,
                'num_images'          => $numImages,
                'safety_tolerance'    => $safetyTolerance,
                'seed'                => $seed,
            ]
        );

        return $this->waitForRun($run['request_id'], $run['response_url']);
    }

    /**
     * @param string   $prompt
     * @param int      $width
     * @param int      $height
     * @param int      $numInferenceSteps
     * @param float    $guidanceScale
     * @param int      $numImages
     * @param bool     $enableSafetyChecker
     * @param int|null $seed
     *
     * @throws HttpException
     * @throws RuntimeException
     *
     * @return array<mixed>
     */
    public function fluxDev(
        string $prompt,
        int $width,
        int $height,
        int $numInferenceSteps = 20,
        float $guidanceScale = 3.5,
        int $numImages = 1,
        bool $enableSafetyChecker = false,
        ?int $seed = null
    ): array {
        $seed = $seed ?? random_int(0, 2 ** 32 - 1);

        $run = $this->createRun(
            '/fal-ai/flux/dev',
            [
                'prompt'     => $prompt,
                'image_size' => [
                    'width'  => $width,
                    'height' => $height,
                ],
                'num_inference_steps'   => $numInferenceSteps,
                'guidance_scale'        => $guidanceScale,
                'num_images'            => $numImages,
                'enable_safety_checker' => $enableSafetyChecker,
                'seed'                  => $seed,
            ]
        );

        return $this->waitForRun($run['request_id'], $run['response_url']);
    }
}