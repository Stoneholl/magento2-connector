<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model\Api;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json;
use Stoneholl\Connector\Api\TransportInterface;

class FixtureTransport implements TransportInterface
{
    /** @var ComponentRegistrar */
    private $componentRegistrar;

    /** @var File */
    private $file;

    /** @var Json */
    private $json;

    /**
     * Initialize explicit dependencies for fixture transport.
     *
     * @param ComponentRegistrar $componentRegistrar
     * @param File $file
     * @param Json $json
     */
    public function __construct(ComponentRegistrar $componentRegistrar, File $file, Json $json)
    {
        $this->componentRegistrar = $componentRegistrar;
        $this->file = $file;
        $this->json = $json;
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
        $fixture = null;
        if ($method === 'GET' && $path === '/capabilities') {
            $fixture = 'capabilities.json';
        } elseif ($method === 'POST' && $path === '/pairing/exchange') {
            $fixture = 'pairing-success.json';
        } elseif ($method === 'POST' && $path === '/installation/pairing/confirm') {
            $fixture = 'pairing-confirmed.json';
        } elseif ($method === 'POST' && $path === '/installation/metadata') {
            $fixture = 'metadata-updated.json';
        } elseif ($method === 'POST' && $path === '/installation/credentials') {
            $fixture = 'credential-issued.json';
        } elseif ($method === 'POST' && preg_match(
            '#^/installation/credentials/[0-9a-f-]{36}/revoke$#Di',
            $path
        ) === 1) {
            $fixture = 'credential-revoked.json';
        } elseif ($method === 'POST' && $path === '/installation/unlink') {
            $fixture = 'installation-unlinked.json';
        } elseif ($method === 'POST' && $path === '/installation/local-reset/authorize') {
            $fixture = 'installation-local-reset-authorized.json';
        } elseif ($method === 'GET' && $path === '/installation/entitlements') {
            $fixture = 'entitlements.json';
        } elseif ($method === 'GET' && $path === '/releases/latest-compatible') {
            $fixture = 'latest-compatible.json';
        }
        if ($fixture === null) {
            throw new ApiException('fixture_not_found', 'No synthetic fixture exists for this API request.');
        }

        $modulePath = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Stoneholl_Connector');
        $contents = $this->file->fileGetContents($modulePath . '/Test/Fixture/Api/' . $fixture);
        $decoded = $this->json->unserialize($contents);
        if (!is_array($decoded)) {
            throw new ApiException('invalid_fixture', 'The synthetic API fixture is invalid.');
        }
        if ($fixture === 'pairing-success.json' && isset($body['installation_id'])) {
            if ($body['installation_id'] !== $decoded['data']['installation']['id']) {
                throw new ApiException(
                    'recovery_installation_mismatch',
                    'The synthetic recovery code belongs to another installation.',
                    409
                );
            }
            $decoded['data']['recovered'] = true;
        } elseif ($fixture === 'credential-revoked.json') {
            $segments = explode('/', $path);
            $decoded['data']['id'] = $segments[3] ?? '';
        } elseif ($fixture === 'installation-local-reset-authorized.json' && is_array($body)) {
            $decoded['data']['installation_id'] = (string)($body['installation_id'] ?? '');
        }
        return $decoded;
    }
}
