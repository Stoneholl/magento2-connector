<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class BoundedInteger extends Value
{
    /**
     * @inheritdoc
     */
    public function beforeSave()
    {
        $value = (string)$this->getValue();
        $field = (string)$this->getField();
        $bounds = [
            'check_interval_hours' => [1, 168],
            'stale_after_hours' => [1, 720],
        ];
        if (!isset($bounds[$field]) || !ctype_digit($value)) {
            throw new LocalizedException(__('Enter a valid whole number.'));
        }
        $integer = (int)$value;
        if ($integer < $bounds[$field][0] || $integer > $bounds[$field][1]) {
            throw new LocalizedException(
                __('Enter a value from %1 through %2.', $bounds[$field][0], $bounds[$field][1])
            );
        }
        $this->setValue($integer);
        return parent::beforeSave();
    }
}
