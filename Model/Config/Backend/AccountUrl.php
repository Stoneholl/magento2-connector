<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Stoneholl\Connector\Model\Config\AccountUrlValidator;

class AccountUrl extends Value
{
    /** @var AccountUrlValidator */
    private $accountUrlValidator;

    /**
     * Initialize explicit dependencies for account url.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param AccountUrlValidator $accountUrlValidator
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        AccountUrlValidator $accountUrlValidator,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->accountUrlValidator = $accountUrlValidator;
    }

    /**
     * @inheritdoc
     */
    public function beforeSave()
    {
        $rawValue = trim((string)$this->getValue());
        if ($rawValue === '') {
            $this->setValue('');
            return parent::beforeSave();
        }

        $value = $this->accountUrlValidator->normalize($rawValue);
        if ($value === '') {
            throw new LocalizedException(
                __('Enter an absolute HTTPS URL without credentials, a query, or a fragment.')
            );
        }

        $this->setValue($value);
        return parent::beforeSave();
    }
}
