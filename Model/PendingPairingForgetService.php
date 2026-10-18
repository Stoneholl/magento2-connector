<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

use Stoneholl\Connector\Model\Api\ApiException;

class PendingPairingForgetService
{
    /** @var CredentialStorage */
    private $credentialStorage;
    /** @var SecureWorkflowStorage */
    private $workflowStorage;
    /** @var StateRepository */
    private $stateRepository;
    /** @var LocalForgetService */
    private $localForgetService;

    /**
     * Restrict the legacy Admin cleanup route without restricting server-verified internal cleanup.
     *
     * @param CredentialStorage $credentialStorage
     * @param SecureWorkflowStorage $workflowStorage
     * @param StateRepository $stateRepository
     * @param LocalForgetService $localForgetService
     */
    public function __construct(
        CredentialStorage $credentialStorage,
        SecureWorkflowStorage $workflowStorage,
        StateRepository $stateRepository,
        LocalForgetService $localForgetService
    ) {
        $this->credentialStorage = $credentialStorage;
        $this->workflowStorage = $workflowStorage;
        $this->stateRepository = $stateRepository;
        $this->localForgetService = $localForgetService;
    }

    /**
     * Forget only an unfinished attempt with no saved credential or installation identity.
     *
     * @throws ApiException
     */
    public function execute(): void
    {
        if ($this->credentialStorage->has()
            || (string)$this->stateRepository->get()->getData('installation_id') !== ''
        ) {
            throw new ApiException(
                'local_forget_unavailable',
                'Only an unfinished pairing attempt without a saved installation can be forgotten locally.'
            );
        }
        $pending = $this->workflowStorage->getPairing();
        if ($pending === null
            || !empty($pending['installation_id'])
            || !empty($pending['payload']['installation_id'])
            || $this->workflowStorage->getRotation() !== null
        ) {
            throw new ApiException(
                'local_forget_unavailable',
                'The local workflow cannot be discarded without retaining its installation identity.'
            );
        }
        $this->localForgetService->execute();
    }
}
