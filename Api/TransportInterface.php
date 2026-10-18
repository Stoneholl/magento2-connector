<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Api;

/**
 * Internal boundary for versioned Stoneholl Licensing API requests.
 */
interface TransportInterface
{
    /**
     * Send one request to the versioned licensing API.
     *
     * @param string $method
     * @param string $path
     * @param array $query Scalar query parameters.
     * @param array|null $body JSON request body.
     * @param string|null $credential
     * @return array<string, mixed>
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        ?string $credential = null
    ): array;
}
