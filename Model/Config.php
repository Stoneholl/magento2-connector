<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Stoneholl\Connector\Model\Config\AccountUrlValidator;
use Stoneholl\Core\Model\MarketplaceUrl;

class Config
{
    private const PREFIX = 'stoneholl_connector/';

    /** @var ScopeConfigInterface */
    private $scopeConfig;
    /** @var AccountUrlValidator */
    private $accountUrlValidator;

    /** @var MarketplaceUrl */
    private $marketplaceUrl;

    /**
     * Initialize explicit dependencies for config.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param AccountUrlValidator $accountUrlValidator
     * @param MarketplaceUrl $marketplaceUrl
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        AccountUrlValidator $accountUrlValidator,
        MarketplaceUrl $marketplaceUrl
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->accountUrlValidator = $accountUrlValidator;
        $this->marketplaceUrl = $marketplaceUrl;
    }

    /**
     * Read the Default-scope automatic-check switch.
     *
     * @return bool
     */
    public function automaticChecksEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'general/automatic_checks');
    }

    /**
     * Clamp the polling interval and use the default when the configured value is zero.
     *
     * @return int
     */
    public function getCheckIntervalHours(): int
    {
        return $this->boundedInt('general/check_interval_hours', 6, 1, 168);
    }

    /**
     * Read the bounded age at which a successful snapshot becomes stale.
     *
     * @return int
     */
    public function getStaleAfterHours(): int
    {
        return $this->boundedInt('general/stale_after_hours', 24, 1, 720);
    }

    /**
     * Read the deployment-controlled API origin and base path.
     *
     * @return string
     */
    public function getApiUrl(): string
    {
        return rtrim((string)$this->scopeConfig->getValue(self::PREFIX . 'endpoints/api_url'), '/');
    }

    /**
     * Prefer an explicit account override; otherwise derive navigation from the local marketplace base.
     *
     * @return string
     */
    public function getAccountUrl(): string
    {
        $override = trim((string)$this->scopeConfig->getValue(self::PREFIX . 'endpoints/account_url'));
        if ($override !== '') {
            return $this->accountUrlValidator->normalize($override);
        }

        $base = $this->marketplaceUrl->getUrl();
        return $base === '' ? '' : $this->accountUrlValidator->normalize($base . '/account');
    }

    /**
     * Read the repository URL without using it as an API destination.
     *
     * @return string
     */
    public function getRepositoryUrl(): string
    {
        return rtrim((string)$this->scopeConfig->getValue(self::PREFIX . 'endpoints/repository_url'), '/');
    }

    /**
     * Return exact API hostnames allowed by deployment configuration.
     *
     * @return string[]
     */
    public function getAllowedApiHosts(): array
    {
        $value = (string)$this->scopeConfig->getValue(self::PREFIX . 'endpoints/allowed_api_hosts');
        $hosts = array_filter(array_map('trim', explode(',', strtolower($value))));
        return array_values(array_unique($hosts));
    }

    /**
     * Bound the network connection timeout in seconds.
     *
     * @return int
     */
    public function getConnectTimeout(): int
    {
        return $this->boundedInt('transport/connect_timeout', 5, 1, 10);
    }

    /**
     * Bound the total timeout for each API request in seconds.
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->boundedInt('transport/timeout', 15, 2, 30);
    }

    /**
     * Bound the maximum accepted API response size.
     *
     * @return int
     */
    public function getMaximumResponseBytes(): int
    {
        return $this->boundedInt('transport/maximum_response_bytes', 1048576, 1024, 2097152);
    }

    /**
     * Read the deployment-only switch for synthetic offline transport.
     *
     * @return bool
     */
    public function fixtureModeEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'developer/fixture_mode');
    }

    /**
     * Clamp the numeric setting to its limits, using the default for a zero or empty value.
     *
     * @param string $path
     * @param int $default
     * @param int $minimum
     * @param int $maximum
     * @return int
     */
    private function boundedInt(string $path, int $default, int $minimum, int $maximum): int
    {
        $value = (int)$this->scopeConfig->getValue(self::PREFIX . $path);
        return max($minimum, min($maximum, $value ?: $default));
    }
}
