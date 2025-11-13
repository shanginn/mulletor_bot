<?php

declare(strict_types=1);

namespace Bot\Fal\Fal;

interface FalSerializerInterface
{
    /**
     * @param array<mixed> $data
     */
    public function serialize(array $data): string;

    /**
     * @param string $json
     *
     * @return array<mixed>
     */
    public function deserialize(string $json): array;
}