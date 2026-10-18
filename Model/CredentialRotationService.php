<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Stoneholl\Connector\Model\Api\ApiException;
use Stoneholl\Connector\Model\Api\Client;

class CredentialRotationService
{
    private const LOCK_NAME = 'stoneholl_connector_operation';

    /** @var Client */
    private $client;
    /** @var CredentialStorage */
    private $credentialStorage;
    /** @var SecureWorkflowStorage */
    private $workflowStorage;
    /** @var LockManagerInterface */
    private $lockManager;
    /** @var IdentityGeneratorInterface */
    private $identityGenerator;

    /**
     * Initialize explicit dependencies for credential rotation service.
     *
     * @param Client $client
     * @param CredentialStorage $credentialStorage
     * @param SecureWorkflowStorage $workflowStorage
     * @param LockManagerInterface $lockManager
     * @param IdentityGeneratorInterface $identityGenerator
     */
    public function __construct(
        Client $client,
        CredentialStorage $credentialStorage,
        SecureWorkflowStorage $workflowStorage,
        LockManagerInterface $lockManager,
        IdentityGeneratorInterface $identityGenerator
    ) {
        $this->client = $client;
        $this->credentialStorage = $credentialStorage;
        $this->workflowStorage = $workflowStorage;
        $this->lockManager = $lockManager;
        $this->identityGenerator = $identityGenerator;
    }

    /**
     * Reject concurrent rotation and retain the lock until the resumable operation finishes.
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            throw new ApiException(
                'rotation_in_progress',
                'Another Connector operation is running. Try again after it finishes.'
            );
        }

        try {
            $this->rotate();
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    /**
     * Persist the issuance attempt, validate and promote its replacement, then revoke the original.
     *
     * @return void
     */
    private function rotate(): void
    {
        $currentCredential = $this->credentialStorage->get();
        if ($currentCredential === null) {
            throw new ApiException('credential_missing', 'This Magento installation is not paired.');
        }
        $rotation = $this->workflowStorage->getRotation();
        $features = ['installation_credential_management'];
        if (empty($rotation['token'])) {
            $features[] = 'idempotent_credential_rotation';
        }
        try {
            $this->client->requireCapabilities($features);
        // phpcs:ignore Magento2.Exceptions.ThrowCatch.ThrowCatch -- Add upgrade guidance without masking transport failures.
        } catch (ApiException $exception) {
            if ($exception->getApiCode() === 'capability_mismatch' && empty($rotation['token'])) {
                throw new ApiException(
                    'capability_mismatch',
                    'Update the Stoneholl API before starting or resuming this credential rotation.'
                );
            }
            throw $exception;
        }

        if ($rotation === null) {
            $rotation = ['attempt_id' => $this->identityGenerator->generateId()];
            $this->workflowStorage->saveRotation($rotation);
        }
        if (empty($rotation['token'])) {
            $attemptId = (string)($rotation['attempt_id'] ?? '');
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di', $attemptId) !== 1) {
                throw new ApiException('workflow_state_unreadable', 'The encrypted rotation request is invalid.');
            }
            $issued = $this->client->issueCredential($currentCredential, $attemptId);
            $rotation = [
                'attempt_id' => $attemptId,
                'token' => $issued['token'],
                'previous_credential_id' => $issued['previous_credential_id'],
            ];
            $this->workflowStorage->saveRotation($rotation);
        }

        $newCredential = (string)($rotation['token'] ?? '');
        $previousCredentialId = (string)($rotation['previous_credential_id'] ?? '');
        if ($newCredential === '' || $previousCredentialId === '') {
            throw new ApiException('workflow_state_unreadable', 'The encrypted credential rotation state is invalid.');
        }

        $this->client->getEntitlements($newCredential);
        $this->credentialStorage->save($newCredential);
        $this->client->revokeCredential($newCredential, $previousCredentialId);
        $this->workflowStorage->deleteRotation();
    }
}
