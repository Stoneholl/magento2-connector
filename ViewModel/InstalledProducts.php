<?php

declare(strict_types=1);

namespace Stoneholl\Connector\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\Escaper;
use Stoneholl\Connector\Model\PackageVersionResolver;
use Stoneholl\Core\Api\ProductRegistryInterface;

class InstalledProducts implements ArgumentInterface
{
    /** @var ProductRegistryInterface */
    private $productRegistry;

    /** @var PackageVersionResolver */
    private $packageVersionResolver;

    /** @var Escaper */
    private $escaper;

    /**
     * Use only local product metadata and Composer's installed-package information.
     *
     * @param ProductRegistryInterface $productRegistry
     * @param PackageVersionResolver $packageVersionResolver
     * @param Escaper $escaper
     */
    public function __construct(
        ProductRegistryInterface $productRegistry,
        PackageVersionResolver $packageVersionResolver,
        Escaper $escaper
    ) {
        $this->productRegistry = $productRegistry;
        $this->packageVersionResolver = $packageVersionResolver;
        $this->escaper = $escaper;
    }

    /**
     * Provide escaping explicitly because Magento 2.3 does not inject the template variable.
     *
     * @return Escaper
     */
    public function getEscaper(): Escaper
    {
        return $this->escaper;
    }

    /**
     * List enabled registered products without consulting credentials or the entitlement cache.
     *
     * @return array[]
     */
    public function getProducts(): array
    {
        $products = [];
        foreach ($this->productRegistry->getProducts() as $product) {
            $packages = [];
            foreach ($product->getPackageNames() as $packageName) {
                $packages[] = [
                    'name' => $packageName,
                    'version' => $this->packageVersionResolver->get($packageName),
                ];
            }
            $products[] = [
                'name' => $product->getName(),
                'modules' => $product->getModuleNames(),
                'packages' => $packages,
            ];
        }
        return $products;
    }
}
