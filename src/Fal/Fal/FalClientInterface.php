<?php

declare(strict_types=1);

namespace Bot\Fal\Fal;

use Amp\ByteStream\BufferException;
use Amp\ByteStream\StreamException;
use Amp\Http\Client\HttpException;

interface FalClientInterface
{
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
    public function postRequest(string $url, string $json): string;

    /**
     * @param string $url
     *
     * @throws BufferException
     * @throws StreamException
     * @throws HttpException
     *
     * @return string
     */
    public function getRequest(string $url): string;
}