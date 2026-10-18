<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model\Api;

use Stoneholl\Connector\Api\TransportInterface;
use Stoneholl\Connector\Model\Config;

class Transport implements TransportInterface
{
    /** @var Config */
    private $config;

    /** @var HttpTransport */
    private $httpTransport;

    /** @var FixtureTransport */
    private $fixtureTransport;

    /**
     * Initialize explicit dependencies for transport.
     *
     * @param Config $config
     * @param HttpTransport $httpTransport
     * @param FixtureTransport $fixtureTransport
     */
    public function __construct(Config $config, HttpTransport $httpTransport, FixtureTransport $fixtureTransport)
    {
        $this->config = $config;
        $this->httpTransport = $httpTransport;
        $this->fixtureTransport = $fixtureTransport;
    }

    /**
     * @inheritdoc
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        ?string $credential = null
    ): array {
        $transport = $this->config->fixtureModeEnabled() ? $this->fixtureTransport : $this->httpTransport;
        return $transport->request($method, $path, $query, $body, $credential);
    }
}
