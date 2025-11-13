<?php

declare(strict_types=1);

namespace Bot\Fal\Fal;

use Amp\ByteStream\BufferException;
use Amp\ByteStream\StreamException;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;

final readonly class FalClient implements FalClientInterface
{
    public const USER_AGENT = 'fal-client/7.7.7 (php)';
    private HttpClient $client;

    public function __construct(
        private string $apiKey,
        public string $runUrlFormat = 'https://run.fal.run',
        public string $queueUrlFormat = 'https://queue.fal.run',
        public string $cdnUrl = 'https://fal.media',
    ) {
        $this->client = HttpClientBuilder::buildDefault();
    }

    /**
     * @param string $url
     * @param string $json
     *
     * @throws BufferException
     * @throws StreamException
     * @throws HttpException
     *
     * @return string
     */
    public function postRequest(string $url, string $json): string
    {
        $request = new Request($url, 'POST');
        $request->setBody($json);
        $request->setHeader('Authorization', "Key {$this->apiKey}");
        $request->setHeader('Content-Type', 'application/json');
        $request->setHeader('User-Agent', self::USER_AGENT);
        $request->setTransferTimeout(120);
        $request->setInactivityTimeout(120);

        $response = $this->client->request($request);

        return $response->getBody()->buffer();
    }

    /**
     * @param string $url
     *
     * @throws BufferException
     * @throws StreamException
     * @throws HttpException
     */
    public function getRequest(string $url): string
    {
        $request = new Request($url, 'GET');
        $request->setHeader('Authorization', "Key {$this->apiKey}");
        $request->setHeader('User-Agent', self::USER_AGENT);
        $request->setTransferTimeout(120);
        $request->setInactivityTimeout(120);

        $response = $this->client->request($request);

        return $response->getBody()->buffer();
    }
}