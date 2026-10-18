<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

class PackageVersionResolver
{
    /**
     * Resolve a Composer version when available, returning null for manual or unknown packages.
     *
     * @param string $packageName
     * @return string|null
     */
    public function get(string $packageName): ?string
    {
        if (!class_exists(\Composer\InstalledVersions::class)
            || !\Composer\InstalledVersions::isInstalled($packageName)
        ) {
            return null;
        }
        $version = \Composer\InstalledVersions::getPrettyVersion($packageName);
        return is_string($version) && $version !== '' ? $version : null;
    }
}
