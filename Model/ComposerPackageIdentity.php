<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

class ComposerPackageIdentity
{
    private const SLUGS = [
        'admin-accent-color', 'cart-share', 'cart-share-hyva', 'connector', 'core',
        'customer-local-time', 'latest-internal-comment', 'order-coupon-grid-filter',
        'order-navigation', 'order-operations', 'order-payment-state-filter', 'order-sla-monitor',
        'related-customer-orders', 'shopify-catalog-export', 'store-snapshot', 'webhook',
        'webhook-inventory', 'webhook-inventory-msi',
    ];

    /**
     * Normalize only the explicitly migrated Stoneholl package identities.
     *
     * @param string $name
     * @return string
     */
    public function canonical(string $name): string
    {
        foreach (self::SLUGS as $slug) {
            if ($name === 'stoneholl/module-' . $slug) {
                return 'stoneholl/magento2-' . $slug;
            }
        }
        return $name;
    }

    /**
     * Prefer a real canonical installation, then its corresponding legacy package.
     *
     * @param string $name
     * @return string[]
     */
    public function candidates(string $name): array
    {
        $canonical = $this->canonical($name);
        foreach (self::SLUGS as $slug) {
            if ($canonical === 'stoneholl/magento2-' . $slug) {
                return [$canonical, 'stoneholl/module-' . $slug];
            }
        }
        return [$name];
    }
}
