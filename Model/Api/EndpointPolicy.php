<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model\Api;

use Stoneholl\Connector\Model\Config;

class EndpointPolicy
{
    /** @var Config */
    private $config;

    /**
     * Initialize explicit dependencies for endpoint policy.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Enforce HTTPS, the fixed API base path and the exact deployment host allowlist.
     *
     * @return string
     */
    public function getValidatedBaseUrl(): string
    {
        $url = $this->config->getApiUrl();
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            throw new ApiException('endpoint_rejected', 'The configured API endpoint is invalid.');
        }

        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged -- Structural URL validation needs all parts.
        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new ApiException(
                'endpoint_rejected',
                'The API endpoint must be an HTTPS URL without credentials, query, or fragment.'
            );
        }

        $host = strtolower(rtrim((string)$parts['host'], '.'));
        if (!in_array($host, $this->config->getAllowedApiHosts(), true)) {
            throw new ApiException('endpoint_rejected', 'The API endpoint host is not in the deployment allowlist.');
        }
        if (isset($parts['port']) && (int)$parts['port'] !== 443) {
            throw new ApiException('endpoint_rejected', 'The API endpoint must use the standard HTTPS port.');
        }

        $path = rtrim((string)($parts['path'] ?? ''), '/');
        if ($path === '' || substr($path, -7) !== '/api/v1') {
            throw new ApiException('endpoint_rejected', 'The API endpoint must identify the /api/v1 base path.');
        }
        return 'https://' . $host . $path;
    }
}
