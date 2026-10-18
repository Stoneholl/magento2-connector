<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model\Config;

class AccountUrlValidator
{
    /**
     * Require an absolute HTTPS navigation URL without credentials, query or fragment.
     *
     * @param string $value
     * @return string
     */
    public function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged -- Every URL component must be inspected.
        $parts = parse_url($value);
        if (!is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            return '';
        }

        return rtrim($value, '/');
    }
}
