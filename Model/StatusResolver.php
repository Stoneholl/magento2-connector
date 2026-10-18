<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

use Stoneholl\Connector\Model\Api\ApiException;

class StatusResolver
{
    /** @var CredentialStorage */
    private $credentialStorage;
    /** @var Config */
    private $config;

    /**
     * Initialize explicit dependencies for status resolver.
     *
     * @param CredentialStorage $credentialStorage
     * @param Config $config
     */
    public function __construct(CredentialStorage $credentialStorage, Config $config)
    {
        $this->credentialStorage = $credentialStorage;
        $this->config = $config;
    }

    /**
     * Compute informational connection status from saved credentials, state and cache age.
     *
     * @param State $state
     * @return string
     */
    public function resolve(State $state): string
    {
        try {
            if (!$this->credentialStorage->has()) {
                return ConnectionStatus::UNPAIRED;
            }
        } catch (ApiException $exception) {
            return ConnectionStatus::CREDENTIAL_REJECTED;
        }
        $status = (string)$state->getData('connection_status');
        if ($status === ConnectionStatus::PAIRING_CONFIRMATION_PENDING) {
            return $status;
        }
        if ($status === ConnectionStatus::CREDENTIAL_REJECTED) {
            return $status;
        }
        $lastSuccess = strtotime((string)$state->getData('last_success_at'));
        if ($lastSuccess && $lastSuccess < time() - ($this->config->getStaleAfterHours() * 3600)) {
            return ConnectionStatus::STALE;
        }
        return $status === ConnectionStatus::OFFLINE ? ConnectionStatus::OFFLINE : ConnectionStatus::CONNECTED;
    }
}
