<?php

declare(strict_types=1);

namespace Bot\Fal\Fal;

use JsonException;
use RuntimeException;

final readonly class FalSerializer implements FalSerializerInterface
{
    /**
     * @param array<mixed> $data
     */
    public function serialize(array $data): string
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);

        if ($json === false) {
            throw new RuntimeException('Could not serialize data into json');
        }

        return $json;
    }

    /**
     * @param string $json
     *
     * @return array<mixed>
     */
    public function deserialize(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                throw new RuntimeException('Expected json to return array but got something else');
            }

            return $data;
        } catch (JsonException $e) {
            throw new RuntimeException("Could not parse json because {$e->getMessage()}");
        }
    }
}