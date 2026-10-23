<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

class PackageVersionResolver
{
    /** @var ComposerPackageIdentity */
    private $packageIdentity;

    /**
     * @param ComposerPackageIdentity|null $packageIdentity
     */
    public function __construct(?ComposerPackageIdentity $packageIdentity = null)
    {
        // Preserve construction through pre-upgrade compiled DI metadata.
        $this->packageIdentity = $packageIdentity ?? new ComposerPackageIdentity();
    }

    /**
     * Resolve a Composer version when available, returning null for manual or unknown packages.
     *
     * @param string $packageName
     * @return string|null
     */
    public function get(string $packageName): ?string
    {
        if (!class_exists(\Composer\InstalledVersions::class)) {
            return null;
        }
        foreach ($this->packageIdentity->candidates($packageName) as $candidate) {
            if (\Composer\InstalledVersions::isInstalled($candidate)) {
                $version = \Composer\InstalledVersions::getPrettyVersion($candidate);
                if (is_string($version) && $version !== '') {
                    return $version;
                }
            }
        }
        return null;
    }
}
