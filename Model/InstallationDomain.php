<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

use Magento\Store\Model\StoreManagerInterface;

class InstallationDomain
{
    /** @var StoreManagerInterface */
    private $storeManager;

    /**
     * Initialize explicit dependencies for installation domain.
     *
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    /**
     * Normalize the default-store base URL to a disclosed installation host.
     *
     * @return string
     */
    public function get(): string
    {
        $url = (string)$this->storeManager->getDefaultStoreView()->getBaseUrl();
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged -- Only the validated hostname is retained.
        $host = strtolower(rtrim((string)parse_url($url, PHP_URL_HOST), '.'));
        if ($host === '' || strlen($host) > 253 || preg_match('/^[a-z0-9.-]+$/D', $host) !== 1) {
            throw new \RuntimeException('The default store base URL does not contain a valid installation host.');
        }
        return $host;
    }
}
