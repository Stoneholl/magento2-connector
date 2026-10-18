<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

use Magento\Framework\App\ProductMetadataInterface;

class Platform
{
    /** @var ProductMetadataInterface */
    private $productMetadata;

    /**
     * Initialize explicit dependencies for platform.
     *
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(ProductMetadataInterface $productMetadata)
    {
        $this->productMetadata = $productMetadata;
    }

    /**
     * Disclose only Magento edition/version and the executing PHP version.
     *
     * @return array
     */
    public function getData(): array
    {
        $edition = strtolower((string)$this->productMetadata->getEdition());
        if (!in_array($edition, ['community', 'enterprise'], true)) {
            throw new \RuntimeException('The Magento edition is not supported by the Connector API contract.');
        }
        return [
            'magento_edition' => $edition === 'community' ? 'community' : 'enterprise',
            'magento_version' => (string)$this->productMetadata->getVersion(),
            'php_version' => PHP_VERSION,
        ];
    }
}
