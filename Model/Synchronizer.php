<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

use Magento\Framework\Lock\LockManagerInterface;
use Stoneholl\Connector\Model\Api\ApiException;
use Stoneholl\Connector\Model\Api\Client;

class Synchronizer
{
    private const LOCK_NAME = 'stoneholl_connector_operation';

    /** @var Client */
    private $client;
    /** @var CredentialStorage */
    private $credentialStorage;
    /** @var StateRepository */
    private $stateRepository;
    /** @var Platform */
    private $platform;
    /** @var InstallationDomain */
    private $installationDomain;
    /** @var PackageVersionResolver */
    private $packageVersionResolver;
    /** @var LockManagerInterface */
    private $lockManager;

    /**
     * Initialize explicit dependencies for synchronizer.
     *
     * @param Client $client
     * @param CredentialStorage $credentialStorage
     * @param StateRepository $stateRepository
     * @param Platform $platform
     * @param InstallationDomain $installationDomain
     * @param PackageVersionResolver $packageVersionResolver
     * @param LockManagerInterface $lockManager
     */
    public function __construct(
        Client $client,
        CredentialStorage $credentialStorage,
        StateRepository $stateRepository,
        Platform $platform,
        InstallationDomain $installationDomain,
        PackageVersionResolver $packageVersionResolver,
        LockManagerInterface $lockManager
    ) {
        $this->client = $client;
        $this->credentialStorage = $credentialStorage;
        $this->stateRepository = $stateRepository;
        $this->platform = $platform;
        $this->installationDomain = $installationDomain;
        $this->packageVersionResolver = $packageVersionResolver;
        $this->lockManager = $lockManager;
    }

    /**
     * Publish a complete snapshot or preserve the existing cache and record a safe failure.
     *
     * @return bool
     */
    public function execute(): bool
    {
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            return false;
        }
        try {
            $credential = $this->credentialStorage->get();
            if ($credential === null) {
                throw new ApiException('credential_missing', 'This Magento installation is not paired.');
            }
            $this->stateRepository->markAttempt();
            $snapshot = $this->fetchWithRetry($credential);
            $this->stateRepository->markSuccess($snapshot);
            return true;
        // phpcs:ignore Magento2.Exceptions.ThrowCatch.ThrowCatch -- Record safe state, then preserve the API failure.
        } catch (ApiException $exception) {
            $status = $exception->isCredentialRejected()
                ? ConnectionStatus::CREDENTIAL_REJECTED
                : ConnectionStatus::OFFLINE;
            $this->stateRepository->markFailure($status, $exception->getApiCode(), $exception->getMessage());
            throw $exception;
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    /**
     * Retry a failed snapshot once, excluding rejected credentials from retry.
     *
     * @param string $credential
     * @return array
     */
    private function fetchWithRetry(string $credential): array
    {
        $lastException = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                return $this->fetch($credential);
            } catch (ApiException $exception) {
                $lastException = $exception;
                if ($exception->isCredentialRejected() || $attempt === 2) {
                    throw $exception;
                }
            }
        }
        throw $lastException ?: new ApiException('transport_error', 'The API request could not be completed.');
    }

    /**
     * Resolve every entitlement and package release before returning a cacheable snapshot.
     *
     * @param string $credential
     * @return array
     */
    private function fetch(string $credential): array
    {
        $platform = $this->platform->getData();
        $this->client->requireCapabilities();
        $this->client->updateMetadata($credential, [
            'domain' => $this->installationDomain->get(),
            'magento_edition' => $platform['magento_edition'],
            'magento_version' => $platform['magento_version'],
            'php_version' => $platform['php_version'],
        ]);
        $entitlements = $this->client->getEntitlements($credential);
        foreach ($entitlements as &$entitlement) {
            $releases = $this->client->getLatestCompatible(
                $credential,
                $entitlement['product_code'],
                $platform
            );
            $entitlement['package_statuses'] = [];
            foreach ($entitlement['packages'] as $package) {
                $entitlement['package_statuses'][] = [
                    'package' => $package,
                    'installed_version' => $this->packageVersionResolver->get($package),
                    'latest_compatible_version' => $releases[$package]['version'] ?? null,
                    'latest_compatible_published_at' => $releases[$package]['published_at'] ?? null,
                ];
            }
        }
        unset($entitlement);
        return ['platform' => $platform, 'entitlements' => $entitlements];
    }
}
