<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Stoneholl\Connector\Model\Api\Client;
use Stoneholl\Connector\Model\Api\ApiException;

class PairingService
{
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
    /** @var IdentityGeneratorInterface */
    private $identityGenerator;
    /** @var SecureWorkflowStorage */
    private $workflowStorage;

    /**
     * Initialize explicit dependencies for pairing service.
     *
     * @param Client $client
     * @param CredentialStorage $credentialStorage
     * @param StateRepository $stateRepository
     * @param Platform $platform
     * @param InstallationDomain $installationDomain
     * @param IdentityGeneratorInterface $identityGenerator
     * @param SecureWorkflowStorage $workflowStorage
     */
    public function __construct(
        Client $client,
        CredentialStorage $credentialStorage,
        StateRepository $stateRepository,
        Platform $platform,
        InstallationDomain $installationDomain,
        IdentityGeneratorInterface $identityGenerator,
        SecureWorkflowStorage $workflowStorage
    ) {
        $this->client = $client;
        $this->credentialStorage = $credentialStorage;
        $this->stateRepository = $stateRepository;
        $this->platform = $platform;
        $this->installationDomain = $installationDomain;
        $this->identityGenerator = $identityGenerator;
        $this->workflowStorage = $workflowStorage;
    }

    /**
     * Start or resume an identical pairing attempt using the disclosed host and environment.
     *
     * @param string $code
     * @param string $environment
     * @return void
     */
    public function pair(string $code, string $environment): void
    {
        $this->exchange($code, $environment, $this->installationDomain->get());
    }

    /**
     * Recover the existing saved installation with an account-issued code.
     *
     * @param string $code
     * @return void
     */
    public function recover(string $code): void
    {
        $state = $this->stateRepository->get();
        $installationId = (string)$state->getData('installation_id');
        $environment = (string)$state->getData('environment');
        $domain = (string)$state->getData('domain');
        if (!$this->isUuid($installationId)) {
            throw new ApiException(
                'credential_recovery_unavailable',
                'Credential recovery requires an existing installation identifier.'
            );
        }

        $this->exchange($code, $environment, $domain, $installationId);
    }

    /**
     * Persist a stable encrypted attempt before exchange and retain issued state for confirmation.
     *
     * @param string $code
     * @param string $environment
     * @param string $domain
     * @param string $installationId
     * @return void
     */
    private function exchange(
        string $code,
        string $environment,
        string $domain,
        string $installationId = ''
    ): void {
        $code = strtoupper(trim($code));
        if (preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/D', $code) !== 1) {
            throw new \InvalidArgumentException('Enter a pairing code in the XXXX-XXXX format.');
        }
        if (!in_array($environment, ['production', 'staging', 'development', 'test', 'ci'], true)) {
            throw new \InvalidArgumentException('Select a valid installation environment.');
        }

        $payload = array_merge($this->platform->getData(), [
            'code' => $code,
            'environment' => $environment,
            'domain' => $domain,
        ]);
        if ($installationId !== '') {
            $payload['installation_id'] = $installationId;
        }
        $pending = $this->workflowStorage->getPairing();
        if ($pending === null) {
            $payload['attempt_id'] = $this->identityGenerator->generateId();
            $this->workflowStorage->savePairing(['payload' => $payload]);
        } else {
            $storedPayload = is_array($pending['payload'] ?? null) ? $pending['payload'] : [];
            $attemptId = (string)($storedPayload['attempt_id'] ?? '');
            unset($storedPayload['attempt_id']);
            if ($storedPayload !== $payload || !$this->isUuid($attemptId)) {
                throw new ApiException(
                    'pairing_attempt_mismatch_local',
                    'A different recoverable pairing attempt is already pending. '
                    . 'Forget it locally before starting again.'
                );
            }
            $payload['attempt_id'] = $attemptId;
        }

        $features = ['recoverable_pairing', 'pairing_confirmation'];
        if ($installationId !== '') {
            $features[] = 'credential_recovery';
        }
        $this->client->requireCapabilities($features);
        try {
            $result = $this->exchangeWithRecovery($payload);
        // phpcs:ignore Magento2.Exceptions.ThrowCatch.ThrowCatch -- Discard only a definitively rejected recovery draft.
        } catch (ApiException $exception) {
            if ($installationId !== ''
                && $this->isRecoveryCodeRejected($exception)
                && empty($pending['installation_id'])
            ) {
                // Keep the saved installation and credential; only the rejected code's draft is obsolete.
                $this->workflowStorage->deletePairing();
            }
            throw $exception;
        }
        if ($installationId !== ''
            && ($result['installation_id'] !== $installationId || $result['recovered'] !== true)
        ) {
            throw new ApiException(
                'invalid_response',
                'The credential recovery response referred to another installation.'
            );
        }
        $this->credentialStorage->save($result['token']);
        $this->workflowStorage->savePairing([
            'payload' => $payload,
            'installation_id' => $result['installation_id'],
        ]);
        $this->stateRepository->markPairingPending($result['installation_id'], $environment, $domain);
        $this->confirmPending();
    }

    /**
     * Confirm the saved credential and require the expected installation identity.
     *
     * @return void
     */
    public function confirmPending(): void
    {
        $pending = $this->workflowStorage->getPairing();
        $credential = $this->credentialStorage->get();
        if ($pending === null || $credential === null) {
            throw new ApiException('pairing_confirmation_missing', 'No recoverable pairing confirmation is pending.');
        }
        $payload = is_array($pending['payload'] ?? null) ? $pending['payload'] : [];
        $environment = (string)($payload['environment'] ?? '');
        $domain = (string)($payload['domain'] ?? '');
        $this->client->requireCapabilities(['pairing_confirmation']);
        $confirmation = $this->client->confirmPairing($credential);
        $expectedId = (string)($pending['installation_id'] ?? '');
        if ($expectedId !== '' && $confirmation['installation_id'] !== $expectedId) {
            throw new ApiException('invalid_response', 'The pairing confirmation referred to another installation.');
        }
        $this->stateRepository->markPaired($confirmation['installation_id'], $environment, $domain);
        $this->workflowStorage->deletePairing();
    }

    /**
     * Retry a lost exchange response once using the identical persisted request.
     *
     * @param array $payload
     * @return array
     */
    private function exchangeWithRecovery(array $payload): array
    {
        try {
            return $this->client->exchangePairingCode($payload);
        } catch (ApiException $exception) {
            if ($exception->getApiCode() !== 'transport_error') {
                throw $exception;
            }
        }
        return $this->client->exchangePairingCode($payload);
    }

    /**
     * Validate saved workflow identities before accepting a resumable attempt.
     *
     * @param string $value
     * @return bool
     */
    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di', $value) === 1;
    }

    /**
     * Recognize definitive code rejections before the server issues a replacement credential.
     *
     * @param ApiException $exception
     * @return bool
     */
    private function isRecoveryCodeRejected(ApiException $exception): bool
    {
        $statuses = [
            'invalid_pairing_code' => 422,
            'pairing_code_not_recovery' => 422,
            'recovery_code_consumed' => 422,
            'recovery_code_expired' => 410,
            'recovery_installation_mismatch' => 409,
        ];
        return isset($statuses[$exception->getApiCode()])
            && $exception->getHttpStatus() === $statuses[$exception->getApiCode()];
    }
}
