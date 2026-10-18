<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

use Stoneholl\Connector\Model\Api\ApiException;
use Stoneholl\Connector\Model\Api\Client;

class VerifiedLocalResetService
{
    /** @var Client */
    private $client;
    /** @var CredentialStorage */
    private $credentialStorage;
    /** @var StateRepository */
    private $stateRepository;
    /** @var LocalForgetService */
    private $localForgetService;

    /**
     * Initialize explicit dependencies for verified local reset service.
     *
     * @param Client $client
     * @param CredentialStorage $credentialStorage
     * @param StateRepository $stateRepository
     * @param LocalForgetService $localForgetService
     */
    public function __construct(
        Client $client,
        CredentialStorage $credentialStorage,
        StateRepository $stateRepository,
        LocalForgetService $localForgetService
    ) {
        $this->client = $client;
        $this->credentialStorage = $credentialStorage;
        $this->stateRepository = $stateRepository;
        $this->localForgetService = $localForgetService;
    }

    /**
     * Clear local state only after server verification of the exact unlinked installation.
     *
     * @return void
     */
    public function execute(): void
    {
        $state = $this->stateRepository->get();
        if ((string)$state->getData('connection_status') !== ConnectionStatus::CREDENTIAL_REJECTED) {
            throw new ApiException(
                'local_reset_unavailable',
                'Reset after unlink is available only after the saved credential is rejected.'
            );
        }

        $installationId = (string)$state->getData('installation_id');
        $credential = $this->credentialStorage->get();
        if (!$this->isUuid($installationId) || $credential === null) {
            throw new ApiException(
                'local_reset_unavailable',
                'The Connector does not have the local identity required for a verified reset.'
            );
        }

        $this->client->requireCapabilities(['verified_local_reset']);
        $authorization = $this->client->authorizeLocalReset($credential, $installationId);
        if (!hash_equals($installationId, (string)($authorization['installation_id'] ?? ''))
            || ($authorization['local_reset_allowed'] ?? null) !== true
        ) {
            throw new ApiException(
                'invalid_response',
                'The local reset authorization response referred to another installation.'
            );
        }

        $this->localForgetService->execute();
    }

    /**
     * Validate the saved installation ID before requesting reset authorization.
     *
     * @param string $value
     * @return bool
     */
    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di', $value) === 1;
    }
}
