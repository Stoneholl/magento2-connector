<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

class LocalForgetService
{
    /** @var CredentialStorage */
    private $credentialStorage;
    /** @var SecureWorkflowStorage */
    private $workflowStorage;
    /** @var StateRepository */
    private $stateRepository;

    /**
     * Initialize explicit dependencies for local forget service.
     *
     * @param CredentialStorage $credentialStorage
     * @param SecureWorkflowStorage $workflowStorage
     * @param StateRepository $stateRepository
     */
    public function __construct(
        CredentialStorage $credentialStorage,
        SecureWorkflowStorage $workflowStorage,
        StateRepository $stateRepository
    ) {
        $this->credentialStorage = $credentialStorage;
        $this->workflowStorage = $workflowStorage;
        $this->stateRepository = $stateRepository;
    }

    /**
     * Clear local secrets, workflows and cached identity after caller authorization.
     *
     * @return void
     */
    public function execute(): void
    {
        $this->credentialStorage->delete();
        $this->workflowStorage->deletePairing();
        $this->workflowStorage->deleteRotation();
        $this->stateRepository->disconnect();
    }
}
