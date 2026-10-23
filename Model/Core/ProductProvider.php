<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model\Core;

use Stoneholl\Core\Api\ProductProviderInterface;
use Stoneholl\Core\Model\Data\ProductDescriptor;

class ProductProvider implements ProductProviderInterface
{
    /**
     * Register Connector as free optional infrastructure in the offline Core registry.
     *
     * @return array
     */
    public function getProducts(): array
    {
        return [new ProductDescriptor(
            'connector',
            'Stoneholl Connector',
            ['Stoneholl_Connector'],
            ['stoneholl/magento2-connector']
        )];
    }
}
