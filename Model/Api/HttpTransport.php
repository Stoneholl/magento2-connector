<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model\Api;

use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Stoneholl\Connector\Api\TransportInterface;
use Stoneholl\Connector\Model\Config;

class HttpTransport implements TransportInterface
{
    /** @var CurlFactory */
    private $curlFactory;

    /** @var Json */
    private $json;

    /** @var Config */
    private $config;

    /** @var EndpointPolicy */
    private $endpointPolicy;

    /**
     * Initialize explicit dependencies for http transport.
     *
     * @param CurlFactory $curlFactory
     * @param Json $json
     * @param Config $config
     * @param EndpointPolicy $endpointPolicy
     */
    public function __construct(CurlFactory $curlFactory, Json $json, Config $config, EndpointPolicy $endpointPolicy)
    {
        $this->curlFactory = $curlFactory;
        $this->json = $json;
        $this->config = $config;
        $this->endpointPolicy = $endpointPolicy;
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
        if (!in_array($method, ['GET', 'POST'], true) || preg_match('#^/[a-z0-9/_-]+$#D', $path) !== 1) {
            throw new ApiException('request_rejected', 'The Connector refused an unsupported API request.');
        }

        $url = $this->endpointPolicy->getValidatedBaseUrl() . $path;
        if ($query) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'Stoneholl-Connector/1.0.0',
        ];
        if ($credential !== null && $credential !== '') {
            $headers['Authorization'] = 'Bearer ' . $credential;
        }

        $curl = $this->curlFactory->create();
        $curl->setHeaders($headers);
        $curl->setOptions([
            CURLOPT_CONNECTTIMEOUT => $this->config->getConnectTimeout(),
            CURLOPT_TIMEOUT => $this->config->getTimeout(),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_MAXFILESIZE => $this->config->getMaximumResponseBytes(),
        ]);

        try {
            if ($method === 'POST') {
                $payload = $this->json->serialize($body ?: []);
                if (strlen($payload) > 16384) {
                    throw new ApiException('request_too_large', 'The Connector request exceeded its size limit.');
                }
                $curl->post($url, $payload);
            } else {
                $curl->get($url);
            }
        // phpcs:ignore Magento2.Exceptions.ThrowCatch.ThrowCatch -- Preserve bounded API exceptions at this boundary.
        } catch (ApiException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ApiException('transport_error', 'The API request failed before a response was received.');
        }

        $status = (int)$curl->getStatus();
        $responseBody = (string)$curl->getBody();
        if (strlen($responseBody) > $this->config->getMaximumResponseBytes()) {
            throw new ApiException(
                'response_too_large',
                'The API response exceeded the configured size limit.',
                $status ?: null
            );
        }

        try {
            $decoded = $this->json->unserialize($responseBody);
        } catch (\InvalidArgumentException $exception) {
            throw new ApiException('invalid_response', 'The API returned an invalid JSON response.', $status ?: null);
        }
        if (!is_array($decoded)) {
            throw new ApiException('invalid_response', 'The API returned an unexpected response.', $status ?: null);
        }
        if ($status < 200 || $status >= 300) {
            $code = $this->safeErrorCode($decoded);
            throw new ApiException($code, $this->safeErrorMessage($code), $status);
        }
        return $decoded;
    }

    /**
     * Retain only a bounded machine-readable error code from an API failure.
     *
     * @param array $response
     * @return string
     */
    private function safeErrorCode(array $response): string
    {
        $code = (string)($response['error']['code'] ?? 'http_error');
        return preg_match('/^[a-z0-9_]{1,64}$/D', $code) === 1 ? $code : 'http_error';
    }

    /**
     * Map API failures to fixed local messages without exposing remote response content.
     *
     * @param string $code
     * @return string
     */
    private function safeErrorMessage(string $code): string
    {
        $messages = [
            'unauthenticated' => 'The installation credential was rejected.',
            'installation_unlinked' => 'This installation is no longer linked.',
            'invalid_pairing_code' => 'The pairing code is invalid, expired, or already used.',
            'pairing_attempt_mismatch' => 'The pairing code is bound to a different recoverable attempt.',
            'pairing_attempt_conflict' => 'The pairing attempt identifier has already been used.',
            'pairing_attempt_unavailable' => 'The recoverable pairing attempt is no longer available.',
            'pairing_confirmation_required' => 'Pairing must be confirmed before status requests can run.',
            'pairing_confirmation_expired' =>
                'The pairing confirmation window expired. Forget the attempt locally and start again.',
            'production_installation_exists' => 'Another production installation is already linked to this project.',
            'no_compatible_release' => 'No entitled compatible release is available.',
            'not_entitled' => 'This project is not entitled to the requested product.',
            'validation_failed' => 'The API rejected the request data.',
            'request_too_large' => 'The API rejected an oversized request.',
            'last_credential' => 'The final active credential cannot be revoked without a replacement.',
            'invalid_installation_credential' => 'The saved installation credential was rejected.',
            'installation_mismatch' =>
                'The saved credential belongs to another installation. Local state was retained.',
            'installation_still_active' =>
                'This installation is still active. Use Reconnect Installation instead of resetting local state.',
            'local_reset_unavailable' => 'Verified local reset is unavailable. Local state was retained.',
            'rate_limited' => 'The API rate limit was reached. Try again later.',
        ];
        return $messages[$code] ?? 'The API returned an error response.';
    }
}
