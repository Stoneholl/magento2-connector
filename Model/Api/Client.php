<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model\Api;

use Stoneholl\Connector\Api\TransportInterface;

class Client
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di';

    /** @var TransportInterface */
    private $transport;

    /** @var ResponseNormalizer */
    private $normalizer;

    /**
     * Initialize explicit dependencies for client.
     *
     * @param TransportInterface $transport
     * @param ResponseNormalizer $normalizer
     */
    public function __construct(TransportInterface $transport, ResponseNormalizer $normalizer)
    {
        $this->transport = $transport;
        $this->normalizer = $normalizer;
    }

    /**
     * Exchange the persisted pairing payload and validate the one-time credential response.
     *
     * @param array $payload
     * @return array
     */
    public function exchangePairingCode(array $payload): array
    {
        return $this->normalizer->pairing(
            $this->transport->request('POST', '/pairing/exchange', [], $payload)
        );
    }

    /**
     * Negotiate API/schema versions and require each operation-specific capability.
     *
     * @param array $features
     * @return array
     */
    public function requireCapabilities(array $features = []): array
    {
        $capabilities = $this->normalizer->capabilities(
            $this->transport->request('GET', '/capabilities')
        );
        foreach ($features as $feature) {
            if (($capabilities['features'][$feature] ?? false) !== true) {
                throw new ApiException(
                    'capability_mismatch',
                    'The Stoneholl API does not support a required Connector capability.'
                );
            }
        }
        return $capabilities;
    }

    /**
     * Confirm a saved installation credential and validate the returned identity.
     *
     * @param string $credential
     * @return array
     */
    public function confirmPairing(string $credential): array
    {
        return $this->normalizer->pairingConfirmation(
            $this->transport->request('POST', '/installation/pairing/confirm', [], [], $credential)
        );
    }

    /**
     * Send only the supplied documented platform metadata and validate acknowledgement.
     *
     * @param string $credential
     * @param array $metadata
     * @return void
     */
    public function updateMetadata(string $credential, array $metadata): void
    {
        $this->normalizer->acknowledgement(
            $this->transport->request('POST', '/installation/metadata', [], $metadata, $credential)
        );
    }

    /**
     * Issue a replacement, optionally replaying a persisted UUID attempt.
     *
     * @param string $credential
     * @param string|null $attemptId
     * @return array
     */
    public function issueCredential(string $credential, ?string $attemptId = null): array
    {
        if ($attemptId !== null) {
            $this->assertUuid($attemptId);
        }
        return $this->normalizer->issuedCredential(
            $this->transport->request(
                'POST',
                '/installation/credentials',
                [],
                $attemptId === null ? [] : ['attempt_id' => $attemptId],
                $credential
            )
        );
    }

    /**
     * Revoke the exact credential UUID and validate the acknowledgement identity.
     *
     * @param string $credential
     * @param string $credentialId
     * @return void
     */
    public function revokeCredential(string $credential, string $credentialId): void
    {
        $this->assertUuid($credentialId);
        $this->normalizer->revokedCredential(
            $this->transport->request(
                'POST',
                '/installation/credentials/' . $credentialId . '/revoke',
                [],
                [],
                $credential
            ),
            $credentialId
        );
    }

    /**
     * Request server-side unlink before any local cleanup is permitted.
     *
     * @param string $credential
     * @return void
     */
    public function unlink(string $credential): void
    {
        $this->normalizer->acknowledgement(
            $this->transport->request('POST', '/installation/unlink', [], [], $credential)
        );
    }

    /**
     * Validate server authorization to clear the exact saved unlinked installation.
     *
     * @param string $credential
     * @param string $installationId
     * @return array
     */
    public function authorizeLocalReset(string $credential, string $installationId): array
    {
        $this->assertUuid($installationId);
        return $this->normalizer->localResetAuthorization(
            $this->transport->request(
                'POST',
                '/installation/local-reset/authorize',
                [],
                ['installation_id' => $installationId],
                $credential
            ),
            $installationId
        );
    }

    /**
     * Fetch a complete validated entitlement list before the synchronizer may cache it.
     *
     * @param string $credential
     * @return array
     */
    public function getEntitlements(string $credential): array
    {
        $result = [];
        $seen = [];
        $expectedTotal = null;
        $started = microtime(true);
        for ($page = 1; $page <= 100; $page++) {
            if (microtime(true) - $started > 60) {
                throw new ApiException('invalid_response', 'The entitlement page traversal exceeded its time budget.');
            }
            $response = $this->transport->request(
                'GET',
                '/installation/entitlements',
                ['page' => $page],
                null,
                $credential
            );
            $items = $this->normalizer->entitlements($response);
            if (!array_key_exists('meta', $response) && $page === 1) {
                // Older API versions ignore page and return the complete legacy array.
                return $items;
            }
            $meta = $response['meta'] ?? null;
            if (!is_array($meta)
                || ($meta['current_page'] ?? null) !== $page
                || ($meta['per_page'] ?? null) !== 25
                || !is_int($meta['total'] ?? null)
                || $meta['total'] < 0
                || $meta['total'] > 2500
                || !is_int($meta['last_page'] ?? null)
                || $meta['last_page'] !== max(1, (int)ceil($meta['total'] / 25))
                || ($expectedTotal !== null && $meta['total'] !== $expectedTotal)
                || count($items) !== min(25, max(0, $meta['total'] - ($page - 1) * 25))
            ) {
                throw new ApiException(
                    'invalid_response',
                    'The entitlement pagination metadata is invalid or changed.'
                );
            }
            $expectedTotal = $meta['total'];
            foreach ($items as $item) {
                $id = $item['id'];
                if (preg_match(self::UUID_PATTERN, $id) !== 1 || isset($seen[$id])) {
                    throw new ApiException(
                        'invalid_response',
                        'The entitlement pages contain invalid or repeated IDs.'
                    );
                }
                $seen[$id] = true;
                $result[] = $item;
            }
            if ($page === $meta['last_page']) {
                return $result;
            }
        }
        throw new ApiException('invalid_response', 'The entitlement page traversal exceeded its page budget.');
    }

    /**
     * Read validated package releases compatible with the supplied platform dimensions.
     *
     * @param string $credential
     * @param string $productCode
     * @param array $platform
     * @return array
     */
    public function getLatestCompatible(string $credential, string $productCode, array $platform): array
    {
        try {
            $response = $this->transport->request('GET', '/releases/latest-compatible', [
                'product' => $productCode,
                'magento_edition' => $platform['magento_edition'],
                'magento_version' => $platform['magento_version'],
                'php_version' => $platform['php_version'],
            ], null, $credential);
        } catch (ApiException $exception) {
            if ($exception->getApiCode() === 'no_compatible_release' && $exception->getHttpStatus() === 404) {
                return [];
            }
            throw $exception;
        }
        return $this->normalizer->releases($response);
    }

    /**
     * Reject malformed credential or installation IDs before constructing an API path.
     *
     * @param string $value
     * @return void
     */
    private function assertUuid(string $value): void
    {
        if (preg_match(self::UUID_PATTERN, $value) !== 1) {
            throw new ApiException('request_rejected', 'The Connector refused an invalid credential identifier.');
        }
    }
}
