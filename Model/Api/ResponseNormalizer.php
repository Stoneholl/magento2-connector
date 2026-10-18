<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model\Api;

class ResponseNormalizer
{
    private const REQUIRED_LIMITS = [
        'request_body_bytes' => 16384,
        'response_body_bytes' => 1048576,
        'pairing_code_ttl_seconds' => 600,
    ];

    /**
     * Validate the API version, schema version and supported feature flags.
     *
     * @param array $response
     * @return array
     */
    public function capabilities(array $response): array
    {
        $data = $this->data($response);
        $features = is_array($data['features'] ?? null) ? $data['features'] : [];
        $limits = is_array($data['limits'] ?? null) ? $data['limits'] : [];
        if (($data['api_version'] ?? null) !== 'v1' || ($data['schema_version'] ?? null) !== 2) {
            throw new ApiException(
                'capability_mismatch',
                'The Stoneholl API version is not compatible with this Connector.'
            );
        }
        foreach (self::REQUIRED_LIMITS as $name => $expected) {
            if (($limits[$name] ?? null) !== $expected) {
                throw new ApiException(
                    'capability_mismatch',
                    'The Stoneholl API transport limits are not compatible with this Connector.'
                );
            }
        }
        $normalizedFeatures = [];
        foreach ($features as $name => $enabled) {
            if (!is_string($name) || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name) !== 1 || !is_bool($enabled)) {
                throw new ApiException('invalid_response', 'The API capability response is invalid.');
            }
            $normalizedFeatures[$name] = $enabled;
        }
        return [
            'api_version' => 'v1',
            'schema_version' => 2,
            'features' => $normalizedFeatures,
            'limits' => self::REQUIRED_LIMITS,
        ];
    }

    /**
     * Validate a one-time pairing response before persisting its credential.
     *
     * @param array $response
     * @return array
     */
    public function pairing(array $response): array
    {
        $data = $this->data($response);
        $installation = is_array($data['installation'] ?? null) ? $data['installation'] : [];
        $token = (string)($data['token'] ?? '');
        $id = (string)($installation['id'] ?? '');
        if (!$this->uuid($id)
            || preg_match('/^shi_[A-Za-z0-9]+_[A-Za-z0-9]+$/D', $token) !== 1
            || strlen($token) > 255
        ) {
            throw new ApiException(
                'invalid_response',
                'The pairing response did not contain a valid installation credential.'
            );
        }
        if (($data['token_visible_once'] ?? null) !== true
            || !is_bool($data['recovered'] ?? null)
            || !is_bool($data['confirmation_required'] ?? null)
        ) {
            throw new ApiException('invalid_response', 'The pairing response is missing recovery metadata.');
        }
        return [
            'installation_id' => $id,
            'token' => $token,
            'recovered' => $data['recovered'],
            'confirmation_required' => $data['confirmation_required'],
        ];
    }

    /**
     * Validate the confirmed installation identity and completion timestamp.
     *
     * @param array $response
     * @return array
     */
    public function pairingConfirmation(array $response): array
    {
        $data = $this->data($response);
        $installationId = (string)($data['installation_id'] ?? '');
        $confirmedAt = (string)($data['confirmed_at'] ?? '');
        if (!$this->uuid($installationId) || !$this->dateTime($confirmedAt)) {
            throw new ApiException('invalid_response', 'The pairing confirmation response is invalid.');
        }
        return ['installation_id' => $installationId, 'confirmed_at' => $confirmedAt];
    }

    /**
     * Require explicit reset permission for the expected unlinked installation.
     *
     * @param array $response
     * @param string $expectedInstallationId
     * @return array
     */
    public function localResetAuthorization(array $response, string $expectedInstallationId): array
    {
        $data = $this->data($response);
        $installationId = (string)($data['installation_id'] ?? '');
        $unlinkedAt = (string)($data['unlinked_at'] ?? '');
        if (!$this->uuid($installationId)
            || !hash_equals($expectedInstallationId, $installationId)
            || ($data['local_reset_allowed'] ?? null) !== true
            || !$this->dateTime($unlinkedAt)
        ) {
            throw new ApiException(
                'invalid_response',
                'The local reset authorization response is invalid or refers to another installation.'
            );
        }

        return [
            'installation_id' => $installationId,
            'local_reset_allowed' => true,
            'unlinked_at' => $unlinkedAt,
        ];
    }

    /**
     * Validate the replacement secret and original credential reference.
     *
     * @param array $response
     * @return array
     */
    public function issuedCredential(array $response): array
    {
        $data = $this->data($response);
        $credentialId = (string)($data['id'] ?? '');
        $previousId = (string)($data['previous_credential_id'] ?? '');
        $secret = (string)($data['secret'] ?? '');
        if (!$this->uuid($credentialId)
            || !$this->uuid($previousId)
            || preg_match('/^shi_[A-Za-z0-9]+_[A-Za-z0-9]+$/D', $secret) !== 1
            || strlen($secret) > 255
            || ($data['secret_visible_once'] ?? null) !== true
        ) {
            throw new ApiException('invalid_response', 'The credential rotation response is invalid.');
        }
        return [
            'credential_id' => $credentialId,
            'previous_credential_id' => $previousId,
            'token' => $secret,
        ];
    }

    /**
     * Require a revocation response for the requested credential UUID.
     *
     * @param array $response
     * @param string $expectedId
     * @return void
     */
    public function revokedCredential(array $response, string $expectedId): void
    {
        $data = $this->data($response);
        if (($data['id'] ?? null) !== $expectedId || !$this->dateTime((string)($data['revoked_at'] ?? ''))) {
            throw new ApiException('invalid_response', 'The credential revocation response is invalid.');
        }
    }

    /**
     * Reject responses that do not explicitly acknowledge success.
     *
     * @param array $response
     * @return void
     */
    public function acknowledgement(array $response): void
    {
        if (!is_array($this->data($response))) {
            throw new ApiException('invalid_response', 'The API acknowledgement response is invalid.');
        }
    }

    /**
     * Validate entitlement rows without copying arbitrary remote fields into local cache.
     *
     * @param array $response
     * @return array
     */
    public function entitlements(array $response): array
    {
        $data = $this->data($response);
        if (!is_array($data)) {
            throw new ApiException('invalid_response', 'The entitlement response is invalid.');
        }
        $result = [];
        foreach ($data as $item) {
            if (!is_array($item) || !is_array($item['product'] ?? null)) {
                throw new ApiException('invalid_response', 'The entitlement response contains an invalid item.');
            }
            $product = $item['product'];
            $code = (string)($product['slug'] ?? '');
            $name = (string)($product['name'] ?? '');
            if (!$this->code($code) || $name === '' || strlen($name) > 255) {
                throw new ApiException(
                    'invalid_response',
                    'The entitlement response contains invalid product metadata.'
                );
            }
            $packages = [];
            foreach ((array)($product['packages'] ?? []) as $package) {
                $packageName = is_array($package) ? (string)($package['name'] ?? '') : '';
                if (preg_match(
                    '#^[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*$#D',
                    $packageName
                ) !== 1) {
                    throw new ApiException(
                        'invalid_response',
                        'The entitlement response contains an invalid package name.'
                    );
                }
                $packages[] = $packageName;
            }
            $result[] = [
                'id' => substr((string)($item['id'] ?? ''), 0, 64),
                'product_code' => $code,
                'product_name' => $name,
                'local_use' => $this->enum((string)($item['local_use'] ?? ''), ['perpetual', 'revoked']),
                'updates' => $this->term($item['updates'] ?? null),
                'support' => $this->term($item['support'] ?? null),
                'packages' => array_values(array_unique($packages)),
            ];
        }
        return $result;
    }

    /**
     * Validate compatible release rows and index them by Composer package.
     *
     * @param array $response
     * @return array
     */
    public function releases(array $response): array
    {
        $data = $this->data($response);
        if (!is_array($data)) {
            throw new ApiException('invalid_response', 'The release response is invalid.');
        }
        $result = [];
        foreach ($data as $item) {
            $package = is_array($item) ? (string)($item['package'] ?? '') : '';
            $version = is_array($item) ? (string)($item['version'] ?? '') : '';
            if (preg_match('#^[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*$#D', $package) !== 1
                || preg_match('/^[0-9A-Za-z][0-9A-Za-z.+_-]{0,63}$/D', $version) !== 1
            ) {
                throw new ApiException('invalid_response', 'The release response contains invalid package metadata.');
            }
            $result[$package] = [
                'package' => $package,
                'version' => $version,
                'published_at' => substr((string)($item['published_at'] ?? ''), 0, 40),
            ];
        }
        return $result;
    }

    /**
     * Require a data envelope before normalizing its operation-specific contents.
     *
     * @param array $response
     * @return mixed
     */
    private function data(array $response)
    {
        if (!array_key_exists('data', $response)) {
            throw new ApiException('invalid_response', 'The API response envelope is invalid.');
        }
        return $response['data'];
    }

    /**
     * Normalize a bounded service term without enforcing installed functionality.
     *
     * @param mixed $value
     * @return array
     */
    private function term($value): array
    {
        if (!is_array($value) || !is_bool($value['allowed'] ?? null)) {
            throw new ApiException('invalid_response', 'The entitlement term status is invalid.');
        }
        return [
            'allowed' => $value['allowed'],
            'expires_at' => substr((string)($value['expires_at'] ?? ''), 0, 40),
        ];
    }

    /**
     * Require an exact value from the operation-specific allowlist.
     *
     * @param string $value
     * @param array $allowed
     * @return string
     */
    private function enum(string $value, array $allowed): string
    {
        if (!in_array($value, $allowed, true)) {
            throw new ApiException('invalid_response', 'The API response contains an unsupported status value.');
        }
        return $value;
    }

    /**
     * Recognize a stable lowercase product code.
     *
     * @param string $value
     * @return bool
     */
    private function code(string $value): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/D', $value) === 1;
    }

    /**
     * Recognize the UUID shape required for remote identities.
     *
     * @param string $value
     * @return bool
     */
    private function uuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di', $value) === 1;
    }

    /**
     * Recognize a valid date-time value before displaying remote timestamps.
     *
     * @param string $value
     * @return bool
     */
    private function dateTime(string $value): bool
    {
        return $value !== '' && strlen($value) <= 40 && strtotime($value) !== false;
    }
}
